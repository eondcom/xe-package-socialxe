<?php

// 소셜XE에서 사용하는 세션을 관리하게 편하게 한데 모은 클래스
class socialxeSessionManager{

	// 생성자
	function socialxeSessionManager(){
		// 도메인 정보를 세팅
		$this->domain = 'dummy';
	}

	// 인스턴스 얻기
	function &getInstance(){
		static $instance;
		if (!isset($instance)) $instance = new socialxeSessionManager();
		return $instance;
	}

	// 세션 세팅
	function setSession($name, $session){
		if (!is_object($_SESSION['socialxe'][$this->domain])){
			unset($_SESSION['socialxe'][$this->domain]);
		}
		$_SESSION['socialxe'][$this->domain]->{$name} = $session;
	}

	// 세션 얻기
	function getSession($name){
		if (!is_object($_SESSION['socialxe'][$this->domain])) return;
		return $_SESSION['socialxe'][$this->domain]->{$name};
	}

	// 세션 지우기
	function clearSession($name){
		if (!is_object($_SESSION['socialxe'][$this->domain])) return;
		unset($_SESSION['socialxe'][$this->domain]->{$name});
	}

	// 전체 세션 얻기
	function getFullSession(){
		return $_SESSION['socialxe'][$this->domain];
	}

	// 전체 세션 세팅
	function setFullSession($session){
		if (!is_object($session)) return;
		$_SESSION['socialxe'][$this->domain] = $session;
	}
}

?>