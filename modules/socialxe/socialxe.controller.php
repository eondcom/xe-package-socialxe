<?php

	class socialxeController extends socialxe {

		/**
		* @brief 초기화
		**/
		function init() {
		}

		private function getProfileImageFromUrl($url, $saveto){
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			$raw = curl_exec($ch);
			curl_close($ch);
			
			if(file_exists($saveto)) unlink($saveto);
			$fp = fopen($saveto, 'x');
			fwrite($fp, $raw);
			fclose($fp);
			
		}
		private function insertProfileImageFromUrl($member_srl, $url){
			$oModuleModel = &getModel('module');
			$config = $oModuleModel->getModuleConfig('member');
			$max_width = $config->profile_image_max_width;
			if(!$max_width) $max_width = "80"; //default 90
			$max_height = $config->profile_image_max_height;
			if(!$max_height) $max_height = "80"; //default 20
			$target_path = sprintf('files/member_extra_info/profile_image/%s',getNumberingPath($member_srl));
			$target_tmpfile = $target_path."sample.jpg";
			FileHandler::makeDir($target_path);
			
			//지정된 URL 로부터 프로필 이미지 가져오기
			if(file_exists($target_tmpfile)) unlink($target_tmpfile);
			$this->getProfileImageFromUrl($url, $target_tmpfile);
			list($width, $height, $type, $attrs) = @getimagesize($target_tmpfile);
			if($type == 3) $ext = 'png';
			elseif($type == 2) $ext = 'jpg';
			else $ext = 'gif';
			
			$target_filename = sprintf('%s%d.%s', $target_path, $member_srl, $ext);
			$target_filename_mask = sprintf('%s%d.%s', $target_path, $member_srl, '*');
			array_map('unlink', glob($target_filename_mask));
			
			if($width > $max_width || $height > $max_height || !$type != 1)
				FileHandler::createImageFile($target_tmpfile, $target_filename, $max_width, $max_height, $ext);
			else 
				@copy($target_tmpfile, $target_filename);
			
			unlink($target_tmpfile);			
		}
		// 자동 로그인 키 세팅
		function procSocialxeSetAutoLoginKey(){
			debugPrint('procSocialxeSetAutoLoginKey : ');
			$auto_login_key = Context::get('auto_login_key');
			$widget_skin = Context::get('skin'); // 위젯의 스킨명
			$info = Context::get('info'); // info 위젯 여부

			debugPrint('widget_skin : '.$widget_skin);
			debugPrint('info : '.$info);
			debugPrint('auto_login_key : '.$auto_login_key);
			// 세팅
			$this->communicator->setAutoLoginKey($auto_login_key);

			// 입력창 컴파일
			if ($info){
				$output = $this->_compileInfo();
			}else{
				$output = $this->_compileInput();
			}

			$this->add('skin', $widget_skin);
			$this->add('output', $output);
		}

		// 대표 계정 설정
		function procSocialxeChangeMaster(){
			$widget_skin = Context::get('skin'); // 위젯의 스킨명
			$provider = Context::get('provider'); // 서비스
			$info = Context::get('info'); // info 위젯 여부

			$this->providerManager->setMasterProvider($provider);

			// 로그인되어 있지 않고, 로그인되어 있다면 소셜 정보 통합 기능을 사용하지 않을 때만 세션을 전송한다.
			$is_logged = Context::get('is_logged');
			if (!$is_logged || ($is_logged && $this->config->use_social_info != 'Y')){
				$this->communicator->sendSession();
			}

			if ($info){
				$output = $this->_compileInfo();
			}else{
				$output = $this->_compileInput();
			}

			$this->add('skin', $widget_skin);
			$this->add('output', $output);
		}

		// 댓글 달기
		function procSocialxeInsertComment(){
			$oCommentController = &getController('comment');

			// 로그인 상태인지 확인
			if (count($this->providerManager->getLoggedProviderList()) == 0){
				return $this->stop('msg_not_logged');
			}

			$args->document_srl = Context::get('document_srl');

			// 해당 문서의 댓글이 닫혀있는지 확인
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($args->document_srl);
			if (!$oDocument->allowComment()) return new Object(-1, 'msg_invalid_request');

			// 데이터를 준비
			$args->parent_srl = Context::get('comment_srl');
			$args->content = trim(Context::get('content'));
			$args->nick_name = $this->providerManager->getMasterProviderNickName();
			$args->content_link = Context::get('content_link');
			$args->content_title = Context::get('content_title');

			// 1.5이상이 아니거나 모바일 클래스가 없다면, 줄 바꿈과 특수 문자 변환 실행. - XE Core에서 모바일이면 처리를 해버린다.  1.5 이하에서도 이런 현상이 있는지 몰라서 1.5 이하는 예전처럼 처리
			if(!Mobile::isFromMobilePhone() || !defined('__XE__')) {
				$args->content = nl2br(htmlspecialchars($args->content));
			}

			// 해당 문서가 비밀글인지 확인
			if ($oDocument->isSecret()) $args->is_secret = 'Y';

			// 댓글의 moduel_srl
			$oModuleModel = &getModel('module');
			$module_info = $oModuleModel->getModuleInfoByDocumentSrl($args->document_srl);
			$args->module_srl = $module_info->module_srl;

			// 댓글 삽입

			// XE가 대표 계정이면 XE 회원 정보를 이용하여 댓글을 등록
			if ($this->providerManager->getMasterProvider() == 'xe'){
				$manual_inserted = false;
				// 부계정이 없으면 알림 설정
				if (!$this->providerManager->getSlaveProvider())
					$args->notify_message = 'Y';
			}else{
				$manual_inserted = true;
				$args->email_address = '';
				$args->homepage = '';
			}

			$result = $oCommentController->insertComment($args, $manual_inserted);

			if (!$result->toBool()) return $result;

			// 삽입된 댓글의 번호
			$comment_srl = $result->get('comment_srl');

			// 텍스타일이면 지지자 처리
			if ($module_info->module == 'textyle'){
				$oCommentModel = &getModel('comment');
				$oComment = $oCommentModel->getComment($comment_srl);

				$obj->module_srl = $module_info->module_srl;
				$obj->nick_name = $oComment->get('nick_name');
				$obj->member_srl = $oComment->get('member_srl');
				$obj->homepage = $oComment->get('homepage');
				$obj->comment_count = 1;

				$oTextyleController = &getController('textyle');
				$oTextyleController->updateTextyleSupporter($obj);
			}

			// 태그 제거 htmlspecialchars 복원
			$args->content = $this->htmlEntityDecode(strip_tags($args->content));

			// 소셜 서비스로 댓글 전송
			$output = $this->sendSocialComment($args, $comment_srl, $msg);
			if (!$output->toBool()){
				$oCommentController->deleteComment($comment_srl);
				return $output;
			}

			// 위젯에서 화면 갱신에 사용할 정보 세팅
			$this->add('skin', Context::get('skin'));
			$this->add('document_srl', Context::get('document_srl'));
			$this->add('comment_srl', Context::get('comment_srl'));
			$this->add('list_count', Context::get('list_count'));
			$this->add('content_link', Context::get('content_link'));
			$this->add('msg', $msg);
		}

		// 소셜 사이트로 전송
		function sendSocialComment($args, $comment_srl, &$msg, $manual_data = null){
			// 소셜 서비스로 댓글 전송
			if ($args->is_secret != 'Y'){
				$output = $this->communicator->sendComment($args, $manual_data);
				if (!$output->toBool()) return $output;

				$msg = $output->get('msg');
			}else{
				// $output이 아래에서 쓰이기 때문에 빈 Object를 만든다.
				$output = new Object();
			}

			// 추가 정보 준비
			$args->comment_srl = $comment_srl;

			if ($manual_data){
				$master_provider = $manual_data->master_provider;
				$master_id = $manual_data->master_id;
				$master_nickname = $manual_data->master_nickname;
				$master_profile_image = $manual_data->master_profile_image;

				$slave_provider = $manual_data->slave_provider;
				$slave_id = $manual_data->slave_id;
				$slave_nickname = $manual_data->slave_nickname;
			}else{
				$master_provider = $this->providerManager->getMasterProvider();
				$master_id = $this->providerManager->getMasterProviderId();
				$master_nickname = $this->providerManager->getMasterProviderNickName();
				$master_profile_image = $this->providerManager->getMasterProviderProfileImage();

				$slave_provider = $this->providerManager->getSlaveProvider();
				$slave_id = $this->providerManager->getSlaveProviderId();
				$slave_nickname = $this->providerManager->getSlaveProviderNickName();
			}

			// 대표 계정이 XE면 부계정의 정보를 넣는다.
			if ($master_provider == 'xe'){
				$args->provider = $slave_provider;
				$args->id = $slave_id;
				$args->comment_id = $output->get('comment_id');
				$args->social_nick_name = $slave_nickname;
			}

			// 대표 계정이 XE가 아니면 대표 계정의 정보를 넣는다.
			else{
				$args->provider = $master_provider;
				$args->id = $master_id;
				$args->profile_image = $master_profile_image;
				$args->comment_id = $output->get('comment_id');
				$args->social_nick_name = $master_nickname;
			}

			// 추가 정보 삽입
			$output = executeQuery('socialxe.insertSocialxe', $args);
			if (!$output->toBool()) return $output;

			return new Object();
		}

		// 댓글 삭제
		function procSocialxeDeleteComment(){
			$comment_srl = Context::get('comment_srl');
			if (!$comment_srl) return $this->stop('msg_invalid_request');

			// 우선 SocialCommentItem을 만든다.
			// DB에서 읽어오게 되지만, 어차피 권한 체크하려면 읽어야 한다.
			$oComment = new socialCommentItem($comment_srl);

			// comment 모듈의 controller 객체 생성
			$oCommentController = &getController('comment');

			$output = $oCommentController->deleteComment($comment_srl, $oComment->isGranted());
			if(!$output->toBool()) return $output;

			// 위젯에서 화면 갱신에 사용할 정보 세팅
			$this->add('skin', Context::get('skin'));
			$this->add('document_srl', Context::get('document_srl'));
			$this->add('comment_srl', Context::get('comment_srl'));
			$this->add('list_count', Context::get('list_count'));
			$this->add('content_link', Context::get('content_link'));

			$this->setMessage('success_deleted');
		}

		// 입력창 컴파일
		function procSocialxeCompileInput(){
			$this->add('output', $this->_compileInput());
		}

		function _compileInput(){
			$skin = Context::get('skin');

			// socialxe_comment 위젯을 구한다.
			$oWidgetController = &getController('widget');
			$widget = $oWidgetController->getWidgetObject('socialxe_comment');
			if (!$widget)   return;

			$output = $widget->_compileInput($skin, urlencode($this->session->getSession('callback_query')));
			$this->session->clearSession('callback_query');

			return $output;
		}

		// info 컴파일
		function procSocialxeCompileInfo(){
			$this->add('output', $this->_compileInfo());
		}

		function _compileInfo(){
			$skin = Context::get('skin');

			// socialxe_info 위젯을 구한다.
			$oWidgetController = &getController('widget');
			$widget = $oWidgetController->getWidgetObject('socialxe_info');
			if (!$widget)   return;

			$output = $widget->_compileInfo($skin);

			return $output;
		}

		// 목록 컴파일
		function procSocialxeCompileList(){
			$this->add('output', $this->_compileList());
		}

		function _compileList(){
			$skin = Context::get('skin');
			$document_srl = Context::get('document_srl');
			$last_comment_srl = Context::get('last_comment_srl');
			$list_count = Context::get('list_count');
			$content_link = Context::get('content_link');

			// socialxe_comment 위젯을 구한다.
			$oWidgetController = &getController('widget');
			$widget = $oWidgetController->getWidgetObject('socialxe_comment');
			if (!$widget)   return;

			return $output = $widget->_compileCommentList($skin, $document_srl, $content_link, $last_comment_srl, $list_count);
		}

		// 대댓글 컴파일
		function procSocialxeCompileSubList(){
			//@kakikaki - 디버그 추가
			debugPrint('procCompileSubList Entered!');
			debugPrint('function name changed to procSocialxeCompileSubList !');
			//echo "ASDFASDFASDFASDFASFADSF";
			//
			$skin = Context::get('skin');
			$document_srl = Context::get('document_srl');
			$comment_srl = Context::get('comment_srl');
			$content_link = Context::get('content_link');
			$page = Context::get('page');
			
			// socialxe_comment 위젯을 구한다.
			$oWidgetController = &getController('widget');
			$widget = $oWidgetController->getWidgetObject('socialxe_comment');
			if (!$widget)   return;
			
			$output = $widget->_compileSubCommentList($skin, $document_srl, $comment_srl, $content_link, $page);
			
			//@kakikaki - 디버그 추가
			debugPrint('skin : '.$skin);
			debugPrint('document_srl : '.$document_srl);
			debugPrint('comment_srl : '.$comment_srl);
			debugPrint('content_link : '.$content_link);
			debugPrint('page : '.$page);
			debugPrint('output : '.$output->get('output'));
			
			$this->add('output', $output->get('output'));
			$this->add('comment_srl', $comment_srl);
			$this->add('total', $output->get('total'));
		}

		// 소셜 로그인 처리
		function doSocialLogin(){
			$provider = Context::get('provider');
			if (!$this->providerManager->inProvider($provider)) return new Object(-1, 'msg_invalid_provider');

			// 로그인되었는지 확인한다.
			if (!$this->providerManager->isLogged($provider)) return new Object(-1, 'msg_not_logged_social');

			// 아이디
			$id = $this->providerManager->getProviderID($provider);
			if (!$id) return new Object(-1, 'msg_not_logged_social');
            
			//debug : @kakikaki
			$newest_profile_img_url =  $this->providerManager->getMasterProviderProfileImage();
			$newest_access = $this->providerManager->getAccess($provider);
			$newest_account = $this->providerManager->getAccount($provider); 
			
			// 해당 서비스의 아이디로 가입된 회원이 있는지 검색
			$args->provider = $provider;
			$args->id = $id;
			$output = executeQuery('socialxe.getMemberBySocialId', $args);
			if (!$output->toBool()) return $output;

			// 트위터의 경우 하위 호환성을 위해
			// 검색된 결과가 없으면 nick_name을 이용하여 다시 검색해본다.
			if ($provider == 'twitter' && !$output->data){
				$args->id = $this->providerManager->getProviderNickName($provider);
				$output = executeQuery('socialxe.getMemberBySocialId', $args);
				if (!$output->toBool()) return $output;

				// 검색된 결과가 있으면(예전 방식의 아이디 형식으로 저장되어 있으면)
				// 새로운 방식의 아이디 형식으로 변환시켜준다.
				if ($output->data){
					unset($args);
					$args->member_srl = $output->data->member_srl;
					$args->provider = $provider;
					$args->id = $id;
					$output2 = executeQuery('socialxe.updateSocialInfo', $args);
					if (!$output2->toBool()) return $output2;
				}
			}

			// 만약 가입된 회원이 없으면 가입 처리를 위해 일단 리턴한다.
			if (!$output->data){
				$result = new Object();
				$result->add('first', true);
				return $result;
			}

			/**
			 * modified @kakikaki
			 */
			// 로그인 시킨다.
			$member_srl = $output->data->member_srl;

			$oMemberModel = &getModel('member');
			$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
			if (!$member_info) return new Object(-1, '뭔가 잘못딤 테스트 : $output->data->member_srl : '.$output->data->member_srl);
			
			/**
			 * modify end @kakikaki
			 */

			// 1.5.0 이상에서 사용자 확인 변수 확인
			if(defined("__XE__")) {
				/*
				$open_ids = $oMemberModel->getMemberOpenIDByMemberSrl($member_srl);
				if(!$open_ids || count($open_ids) <1 || array_search($member_info->user_id, $open_ids)) {
					$args->member_srl = $member_srl;
					$args->openid = $member_info->user_id;
					executeQuery('member.addOpenIDToMember', $args);
				}
				*/
				$config = $oMemberModel->getMemberConfig();
				if ($config->identifier == 'email_address') {
					$member_info->user_id = $member_info->email_address;
				}
			}
			// 로그인은 기본적으로 자동 로그인으로...
			$oMemberController = &getController('member');
			//TODO XE 자동 로그인 버그 때문에 일단 자동 로그인은 해제
			// http://xe.xpressengine.net/19469260
			$oMemberController->doLogin($member_info->user_id, '', false);
			
			//print_r($_SESSION); //@kakikaki : 여기서는 옛날 프로필로 바뀐다. 고로 oMemberController->doLogin 시 세션값이 db 의 것으로 교체됨

			// 액세스 토큰을 갱신한다.
			//$session->access = $this->providerManager->getAccess($provider);
			//$session->account = $this->providerManager->getAccount($provider);
			$session->access = $newest_access;
			$session->account = $newest_account;
			
			unset($args);
			$args->member_srl = $member_srl;
			$args->access = serialize($session);
			$args->nick_name = $this->providerManager->getProviderNickName($provider);
			$args->provider = $provider;
			$output = executeQuery('socialxe.updateSocialInfo', $args);
			
			//added by @kakikaki : 프로필 이미지 신규 fetch
			$this->insertProfileImageFromUrl($member_srl, $newest_profile_img_url);
			//adding end
			
			if (!$output->toBool()) return $output;

			return new Object();
		}

		// 소셜 로그인 가입 처리
		function procSocialxeLoginInsert(){
			$config = $this->config;

			$email_address = Context::get('email_address');
			$allow_mailing = Context::get('allow_mailing');

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
			if (!$this->providerManager->isLogged($provider)) return $this->stop('msg_not_logged_social');

			// 회원 가입 시킨다.
			$id = $this->providerManager->getProviderID($provider);
			$nick_name = $this->providerManager->getProviderNickName($provider);

			// 닉네임 중복 검사(100번 시도한다)
			$tmp_nick_name = $nick_name;
			$nick_name_ok = false;
			$oMemberModel = &getModel('member');
			for ($i = 0; $i < 100; $i++){
				$member_srl = $oMemberModel->getMemberSrlByNickName($tmp_nick_name);
				if($member_srl){
					$tmp_nick_name = $nick_name . $i;
				}else{
					$nick_name = $tmp_nick_name;
					$nick_name_ok = true;
					break;
				}
			}
			if (!$nick_name_ok) return $this->stop('msg_exists_nick_name');

			// 준비 1.5.0 이상에서는 아이디를 지정하지 않음
			if(!defined("__XE__")) $args->user_id = '_sx.' . $provider . '.' . $id;
			$args->nick_name = $args->user_name = $nick_name;
			$args->email_address = $email_address;
			$text_array = array('\~','\!','\@','\#','\$','\%','\^','\&','\*','\(','\)','\_','\+','\=','\-','\`','\,','\.','\/','\<','\>','\?','\;','\'','\:','\"','\[','\]','\{','\}','\\','\|');

			$args->password = $text_array[rand(0,31)].md5(time()).$text_array[rand(0,31)].md5(getmicrotime());
			$args->allow_mailing = $allow_mailing;
			if ($args->allow_mailing != 'Y') $args->allow_mailing = 'N';

			// 가입!
			$oMemberController = &getController('member');
			$output = $oMemberController->insertMember($args);
			if(!$output->toBool()) return $this->stop($output->getMessage());
			$member_srl = $output->get('member_srl');

			// 1.5.0 이상에서 아이디 추가 - 꼼수인가;
			if(defined("__XE__")) {
				$args->member_srl = $member_srl;
				$args->user_id = '_sx.' . $provider . '.' . $id;
				$output2 = executeQuery('socialxe.updateMemberId', $args);
				if(!$output2->toBool()) {
					$oMemberController->deleteMember($member_srl);
					return $output2;
				}
				$args->member_srl = $member_srl;
				$args->openid = $args->user_id;
				executeQuery('member.addOpenIDToMember', $args);
			}

			// 소셜 정보 추가
			$session->access = $this->providerManager->getAccess($provider);
			$session->account = $this->providerManager->getAccount($provider);
			$logged_info = Context::get('logged_info');
			$args->member_srl = $member_srl;
			$args->provider = $provider;
			$args->id = $id;
			$args->nick_name = $this->providerManager->getProviderNickName($provider);
			$args->access = serialize($session);
			$output = executeQuery('socialxe.addSocialInfoToMember', $args);
			if (!$output->toBool()){
				$oMemberController->deleteMember($member_srl);
				return $output;
			}
			
			//added by @kakikaki 소셜 프로필 사진을 XE에 추가 
			$profile_img_url = $this->providerManager->getMasterProviderProfileImage();
			
			$this->insertProfileImageFromUrl($member_srl, $profile_img_url);
			//add end

			// 마스터 설정
			$output = $this->setSocialInfoMaster($member_srl, $provider);
			if (!$output->toBool()){
				$oMemberController->deleteMember($member_srl);
				return $output;
			}

			// 1.5.0 이상에서 사용자 확인 변수 확인
			if(defined("__XE__")) {
				$config = $oMemberModel->getMemberConfig();
				if ($config->identifier == 'email_address') {
					$args->user_id = $args->email_address;
				}
			}
			// 로그인은 기본적으로 자동 로그인으로...
			//TODO XE 자동 로그인 버그 때문에 일단 자동 로그인은 해제
			// http://xe.xpressengine.net/19469260
			$output = $oMemberController->doLogin($args->user_id, '', false);
			if(!$output->toBool()) return $this->stop($output->getMessage());
		}

		// 소셜 정보 대표 계정 설정
		function setSocialInfoMaster($member_srl, $provider){
			if (!$member_srl || !$provider) return new Object(-1, 'msg_invalid_request');

			// 해당 회원의 대표 계정이 설정되었는지 확인
			$oSocialxeModel = &getModel('socialxe');
			$output = $oSocialxeModel->getSocialInfoMasterByMemberSrl($member_srl);
			if (!$output->toBool()) return $output;
			$master = $output->get('master_provider');

			$args->member_srl = $member_srl;
			$args->master = $provider;

			// 대표 계정이 설정되었으면 업데이트
			if ($master){
				$output = executeQuery('socialxe.updateMasterProvider', $args);
			}

			// 아직 대표 계정이 설정되지 않았으면 삽입
			else{
				$output = executeQuery('socialxe.insertMasterProvider', $args);
			}

			return $output;
		}

		// 소셜 정보 연결
		function linkSocialInfo(){
			$config = $this->config;

			$provider = Context::get('provider');
			if (!$provider) return new Object(-1, 'msg_invalid_request');

			// 소셜 정보 통합을 사용하지 않으면 중지
			if ($config->use_social_info != 'Y') return new Object(-1, 'msg_not_use_social_info');

			// 로그인 중이 아니면 중지
			$logged_info = Context::get('logged_info');
			if (!$logged_info) return new Object(-1, 'msg_not_permitted');

			// 소셜 연결 중이 아니면 중지
			$mode = $this->session->getSession('mode');
			if ($mode != 'linkSocialInfo') return $this->stop('msg_invalid_request');

			// 해당 서비스의 로그인이 되어 있지 않으면 중지
			if (!$this->providerManager->isLogged($provider)) return $this->stop('msg_not_logged_social');

			// 해당 서비스가 이미 추가되어 있는지 확인
			$args->provider = $provider;
			$args->id = $this->providerManager->getProviderID($provider);
			$output = executeQuery('socialxe.getMemberBySocialId', $args);
			if (!$output->toBool()) return $output;
			if ($output->data){
				// 해당 서비스 로그아웃 처리
				$this->providerManager->doLogout($provider);
				return new Object(-1, 'msg_provider_id_exist');
			}

			// DB에 추가
			$session->access = $this->providerManager->getAccess($provider);
			$session->account = $this->providerManager->getAccount($provider);
			$args->member_srl = $logged_info->member_srl;
			$args->nick_name = $this->providerManager->getProviderNickName($provider);
			$args->access = serialize($session);
			$output = executeQuery('socialxe.addSocialInfoToMember', $args);
			
			// 프로필 사진 fetch @kakikaki
			$profile_img_url = $this->providerManager->getProviderProfileImage($provider);
			$this->insertProfileImageFromUrl($logged_info->member_srl, $profile_img_url);
			// fetch end
			
			if (!$output->toBool()) return $output;

			return new Object();
		}

		// 소셜 연결 끊기
		function procSocialxeUnlinkSocialInfo(){
			$provider = Context::get('provider');
			if (!$provider) return $this->stop('msg_invalid_request');

			// 로그인되어 있지 않으면 중지
			$logged_info = Context::get('logged_info');
			if (!$logged_info->member_srl) return $this->stop('msg_not_permitted');

			// 가입 때 사용한 서비스인지 확인
			$oSocialxeModel = &getModel('socialxe');
			$first_provider = $oSocialxeModel->getFirstProviderById($logged_info->user_id);
			if ($first_provider == $provider) return $this->stop('msg_first_provider');

			// DB에서 삭제
			$args->member_srl = $logged_info->member_srl;
			$args->provider = $provider;
			$output = executeQuery('socialxe.deleteSocialInfoByProvider', $args);
			return $output;
		}

		// 소셜 전송 켜기/끄기
		function procSocialxeSetSend(){
			$provider = Context::get('provider');
			$sw = Context::get('sw');
			if (!$provider || !$sw) return $this->stop('msg_invalid_request');

			// 로그인되어 있지 않으면 중지
			$logged_info = Context::get('logged_info');
			if (!$logged_info->member_srl) return $this->stop('msg_not_permitted');

			// 스위치 확인
			if ($sw != 'Y') $sw = 'N';

			// DB 업데이트
			$args->member_srl = $logged_info->member_srl;
			$args->provider = $provider;
			$args->send = $sw;
			$output = executeQuery('socialxe.updateSocialSend', $args);
			if (!$output->toBool()) return $output;

			// 켰으면 로그인 처리
			if ($sw == 'Y'){
				$oSocialxeModel = &getModel('socialxe');
				$output = $oSocialxeModel->getSocialInfoByMemberSrl($logged_info->member_srl);
				$social_info = $output->get('social_info');
				$this->providerManager->doLogin($provider, $social_info[$provider]['session']->access, $social_info[$provider]['session']->account);
			}

			// 껏으면 로그아웃 처리
			else{
				$this->providerManager->doLogout($provider);
			}
		}

		// 소셜 정보 대표 계정 변경
		function procSocialxeChangeMasterProvider(){
			$provider = Context::get('provider');
			if (!$this->providerManager->inProvider($provider)) return $this->stop('msg_invalid_provider');

			// 로그인되어 있지 않으면 중지
			$logged_info = Context::get('logged_info');
			if (!$logged_info->member_srl) return $this->stop('msg_not_permitted');

			$output = $this->setSocialInfoMaster($logged_info->member_srl, $provider);
			if (!$output->toBool()) return $output;

			// 마스터계정의 프로필 이미지로 프로필 이미지 등록 : @kakikaki
			$profile_img_url = $this->providerManager->getProviderProfileImage($provider);
			$this->insertProfileImageFromUrl($logged_info->member_srl, $profile_img_url);
			debugPrint($profile_img_url); //@kakikaki - 지울것
			
			// 대표 계정 설정
			return $this->providerManager->setMasterProvider($provider);
		}

		// 내 소셜 설정으로 초기화
		function procSocialxeResetSocialInfo(){
			$skin = Context::get('skin');

			// 로그인되어 있지 않으면 중지
			$logged_info = Context::get('logged_info');
			if (!$logged_info->member_srl) return $this->stop('msg_not_permitted');

			$this->_initSocialInfo();


			$output = $this->_compileInfo();
			$this->add('skin', $skin);
			$this->add('output', $output);
		}

		// 회원 로그인 시 트리거
		function triggerLogin(&$member_info){
			// 소셜 정보 통합 기능을 사용중이면 모든 소셜 서비스를 로그아웃 시킨 후
			// DB에 저장된 정보를 이용하여 로그인시킨다.
			if ($this->config->use_social_info != 'Y') return new Object();

			$this->_initSocialInfo($member_info);

			return new Object();
		}

		// 소셜 설정으로 초기화
		function _initSocialInfo($member_info = null){
			// 전부 로그아웃
			$this->providerManager->doFullLogout();

			if (!$member_info){
				$member_info = Context::get('logged_info');
				if (!$member_info) return;
			}

			// 이 회원의 소셜 정보 얻기
			$oSocialxeModel = &getModel('socialxe');
			$output = $oSocialxeModel->getSocialInfoByMemberSrl($member_info->member_srl);
			if (!$output) return $output;
			$social_info = $output->get('social_info');
			if (!$social_info) $social_info = array();

			// 로그인 처리
			foreach($social_info as $provider => $val){
				// 전송 설정되어 있는 것만 로그인
				if ($val['send'] == 'Y'){
					$this->providerManager->doLogin($provider, $val['session']->access, $val['session']->account);
				}
			}

			// 대표 계정 설정
			$output = $oSocialxeModel->getSocialInfoMasterByMemberSrl($member_info->member_srl);
			if (!$output->toBool()) return $output;
			$master = $output->get('master_provider');
			$output = $this->providerManager->setMasterProvider($master);
			if (!$output->toBool()) return $output;

			// 자동로그인 키는 자동로그인 시도를 막기 위해 더미값을 설정해둔다.
			$this->session->setSession('auto_login_key', 'dummy');

			$this->providerManager->syncLogin();
		}

		// 회원 탈퇴 시 트리거
		function triggerDeleteMember(&$obj){
			// 회원과 연결된 소셜 정보를 삭제한다.
			$args->member_srl = $obj->member_srl;
			$output = executeQuery('socialxe.deleteSocialInfoByMemberSrl', $args);
			if (!$output->toBool()) return $output;

			$output = executeQuery('socialxe.deleteMasterProvider', $args);
			return $output;
		}

		// 글 작성 트리거
		function triggerInsertDocument(&$document){
			// widget, textyle 모듈은 실행하지 않는다.
			$module_info = Context::get('module_info');
			if (!$module_info->module){
				$oModuleModel = &getModel('module');
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_info->module_srl);
			}
			if (in_array($module_info->module, array('widget', 'textyle'))) return new Object();

			// 현재 모듈이 소셜 통합 기능 사용 중인지 확인한다.
			$oSocialxeModel = &getModel('socialxe');
			$config = $oSocialxeModel->getModulePartConfig($document->module_srl);
			if ($config->use_social_info != Y) return new Object();

			// 비밀글인지 확인
			if ($document->is_secret == 'Y') return new Object();

			// 임시저장, 게시물 관리 때는 실행하지 않는다.
			if (in_array(Context::get('act'), array('procMemberSaveDocument', 'procDocumentManageCheckedDocument'))) return new Object();

			// 소셜 사이트로 전송한다.
			$this->sendDocumentToSocial($document, $module_info);

			return new Object();
		}

		// 글 업데이트 전 트리거
		function triggerBeforeUpdateDocument(&$obj){
			// 게시물 관리 때는 실행하지 않는다.
			if (Context::get('act') == 'procDocumentManageCheckedDocument') return new Object();

			// widget, textyle 모듈은 실행하지 않는다.
			$module_info = Context::get('module_info');
			if (!$module_info->module){
				$oModuleModel = &getModel('module');
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_info->module_srl);
			}
			if (in_array($module_info->module, array('widget', 'textyle'))) return new Object();

			// 글을 가져온다.
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($obj->document_srl);

			// 글의 module_srl과 obj의 module_srl이 다르다면 임시저장된 글을 등록한다고 판단할 수 있다.
			// 즉, 글의 module_srl과 obj의 module_srl이 같으면 글 수정이므로 더 이상 진행하지 않는다.
			if ($oDocument->get('module_srl') == $obj->module_srl) return new Object();

			// 글 업데이트 후 트리거에서 실행하도록 글로벌 변수를 하나 설정한다.
			$GLOBALS['socialxe_update_document_flag'] = true;

			return new Object();
		}

		// 글 업데이트 후 트리거
		function triggerAfterUpdateDocument(&$obj){
			// 플래그 설정되었는지 확인
			if (!$GLOBALS['socialxe_update_document_flag']) return new Object();

			// 현재 모듈이 소셜 통합 기능 사용 중인지 확인한다.
			$oSocialxeModel = &getModel('socialxe');
			$config = $oSocialxeModel->getModulePartConfig($obj->module_srl);
			if ($config->use_social_info != Y) return new Object();

			// 비밀글인지 확인
			if ($obj->is_secret == 'Y') return new Object();

			// 소셜 전송
			$this->sendDocumentToSocial($obj, null);

			return new Object();
		}

		// 글을 소셜 전송
		function sendDocumentToSocial($document, $module_info){
			// 파일 첨부 시 이미 생성된 전역 변수로 인해 제대로 가져오기 못한다.
			// 전역 변수를 없애버린다!
			unset($GLOBALS['XE_DOCUMENT_LIST'][$document->document_srl]);

			// 글을 가져온다.
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($document->document_srl);

			// 데이터 준비
			$args->module_srl = $document->module_srl;
			$args->content = '';
			$args->content_link = getNotEncodedFullUrl('', 'document_srl', $document->document_srl);
			$args->content_title = $this->htmlEntityDecode($document->title);
			$args->content_thumbnail = $oDocument->getThumbnail(250, 250);

			// 플래닛은 따로 처리
			if ($module_info->module == "planet"){
				$args->content_title = '';
				$args->content = $document->content;
			}

			// 소셜 서비스로 전송
			$output = $this->sendSocialComment($args, $document->document_srl, $msg);
			// 에러는 무시하자...
		}

		// 글 삭제 트리거
		function triggerDeleteDocument(&$document){
			$args->comment_srl = $document->document_srl;
			$output = executeQuery('socialxe.deleteSocialxe', $args);
			if (!$output->toBool()) return $output;
		}

		// 댓글 작성 트리거
		function triggerInsertComment(&$comment){
			// SocialXE 댓글 위젯에서 작성되는 댓글에는 작동하지 않는다.
			if (Context::get('act') == 'procSocialxeInsertComment') return new Object();

			// 현재 모듈이 소셜 통합 기능 사용 중인지 확인한다.
			$oSocialxeModel = &getModel('socialxe');
			$config = $oSocialxeModel->getModulePartConfig($comment->module_srl);
			if ($config->use_social_info != Y) return new Object();

			// 글이 비밀글인지 확인
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($comment->document_srl);
			if ($oDocument->isSecret()) return new Object();

			// 비밀 댓글인지 확인
			if ($comment->is_secret == 'Y') return new Object();

			// 데이터 준비
			$args->module_srl = $comment->module_srl;
			$args->content = $this->htmlEntityDecode(cut_str(strip_tags($comment->content), 400, ''));
			$args->content_link = getFullUrl('', 'document_srl', $comment->document_srl) . '#comment_' . $comment->comment_srl;
			$args->content_thumbnail = $oDocument->getThumbnail(250, 250);

			// 댓글의 최고 부모 댓글을 구한다.
			$output = executeQuery('comment.getCommentListItem', $comment);
			$head = $output->data->head;

			// 댓글 depth 2이상이면 부모 댓글을 최고 부모 댓글로 설정한다.
			if ($comment->parent_srl && $head != $comment->parent_srl){
				$args->parent_srl = $head;

				// 부모 댓글의 소셜 정보를 가져온다.
				$output = $oSocialxeModel->getSocialByCommentSrl($comment->parent_srl);

				// 내용에 부모 댓글의 사용자에게 보내는 멘션 형식을 포함시킨다.
				if ($output->data){
					$mention_type = $this->providerManager->getReplyPrefix($output->data->provider, $output->data->id, $output->data->social_nick_name);
					$args->content = $mention_type . ' ' . $args->content;
				}
			}

			// depth 0
			else if (!$comment->parent_srl){
				// 글의 소셜 정보를 가져온다.
				$output = $oSocialxeModel->getSocialByCommentSrl($comment->document_srl);

				// 내용에 글의 사용자에게 보내는 멘션 형식을 포함시킨다.
				if ($output->data){
					$mention_type = $this->providerManager->getReplyPrefix($output->data->provider, $output->data->id, $output->data->social_nick_name);
					$args->content = $mention_type . ' ' . $args->content;
				}
			}

			// depth 1
			else{
				$args->parent_srl = $comment->parent_srl;
			}

			if (!$args->parent_srl){
				$args->content_title = $this->htmlEntityDecode($oDocument->getTitleText());
			}

			// 소셜 서비스로 전송
			$output = $this->sendSocialComment($args, $comment->comment_srl, $msg);
			// 에러는 무시하자...
		}

		// 댓글 삭제 트리거
		function triggerDeleteComment(&$comment){
			if (!$comment->comment_srl) return new Object();

			$args->comment_srl = $comment->comment_srl;
			$output = executeQuery('socialxe.deleteSocialxe', $args);
			if (!$output->toBool()) return $output;

			// 텍스타일이면 지지자 처리
			$oModuleModel = &getModel('module');
			$module_info = $oModuleModel->getModuleInfoByDocumentSrl($comment->document_srl);
			if ($module_info->module == 'textyle'){
				unset($args);
				$args->module_srl = $module_info->module_srl;
				$args->nick_name = $comment->nick_name;
				$args->member_srl = $comment->member_srl;
				$args->homepage = $comment->homepage;
				$args->comment_count = -1;

				$oTextyleController = &getController('textyle');
				$oTextyleController->updateTextyleSupporter($args);
			}

			return new Object();

		}

		// 텍스타일 메뉴 설정
		function triggerGetTextyleCustomMenu(&$custom_menu) {
			// menu 5(설정) 메뉴에 추가
			$attache_menu5 = array(
				'dispSocialxeTextyleTool' => Context::getLang('socialxe')
			);
			if(!$custom_menu->attached_menu[5]) $custom_menu->attached_menu[5] = array();
			$custom_menu->attached_menu[5] = array_merge($custom_menu->attached_menu[5], $attache_menu5);
		}

		// 텍스타일 발행 시 처리
		function textylePostPublish($oModule){
			// 텍스타일 발행 처리 오류가 있으면 처리하지 않음
			if ($oModule->error) return;

			// 문서 번호
			$document_srl = Context::get('document_srl');
			if (!$document_srl) return;

			// 문서를 얻는다.
			$document = $GLOBALS['socialxe_textyle_document_' . $document_srl];
			if (!$document) return;

			// 기발행 여부 체크
			$isPublished = $GLOBALS['socialxe_textyle_published_' . $document_srl];

			// 예약 발행 여부
			$isSubscription = Context::get('subscription');

			// 처음 발행 && !예약발행
			// 또는
			// 기발행 && !예약발행 && 이전에 예약발행 설정이었으면
			// 소셜 사이트로 전송
			if ( (!$isPublished && $isSubscription == 'N') || ($isPublished && $isSubscription == 'N' && $document->get('module_srl') < 0) ){
				// 데이터 준비
				$args->module_srl = $document->get('module_srl');
				$args->content = '';
				$args->content_link = getNotEncodedFullUrl('', 'document_srl', $document->get('document_srl'));
				$args->content_title = $document->getTitleText();

				// 소셜 서비스로 전송
				$output = $this->sendSocialComment($args, $document->document_srl, $msg);
				// 에러는 무시하자...
			}

			// 예약 발행이면 예약 정보를 DB에 저장
			else if ($isSubscription == 'Y'){
				// 우선 현재 예약 정보를 삭제
				$args->document_srl = $document_srl;
				$output = executeQuery('socialxe.deleteTextylePostSubscriptionSocialInfo', $args);
				// 에러는 무시하자

				// 현재 로그인한 서비스와 액세스 정보를 얻는다.
				$config->logged_provider_list = $this->providerManager->getLoggedProviderList();

				$config->master_provider = $this->providerManager->getMasterProvider();
				$config->master_id = $this->providerManager->getMasterProviderId();
				$config->master_nickname = $this->providerManager->getMasterProviderNickName();
				$config->master_profile_image = $this->providerManager->getMasterProviderProfileImage();

				$config->slave_provider = $this->providerManager->getSlaveProvider();
				$config->slave_id = $this->providerManager->getSlaveProviderId();
				$config->slave_nickname = $this->providerManager->getSlaveProviderNickName();

				foreach($config->logged_provider_list as $provider){
					$config->access[$provider] = $this->providerManager->getAccess($provider);
				}

				$args->document_srl = $document_srl;
				$args->config = serialize($config);
				$output = executeQuery('socialxe.insertTextylePostSubscriptionSocialInfo', $args);
				// 에러는 무시하자...
			}
		}

		// 텍스타일 예약 발행 처리
		function textylePublishSubscriptedPost($document_srl){
			// 문서를 얻는다.
			$oDocumentModel = &getModel('document');
			$document = $oDocumentModel->getDocument($document_srl);
			if (!$document) return;

			// 소셜 정보 준비
			$args->document_srl = $document_srl;
			$output = executeQuery('socialxe.getTextylePostSubscriptionSocialInfo', $args);
			if (!$output->data) return;

			$config = unserialize($output->data->config);

			// 데이터 준비
			$args->module_srl = intVal($document->get('module_srl')) * -1;
			$args->content = '';
			$args->content_link = getNotEncodedFullUrl('', 'document_srl', $document->get('document_srl'));
			$args->content_title = $document->getTitleText();

			// 소셜 서비스로 전송
			$output = $this->sendSocialComment($args, $document->document_srl, $msg, $config);
			// 에러는 무시하자...

			// 소셜 정보 삭제
			$args->document_srl = $document_srl;
			$output = executeQuery('socialxe.deleteTextylePostSubscriptionSocialInfo', $args);
		}

		// 모듈에 속한 소셜 정보 삭제
		function deleteModuleSocial(&$obj) {
			$module_srl = $obj->module_srl;
			if(!$module_srl) return new Object();

			// 삭제
			$args->module_srl = $module_srl;
			$output = executeQuery('socialxe.deleteModuleSocial', $args);

			return $output;
		}
	}
?>
