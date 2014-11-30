<?php

// 각 소셜 서비스를 위한 클래스
class socialxeProvider{
	var $name;
	var $is_logged = false;

	// 생성자
	function socialxeProvider($name, &$sessionManager){
		// 서비스 이름
		$this->name = $name;

		// 세션관리자 저장
		$this->session = $sessionManager;

		// 관련 세션
		$this->syncLogin();
	}

	function getProviderName(){
		return $this->name;
	}

	// 로그인 여부 갱신
	function syncLogin(){
		$this->setLogin(false);

		// 관련 세션
		$session = $this->session->getSession($this->name);

		//debug
		//print_r($session);
		//debug end
		if ($session){
			// 액세스 토큰
			$this->access = $session->access;
			//echo '$this->access : '.$this->access; //@kakikaki

			// 사용자 정보
			$this->account = $session->account;
			//echo '$this->account : '.$session->account; //@kakikaki
			//print_r($this->account); //여기까지도 최신이미지 @kakikaki
			
			// 로그인
			$this->setLogin(true);
			//echo '$this->is_logged : '.$this->is_logged; //@kakikaki
			//여기까지는 통과 131127 - 1604 분 @kakikaki
		}
	}

	// 로그인 여부 설정
	function setLogin($is_logged){
		$this->is_logged = $is_logged;
	}

	// 로그인 여부
	function isLogged(){
		$this->syncLogin();
		return $this->is_logged;
	}

	// 아이디(상속 클래스에서 구현)
	function getId(){
		return;
	}

	// 닉네임(상속 클래스에서 구현)
	function getNickName(){
		return;
	}

	// 프로필 이미지(상속 클래스에서 구현)
	function getProfileImage(){
		return;
	}

	// 로그인
	function doLogin($access, $account){
		$this->access = $access;
		$this->account = $account;

		//@kakikaki
		//print_r($account); //여기까지도 최신이미지
		$session->access = $access;
		$session->account = $account;
		$this->session->setSession($this->name, $session);

		$this->setLogin(true);
	}

	// 로그아웃
	function doLogout(){
		$this->session->clearSession($this->name);
		$this->setLogin(false);
	}

	// 액세스 정보
	function getAccess(){
		return $this->access;
	}

	// 사용자 정보
	function getAccount(){
		return $this->account;
	}

	// 링크
	function getAuthorLink($id, $nick_name){
		return;
	}

	// 리플 형식 반환
	function getReplyPrefix($id, $nick_name){
		return;
	}

	// 리플 형식이 포함되었는지 확인
	function isContainReply($content){
		return false;
	}
}

?>