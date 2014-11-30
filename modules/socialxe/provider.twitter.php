<?php

// 트위터를 위한 클래스
class socialxeProviderTwitter extends socialxeProvider{

	// 인스턴스
	function getInstance(&$sessionManager){
		static $instance;
		if (!isset($instance)) $instance = new socialxeProviderTwitter($sessionManager);
		return $instance;
	}

	// 생성자
	function socialxeProviderTwitter(&$sessionManager){
		parent::socialxeProvider('twitter', $sessionManager);
	}

	// 아이디
	function getId(){
		if (!$this->isLogged())   return;
		return $this->access->user_id;
	}

	// 닉네임
	function getNickName(){
		if (!$this->isLogged())   return;
		return $this->access->screen_name;
	}

	// 프로필 이미지
	function getProfileImage(){
		if (!$this->isLogged())   return;
		return $this->account->profile_image_url;
	}

	// 링크
	function getAuthorLink($id, $nick_name){
		return 'http://twitter.com/' . $nick_name;
	}

	// 리플 형식 반환
	function getReplyPrefix($id, $nick_name){
		return '@' . $nick_name;
	}

	// 리플 형식이 포함되었는지 확인
	function isContainReply($content){
		$pattern = '/@[_0-9a-zA-Z-]+($| )/';
		if (preg_match($pattern, $content))
			return true;
		else
			return false;
	}
}

?>