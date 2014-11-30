<?php

// 서비스 관리를 위한 클래스
class socialxeProviderManager{
	var $master_provider = null;

	// 인스턴스
	function getInstance(&$sessionManager){
		static $instance;
		if (!isset($instance)) $instance = new socialxeProviderManager($sessionManager);
		return $instance;
	}

	// 생성자
	function socialxeProviderManager(&$sessionManager){
		// 세션 관리자 저장
		$this->session = $sessionManager;

		// 제공하는 서비스
		$this->provider_list = array('xe', 'twitter', 'me2day', 'facebook');

		// 각 서비스 클래스
		$this->provider['xe'] = &socialxeProviderXE::getInstance($this->session);
		$this->provider['twitter'] = &socialxeProviderTwitter::getInstance($this->session);
		$this->provider['me2day'] = &socialxeProviderMe2day::getInstance($this->session);
		$this->provider['facebook'] = &socialxeProviderFacebook::getInstance($this->session);
	}

	// 환경 설정 값 세팅
	function setConfig($config){
		if ($this->config) return;

		$this->config = $config;

		$this->init();
	}

	// 초기화
	function init(){
		// 부계정 설정
		$slave_provider = $this->session->getSession('slave');
		if ($slave_provider)
			$this->setSlaveProvider($slave_provider);

		// 대표계정 설정
		$master_provider = $this->session->getSession('master');
		if ($master_provider)
			$this->setMasterProvider($master_provider, true);
		else
			$this->setNextMasterProvider();

		// 보험용! 대표 계정 확인
		if (!$this->getMasterProvider())
			$this->setNextMasterProvider();
	}

	// 제공하는 전체 서비스 목록
	function getFullProviderList(){
		return $this->provider_list;
	}

	// 제공하는 서비스 목록(환경설정에서 선택한 것만)
	function getProviderList(){
		static $result;
		// 이미 호출 된 적 있다면, 저장된 값 반환
		if(!is_array($result)) {
			$provider_list = $this->getFullProviderList();
			$result = array();
			foreach($provider_list as $provider){
				if ($this->config->select_service[$provider] == 'Y'){
					$result[] = $provider;
				}
			}
		}
		return $result;
	}

	// 제공하는 서비스 여부 확인
	function inProvider($provider){
		return in_array($provider, $this->getProviderList());
	}

	// 로그인
	function doLogin($provider, $access, $account){
		$result = new Object();

		// 제공하는 서비스인지 확인
		if (!$this->inProvider($provider)){
			$result->setError(-1);
			return $result->setMessage('msg_invalid_provider');
		}

		// 로그인 처리
		$this->provider[$provider]->doLogin($access, $account);

		// 대표계정 설정
		if (count($this->getLoggedProviderList()) == 1){
			$this->setMasterProvider($provider);
		}

		return $result;
	}

	// 로그아웃
	function doLogout($provider){
		$result = new Object();

		// 제공하는 서비스인지 확인
		if (!$this->inProvider($provider)){
			$result->setError(-1);
			return $result->setMessage('msg_invalid_provider');
		}

		// 로그아웃 처리
		$this->provider[$provider]->doLogout();

		// 대표계정 설정
		if ($this->getMasterProvider() == $provider)
			$this->setNextMasterProvider();

		// 부계정 설정
		if ($this->getSlaveProvider() == $provider)
			$this->setNextSlaveProvider();

		return $result;
	}

	// 전부 로그아웃
	function doFullLogout(){
		$full_provider_list = $this->getFullProviderList();

		foreach($full_provider_list as $provider){
			$this->provider[$provider]->doLogout();
		}

		$this->clearMasterProvider();
		$this->clearSlaveProvider();
	}

	// 로그인 여부 싱크
	function syncLogin(){
		foreach($this->getProviderList() as $provider){
			$this->provider[$provider]->syncLogin();
		}

		// 다시 초기화
		$this->init();
	}

	// 로그인 여부
	function isLogged($provider){
		if (!$this->inProvider($provider)) return;
		return $this->provider[$provider]->isLogged();
	}

	// 로그인된 서비스 리스트
	function getLoggedProviderList(){
		$result = array();

		foreach($this->getProviderList() as $provider){
			if ($this->provider[$provider]->isLogged()){
				$result[] = $provider;
			}
		}

		return $result;
	}

	// 대표계정 설정
	function setMasterProvider($provider, $init = false){
		$result = new Object();

		if (!$provider){
			$this->setNextMasterProvider();
			return $result;
		}

		// 제공하는 서비스인지 확인
		if (!$this->inProvider($provider) && $provider != 'xe'){
			$result->setError(-1);
			return $result->setMessage('msg_invalid_provider');
		}

		// XE를 사용하고
		// XE가 로그인되어 있으면 따지지 말고 XE를 대표계정으로 만든다.
		if ($this->inProvider('xe') && $this->provider['xe']->isLogged()){
			// 부계정이 없으면 지금 대표 계정을
			if (!$this->getSlaveProvider() && $init){
				if ($provider == 'xe')
					$this->setNextSlaveProvider();
				else
					$this->setSlaveProvider($provider);
			}

			// XE 로그인 상태에서는 요청을 부계정으로 넘긴다.
			else{
				$this->setSlaveProvider($provider);
			}

			$provider = 'xe';
		}

		// XE 로그인 상태가 아닌데 세션에 XE로 되어 있으면 부계정을 대표계정으로 만든다.
		else if ($provider == 'xe'){
			$this->setMasterProvider($this->getSlaveProvider());
			$this->clearSlaveProvider();
			return $result;
		}

		// 로그인되어 있는지 확인
		if ($this->isLogged($provider)){
			// 대표계정 설정
			$this->master_provider = $provider;
			$this->session->setSession('master', $provider);
		}else{
			$this->setNextMasterProvider();
		}

		return $result;
	}

	// 다음 대표계정 설정
	function setNextMasterProvider(){
		// 대표 계정을 현재 로그인된 서비스 중 그냥 첫번째로 선택한다.
		$logged_provider_list = $this->getLoggedProviderList();

		if (count($logged_provider_list)){
			$this->setMasterProvider($logged_provider_list[0]);
		}else{
			$this->clearMasterProvider();
		}
	}

	// 대표 계정 삭제
	function clearMasterProvider(){
		$this->master_provider = null;
		$this->session->clearSession('master');
	}

	// 부계정 설정
	function setSlaveProvider($provider){
		$result = new Object();

		// 제공하는 서비스인지 확인
		if (!$this->inProvider($provider) || $provider == 'xe'){
			$result->setError(-1);
			return $result->setMessage('msg_invalid_provider');
		}

		// 로그인되어 있는지 확인
		if ($this->isLogged($provider)){
			// XE 로그인이 아니면 끝
			if ($this->provider['xe']->isLogged()){
				// 부계정 설정
				$this->slave_provider = $provider;
				$this->session->setSession('slave', $provider);
			}else{
				$this->clearSlaveProvider();
			}
		}else{
			$this->setNextSlaveProvider();
		}

		return $result;
	}

	// 부계정 삭제
	function clearSlaveProvider(){
		$this->slave_provider = null;
		$this->session->clearSession('slave');
	}

	// 다음 부계정 설정
	function setNextSlaveProvider(){
		// 부계정을 현재 로그인된 서비스 중 그냥 첫번째로 선택한다.
		$logged_provider_list = $this->getLoggedProviderList();

		if (count($logged_provider_list)){
			if ($logged_provider_list[0] != 'xe')
				$this->setSlaveProvider($logged_provider_list[0]);
			else if ($logged_provider_list[1])
				$this->setSlaveProvider($logged_provider_list[1]);
			else
				$this->clearSlaveProvider();
		}else{
			$this->clearSlaveProvider();
		}
	}

	// 대표계정
	function getMasterProvider(){
		return $this->master_provider;
	}

	// 부계정
	function getSlaveProvider(){
		return $this->slave_provider;
	}

	// 해당 서비스의 현재 로그인 아이디
	function getProviderID($provider){
		if (!$this->inProvider($provider)) return;
		return $this->provider[$provider]->getId();
	}

	// 해당 서비스의 현재 로그인 닉네임
	function getProviderNickName($provider){
		if (!$this->inProvider($provider)) return;
		return $this->provider[$provider]->getNickName();
	}

	// 대표계정의 아이디
	function getMasterProviderId(){
		// 대표계정이 설정되었는지 확인
		if (!$this->inProvider($this->getMasterProvider())) return;

		// 대표계정의 아이디
		return $this->provider[$this->getMasterProvider()]->getId();
	}

	// 대표계정의 닉네임
	function getMasterProviderNickName(){
		// 대표계정이 설정되었는지 확인
		if (!$this->inProvider($this->getMasterProvider())) return;

		// 대표계정의 닉네임
		return $this->provider[$this->getMasterProvider()]->getNickName();
	}

	// 대표계정의 프로필 이미지
	function getMasterProviderProfileImage(){
		// 대표계정이 설정되었는지 확인
		if (!$this->inProvider($this->getMasterProvider())) return;

		// 대표계정의 닉네임
		return $this->provider[$this->getMasterProvider()]->getProfileImage();
	}

	// 부계정의 아이디
	function getSlaveProviderId(){
		// 부계정이 설정되었는지 확인
		if (!$this->inProvider($this->getSlaveProvider())) return;

		// 부계정의 아이디
		return $this->provider[$this->getSlaveProvider()]->getId();
	}

	// 부계정의 닉네임
	function getSlaveProviderNickName(){
		// 부계정이 설정되었는지 확인
		if (!$this->inProvider($this->getSlaveProvider())) return;

		// 부계정의 닉네임
		return $this->provider[$this->getSlaveProvider()]->getNickName();
	}

	// 부계정의 프로필 이미지
	function getSlaveProviderProfileImage(){
		// 부계정이 설정되었는지 확인
		if (!$this->inProvider($this->getSlaveProvider())) return;

		// 부계정의 닉네임
		return $this->provider[$this->getSlaveProvider()]->getProfileImage();
	}

	// 액세스 정보 얻기
	function getAccess($provider){
		// 제공하는 서비스인지 확인
		if (!$this->inProvider($provider)) return;

		return $this->provider[$provider]->getAccess();
	}

	// 사용자 정보 얻기
	function getAccount($provider){
		// 제공하는 서비스인지 확인
		if (!$this->inProvider($provider)) return;

		return $this->provider[$provider]->getAccount();
	}

	// 액세스 정보 통으로 얻기
	function getAccesses(){
		$result = array();

		foreach($this->provider_list as $provider){
			$result[$provider] = $this->provider[$provider]->getAccess();
		}

		return $result;
	}

	// 소셜 서비스 링크
	function getAuthorLink($provider, $id, $nick_name){
		if (!$this->inProvider($provider)) return;

		return $this->provider[$provider]->getAuthorLink($id, $nick_name);
	}

	// 소셜 서비스의 리플 형식으로 반환
	function getReplyPrefix($provider, $id, $nick_name){
		if (!$this->inProvider($provider)) return;

		return $this->provider[$provider]->getReplyPrefix($id, $nick_name);
	}

	// 각 소셜 서비스의 리플 형식이 들어있는지 확인
	function getReplyProviderList($content){
		$result = Array();
		foreach($this->provider_list as $provider){
			if ($this->provider[$provider]->isContainReply($content)){
				$result[] = $provider;
			}
		}

		return $result;
	}
}

?>
