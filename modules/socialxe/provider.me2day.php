<?php

// 미투데이를 위한 클래스
class socialxeProviderMe2day extends socialxeProvider{

	// 인스턴스
	function getInstance(&$sessionManager){
		static $instance;
		if (!isset($instance)) $instance = new socialxeProviderMe2day($sessionManager);
		return $instance;
	}

	// 생성자
	function socialxeProviderMe2day(&$sessionManager){
		parent::socialxeProvider('me2day', $sessionManager);
	}

	// 아이디
	function getId(){
		if (!$this->isLogged())   return;
		return $this->account->id;
	}

	// 닉네임
	function getNickName(){
		if (!$this->isLogged())   return;
		return $this->account->nickname;
	}

	// 프로필 이미지
	function getProfileImage(){
		if (!$this->isLogged())   return;
		return $this->account->face;
	}

	// 링크
	function getAuthorLink($id, $nick_name){
		return 'http://me2day.net/' . $id;
	}

	// 리플 형식 반환
	function getReplyPrefix($id, $nick_name){
		return '\\' . $nick_name . '\\';
	}

	// 리플 형식이 포함되었는지 확인
	function isContainReply($content){
		// PHP의 escape, 정규식의 escape...
		// '\S' : any none-whitespace
		$pattern = '/\\\\\S+\\\\/';
		if (preg_match($pattern, $content))
			return true;
		else
			return false;
	}
}
?>