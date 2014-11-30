<?php

class socialxeProviderGoogle extends socialxeProvider{
	
	//인스턴스
	function getInstance(&$sessionManager){
		static $instance;
		if (!isset($instance)) $instance = new socialxeProviderGoogle($sessionManager);
		return $instance;
	}
	
	//행성자
	function socialxeProviderGoogle(&$sessionManager){
		parent::socialxeProvider('google', $sessionManager);
	}
	
	/**
	 *  socialxeserver 의 provider.google.php 에서 $info['account'] 에 박은 배열의 키 값을 참조한다.
	 *  
	 *  $info['account'] 에는 GoogleOauth2 객체가 리턴한 $user[] 가 있으며 해당 키 값이 $this->account->키값 형식
	 */
	
	//아이디  
	function getId(){
		if (!$this->isLogged()) return;
		return $this->account->id;
	}
	
	//닉네임
	function getNickName(){
		if (!$this->isLogged()) return;
		return $this->account->displayName;
	}
	
	//프로필 이미지
	function getProfileImage(){
		if (!$this->isLogged()) return;
		$image_url = $this->account->image->url;
		if( strpos($image_url,'?') !== FALSE)
			$image_url = substr($image_url,0,strpos($image_url,'?'));
			
		return $image_url;
	}
	
	//링크
	function getAuthorLink($id, $nick_name){
		//if (!$this->isLogged()) return;
		//return $this->account->url;
		return 'https://plus.google.com/'.$id;
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