<?php

// XE를 위한 클래스
class socialxeProviderXE extends socialxeProvider{

	// 인스턴스
	function getInstance(&$sessionManager){
		static $instance;
		if (!isset($instance)) $instance = new socialxeProviderXE($sessionManager);
		return $instance;
	}

	// 생성자
	function socialxeProviderXE(&$sessionManager){
		parent::socialxeProvider('xe', $sessionManager);
	}

	// 로그인 여부 갱신
	function syncLogin(){
		$logged_info = Context::get('logged_info');
		if ($logged_info->member_srl){
			$this->logged_info = $logged_info;
			$this->setLogin(true);
		}
	}

	// 아이디
	function getId(){
		if (!$this->isLogged())   return;
		return $this->logged_info->user_id;
	}

	// 닉네임
	function getNickName(){
		if (!$this->isLogged())   return;
		return $this->logged_info->nick_name;
	}

	// 프로필 이미지
	function getProfileImage(){
		if (!$this->isLogged())   return;
		$oMemberModel = &getModel('member');
		$profile_info = $oMemberModel->getProfileImage($this->logged_info->member_srl);
		return $profile_info->src;
	}

	// 링크
	function getAuthorLink($id, $nick_name){
		return;
	}

	// 리플 형식 반환
	function getReplyPrefix($id, $nick_name){
		return $nick_name . '//';
	}

	// 리플 형식이 포함되었는지 확인
	function isContainReply($content){
		return false;
	}
}

?>