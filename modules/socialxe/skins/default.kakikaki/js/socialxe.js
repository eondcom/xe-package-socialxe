// 로그인 후
function completeInsert(){
	location.href = current_url.setQuery('act','').setQuery('provider', '');
}

// 서비스 연결
function providerLogin(url){
	// JS 사용을 알린다.
	url += '&js=1';

	// 윈도우 오픈
	var android = navigator.userAgent.indexOf('Android') != -1;
	if(android) window.open(url,'socialxeLogin');
	else window.open(url,'socialxeLogin','top=0, left=0, width=800, height=500');
}

// 서비스 연결 후
function completeSocialxeLogin(){
	location.href = current_url;
}

// 서비스 연결 끊기
function unlinkSocialInfo(provider){
	var params = new Array();
	params['provider'] = provider;
	var response_tags = new Array('error','message');
	exec_xml('socialxe', 'procSocialxeUnlinkSocialInfo', params, completeSocialxeLogin, response_tags);
}

// 소셜 전송 온/오프
function setSend(provider, sw){
	var params = new Array();
	params['provider'] = provider;
	params['sw'] = sw;
	var response_tags = new Array('error','message');
	exec_xml('socialxe', 'procSocialxeSetSend', params, completeSocialxeLogin, response_tags);
}

// 대표 계정 변경
function changeMasterProvider(){
	var provider = jQuery('#master_provider').val();

	var params = new Array();
	params['provider'] = provider;
	var response_tags = new Array('error','message');
	exec_xml('socialxe', 'procSocialxeChangeMasterProvider', params, completeSocialxeLogin, response_tags);
}