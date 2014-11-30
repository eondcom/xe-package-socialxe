<?php

	class socialxeAdminView extends socialxe {

		/**
		* @brief 초기화
		**/
		function init() {
		}

		/**
		* @brief 설정
		**/
		function dispSocialxeAdminConfig() {
			// 설정 정보를 받아옴
			Context::set('config',$this->config);

			// 서비스 목록
			$provider_list = $this->providerManager->getFullProviderList();
			Context::set('provider_list', $provider_list); //여기서 provider_list 를 셋하면 index.html 에서 $provider_list 로 엑세스 가능

			// 스킨 리스트
			$oModuleModel = &getModel('module');
			$skin_list = $oModuleModel->getSkins($this->module_path);
			Context::set('skin_list', $skin_list);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('index');
		}

		// bit.ly 통계
		function dispSocialxeAdminBitly(){
			// 설정 정보를 받아옴
			Context::set('config',$this->config);

			// bit.ly 설정이 되어 있지 않으면 환경설정으로 보낸다.
			if (!$this->config->bitly_username || !$this->config->bitly_api_key){
				header('Location: ' . getNotEncodedUrl('act', 'dispSocialxeAdminConfig'));
				return;
			}

			// 목록을 구하기 위한 옵션
			$args->page = Context::get('page');
			$args->title = Context::get('title');
			$output = executeQueryArray('socialxe.getBitlyPageList', $args);
			if (!$output->toBool()) return $output;

			// 템플릿에 쓰기 위해서 comment_model::getTotalCommentList() 의 return object에 있는 값들을 세팅
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('bitly_list', $output->data);
			Context::set('page_navigation', $output->page_navigation);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path.'tpl');
			$this->setTemplateFile('bitly_index');
		}
	}
?>
