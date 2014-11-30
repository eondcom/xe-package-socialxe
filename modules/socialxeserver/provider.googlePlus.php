<?php

// Loads Google Libs

require_once(_XE_PATH_.'modules/socialxeserver/google/Google_Client.php');
require_once(_XE_PATH_.'modules/socialxeserver/google/contrib/Google_Oauth2Service.php');
require_once(_XE_PATH_.'modules/socialxeserver/google/contrib/Google_PlusService.php');

class socialxeServerProviderGoogle extends socialxeServerProvider{
	
	//구글 클라이언트 객체 리턴
	private function getGoogleClient(){
		
		if ( isset($this->google_client_id) && isset($this->google_client_secret) && isset($this->google_redirect_url) && isset($this->google_developer_key)){
			
			$requestVisibleActions = Array(
				'http://schemas.google.com/AddActivity'
				,	'http://schemas.google.com/BuyActivity'
				//,	'http://schemas.google.com/CheckInActivity'
				,	'http://schemas.google.com/CommentActivity'
				//,	'http://schemas.google.com/CreateActivity'
				//,	'http://schemas.google.com/DiscoverActivity'
				//,	'http://schemas.google.com/ListenActivity'
				//,	'http://schemas.google.com/ReserveActivity'
				//,	'http://schemas.google.com/ReviewActivity'
				//,	'http://schemas.google.com/WantActivity'
			);
			
			$gClient = new Google_Client();
			$gClient->setApplicationName('Login to ceri2013.cafe24.com');
			$gClient->setClientId($this->google_client_id);
			$gClient->setClientSecret($this->google_client_secret);
			$gClient->setRedirectUri($this->google_redirect_url);
			$gClient->setDeveloperKey($this->google_developer_key);
			$gClient->setRequestVisibleActions($requestVisibleActions);
			$gClient->setAccessType('offline');
			$gClient->setApprovalPrompt('auto');
		
			return $gClient;
		}
	}
	
	//구글 OAuth2 객체 리턴
	private function getGoogleOauth2($gClient = NULL){
		if ($gClient != NULL) return new Google_Oauth2Service($gClient);
	}
	
	//구글 플러스 객체 리턴
	private function getGooglePlus($gClient = NULL){
		if($gClient != NULL) return new Google_PlusService($gClient);
	}
	
	
	//인스턴스
	function getInstance(&$sessionManager
			             , $google_client_id
			             , $google_client_secret
						 , $google_developer_key){
		static $instance;
		if (!isset($instance)) $instance = new socialxeServerProviderGoogle($sessionManager, $google_client_id, $google_client_secret, $google_developer_key);
		return $instance;
	}
	
	//생성자
	function socialxeServerProviderGoogle(&$sessionManager
										  , $google_client_id = 1
										  , $google_client_secret = 1
										  , $google_developer_key = 1){
										  

		parent::socialxeServerProvider('google', $sessionManager);
		$this->google_client_id = $google_client_id;
		$this->google_client_secret = $google_client_secret;
		$this->google_redirect_url = $this->getNotEncodedFullUrl('', 'module', 'socialxeserver', 'act', 'procSocialxeserverCallback', 'provider', 'google');
		$this->google_developer_key = $google_developer_key;
	}
	
	//로그인 url 을 얻는다 
	function getLoginUrl(){
		$gClient = $this->getGoogleClient();
		if ($gClient != NULL){
			
			//$google_oauthV2 = new Google_Oauth2Service($gClient);
			$google_oauthV2 = $this->getGoogleOauth2($gClient);
			$google_plus = $this->getGooglePlus($gClient);
			$loginUrl = $gClient->createAuthUrl();
			
			$result = new Object();
			$result->add('url', $loginUrl);
			return $result;
			
		}else{
			
			$inspect_str = "google_client_id : ".$this->google_client_id."\n";
			$inspect_str .= "google_client_secret : ".$this->google_client_secret."\n";
			$inspect_str .= "google_redirect_url : ".$this->google_redirect_url."\n";
			$inspect_str .= "google_developer_key : ".$this->google_developer_key."\n";
			$inspect_str .= "gClient : ".$gClient."\n";
			//debug end 
			
			return $this->stop("구글 클라이언트 객체 생성 실패. Google API 인증키 설정을 확인하십시오<br/>".$inspect_str);
		}
	}
	
	//콜백 처리 
	function callback(){
		
		//구글로부터 받은 코드를 기반으로 인증을 진행하고 엑세스 토큰을 얻으며 세션에  굽는다
		$oauth_token = Context::get('code'); 
		$gClient = $this->getGoogleClient();
		$google_oauthV2 = $this->getGoogleOauth2($gClient); //auth 다음에 하면  Cant add services after having authenticated
		$google_plus = $this->getGooglePlus($gClient);
		$gClient->authenticate($oauth_token);
		
		$access_token = $gClient->getAccessToken();
				
		if($access_token != NULL){
			$this->session->setSession('google', $access_token);
			
			//사용자 정보를  받아서 저장한다.			
			$account = $google_plus->people->get('me');
			
			//엑세스 토큰과 사용자 정보를 묶는다.
			$info['access'] = $this->session->getSession('google');
			$info['account'] = $account;
			
			$result = new Object();
			$result->add('info',$info);
			
			return $result;
		}else{
			//return $this->stop('msg_error_google');
			//로그인 취소 처리 - 이전페이지 이동 :@kakikaki 140108-0351 
			return new Object();
		}
		
	}
	
	// 댓글 전송
	function send($comment, $access, $userlang = 'en', $use_socialxe = false){
		
		$res = new Object();
		$lang->comment = $this->lang->comment[$userlang];
		if(!$lang->comment) $lang->comment = $this->lang->comment['en'];
		$lang->notify = $this->lang->notify[$uselang];
		if(!$lang->notify) $lang->notify = $this->lang->notify['en'];
		
		// 최대 포스팅 가능 길이
		$max_length = 100000;
		
		// 내용 준비
		if ($comment->content_title)
			$title = $comment->content_title;
		elseif ($use_socialxe)
			$title = $lang->notify;
		else 
			$title = $lang->comment;
		
		$content2 = '「' . $title . '」 ' . $comment->content;
		
		// 내용 길이가 최대 길이를 넘는지 확인
		$content = $this->cut_str($content2, $max_length-3, '...');
		
		// 썸네일이 제공되면 그것을 사용
		if ($comment->content_thumbnail){
			$image = $comment->content_thumbnail;
		}
		
		// 썸네일 없으면 1x1 투명 gif 파일
		else{
			$image = Context::getRequestUri() . 'modules/socialxeserver/tpl/images/blank.gif'; 
		}
		
		
		if($comment->parent && $comment->parent->provider == 'google'){
			
			$output = 'dummy';
			$userid = $comment->parent->id;
			debugPrint('userid : '.$userid);
			
		}else{
			
			$gClient = $this->getGoogleClient();
			$gClient->setAccessToken($access);
			$gPlus = $this->getGooglePlus($gClient);

			// 댓글 전송
			$moment_body = new Google_Moment();
			$moment_body->setType('http://schemas.google.com/CommentActivity');
			
			$target = new Google_ItemScope();
			$target->setUrl($comment->content_link);
			//$target->setImage($image);
			$moment_body->setTarget($target);
			
			$result = new Google_ItemScope();
			$result->setType('http://schema.org/Comment');
			$result->setUrl($comment->content_link); //원래는 comment_link 가 들어가야 하는데 xe 에서는 지원않함
			$result->setName('「' . $title . '」 ');
			$result->setText($content);
			$result->setImage($image);
			$moment_body->setResult($result);
			
			$momentResult = $gPlus->moments->insert('me','vault', $moment_body);
			
			$output = $momentResult;		
		}
		
		$res->add('result',$output);				
		return $res;
	}

}

?>
