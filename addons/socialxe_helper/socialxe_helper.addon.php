<?php
	if(!defined('__ZBXE__') && !defined('__XE__')) exit();

	Context::loadLang('./addons/socialxe_helper/lang');

	// 팝업 및 회원정보 보기에 소셜 메뉴 추가.
	if($called_position == 'before_module_init' && $this->module != 'member' && Context::get('logged_info')){
		// 회원 로그인 정보에 소셜 메뉴 추가
		$oMemberController = &getController('member');
		$oMemberController->addMemberMenu('dispSocialxeSocialInfo', 'cmd_config_social');
	}

	// 사용자 이름 클릭 시 팝업메뉴에 메뉴 추가.
	if($called_position == 'before_module_proc' && $this->act == 'getMemberMenu'){
		$oMemberController = &getController('member');
		$member_srl = Context::get('target_srl');
		$mid = Context::get('cur_mid');

		// 자신이라면 설정 추가
		//debug :@kakikaki
		//debugPrint('mid : '.$mid);
		//debug end
		
		if ($logged_info->member_srl == $member_srl){
			$oMemberController->addMemberPopupMenu(getUrl('', 'mid', $mid, 'act', 'dispSocialxeSocialInfo'), 'cmd_config_social', './modules/member/tpl/images/icon_view_info.gif', 'self');
		}

		// 다른 사람이면 보기 추가
		else{
			$oMemberController->addMemberPopupMenu(getUrl('', 'mid', $mid, 'act', 'dispSocialxeSocialInfo', 'member_srl', $member_srl), 'cmd_view_social_info', './modules/member/tpl/images/icon_view_info.gif', 'self');
		}
	}

	// 현재 화면의 글, 댓글 번호를 수집하여 소셜 전송 정보를 출력해준다.
	if($called_position == 'before_display_content' && Context::getResponseMethod() == 'HTML'){
		// 소셜 전송 정보를 출력하지 않게 설정 되어 있는지 확인
		if($addon_info->is_view_info == 1) return;

		// 댓글
		$pattern = "/<!--BeforeComment\((.*),.*\)-->/U";
		unset($matches);
		preg_match_all($pattern, $output, $matches);
		$comment_srls = $matches[1];

		// 문서
		$pattern = "/<!--BeforeDocument\((.*),.*\)-->/U";
		unset($matches);
		preg_match_all($pattern, $output, $matches);
		$document_srls = $matches[1];

		// 문서 번호와 댓글 번호를 합친다.
		$srls = array_merge($comment_srls, $document_srls);

		// 해당 소셜 정보를 가져온다.
		$args->srls = $srls;
		$res = executeQueryArray('addons.socialxe_helper.getSocialxes', $args);

		if ($res->data){
			// CSS 파일 불러오기
			Context::addCssFile('./addons/socialxe_helper/css/css.css');

			// 소셜 정보를 가공
			foreach($res->data as $val){
				$GLOBALS['social_info'][$val->comment_srl]->provider = $val->provider;
				$GLOBALS['social_info'][$val->comment_srl]->id = $val->id;
				$GLOBALS['social_info'][$val->comment_srl]->social_nick_name = $val->social_nick_name;
			}

			// 댓글의 소셜 정보 출력
			$pattern = "/<!--BeforeComment\((.*),.*\)-->/U";
			$output = preg_replace_callback($pattern, create_function('$matches',
							'$social_info = $GLOBALS["social_info"][$matches[1]];' .
							'if (!$social_info->provider || $social_info->provider == "xe") return $matches[0];' .
							'$oSocialxeModel = &getModel("socialxe");' .
							'$link = $oSocialxeModel->getAuthorLink($social_info->provider, $social_info->id, $social_info->social_nick_name);' .
							'$lang_provider = Context::getLang("provider");' .
							'return \'<div class="socialxe_helper_info" style="text-align: right;"><a href="\' . $link . \'" target="_blank" class="socialxe_helper \' . $social_info->provider . \'" title="\' . Context::getLang("prefix_social_info") . $lang_provider[$social_info->provider] . \'">\' . $lang_provider[$social_info->provider] . \'</a></div>\' . $matches[0];'
						), $output);

			// 문서의 소셜 정보 출력
			$pattern = "/<!--BeforeDocument\((.*),.*\)-->/U";
			$output = preg_replace_callback($pattern, create_function('$matches',
							'$social_info = $GLOBALS["social_info"][$matches[1]];' .
							'if (!$social_info->provider || $social_info->provider == "xe") return $matches[0];' .
							'$oSocialxeModel = &getModel("socialxe");' .
							'$link = $oSocialxeModel->getAuthorLink($social_info->provider, $social_info->id, $social_info->social_nick_name);' .
							'$lang_provider = Context::getLang("provider");' .
							'return \'<div class="socialxe_helper_info" style="text-align: right;"><a href="\' . $link . \'" target="_blank" class="socialxe_helper \' . $social_info->provider . \'" title="\' . Context::getLang("prefix_social_info") . $lang_provider[$social_info->provider] . \'">\' . $lang_provider[$social_info->provider] . \'</a></div>\' . $matches[0];'
						), $output);
		}
	}

	// 텍스타일의 발행 화면에 SocialXE Info 위젯 표시
	if($called_position == 'before_display_content' && Context::get('act') == 'dispTextyleToolPostManagePublish'){
		$oTemplate = &TemplateHandler::getInstance();
		$compiled = $oTemplate->compile('./addons/socialxe_helper', 'textylePublish.html');
		$oWidgetController = &getController('widget');
		$compiled = $oWidgetController->transWidgetCode($compiled);
		$output = preg_replace("/(When<\/legend>.*)(<!-- wPublish -->)/s", "$1 $compiled $2", $output);

	}

	// 텍스타일 글 발행 실행 전
	if ($called_position == 'before_module_proc' && Context::get('act') == 'procTextylePostPublish'){
		// 문서를 얻는다.
		$oDocumentModel = &getModel('document');
		$document_srl = Context::get('document_srl');
		$document = $oDocumentModel->getDocument($document_srl);
		$GLOBALS['socialxe_textyle_document_' . $document_srl] = $document;

		// 기발행여부
		$args->document_srl = $document_srl;
		$output = executeQuery('textyle.getPublishLogs', $args);
		$isPublished = (!$output->data) ? false : true;
		$GLOBALS['socialxe_textyle_published_' . $document_srl] = $isPublished;
	}

	// 텍스타일 글 발행 실행 후
	if($called_position == 'after_module_proc' && Context::get('act') == 'procTextylePostPublish'){
		$oSocialxeController = &getController('socialxe');
		$oSocialxeController->textylePostPublish(this);
	}

	// 텍스타일 뷰 실행 때마다... 예약 발행을 체크한다.
	if($called_position == 'before_module_init'){
		//
		// ad-hoc... ModuleHandler의 init() 메소드 그대로 실행
		// 현재 실행해야할 모듈을 알아내기 위해
		//
		$oModuleModel = &getModel('module');
		$site_module_info = Context::get('site_module_info');

		if(!$this->document_srl && $this->mid && $this->entry) {
			$oDocumentModel = &getModel('document');
			$this->document_srl = $oDocumentModel->getDocumentSrlByAlias($this->mid, $this->entry);
			if($this->document_srl) Context::set('document_srl', $this->document_srl);
		}

		// Get module's information based on document_srl, if it's specified
		if($this->document_srl && !$this->module) {
			$module_info = $oModuleModel->getModuleInfoByDocumentSrl($this->document_srl);

			// If the document does not exist, remove document_srl
			if(!$module_info) {
				unset($this->document_srl);
			} else {
				// If it exists, compare mid based on the module information
				// if mids are not matching, set it as the document's mid
				if($this->mid != $module_info->mid) {
					$this->mid = $module_info->mid;
					Context::set('mid', $module_info->mid, true);
				}
			}
			// if requested module is different from one of the document, remove the module information retrieved based on the document number
			if($this->module && $module_info->module != $this->module) unset($module_info);
		}

		// If module_info is not set yet, and there exists mid information, get module information based on the mid
		if(!$module_info && $this->mid) {
			$module_info = $oModuleModel->getModuleInfoByMid($this->mid, $site_module_info->site_srl);
			//if($this->module && $module_info->module != $this->module) unset($module_info);
		}

		// redirect, if module_site_srl and site_srl are different
		if(!$this->module && !$module_info && $site_module_info->site_srl == 0 && $site_module_info->module_site_srl > 0) {
			$site_info = $oModuleModel->getSiteInfo($site_module_info->module_site_srl);
			header("location:".getNotEncodedSiteUrl($site_info->domain,'mid',$site_module_info->mid));
			return false;
		}

		// If module_info is not set still, and $module does not exist, find the default module
		if(!$module_info && !$this->module) $module_info = $site_module_info;

		if(!$module_info && !$this->module && $site_module_info->module_site_srl) $module_info = $site_module_info;

		// redirect, if site_srl of module_info is different from one of site's module_info
		if($module_info && $module_info->site_srl != $site_module_info->site_srl && !isCrawler()) {
			// If the module is of virtual site
			if($module_info->site_srl) {
				$site_info = $oModuleModel->getSiteInfo($module_info->site_srl);
				$redirect_url = getNotEncodedSiteUrl($site_info->domain, 'mid',Context::get('mid'),'document_srl',Context::get('document_srl'),'module_srl',Context::get('module_srl'),'entry',Context::get('entry'));
			// If it's called from a virtual site, though it's not a module of the virtual site
			} else {
				$db_info = Context::getDBInfo();
				if(!$db_info->default_url) return Context::getLang('msg_default_url_is_not_defined');
				else $redirect_url = getNotEncodedSiteUrl($db_info->default_url, 'mid',Context::get('mid'),'document_srl',Context::get('document_srl'),'module_srl',Context::get('module_srl'),'entry',Context::get('entry'));
			}
			header("location:".$redirect_url);
			return false;
		}

		// If module info was set, retrieve variables from the module information
		if($module_info) {
			$this->module = $module_info->module;
			$this->mid = $module_info->mid;
			$this->module_info = $module_info;
			Context::setBrowserTitle($module_info->browser_title);
			$part_config= $oModuleModel->getModulePartConfig('layout',$module_info->layout_srl);
			Context::addHtmlHeader($part_config->header_script);
		}

		// Set module and mid into module_info
		$this->module_info->module = $this->module;
		$this->module_info->mid = $this->mid;

		// Still no module? it's an error
		if(!$this->module) $this->error = 'msg_module_is_not_exists';

		// If mid exists, set mid into context
		if($this->mid) Context::set('mid', $this->mid, true);

		// Set current module info into context
		Context::set('current_module_info', $this->module_info);

		//
		// ad-hoc(ModuleHandler init()) 끝!
		//

		//
		// ad-hoc... ModuleHandler의 procModule 약간 변경 후 그대로 실행
		// 현재 실행되는 모듈의 type를 알아내기 위해!
		//

		// If error occurred while preparation, return a message instance
		if($this->error) {
			return;
		}

		$oModuleModel = &getModel('module');

		// Get action information with conf/action.xml
		$xml_info = $oModuleModel->getModuleActionXml($this->module);

		// If not installed yet, modify act
		if($this->module=="install") {
			if(!$this->act || !$xml_info->action->{$this->act}) $this->act = $xml_info->default_index_act;
		}

		// if act exists, find type of the action, if not use default index act
		if(!$this->act) $this->act = $xml_info->default_index_act;

		// still no act means error
		if(!$this->act) {
			$this->error = 'msg_module_is_not_exists';
			return;
		}

		// get type, kind
		$type = $xml_info->action->{$this->act}->type;
		$kind = strpos(strtolower($this->act),'admin')!==false?'admin':'';
		if(!$kind && $this->module == 'admin') $kind = 'admin';
		if($this->module_info->use_mobile != "Y") Mobile::setMobile(false);

		// if(type == view, and case for using mobilephone)
		if($type == "view" && Mobile::isFromMobilePhone() && Context::isInstalled())
		{
			$orig_type = "view";
			$type = "mobile";
		}

		//
		// ad-hoc 끝!(ModuleHandler procModule())
		//

		// 텍스타일뷰일 때만 실행...
		if (!($this->module == 'textyle' && ($type == 'view' || $type == 'mobile'))) return;

		// 예약 발행해야할 문서를 구한다.
		$now = date('YmdHis');
		$oTextyleModel = &getModel('textyle');

		$args->module_srl = $this->module_info->module_srl;
		$args->less_publish_date = $now;
		$output = $oTextyleModel->getSubscription($args);

		if($output->data){
			$oSocialxeController = &getController('socialxe');
			foreach($output->data as $k => $v){
				if($v->publish_date <= $now){
					$oSocialxeController->textylePublishSubscriptedPost($v->document_srl);
				}
			}
		}
	}
?>