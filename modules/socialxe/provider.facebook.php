<?php

// 페이스북을 위한 클래스
class socialxeProviderFacebook extends socialxeProvider{

	// 인스턴스
	function getInstance(&$sessionManager){
		static $instance;
		if (!isset($instance)) $instance = new socialxeProviderFacebook($sessionManager);
		return $instance;
	}

	// 생성자
	function socialxeProviderFacebook(&$sessionManager){
		parent::socialxeProvider('facebook', $sessionManager);
	}

	// 아이디
	function getId(){
		if (!$this->isLogged())   return;
		return $this->account->id;
	}

	// 닉네임
	function getNickName(){
		if (!$this->isLogged())   return;
		return $this->account->name;
	}

	// 프로필 이미지
	function getProfileImage(){
		if (!$this->isLogged())   return;
		
		return 'http://graph.facebook.com/' . $this->account->id . '/picture?type=large'; //@kakikaki 수정됨 (큰 이미지fetch) - 테스트단계
	}

	// 링크
	function getAuthorLink($id, $nick_name){
		return 'http://www.facebook.com/profile.php?id=' . $id;
	}

	// 리플 형식 반환
	function getReplyPrefix($id, $nick_name){
		return;
	}

	// 리플 형식이 포함되었는지 확인
	function isContainReply($content){
		// $pattern = '/\\\\.+\\\\/';
		// if (preg_match($pattern, $content))
			// return true;
		// else
			return false;
	}
}
?>