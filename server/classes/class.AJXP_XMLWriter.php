<?php

class AJXP_XMLWriter
{
	
	function header()
	{
		header('Content-Type: text/xml; charset=UTF-8');
		header('Cache-Control: no-cache');
		print('<?xml version="1.0" encoding="UTF-8"?>');
		print("<tree>");
		
	}
	
	function close()
	{
		print("</tree>");
	}
	
	function reloadCurrentNode()
	{
		print("<reload_instruction object=\"tree\"/>");
	}
	
	function reloadNode($nodeName)
	{
		print("<reload_instruction object=\"tree\" node=\"$nodeName\"/>");
	}
	
	function requireAuth()
	{
		print("<require_auth/>");
	}
	
	function reloadFileList($fileOrBool)
	{
		if(is_string($fileOrBool)) print "<reload_instruction object=\"list\" file=\"".utf8_encode($fileOrBool)."\"/>";
		else print "<reload_instruction object=\"list\"/>";
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
	
	function sendMessage($logMessage, $errorMessage)
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
		print("<message type=\"$messageType\">".utf8_encode($message)."</message>");
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
				if($loggedUser->canRead($rootDirIndex)) print("<repo id=\"".$rootDirIndex."\">".utf8_encode($rootDirObject->getDisplay())."</repo>");
			}
			print("</repositories>");
			print("<preferences>");
			print("<pref name=\"display\" value=\"".$loggedUser->getPref("display")."\"/>");
			print("<pref name=\"lang\" value=\"".$loggedUser->getPref("lang")."\"/>");
			print("</preferences>");
			print("</user>");
		}		
	}
	
	function loggingResult($result)
	{
		print("<logging_result value=\"$result\"/>");
	}
	
}

?>