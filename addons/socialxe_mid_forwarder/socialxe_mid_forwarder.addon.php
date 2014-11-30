<?php
	if(!defined('__ZBXE__') && !defined('__XE__')) exit();

	if($called_position != 'before_module_init') return;
	if(Context::getResponseMethod() != "HTML") return;
	if(Context::get('act')) return;
	if(!Context::get('document_srl')) return;

	// 데이터 준비
	$document_srls = explode(',', $addon_info->document_srls);
	foreach($document_srls as $no => $val){
		$document_srls[$no] = trim($val);
	}

	$forward_mids = explode(',', $addon_info->forward_mids);
	foreach($forward_mids as $no => $val){
		$forward_mids[$no] = trim($val);
	}

	// 현재 document_srl 검사
	$index = array_search(Context::get('document_srl'), $document_srls);
	if ($index === false) return;

	// mid 변경
	$forward_mid = $forward_mids[$index];
	if (!$forward_mid) return;

	$url = getNotEncodedUrl('', 'mid', $forward_mid);
	Header("Location: $url");
	exit;
?>