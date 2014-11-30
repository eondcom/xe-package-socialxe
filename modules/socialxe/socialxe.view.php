<?php

	class socialxeView extends socialxe {

		/**
		* @brief 초기화
		**/
		function init() {
		}

		// 로그인 화면(oauth 시작)
		function dispSocialxeLogin(){
			// 크롤러면 실행하지 않는다...
			// 소셜XE 서버에 쓸데없는 요청이 들어올까봐...
			if (isCrawler()){
				Context::close();
				exit;
			}

			// 로그인에 사용되는 세션을 초기화한다.
			// js 사용시 최초에만 초기화하기 위해 js2 파라미터를 검사
			if (!Context::get('js2')){
				$this->session->clearSession('js');
				$this->session->clearSession('mode');
				$this->session->clearSession('callback_query');
				$this->session->clearSession('widget_skin');
				$this->session->clearSession('info');
			}

			$provider = Context::get('provider'); // 서비스
			$use_js = Context::get('js'); // JS 사용 여부
			$widget_skin = Context::get('skin'); // 위젯의 스킨명

			// 아무 것도 없는 레이아웃 적용
			$template_path = sprintf("%stpl/",$this->module_path);
			$this->setLayoutPath($template_path);
			$this->setLayoutFile("popup_layout");

			if ($provider == 'xe') return $this->stop('msg_invalid_request');

			// JS 사용 여부 확인
			if (($use_js || Context::get('mode') == 'socialLogin') && !Context::get('js2')){
				// JS 사용 여부를 세션에 저장한다.
				$this->session->setSession('js', $use_js);
				$this->session->setSession('widget_skin', $widget_skin);

				// 로그인 안내 페이지 표시후 진행할 URL
				$url = getUrl('js', '', 'skin', '', 'js2', 1);
				Context::set('url', $url);

				// 로그인 안내 페이지 표시
				// 모바일 모드가 아닐때도 모바일 페이지가 정상적으로 표시되도록.
				if(class_exists('Mobile')) {
					if(!Mobile::isFromMobilePhone()) {
						Context::addHtmlHeader('<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=yes, target-densitydpi=medium-dpi" />');
					}
				}
				// jQuery 압축 버전에 로드되는 1.5 이상에서는 min을 항상 로드(모바일 버전 때문)
				if(defined('__XE__')) {
					Context::addJsFile("./common/js/jquery.min.js", true, '', -100000);
				} else {
					Context::addJsFile("./common/js/jquery.js", true, '', -100000);
				}
				$this->setTemplatePath($template_path);
				$this->setTemplateFile('login');
				return;
			}

			$callback_query = Context::get('query'); // 인증 후 돌아갈 페이지 쿼리
			$this->session->setSession('callback_query', $callback_query);

			$mode = Context::get('mode'); // 작동 모드
			$this->session->setSession('mode', $mode);

			$mid = Context::get('mid'); // 소셜 로그인 처리 중인 mid
			$this->session->setSession('mid', $mid);

			$vid = Context::get('vid'); // 소셜 로그인 처리 중인 vid
			$this->session->setSession('vid', $vid);

			$info = Context::get('info'); // SocialXE info 위젯 여부
			$this->session->setSession('info', $info);

			// 로그인 시도 중인 서비스는 로그아웃 시킨다.
			$this->providerManager->doLogout($provider);

			$output = $this->communicator->getLoginUrl($provider);
			if (!$output->toBool()) return $output;
			$url = $output->get('url');

			// 리다이렉트
			header('Location: ' .$url);
			Context::close();
			exit;
		}

		// 콜백 처리
		function dispSocialxeCallback(){
			$provider = Context::get('provider'); // 서비스
			$verifier = Context::get('verifier');
			$oSocialxeModel = &getModel('socialxe');
			$oSocialxeController = &getController('socialxe');

			// verifier가 없으면 원래 페이지로 돌아간다.
			if (!$verifier){
				$this->returnPage();
				return;
			}

			// 처리
			$output = $this->communicator->callback($provider, $verifier);
			if (!$output->toBool()) return $output;

			$mode = $this->session->getSession('mode');
			switch($mode){
				// 소셜 로그인이면 로그인 처리
				case 'socialLogin':
					$output = $oSocialxeController->doSocialLogin();
					if (!$output->toBool()) return $output;

					// 최초 로그인으로 추가 정보 입력이 필요할 경우
					if ($output->get('first')){
						$url = $this->getNotEncodedFullUrl('', 'vid', $this->session->getSession('vid'), 'mid', $this->session->getSession('mid'), 'act', 'dispSocialxeLoginAdditional', 'provider', $provider);
						header('Location: ' . $url);
						Context::close();
						exit;
					}
				break;

				// 소셜 정보 연결 중이면 연결 처리
				case 'linkSocialInfo':
					$output = $oSocialxeController->linkSocialInfo();
					if (!$output->toBool()) return $output;
				break;
			}

			$this->returnPage();
		}

		// 로그아웃
		function dispSocialxeLogout(){
			$use_js = Context::get('js'); // JS 사용 여부
			$widget_skin = Context::get('skin'); // 위젯의 스킨명
			$query = urldecode(Context::get('query')); // 로그아웃 후 돌아갈 페이지 쿼리
			$provider = Context::get('provider'); // 서비스
			$info = Context::get('info'); // SocialXE info 위젯 여부
			$oSocialxeController = &getController('socialxe');

			// 아무 것도 없는 레이아웃 적용
			$template_path = sprintf("%stpl/",$this->module_path);
			$this->setLayoutPath($template_path);
			$this->setLayoutFile("popup_layout");

			if ($provider == 'xe') return $this->stop('msg_invalid_request');

			$output = $this->providerManager->doLogout($provider);
			if (!$output->toBool()) return $output;

			// 로그인되어 있지 않고, 로그인되어 있다면 소셜 정보 통합 기능을 사용하지 않을 때만 세션을 전송한다.
			$is_logged = Context::get('is_logged');
			if (!$is_logged || ($is_logged && $this->config->use_social_info != 'Y')){
				$this->communicator->sendSession();
			}

			// 댓글 권한을 초기화한다...
			unset($_SESSION['own_comment']);

			// JS 사용이면 XMLRPC 응답
			if ($use_js){
				Context::setRequestMethod('XMLRPC');

				// info 위젯이면 info 컴파일
				if ($info){
					$output = $oSocialxeController->_compileInfo();
				}

				// 입력창 컴파일
				else{
					$output = $oSocialxeController->_compileInput();
				}

				$this->add('skin', $widget_skin);
				$this->add('output', $output);
			}

			// JS 사용이 아니면 돌아간다.
			else{
				$this->returnPage($query);
			}
		}

		// 원래 페이지로 돌아간다.
		function returnPage($query = null){
			$js = $this->session->getSession('js');
			$skin = $this->session->getSession('widget_skin');
			$mode = $this->session->getSession('mode');
			$info = $this->session->getSession('info');

			// 쿼리가 파라미터로 넘어왔으면 사용하고 아니면 세션을 사용
			if (empty($query)){
				$query = $this->session->getSession('callback_query');
			}

			// 로그인되어 있지 않고, 로그인되어 있다면 소셜 정보 통합 기능을 사용하지 않을 때만 세션을 전송한다.
			$is_logged = Context::get('is_logged');
			if (!$mode && (!$is_logged || ($is_logged && $this->config->use_social_info != 'Y'))){
				$this->communicator->sendSession();
			}

			// 로그인에 사용되는 세션을 지운다.
			$this->session->clearSession('js');
			$this->session->clearSession('mode');
			$this->session->clearSession('callback_query');
			$this->session->clearSession('widget_skin');

			// JS 사용이면 창을 닫는다.
			if ($js){
				Context::set('skin', $skin);
				Context::set('info', $info);
				$template_path = sprintf("%stpl/",$this->module_path);
				$this->setTemplatePath($template_path);
				$this->setTemplateFile('completeLogin');
				return;
			}

			// XE주소
			$url = Context::getRequestUri();

			// SSL 항상 사용이 아니면 https를 http로 변경.
			// if(Context::get('_use_ssl') != 'always') {
				// $url = str_replace('https', 'http', $url);
			// }

			// 쿼리가 있으면 붙인다.
			if ($query){
				if (strpos($query, 'http') !== false)
					$url = urldecode($query);
				else
					$url .= '?' . urldecode($query);
			}

			header('Location: ' . $url);
			Context::close();
			exit;
		}

		// 텍스타일 설정화면
		function dispSocialxeTextyleTool() {
			// 텍스타일의 최신 버전이 아니면 직접 처리
			$oTextyleView = &getView('textyle');
			if (!method_exists($oTextyleView, 'initTool')){
				$oTextyleModel = &getModel('textyle');

				$site_module_info = Context::get('site_module_info');
				$textyle = $oTextyleModel->getTextyle($site_module_info->index_module_srl);
				Context::set('textyle',$textyle);

				Context::set('custom_menu', $oTextyleModel->getTextyleCustomMenu());

				$template_path = sprintf("%stpl",$oTextyleView->module_path);
				$this->setLayoutPath($template_path);
				$this->setLayoutFile('_tool_layout');

				if($_COOKIE['tclnb']) Context::addBodyClass('lnbClose');
				else Context::addBodyClass('lnbToggleOpen');

				// browser title 지정
				Context::setBrowserTitle($textyle->get('browser_title') . ' - admin');
				Context::addHtmlHeader('<link rel="shortcut icon" href="'.$textyle->getFaviconSrc().'" />');
			}

			// 설정 정보를 받아옴
			Context::set('config',$this->config);

			// 서비스 목록
			$provider_list = $this->providerManager->getFullProviderList();
			Context::set('provider_list', $provider_list);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('textyleConfig');
		}

		// 소셜 로그인 화면
		function dispSocialxeLoginForm(){
			$config = $this->config;

			// 소셜 로그인을 사용하지 않으면 중지
			if ($config->use_social_login != 'Y') return $this->stop('msg_not_allow_social_login');

			// 로그인 중이면 중지
			if (Context::get('logged_info')) return $this->stop('already_logged');

			// 사용 중인 서비스 세팅
			Context::set('provider_list', $this->providerManager->getProviderList());

			// 기본 사이트의 도메인
			$db_info = Context::getDBInfo();
			$domain = str_replace(array('http://', 'https://'), '', $db_info->default_url);
			Context::set('domain', $domain);

			// 세션 파기(가끔씩 기본 사이트와 PHPSESSIONID가 일치하지 않는 문제 때문)
			$oMemberController = &getController('member');
			$oMemberController->destroySessionInfo();

			// template path 지정
			$tpl_path = sprintf('%sskins/%s', $this->module_path, $config->skin);
			if(!is_dir($tpl_path)) $tpl_path = sprintf('%sskins/%s', $this->module_path, 'default');
			$this->setTemplatePath($tpl_path);

			// 템플릿 파일 지정
			$this->setTemplateFile('social_login');
		}

		// 소셜 로그인 추가 입력 화면
		function dispSocialxeLoginAdditional(){
			$config = $this->config;

			$provider = Context::get('provider');
			if (!$provider) return $this->stop('msg_invalid_request');

			// 소셜 로그인을 사용하지 않으면 중지
			if ($config->use_social_login != 'Y') return $this->stop('msg_not_allow_social_login');

			// 로그인 중이면 중지
			if (Context::get('logged_info')) return $this->stop('already_logged');

			// 소셜 로그인 과정 중이 아니면 중지
			$mode = $this->session->getSession('mode');
			if ($mode != 'socialLogin') return $this->stop('msg_invalid_request');

			// 해당 서비스의 로그인이 되어 있지 않으면 중지
			//debug @kakikaki
			/**
				찾는 부분이 여기가 아님 (소셜 계정에 로그인되지 않았습니다) :131128-1329
			 */
			//$debugstr = ''; 
			//$debugstr .= 'provider : '.$provider."<br/>";
			//$debugstr .= '$this->providerManager->isLogged($provider) : '.$this->providerManager->isLogged($provider);
			//debug end
			if (!$this->providerManager->isLogged($provider)) return $this->stop('msg_not_logged_social');
			//if (!$this->providerManager->isLogged($provider)) return $this->stop($debugstr);

			// template path 지정
			$tpl_path = sprintf('%sskins/%s', $this->module_path, $config->skin);
			if(!is_dir($tpl_path)) $tpl_path = sprintf('%sskins/%s', $this->module_path, 'default');
			$this->setTemplatePath($tpl_path);

			// JS 불러오기
			 if(!defined("__XE__")) {
				Context::addJsFile("./common/js/jquery.js", true, '', -100000);
				Context::addJsFile("./common/js/js_app.js", true, '', -100000);
				Context::addJsFile("./common/js/common.js", true, '', -100000);
				Context::addJsFile("./common/js/xml_handler.js", true, '', -100000);
				Context::addJsFile("./common/js/xml_js_filter.js", true, '', -100000);
			}

			// 템플릿 파일 지정
			$this->setTemplateFile('social_login_additional');
		}

		// 소셜 정보 보기/설정 화면
		function dispSocialxeSocialInfo(){
			$config = $this->config;
			$logged_info = Context::get('logged_info');
			$member_srl = Context::get('member_srl');
			if (!$member_srl) $member_srl = $logged_info->member_srl;

			// 회원 정보
			$oMemberModel = &getModel('member');
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
			Context::set('member_info', $member_info);

			// 소셜 로그인 아이디이면 원래 가입한 서비스를 세팅한다.
			// (첫번째 가입 때 사용한 서비스는 연결 끊기할 수 없음)
			$oSocialxeModel = &getModel('socialxe');
			Context::set('first_provider', $oSocialxeModel->getFirstProviderById($member_info->user_id));

			// 로그인하지 않았으면 중지
			if (!$logged_info) return $this->stop('msg_not_permitted');

			// 소셜 정보 통합을 사용하지 않으면 중지
			if ($config->use_social_info != 'Y') return $this->stop('msg_not_use_social_info');

			// 제공하는 서비스
			$provider_list = $this->providerManager->getProviderList();
			Context::set('provider_list', $provider_list);

			// 회원의 소셜 정보 얻기
			$output = $oSocialxeModel->getSocialInfoByMemberSrl($member_srl);
			if (!$output->toBool()) return $output;
			$social_info = $output->get('social_info');

			// 제공 서비스와 소셜 정보 합치기
			$member_social_info = array();
			foreach($provider_list as $provider){
				if ($social_info[$provider]){
					$member_social_info[$provider]['id'] = $social_info[$provider]['id'];
					$member_social_info[$provider]['nick_name'] = $social_info[$provider]['nick_name'];
					$member_social_info[$provider]['link'] = $this->providerManager->getAuthorLink($provider, $social_info[$provider]['id'], $social_info[$provider]['nick_name']);
					$member_social_info[$provider]['send'] = $social_info[$provider]['send'];
				}else{
					$member_social_info[$provider] = null;
				}
			}
			Context::set('member_social_info', $member_social_info);

			// 대표계정
			$output = $oSocialxeModel->getSocialInfoMasterByMemberSrl($member_srl);
			if (!$output->toBool()) return $output;
			$master_provider = $output->get('master_provider');
			Context::set('master_provider', $master_provider);

			// 회원 모듈의 스킨의 common_header 경로를 세팅
			$member_config = $oMemberModel->getMemberConfig();
			$member_skin_path = sprintf('%sskins/%s', $oMemberModel->module_path, $member_config->skin);
			if (!is_dir($member_skin_path)) sprintf('%sskins/%s', $oMemberModel->module_path, 'default');
			$member_skin_header = $member_skin_path . '/common_header.html';
			$member_skin_footer = $member_skin_path . '/common_footer.html';
			Context::set('member_skin_header', $member_skin_header);
			Context::set('member_skin_footer', $member_skin_footer);

			// template path 지정
			$tpl_path = sprintf('%sskins/%s', $this->module_path, $config->skin);
			if(!is_dir($tpl_path)) $tpl_path = sprintf('%sskins/%s', $this->module_path, 'default');
			$this->setTemplatePath($tpl_path);

			// 템플릿 파일 지정
			$this->setTemplateFile('social_info');
		}

		// 모듈 추가 설정 트리거
		function triggerDispAdditionSetup(&$obj){
			$current_module_srl = Context::get('module_srl');
			$current_module_srls = Context::get('module_srls');

			if(!$current_module_srl && !$current_module_srls) {
				// 선택된 모듈의 정보를 가져옴
				$current_module_info = Context::get('current_module_info');
				$current_module_srl = $current_module_info->module_srl;
				if(!$current_module_srl) return new Object();
			}

			// 설정을 구함
			$oSocialxeModel = &getModel('socialxe');
			$config = $oSocialxeModel->getModulePartConfig($current_module_srl);
			Context::set('config', $config);

			// 템플릿 파일 지정
			$oTemplate = &TemplateHandler::getInstance();
			$tpl = $oTemplate->compile($this->module_path.'tpl', 'module_config');
			$obj .= $tpl;

			return new Object();
		}

	}
?>
