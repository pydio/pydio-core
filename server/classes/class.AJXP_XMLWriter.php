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
	
	function renderPaginationData($count, $currentPage, $totalPages){
		$string = '<pagination count="'.$count.'" total="'.$totalPages.'" current="'.$currentPage.'" overflowMessage="306" icon="folder.png" openicon="folder_open.png"/>';		
		AJXP_XMLWriter::write($string, true);
	}
	
	function renderHeaderNode($nodeName, $nodeLabel, $isLeaf, $metaData = array()){
		header('Content-Type: text/xml; charset=UTF-8');
		header('Cache-Control: no-cache');
		print('<?xml version="1.0" encoding="UTF-8"?>');
		AJXP_XMLWriter::renderNode($nodeName, $nodeLabel, $isLeaf, $metaData, false);
	}
	
	function renderNode($nodeName, $nodeLabel, $isLeaf, $metaData = array(), $close=true){
		$string = "<tree";
		$metaData["filename"] = $nodeName;
		$metaData["text"] = $nodeLabel;
		$metaData["is_file"] = ($isLeaf?"true":"false");
		foreach ($metaData as $key => $value){
			$string .= " $key=\"$value\"";
		}
		if($close){
			$string .= "/>";
		}else{
			$string .= ">";
		}
		AJXP_XMLWriter::write($string, true);
	}
	
	function catchError($code, $message, $fichier, $ligne, $context){
		if(error_reporting() == 0) return ;
		$message = "$code : $message in $fichier (l.$ligne)";
		AJXP_Logger::logAction("error", array("message" => $message));
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage(null, $message, true);
		AJXP_XMLWriter::close();
		exit(1);
	}
	
	static function replaceAjxpXmlKeywords($xml, $stripSpaces = false){
		$messages = ConfService::getMessages();			
		$matches = array();
		$xml = str_replace("AJXP_CLIENT_RESOURCES_FOLDER", CLIENT_RESOURCES_FOLDER, $xml);
		$xml = str_replace("AJXP_SERVER_ACCESS", SERVER_ACCESS, $xml);
		$xml = str_replace("AJXP_MIMES_EDITABLE", Utils::getAjxpMimes("editable"), $xml);
		$xml = str_replace("AJXP_MIMES_IMAGE", Utils::getAjxpMimes("image"), $xml);
		$xml = str_replace("AJXP_MIMES_AUDIO", Utils::getAjxpMimes("audio"), $xml);
		$xml = str_replace("AJXP_MIMES_ZIP", Utils::getAjxpMimes("zip"), $xml);
		$loginRedirect = ConfService::getAuthDriverImpl()->getLoginRedirect();
		$xml = str_replace("AJXP_LOGIN_REDIRECT", ($loginRedirect!==false?"'".$loginRedirect."'":"false"), $xml);
        $xml = str_replace("AJXP_REMOTE_AUTH", "false", $xml);
        $xml = str_replace("AJXP_NOT_REMOTE_AUTH", "true", $xml);
		
		if(preg_match_all("/AJXP_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)){
			foreach($matches as $match){
				$messId = str_replace("]", "", str_replace("[", "", $match[1]));
				$xml = str_replace("AJXP_MESSAGE[$messId]", $messages[$messId], $xml);
			}
		}
		if($stripSpaces){
			$xml = preg_replace("/[\n\r]?/", "", $xml);
			$xml = preg_replace("/\t/", " ", $xml);
		}
		return $xml;		
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
	
	function reloadDataNode($nodePath="", $pendingSelection="", $print = true){
		$nodePath = Utils::xmlEntities($nodePath, true);
		$pendingSelection = Utils::xmlEntities($pendingSelection, true);
		return AJXP_XMLWriter::write("<reload_instruction object=\"data\" node=\"$nodePath\" file=\"$pendingSelection\"/>", $print);
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
				$title = SystemTextEncoding::toUTF8($bookmark["TITLE"]);
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
			print("<pref name=\"diapo_autofit\" value=\"".$loggedUser->getPref("diapo_autofit")."\"/>");
			print("<pref name=\"sidebar_splitter_size\" value=\"".$loggedUser->getPref("sidebar_splitter_size")."\"/>");
			print("<pref name=\"vertical_splitter_size\" value=\"".$loggedUser->getPref("vertical_splitter_size")."\"/>");
			print("<pref name=\"history_last_repository\" value=\"".$loggedUser->getArrayPref("history", "last_repository")."\"/>");
			print("<pref name=\"history_last_listing\" value=\"".stripslashes($loggedUser->getArrayPref("history", ConfService::getCurrentRootDirIndex()))."\"/>");
			print("<pref name=\"thumb_size\" value=\"".$loggedUser->getPref("thumb_size")."\"/>");
			print("<pref name=\"columns_size\" value=\"".stripslashes(str_replace("\"", "'", $loggedUser->getPref("columns_size")))."\"/>");
			print("</preferences>");
			print("<special_rights is_admin=\"".($loggedUser->isAdmin()?"1":"0")."\"/>");
			print("</user>");
		}		
	}
	
	function writeRepositoriesData($loggedUser, $details=false){
		$st = "";
		$st .= "<repositories>";
		$streams = ConfService::detectRepositoryStreams(false);
		foreach (ConfService::getRepositoriesList() as $rootDirIndex => $rootDirObject)
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
				$streamString = "";
				if(in_array($rootDirObject->accessType, $streams)){
					$streamString = "allowCrossRepositoryCopy=\"true\"";
				}
				if($toLast){
					$lastString = "<repo access_type=\"".$rootDirObject->accessType."\" id=\"".$rootDirIndex."\"$rightString $streamString><label>".SystemTextEncoding::toUTF8(Utils::xmlEntities($rootDirObject->getDisplay()))."</label>".$rootDirObject->getClientSettings()."</repo>";
				}else{
					$st .= "<repo access_type=\"".$rootDirObject->accessType."\" id=\"".$rootDirIndex."\"$rightString $streamString><label>".SystemTextEncoding::toUTF8(Utils::xmlEntities($rootDirObject->getDisplay()))."</label>".$rootDirObject->getClientSettings()."</repo>";
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
