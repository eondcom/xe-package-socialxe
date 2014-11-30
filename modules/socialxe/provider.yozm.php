<?php

// 요즘을 위한 클래스
class socialxeProviderYozm extends socialxeProvider{

	// 인스턴스
	function getInstance(&$sessionManager){
		static $instance;
		if (!isset($instance)) $instance = new socialxeProviderYozm($sessionManager);
		return $instance;
	}

	// 생성자
	function socialxeProviderYozm(&$sessionManager){
		parent::socialxeProvider('yozm', $sessionManager);
	}

	// 아이디
	function getId(){
		if (!$this->isLogged())   return;
		return $this->account->user->url_name;
	}

	// 닉네임
	function getNickName(){
		if (!$this->isLogged())   return;
		return $this->account->user->nickname;
	}

	// 프로필 이미지
	function getProfileImage(){
		if (!$this->isLogged())   return;
		return $this->account->user->profile_big_img_url;
	}

	// 링크
	function getAuthorLink($id, $nick_name){
		return 'http://yozm.daum.net/' . $id;
	}

	// 리플 형식 반환
	function getReplyPrefix($id, $nick_name){
		return '@' . $nick_name . '@';
	}

	// 리플 형식이 포함되었는지 확인
	function isContainReply($content){
		// '\S' : any none-whitespace
		$pattern = '/@\S+@/';
		if (preg_match($pattern, $content))
			return true;
		else
			return false;
	}
}
?>