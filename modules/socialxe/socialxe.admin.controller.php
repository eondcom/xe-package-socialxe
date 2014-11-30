<?php

	class socialxeAdminController extends socialxe {

		/**
		* @brief 초기화
		**/
		function init() {
		}

		/**
		* @brief 설정
		**/
		function procSocialxeAdminInsertConfig() {
			// 기본 정보를 받음
			$args = Context::getRequestVars();

			if($args->use_ssl != 'Y') $args->use_ssl = 'N';
			if($args->use_short_url != 'Y') $args->use_short_url = 'N';

			// 사용할 서비스 설정
			$provider_list = $this->providerManager->getFullProviderList();
			foreach($provider_list as $provider){
				$tmp = 'select_service_' . $provider;
				if ($args->{$tmp} == 'Y'){
					$args->select_service[$provider] = 'Y';
				}else{
					$args->select_service[$provider] = 'N';
				}
				unset($args->{$tmp});
			}

			// module Controller 객체 생성하여 입력
			$oModuleController = &getController('module');

			// 사이트 정보에 따라 저장
			$module_info = Context::get('site_module_info');
			if ($module_info->site_srl){
				$output = $oModuleController->insertModulePartConfig('socialxe', $module_info->site_srl, $args);
			}else{
				$output = $oModuleController->insertModuleConfig('socialxe',$args);
			}
			return $output;
		}

		// 모듈별 설정
		function procSocialxeAdminInsertModuleConfig() {
			// 필요한 변수를 받아옴
			$module_srl = Context::get('target_module_srl');
			if(preg_match('/^([0-9,]+)$/',$module_srl)) $module_srl = explode(',',$module_srl);
			else $module_srl = array($module_srl);

			$use_social_info = Context::get('use_social_info');
			if(!in_array($use_social_info, array('Y','N'))) $use_social_info = 'N';

			if(!$module_srl || !$use_social_info) return new Object(-1, 'msg_invalid_request');

			for($i=0;$i<count($module_srl);$i++) {
				$srl = trim($module_srl[$i]);
				if(!$srl) continue;
				$output = $this->setTrackbackModuleConfig($srl, $use_social_info);
			}

			$this->setError(-1);
			$this->setMessage('success_updated');
		}

		// 모듈별 설정 함수
		function setTrackbackModuleConfig($module_srl, $use_social_info) {
			$config->use_social_info = $use_social_info;

			$oModuleController = &getController('module');
			$oModuleController->insertModulePartConfig('socialxe', $module_srl, $config);
			return new Object();
		}

		// bit.ly 삭제 삭제
		function procSocialxeAdminDeleteChecked(){
			// 선택된 글이 없으면 오류 표시
			$cart = Context::get('cart');
			if(!$cart) return $this->stop('msg_cart_is_null');
			$bitly_srl_list= explode('|@|', $cart);
			$bitly_count = count($bitly_srl_list);
			if(!$bitly_count) return $this->stop('msg_cart_is_null');

			$args->bitly_srls = implode(',', $bitly_srl_list);
			return executeQuery('socialxe.deleteBitly', $args);
		}

	}
?>
