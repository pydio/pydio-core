<?php
/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Util methods for generating XML outputs.
 */
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
		
	function reloadFileList($fileOrBool, $print = true)
	{
		if(is_string($fileOrBool)) return AJXP_XMLWriter::write("<reload_instruction object=\"list\" file=\"".Utils::xmlEntities(SystemTextEncoding::toUTF8($fileOrBool))."\"/>", $print);
		else return AJXP_XMLWriter::write("<reload_instruction object=\"list\"/>", $print);
	}
	
	function reloadRepositoryList($print = true){
		return AJXP_XMLWriter::write("<reload_instruction object=\"repository_list\"/>", $print);
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
			$message = Utils::xmlEntities($logMessage);
		}
		else
		{
			$messageType = "ERROR";
			$message = Utils::xmlEntities($errorMessage);
		}
		return AJXP_XMLWriter::write("<message type=\"$messageType\">".$message."</message>", $print);
	}
	
	function sendUserData($userObject = null, $details=false)
	{
		$loggedUser = AuthService::getLoggedUser();
		if($userObject != null) $loggedUser = $userObject;
		if($loggedUser != null)
		{
			print("<user id=\"".$loggedUser->id."\">");
			if(!$details){
				print("<active_repo id=\"".ConfService::getCurrentRootDirIndex()."\" write=\"".($loggedUser->canWrite(ConfService::getCurrentRootDirIndex())?"1":"0")."\" read=\"".($loggedUser->canRead(ConfService::getCurrentRootDirIndex())?"1":"0")."\"/>");
			}
			print(AJXP_XMLWriter::writeRepositoriesData($loggedUser, $details));
			print("<preferences>");
			print("<pref name=\"display\" value=\"".$loggedUser->getPref("display")."\"/>");
			print("<pref name=\"lang\" value=\"".$loggedUser->getPref("lang")."\"/>");
			print("</preferences>");
			print("<special_rights is_admin=\"".($loggedUser->isAdmin()?"1":"0")."\"/>");
			print("</user>");
		}		
	}
	
	function writeRepositoriesData($loggedUser, $details=false){
		$st = "";
		$st .= "<repositories>";
		foreach (ConfService::getRootDirsList() as $rootDirIndex => $rootDirObject)
		{		
			$toLast = false;
			if($rootDirObject->getAccessType()=="ajxp_conf"){
				if(ENABLE_USERS && !$loggedUser->isAdmin()){
					continue;
				}else{
					$toLast = true;
				}				
			}
			if($loggedUser == null || $loggedUser->canRead($rootDirIndex) || $details) {
				$rightString = "";
				if($details){
					$rightString = " r=\"".($loggedUser->canRead($rootDirIndex)?"1":"0")."\" w=\"".($loggedUser->canWrite($rootDirIndex)?"1":"0")."\"";
				}
				if($toLast){
					$lastString = "<repo access_type=\"".$rootDirObject->accessType."\" id=\"".$rootDirIndex."\"$rightString><label>".SystemTextEncoding::toUTF8(Utils::xmlEntities($rootDirObject->getDisplay()))."</label>".$rootDirObject->getClientSettings()."</repo>";
				}else{
					$st .= "<repo access_type=\"".$rootDirObject->accessType."\" id=\"".$rootDirIndex."\"$rightString><label>".SystemTextEncoding::toUTF8(Utils::xmlEntities($rootDirObject->getDisplay()))."</label>".$rootDirObject->getClientSettings()."</repo>";
				}
			}
		}
		if(isSet($lastString)){
			$st.= $lastString;
		}
		$st .= "</repositories>";
		return $st;
	}
	
	function loggingResult($result, $rememberLogin="", $rememberPass = "")
	{
		$remString = "";
		if($rememberPass != "" && $rememberLogin!= ""){
			$remString = " remember_login=\"$rememberLogin\" remember_pass=\"$rememberPass\"";
		}
		print("<logging_result value=\"$result\"$remString/>");
	}
	
}

?>