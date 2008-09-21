<?php

class AJXP_XMLWriter
{
	
	function header($docNode="tree")
	{
		header('Content-Type: text/xml; charset=UTF-8');
		header('Cache-Control: no-cache');
		print('<?xml version="1.0" encoding="UTF-8"?>');
		print("<$docNode>");
		
	}
	
	function close($docNode="tree")
	{
		print("</$docNode>");
	}
	
	function write($data, $print){
		if($print) {
			print($data);
			return "";		
		}else{
			return $data;
		}
	}
	
	function reloadCurrentNode($print = true)
	{
		return AJXP_XMLWriter::write("<reload_instruction object=\"tree\"/>", $print);
	}
	
	function reloadNode($nodeName, $print = true)
	{
		return AJXP_XMLWriter::write("<reload_instruction object=\"tree\" node=\"$nodeName\"/>", $print);
	}
		
	function requireAuth($print = true)
	{
		return AJXP_XMLWriter::write("<require_auth/>", $print);
	}
	
	function triggerBgAction($actionName, $parameters, $messageId, $print=true){
		$data = AJXP_XMLWriter::write("<trigger_bg_action name=\"$actionName\" messageId=\"$messageId\">", $print);
		foreach ($parameters as $paramName=>$paramValue){
			$data .= AJXP_XMLWriter::write("<param name=\"$paramName\" value=\"$paramValue\"/>", $print);
		}
		$data .= AJXP_XMLWriter::write("</trigger_bg_action>", $print);
		return $data;		
	}
	
	function reloadFileList($fileOrBool, $print = true)
	{
		if(is_string($fileOrBool)) return AJXP_XMLWriter::write("<reload_instruction object=\"list\" file=\"".SystemTextEncoding::toUTF8($fileOrBool)."\"/>", $print);
		else return AJXP_XMLWriter::write("<reload_instruction object=\"list\"/>", $print);
	}
	
	function writeBookmarks($allBookmarks)
	{
		foreach ($allBookmarks as $bookmark)
		{
			$path = ""; $title = "";
			if(is_array($bookmark)){
				$path = $bookmark["PATH"];
				$title = $bookmark["TITLE"];
			}else if(is_string($bookmark)){
				$path = $bookmark;
				$title = basename($bookmark);
			}
			print("<bookmark path=\"".$path."\" title=\"".$title."\"/>");
		}
	}
	
	function sendMessage($logMessage, $errorMessage, $print = true)
	{
		$messageType = ""; 
		$message = "";
		if($errorMessage == null)
		{
			$messageType = "SUCCESS";
			$message = $logMessage;
		}
		else
		{
			$messageType = "ERROR";
			$message = $errorMessage;
		}
		return AJXP_XMLWriter::write("<message type=\"$messageType\">".$message."</message>", $print);
	}
	
	function sendUserData()
	{
		$loggedUser = AuthService::getLoggedUser();
		if($loggedUser != null)
		{
			print("<user id=\"".$loggedUser->id."\">");
			print("<active_repo id=\"".ConfService::getCurrentRootDirIndex()."\" write=\"".($loggedUser->canWrite(ConfService::getCurrentRootDirIndex())?"1":"0")."\" read=\"".($loggedUser->canRead(ConfService::getCurrentRootDirIndex())?"1":"0")."\"/>");
			print("<repositories>");
			foreach (ConfService::getRootDirsList() as $rootDirIndex => $rootDirObject)
			{
				if($loggedUser->canRead($rootDirIndex)) print("<repo id=\"".$rootDirIndex."\">".SystemTextEncoding::toUTF8($rootDirObject->getDisplay())."</repo>");
			}
			print("</repositories>");
			print("<preferences>");
			print("<pref name=\"display\" value=\"".$loggedUser->getPref("display")."\"/>");
			print("<pref name=\"lang\" value=\"".$loggedUser->getPref("lang")."\"/>");
			print("</preferences>");
			print("</user>");
		}		
	}
	
	function loggingResult($result, $rememberPass = "")
	{
		print("<logging_result value=\"$result\">");
		if($rememberPass != ""){
			print("<remember_pass value=\"$rememberPass\"/>");
		}
		print ("</logging_result>");
	}
	
}

?>
