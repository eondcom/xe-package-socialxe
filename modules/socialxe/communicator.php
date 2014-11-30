<?php
if (!class_exists("Services_JSON_SocialXE")){
	require_once(_XE_PATH_.'modules/socialxe/JSON.php');
}

// 소셜XE 서버와 통신을 위한 클래스
class socialxeCommunicator{
	var $version = '0.9.1';

	// 인스턴스
	function getInstance(&$sessionManager, &$providerManager, &$config){
		static $instance;
		if (!isset($instance)) $instance = new socialxeCommunicator($sessionManager, $providerManager, $config);
		return $instance;
	}

	// 생성자
	function socialxeCommunicator(&$sessionManager, &$providerManager, &$config){
		// 세션 관리자 저장
		$this->session = $sessionManager;

		// 서비스 관리자 저장
		$this->providerManager = $providerManager;

		// 환경설정 저장
		$this->config = $config;
	}

	// 로그인 URL을 얻는다.
	function getLoginUrl($provider){
		$result = new Object();

		// 제공하는 서비스인지 확인
		if (!$this->providerManager->inProvider($provider)){
			$result->setError(-1);
			$result->setMessage('msg_invalid_provider');
			return $result;
		}

		// 요청 토큰을 얻는다.
		$output = $this->getRequestToken();
		if ($output->error){
			$result->setError($output->error);
			$result->setMessage($output->message);
			return $result;
		}
		$request_token = $output->request_token;
		if (!$request_token){
			$result->setError(-1);
			$result->setMessage('msg_request_error');
			return $result;
		}

		// 요청 토큰을 세션에 저장한다.
		$this->session->setSession('request_token', $request_token);

		// 요청 URL 생성
		$xe = preg_replace('@^https?://[^/]+/?@', '', Context::getRequestUri());
		$data = array(
			'provider' => $provider,
			'request_token' => $request_token,
			'xe' => $xe
		);
		$url = $this->getURL('login', $data);
		$result->add('url', $url);

		return $result;
	}

	// 콜백 처리
	function callback($provider, $verifier){
		$result = new Object();

		// 제공하는 서비스인지 확인
		if (!$this->providerManager->inProvider($provider)){
			$result->setError(-1);
			$result->setMessage('msg_invalid_provider');
			return $result;
		}

		// 액세스 토큰 요청
		$access_token = $this->getAccessToken($verifier);
		if (!$access_token){
			$result->setError(-1);
			$result->setMessage('msg_request_error');
			return $result;
		}

		// 요청 토큰은 이제 필요없다.
		$this->session->clearSession('request_token');

		// 로그인처리
		$access = $access_token->access;
		$account = $access_token->account;
		$output = $this->providerManager->doLogin($provider, $access, $account);
		if (!$output->toBool()) return $output;

		return $result;
	}

	// 소셜XE 서버로 세션 전송
	function sendSession(){
		// API 요청 준비
		$data = array(
			'auto_login_key' => $this->session->getSession('auto_login_key'),
			'session' => $this->session->getFullSession()
		);

		// URL 생성
		$url = $this->getURL('setsession', $data);

		$content = $this->httpRequest($url, 'POST');
	}

	// 소셜 서비스로 댓글 전송
	function sendComment($args, $manual_data = null){
		$result = new Object();

		set_time_limit(0);

		$oSocialxeModel = &getModel('socialxe');

		if ($manual_data){
			$logged_provider_list = $manual_data->logged_provider_list;
			$master_provider = $manual_data->master_provider;
			$slave_provider = $manual_data->slave_provider;
		}else{
			$logged_provider_list = $this->providerManager->getLoggedProviderList();
			$master_provider = $this->providerManager->getMasterProvider();
			$slave_provider = $this->providerManager->getSlaveProvider();
		}
		$config = $this->config;

		// $logged_provider_list에서 xe 제외
		if ($logged_provider_list[0] == 'xe'){
			array_shift($logged_provider_list);
		}

		// 데이터 준비
		$comment->content = $args->content;
		$comment->content_link = $args->content_link;
		$comment->hashtag = $config->hashtag;
		$comment->content_title = $args->content_title;
		$comment->content_thumbnail = $args->content_thumbnail;

		// 대댓글이면 부모 댓글의 정보를 준비
		if ($args->parent_srl){
			$output = $oSocialxeModel->getSocialByCommentSrl($args->parent_srl);
			$comment->parent = $output->data;
		}

		// 내용을 분석해서 각 소셜 서비스의 리플 형식이 들어있는지 확인
		$reply_provider_list = $this->providerManager->getReplyProviderList($comment->content);

		// 보낼 필요가 있는지 확인
		if (count($reply_provider_list) == 0){
			// 대댓글이면
			if ($args->parent_srl){
				// 부모 댓글에 소셜 정보가 없으면 리턴~
				if (!$comment->parent->provider || $comment->parent->provider == 'xe') return new Object();
			}

			// 대댓글이 아니면
			else{
				
				// 로그인한 소셜 서비스가 없으면 리턴~
				if (!count($logged_provider_list)) return new Object();
			}
		}

		$args->url = $this->_getCommentUrl($comment->content_link, $comment->parent->comment_srl);
		if($config->use_short_url != 'N') {
			// 생성된 짧은 주소가 있는지 확인한다.
			$output = executeQuery('socialxe.getBitlyByUrl', $args);
			$comment->short_link = $output->data->short_url;

			// 생성된 짧은 주소가 없으며 생성
			if ($this->config->bitly_username && $this->config->bitly_api_key && !$comment->short_link){
				// bit.ly 라이브러리 로드
				if (!class_exists("bitly_SocialXE")){
					require_once(_XE_PATH_.'modules/socialxe/bitly.php');
				}

				// 링크 생성
				$bitly = new bitly_SocialXE($this->config->bitly_username, $this->config->bitly_api_key);
				$content_link = $bitly->shorten(urlencode($this->_getCommentUrl($comment->content_link, $comment->parent->comment_srl)));
				if ($content_link) $comment->short_link = $content_link;
			}
		} else {
			$comment->short_link = $args->url;
		}

		// API 요청 준비
		$data = array(
			'client' => $config->client_token,
			'comment' => $comment,
			'logged_provider_list' => $logged_provider_list,
			'reply_provider_list' => $reply_provider_list,
			'master_provider' => $master_provider,
			'uselang' => Context::getLangType()
		);

		// 소셜 서비스 액세스 정보 준비
		if ($manual_data){
			$accesses = $manual_data->access;
		}else{
			$accesses = $this->providerManager->getAccesses();
		}

		foreach($accesses as $provider => $access){
			$data[$provider] = serialize($access);
		}

		// URL 생성
		$url = $this->getURL('send', $data);

		// 요청
		$content = $this->httpRequest($url, 'POST');
		
		//debug :@kakikaki
		debugPrint('$url : '.$url);
		debugPrint('$content : '.$content);
		//end

		if (!$content){
			$result->setError(-1);
			$result->setMessage('msg_request_error'); //여기서 에러 발생 (댓글 작성 : XE-1.7.4) 
			return $result;
		}

		// JSON 디코딩
		$json = new Services_JSON_SocialXE();
		$output = $json->decode($content);
		if (!$output){
			return new Object(-1, $content);
		}

		// 오류 체크
		if ($output->error){
			$result->setError(-1);
			$result->setMessage($output->message);
			return $result;
		}

		// 전송 결과를 체크
		$msg = array();
		$lang_provider = Context::getLang('provider');
		foreach($this->providerManager->getProviderList() as $provider){
			if ($output->result->{$provider}->error){
				$msg[] = sprintf(Context::getLang('msg_send_failed'), $lang_provider[$provider], $output->result->{$provider}->error);
			}
		}
		if (count($msg)){
			$msg = implode("\n", $msg);
			$result->add('msg', $msg);
		}

		if($config->use_short_url != 'N') {
			// bit.ly 결과를 저장한다.
			if ($bitly){
				$bitly_result = $bitly->getRawResults();
				$args->hash = $bitly_result['userHash'];
				$args->title = $comment->content_title;
				$args->short_url = $comment->short_link;
				$args->url = $this->_getCommentUrl($comment->content_link, $comment->parent->comment_srl);
				executeQuery('socialxe.insertBitly', $args);
			}
		}

		// 대표 계정의 댓글 번호를 세팅한다.
		if ($master_provider == 'xe' && $slave_provider)
			$comment_id = $output->result->{$slave_provider}->id;
		else if ($master_provider)
			$comment_id = $output->result->{$master_provider}->id;

		$result->add('comment_id', $comment_id);

		return $result;
	}

	// 요청 토큰 얻기
	function getRequestToken(){
		$config = $this->config;

		// 데이터 준비
		$data = array('client' => $config->client_token);

		// 요청 URL 생성
		$url = $this->getURL('request', $data);

		// 요청
		$content = $this->httpRequest($url);

		//JSON 디코딩
		$json = new Services_JSON_SocialXE();
		$output = $json->decode($content);

		return $output;
	}

	// 액세스 토큰 얻기
	function getAccessToken($verifier){
		$config = $this->getconfig;

		// 데이터 준비
		$data = array('verifier' => $verifier);

		// 요청 URL 생성
		$url = $this->getURL('access', $data);

		// 요청
		$content = $this->httpRequest($url);


		//JSON 디코딩
		$json = new Services_JSON_SocialXE();
		$output = $json->decode($content);
		return $output->access_token;
	}

	// 자동 로그인 키
	function getAutoLoginKeyUrl(){
		// 요청 URL 생성
		return $url = $this->getURL('autologinkey', null, 'N');
	}

	// 자동 로그인 키 세팅
	function setAutoLoginKey($auto_login_key){
		$this->session->setSession('auto_login_key', $auto_login_key);

		// 소셜XE 서버에서 세션을 받아 세팅한다.

		// 데이터 준비
		$data = array('auto_login_key' => $auto_login_key);

		// 요청 URL 생성
		$url = $this->getURL('getsession', $data);

		// 요청
		$content = $this->httpRequest($url);


		//JSON 디코딩
		$json = new Services_JSON_SocialXE();
		$output = $json->decode($content);

		// 세션 세팅
		if ($output->session){
			$this->session->setFullSession($output->session);
		}

		// 서비스 로그인 여부 싱크
		$this->providerManager->syncLogin();
	}

	// 요청 URL 생성
	function getURL($mode, $data, $use_ssl = 'Y'){
		$config = $this->config;

		if ($config->use_ssl == 'Y' && $use_ssl == 'Y')
			$url = 'https://';
		else
			$url = 'http://';

		$url .= $config->server_hostname . $config->server_query;

		$data = base64_encode(urlencode(serialize($data)));

		$url .= "&mode={$mode}&data={$data}&ver={$this->version}";

		return $url;
	}

	// HTTP 요청
	function httpRequest($url, $mode = 'GET'){
		if ($mode == 'POST'){
			$url_info = parse_url($url);
			$url = $url_info['scheme'] . '://' . $url_info['host'];
			if (!$url_info['port'] || $url_info['port'] == '80'){
				$url .= $url_info['path'];
			}else{
				$url .= ':'. $url_info['port'] . $url_info['path'];
			}
			$body = $url_info['query'];
		}

		$headers = array('User-Agent' => "SocialXE ClientBot Ver. {$this->version}");
		$output = FileHandler::getRemoteResource($url, $body, 30, $mode, 'application/json', $headers);
		return $output;
	}

	// comment_srl이 붙은 주소를 만든다.
	function _getCommentUrl($content_link, $comment_srl){
		if (!$comment_srl) return $content_link;

		$url_info = parse_url($content_link);
		$url = $url_info[scheme] . '://' . $url_info[host];

		if ($url_info[path])
			$url .= $url_info[path];
		else
			$url .= '/';

		if ($comment_srl){
			if ($url_info[query])
				$url .= '?' . $url_info[query] . '&comment_srl=' . $comment_srl;
			else
				$url .= '?comment_srl=' . $comment_srl;

			if ($url_info['fragment'])
				$url .= '#' . $url_info['fragment'];
			else
				$url .= '#socialxe_comment';
		}else{
			if ($url_info[query])
				$url .= '?' . $url_info[query];
		}
		return $url;
	}
}

?>
