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
				,	'http://schemas.google.com/CheckInActivity'
				,	'http://schemas.google.com/CommentActivity'
				,	'http://schemas.google.com/CreateActivity'
				,	'http://schemas.google.com/DiscoverActivity'
				,	'http://schemas.google.com/ListenActivity'
				,	'http://schemas.google.com/ReserveActivity'
				,	'http://schemas.google.com/ReviewActivity'
				,	'http://schemas.google.com/WantActivity'
			);
			
			$gClient = new Google_Client();
			$gClient->setApplicationName('Login to ceri2013.cafe24.com');
			$gClient->setClientId($this->google_client_id);
			$gClient->setClientSecret($this->google_client_secret);
			$gClient->setRedirectUri($this->google_redirect_url);
			$gClient->setDeveloperKey($this->google_developer_key);
			$gClient->setRequestVisibleActions($requestVisibleActions);
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
			//echo 'Can not instanicate Google_Client object';

			//debug
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
		$oauth_token = Context::get('code'); //codeFromGoogle
		$gClient = $this->getGoogleClient();
		$google_oauthV2 = $this->getGoogleOauth2($gClient); //auth 다음에 하면  Cant add services after having authenticated
		$gClient->authenticate($oauth_token);
		
		$access_token = $gClient->getAccessToken();
		

		
		if($access_token != NULL){
			$this->session->setSession('google', $access_token);
			
			//사용자 정보를  받아서 저장한다.			
			$account = $google_oauthV2->userinfo->get();
			
			//엑세스 토큰과 사용자 정보를 묶는다.
			$info['access'] = $this->session->getSession('google');
			$info['account'] = $account;
			
			
			//return $this->stop('msg_error_google'.'324324324324'.$account['picture']); //여기선 최신사진 잘 가져옴
			$result = new Object();
			$result->add('info',$info);
			
			return $result;
		}else{
			return $this->stop('msg_error_google');
		}
		
	}
	
	// 댓글 전송
	function send($comment, $access, $userlang = 'en', $use_socialxe = false){
		$result = new Object();
		//$result->add('result', $output);
		return $result;
	}

}

?>