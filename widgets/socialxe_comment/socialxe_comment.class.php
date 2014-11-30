<?php

	class socialxe_comment extends WidgetHandler {

		/**
		* @brief 위젯의 실행 부분
		*
		* ./widgets/위젯/conf/info.xml 에 선언한 extra_vars를 args로 받는다
		* 결과를 만든후 print가 아니라 return 해주어야 한다
		**/

		function proc($args) {
			$oSocialxeModel = &getModel('socialxe');

			Context::set('colorset', $args->colorset);

			// 언어 로드
			Context::loadLang($this->widget_path . 'lang');

			// 페이지 수정일 때는 실제 모습은 보이지 않도록
			if (in_array(Context::get('act'), array("procWidgetGenerateCodeInPage", "dispPageAdminContentModify", "dispPageAdminMobileContentModify"))){
				$tpl_path = sprintf('%stpl', $this->widget_path);
				$tpl_file = 'pageedit';
				$oTemplate = &TemplateHandler::getInstance();
				return $oTemplate->compile($tpl_path, $tpl_file);
			}


			// 서비스 목록
			$provider_list = $oSocialxeModel->getProviderList();
			Context::set('provider_list', $provider_list);

			// 서비스 로그인 상태
			$logged_provider = $oSocialxeModel->loggedProviderList();
			$logged_count = count($logged_provider);

			foreach($provider_list as $provider){
				$provider_is_logged[$provider] = $oSocialxeModel->isLogged($provider);
			}
			if (!isset($provider_is_logged)) $provider_is_logged = array();
			Context::set('provider_is_logged', $provider_is_logged);
			Context::set('logged_provider', $logged_provider);
			Context::set('logged_count', $logged_count);

			// 로그인한 서비스의 닉네임들
			foreach($logged_provider as $provider){
				$nick_names[$provider] = $oSocialxeModel->getProviderNickName($provider);
			}
			Context::set('nick_names', $nick_names);

			// 대표 계정
			$master_provider = $oSocialxeModel->getMasterProvider();
			Context::set('master_provider', $master_provider);

			// 대표 계정의 프로필 이미지
			$profile_image = $oSocialxeModel->getProfileImage();
			Context::set('profile_image', $profile_image);

			// 대표 계정의 닉네임
			$nick_name = $oSocialxeModel->getNickName();
			Context::set('nick_name', $nick_name);

			// 부계정
			$slave_provider = $oSocialxeModel->getSlaveProvider();
			Context::set('slave_provider', $slave_provider);

			// 부계정의 프로필 이미지
			$slave_profile_image = $oSocialxeModel->getSlaveProfileImage();
			Context::set('slave_profile_image', $slave_profile_image);

			// 부계정의 닉네임
			$slave_nick_name = $oSocialxeModel->getSlaveNickName();
			Context::set('slave_nick_name', $slave_nick_name);

			// 현재 페이지 주소의 쿼리 정보를 세팅
			$url_info = parse_url(Context::getRequestUrl());
			Context::set('query', urlencode($url_info['query']));

			// 댓글이 등록될 문서번호
			Context::set('document_srl', $args->document_srl);

			// 소셜 서비스에 등록될 주소
			Context::set('content_link', htmlspecialchars($args->content_link));

			// 소셜 서비스에 등록될 제목
			Context::set('content_title', htmlspecialchars($args->content_title));

			// 자동 로그인 키
			$auto_login_key = $oSocialxeModel->getAutoLoginKey();
			Context::set('auto_login_key', $auto_login_key);

			// 자동 로그인 키 요청 주소
			$auto_login_key_url = $oSocialxeModel->getAutoLoginKeyUrl();
			Context::set('auto_login_key_url', $auto_login_key_url);

			// 엔터 전송 여부
			$enter_send = $args->enter_send;
			if (!$enter_send) $enter_send = 'Y';
			Context::set('enter_send', $enter_send);

			// 대댓글 자동 펼침 여부
			$auto_view_sub = $args->auto_view_sub;
			if (!$auto_view_sub) $auto_view_sub = 'N';
			Context::set('auto_view_sub', $auto_view_sub);

			// 한번에 볼 댓글 개수
			$list_count = $args->list_count;
			if (!$list_count) $list_count = 10;
			Context::set('list_count', $list_count);



			// comment_srl이 있으면 해당 댓글을 가져온다.
			$comment_srl = Context::get('comment_srl');
			if ($comment_srl){
				$comment_list = $oSocialxeModel->getComment($args->document_srl, $comment_srl);
				Context::set('comment_srl', null, true);
				Context::set('use_comment_srl', $comment_list->get('use_comment_srl'));
				Context::set('socialxe_comment_srl', $comment_srl);
			}

			// 댓글 목록을 가져온다.
			else {
				$comment_list = $oSocialxeModel->getCommentList($args->document_srl, 0, $list_count);
			}

			Context::set('comment_list', $comment_list->get('list'));
			Context::set('total', $comment_list->get('total'));

			// 해당 글의 댓글 잠김을 확인한다.
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($args->document_srl);
			Context::set('allow_comment', $oDocument->allowComment());

			// mid와 document_srl이 속한 모듈을 체크해서 같을 때만 _document_srl을 세팅
			$oModuleModel = &getModel('module');
			$module_info_by_mid = $oModuleModel->getModuleInfoByMid(Context::get('mid'));
			$module_info_by_document_srl = $oModuleModel->getModuleInfoByDocumentSrl($args->document_srl);
			if ($module_info_by_mid->module_srl == $module_info_by_document_srl->module_srl){
				Context::set('_document_srl', $args->document_srl);
			}

			// 사용하는 필터 등록
			Context::addJsFilter($this->widget_path.'filter', 'insert_social_comment.xml');
			Context::addJsFilter($this->widget_path.'filter', 'delete_social_comment.xml');
			Context::addJsFilter($this->widget_path.'filter', 'insert_sub_comment.xml');

			// 템플릿의 스킨 경로를 지정 (skin, colorset에 따른 값을 설정)
			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);
			Context::set('skin', $args->skin);

			// 템플릿 파일을 지정
			$tpl_file = 'comment';

			// 템플릿 컴파일
			$oTemplate = &TemplateHandler::getInstance();
			return $oTemplate->compile($tpl_path, $tpl_file);
		}

		// 댓글 입력 컴파일
		function _compileInput($skin, $query){
			// 언어 로드
			Context::loadLang($this->widget_path . 'lang');

			// 서비스 목록
			$oSocialxeModel = &getModel('socialxe');
			$provider_list = $oSocialxeModel->getProviderList();
			Context::set('provider_list', $provider_list);

			// 서비스 로그인 상태
			$logged_provider = $oSocialxeModel->loggedProviderList();
			$logged_count = count($logged_provider);

			foreach($provider_list as $provider){
				$provider_is_logged[$provider] = $oSocialxeModel->isLogged($provider);
			}
			if (!isset($provider_is_logged)) $provider_is_logged = array();
			Context::set('provider_is_logged', $provider_is_logged);
			Context::set('logged_provider', $logged_provider);
			Context::set('logged_count', $logged_count);

			// 로그인한 서비스의 닉네임들
			foreach($logged_provider as $provider){
				$nick_names[$provider] = $oSocialxeModel->getProviderNickName($provider);
			}
			Context::set('nick_names', $nick_names);

			// 대표 계정
			$master_provider = $oSocialxeModel->getMasterProvider();
			Context::set('master_provider', $master_provider);

			// 대표 계정의 프로필 이미지
			$profile_image = $oSocialxeModel->getProfileImage();
			Context::set('profile_image', $profile_image);

			// 대표 계정의 닉네임
			$nick_name = $oSocialxeModel->getNickName();
			Context::set('nick_name', $nick_name);

			// 부계정
			$slave_provider = $oSocialxeModel->getSlaveProvider();
			Context::set('slave_provider', $slave_provider);

			// 부계정의 프로필 이미지
			$slave_profile_image = $oSocialxeModel->getSlaveProfileImage();
			Context::set('slave_profile_image', $slave_profile_image);

			// 부계정의 닉네임
			$slave_nick_name = $oSocialxeModel->getSlaveNickName();
			Context::set('slave_nick_name', $slave_nick_name);

			// 현재 페이지 주소의 쿼리 정보를 세팅
			Context::set('query', $query);

			// 엔터 전송 여부
			$enter_send = $args->enter_send;
			if (!$enter_send) $enter_send = 'Y';
			Context::set('enter_send', $enter_send);

			// vid, mid, document_srl을 복원
			$vid = Context::get('_vid');
			$mid = Context::get('_mid');
			$document_srl = Context::get('_document_srl');

			if ($vid) Context::set('vid', $vid);
			if ($mid) Context::set('mid', $mid);
			if ($document_srl) Context::set('document_srl', $document_srl);

			// 템플릿의 스킨 경로를 지정 (skin, colorset에 따른 값을 설정)
			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $skin);
			Context::set('skin', $skin);

			// 템플릿 파일을 지정
			$tpl_file = 'comment_input';

			// 템플릿 컴파일
			$oTemplate = &TemplateHandler::getInstance();
			return $oTemplate->compile($tpl_path, $tpl_file);
		}

		// 댓글 목록 컴파일
		function _compileCommentList($skin, $document_srl, $content_link, $last_comment_srl = 0, $list_count = 10){
			$oSocialxeModel = &getModel('socialxe');
			Context::set('content_link', $content_link);

			// 언어 로드
			Context::loadLang($this->widget_path . 'lang');

			// comment_srl이 있으면 해당 댓글을 가져온다.
			$comment_srl = Context::get('comment_srl');
			if ($comment_srl){
				$comment_list = $oSocialxeModel->getComment($document_srl, $comment_srl);
				Context::set('comment_srl', null, true);
				Context::set('use_comment_srl', $comment_list->get('use_comment_srl'));
				Context::set('socialxe_comment_srl', $comment_srl);
			}

			// 댓글 목록을 가져온다.
			else {
				$comment_list = $oSocialxeModel->getCommentList($document_srl, $last_comment_srl, $list_count);
			}

			Context::set('total', $comment_list->get('total'));
			Context::set('comment_list', $comment_list->get('list'));

			// 자동 로그인 키
			$auto_login_key = $oSocialxeModel->getAutoLoginKey();
			Context::set('auto_login_key', $auto_login_key);

			// 템플릿의 스킨 경로를 지정 (skin, colorset에 따른 값을 설정)
			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $skin);
			Context::set('skin', $skin);

			// 템플릿 파일을 지정
			$tpl_file = 'comment_list';

			// 템플릿 컴파일
			$oTemplate = &TemplateHandler::getInstance();
			return $oTemplate->compile($tpl_path, $tpl_file);
		}

		// 대댓글 컴파일
		function _compileSubCommentList($skin, $document_srl, $comment_srl, $content_link, $page){
			$oSocialxeModel = &getModel('socialxe');
			Context::set('document_srl', $document_srl);
			Context::set('comment_srl', $comment_srl);
			Context::set('content_link', $content_link);
			$result = new Object();

			// 언어 로드
			Context::loadLang($this->widget_path . 'lang');

			// 대댓글 목록을 가져온다.
			$comment_list = $oSocialxeModel->getSubCommentList($comment_srl, $page);
			Context::set('comment_list', $comment_list->get('list'));

			// 페이지
			$page_navigation = $comment_list->get('page_navigation');
			Context::set('page_navigation', $page_navigation);
			$result->add('total', $page_navigation->total_count);

			// 해당 글의 댓글 잠김을 확인한다.
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);
			Context::set('allow_comment', $oDocument->allowComment());

			// 템플릿의 스킨 경로를 지정 (skin, colorset에 따른 값을 설정)
			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $skin);
			Context::set('skin', $skin);

			// 템플릿 파일을 지정
			$tpl_file = 'sub_comment_list';

			// 템플릿 컴파일
			$oTemplate = &TemplateHandler::getInstance();
			$output = $oTemplate->compile($tpl_path, $tpl_file);


			$result->add('output', $output);

			return $result;
		}
	}
?>