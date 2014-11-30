<?php

	class socialxe_info extends WidgetHandler {

		/**
		* @brief 위젯의 실행 부분
		*
		* ./widgets/위젯/conf/info.xml 에 선언한 extra_vars를 args로 받는다
		* 결과를 만든후 print가 아니라 return 해주어야 한다
		**/

		function proc($args) {
			Context::set('colorset', $args->colorset);

			// 페이지 수정일 때는 실제 모습은 보이지 않도록
			if (in_array(Context::get('act'), array("procWidgetGenerateCodeInPage", "dispPageAdminContentModify", "dispPageAdminMobileContentModify"))){
				$tpl_path = sprintf('%stpl', $this->widget_path);
				$tpl_file = 'pageedit';
				$oTemplate = &TemplateHandler::getInstance();
				return $oTemplate->compile($tpl_path, $tpl_file);
			}

			return $this->_compileInfo($args->skin);
		}

		// 컴파일
		function _compileInfo($skin){
			$oSocialxeModel = &getModel('socialxe');

			// 언어 로드
			Context::loadLang($this->widget_path . 'lang');

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

			// 자동 로그인 키
			$auto_login_key = $oSocialxeModel->getAutoLoginKey();
			Context::set('auto_login_key', $auto_login_key);

			// 자동 로그인 키 요청 주소
			$auto_login_key_url = $oSocialxeModel->getAutoLoginKeyUrl();
			Context::set('auto_login_key_url', $auto_login_key_url);

			// 템플릿의 스킨 경로를 지정 (skin, colorset에 따른 값을 설정)
			$tpl_path = sprintf('%sskins/%s', $this->widget_path, $skin);
			Context::set('skin', $skin);

			// 템플릿 파일을 지정
			$tpl_file = 'info';

			// 템플릿 컴파일
			$oTemplate = &TemplateHandler::getInstance();
			return $oTemplate->compile($tpl_path, $tpl_file);
		}
	}
?>