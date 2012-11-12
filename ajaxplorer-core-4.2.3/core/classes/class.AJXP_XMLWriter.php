<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.core
 */
/**
 * XML output Generator
 */
class AJXP_XMLWriter
{
	static $headerSent = false;

    /**
     * Output Headers, XML <?xml version...?> tag and a root node
     * @static
     * @param string $docNode
     * @param array $attributes
     * @return
     */
	static function header($docNode="tree", $attributes=array())
	{
		if(self::$headerSent !== false && self::$headerSent == $docNode) return ;
		header('Content-Type: text/xml; charset=UTF-8');
		header('Cache-Control: no-cache');
		print('<?xml version="1.0" encoding="UTF-8"?>');
		$attString = "";
		if(count($attributes)){
			foreach ($attributes as $name=>$value){
				$attString.="$name=\"$value\" ";
			}
		}
		self::$headerSent = $docNode;
		print("<$docNode $attString>");
		
	}
	/**
     * Outputs a closing root not (</tree>)
     * @static
     * @param string $docNode
     * @return void
     */
	static function close($docNode="tree")
	{
		print("</$docNode>");
	}

    /**
     * @static
     * @param string $data
     * @param bool $print
     * @return string
     */
	static function write($data, $print){
		if($print) {
			print($data);
			return "";		
		}else{
			return $data;
		}
	}

    /**
     * Ouput the <pagination> tag
     * @static
     * @param integer $count
     * @param integer $currentPage
     * @param integer $totalPages
     * @param integer $dirsCount
     * @return void
     */
	static function renderPaginationData($count, $currentPage, $totalPages, $dirsCount = -1){
		$string = '<pagination count="'.$count.'" total="'.$totalPages.'" current="'.$currentPage.'" overflowMessage="306" icon="folder.png" openicon="folder_open.png" dirsCount="'.$dirsCount.'"/>';		
		AJXP_XMLWriter::write($string, true);
	}
	/**
     * Prints out the XML headers and preamble, then an open node
     * @static
     * @param $nodeName
     * @param $nodeLabel
     * @param $isLeaf
     * @param array $metaData
     * @return void
     */
	static function renderHeaderNode($nodeName, $nodeLabel, $isLeaf, $metaData = array()){
		header('Content-Type: text/xml; charset=UTF-8');
		header('Cache-Control: no-cache');
		print('<?xml version="1.0" encoding="UTF-8"?>');
		self::$headerSent = "tree";
		AJXP_XMLWriter::renderNode($nodeName, $nodeLabel, $isLeaf, $metaData, false);
	}

    /**
     * @static
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    static function renderAjxpHeaderNode($ajxpNode){
        header('Content-Type: text/xml; charset=UTF-8');
        header('Cache-Control: no-cache');
        print('<?xml version="1.0" encoding="UTF-8"?>');
        self::$headerSent = "tree";
        self::renderAjxpNode($ajxpNode, false);
    }

    /**
     * The basic node
     * @static
     * @param string $nodeName
     * @param string $nodeLabel
     * @param bool $isLeaf
     * @param array $metaData
     * @param bool $close
     * @return void
     */
	static function renderNode($nodeName, $nodeLabel, $isLeaf, $metaData = array(), $close=true){
		$string = "<tree";
		$metaData["filename"] = $nodeName;
		if(!isSet($metaData["text"])){
			$metaData["text"] = $nodeLabel;
		}
		$metaData["is_file"] = ($isLeaf?"true":"false");

		foreach ($metaData as $key => $value){
            $value = AJXP_Utils::xmlEntities($value, true);
			$string .= " $key=\"$value\"";
		}
		if($close){
			$string .= "/>";
		}else{
			$string .= ">";
		}
		AJXP_XMLWriter::write($string, true);
	}

    /**
     * @static
     * @param AJXP_Node $ajxpNode
     * @param bool $close
     * @return void
     */
    static function renderAjxpNode($ajxpNode, $close = true){
        AJXP_XMLWriter::renderNode(
            $ajxpNode->getPath(),
            $ajxpNode->getLabel(),
            $ajxpNode->isLeaf(),
            $ajxpNode->metadata,
            $close);
    }

    /**
     * Render a node with arguments passed as array
     * @static
     * @param $array
     * @return void
     */
	static function renderNodeArray($array){
		self::renderNode($array[0],$array[1],$array[2],$array[3]);
	}
	/**
     * Error Catcher for PHP errors. Depending on the SERVER_DEBUG config
     * shows the file/line info or not.
     * @static
     * @param $code
     * @param $message
     * @param $fichier
     * @param $ligne
     * @param $context
     * @return
     */
	static function catchError($code, $message, $fichier, $ligne, $context){
		if(error_reporting() == 0) return ;
		if(ConfService::getConf("SERVER_DEBUG")){
			$message = "$message in $fichier (l.$ligne)";
		}
        try{
            AJXP_Logger::logAction("error", array("message" => $message));
        }catch(Exception $e){
            // This will probably trigger a double exception!
            echo "<pre>Error in error";
            debug_print_backtrace();
            echo "</pre>";
            die("Recursive exception. Original error was : ".$message. " in $fichier , line $ligne");
        }
		if(!headers_sent()) AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage(null, SystemTextEncoding::toUTF8($message), true);
		AJXP_XMLWriter::close();
		exit(1);
	}
	
	/**
	 * Catch exceptions, @see catchError
	 * @param Exception $exception
	 */
	static function catchException($exception){
        try{
            AJXP_XMLWriter::catchError($exception->getCode(), SystemTextEncoding::fromUTF8($exception->getMessage()), $exception->getFile(), $exception->getLine(), null);
        }catch(Exception $innerEx){
            print get_class($innerEx)." thrown within the exception handler! Message was: ".$innerEx->getMessage()." in ".$innerEx->getFile()." on line ".$innerEx->getLine()." ".$innerEx->getTraceAsString();            
        }
	}
	/**
     * Dynamically replace XML keywords with their live values.
     * AJXP_SERVER_ACCESS, AJXP_MIMES_*,AJXP_ALL_MESSAGES, etc.
     * @static
     * @param string $xml
     * @param bool $stripSpaces
     * @return mixed
     */
	static function replaceAjxpXmlKeywords($xml, $stripSpaces = false){
		$messages = ConfService::getMessages();
        $confMessages = ConfService::getMessagesConf();
		$matches = array();
		if(isSet($_SESSION["AJXP_SERVER_PREFIX_URI"])){
			//$xml = str_replace("AJXP_THEME_FOLDER", $_SESSION["AJXP_SERVER_PREFIX_URI"].AJXP_THEME_FOLDER, $xml);
			$xml = str_replace("AJXP_SERVER_ACCESS", $_SESSION["AJXP_SERVER_PREFIX_URI"].AJXP_SERVER_ACCESS, $xml);
		}else{
			//$xml = str_replace("AJXP_THEME_FOLDER", AJXP_THEME_FOLDER, $xml);
			$xml = str_replace("AJXP_SERVER_ACCESS", AJXP_SERVER_ACCESS, $xml);
		}
		$xml = str_replace("AJXP_MIMES_EDITABLE", AJXP_Utils::getAjxpMimes("editable"), $xml);
		$xml = str_replace("AJXP_MIMES_IMAGE", AJXP_Utils::getAjxpMimes("image"), $xml);
		$xml = str_replace("AJXP_MIMES_AUDIO", AJXP_Utils::getAjxpMimes("audio"), $xml);
		$xml = str_replace("AJXP_MIMES_ZIP", AJXP_Utils::getAjxpMimes("zip"), $xml);
		$authDriver = ConfService::getAuthDriverImpl();
		if($authDriver != NULL){
			$loginRedirect = $authDriver->getLoginRedirect();
			$xml = str_replace("AJXP_LOGIN_REDIRECT", ($loginRedirect!==false?"'".$loginRedirect."'":"false"), $xml);
		}
        $xml = str_replace("AJXP_REMOTE_AUTH", "false", $xml);
        $xml = str_replace("AJXP_NOT_REMOTE_AUTH", "true", $xml);
        $xml = str_replace("AJXP_ALL_MESSAGES", "MessageHash=".json_encode(ConfService::getMessages()).";", $xml);
		
		if(preg_match_all("/AJXP_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)){
			foreach($matches as $match){
				$messId = str_replace("]", "", str_replace("[", "", $match[1]));
				$xml = str_replace("AJXP_MESSAGE[$messId]", $messages[$messId], $xml);
			}
		}
		if(preg_match_all("/CONF_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)){
			foreach($matches as $match){
				$messId = str_replace(array("[", "]"), "", $match[1]);
                $message = $messId;
                if(array_key_exists($messId, $confMessages)){
                    $message = $confMessages[$messId];
                }
				$xml = str_replace("CONF_MESSAGE[$messId]", $message, $xml);
			}
		}
		if(preg_match_all("/MIXIN_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)){
			foreach($matches as $match){
				$messId = str_replace(array("[", "]"), "", $match[1]);
                $message = $messId;
                if(array_key_exists($messId, $confMessages)){
                    $message = $confMessages[$messId];
                }
				$xml = str_replace("MIXIN_MESSAGE[$messId]", $message, $xml);
			}
		}
		if($stripSpaces){
			$xml = preg_replace("/[\n\r]?/", "", $xml);
			$xml = preg_replace("/\t/", " ", $xml);
		}
        $xml = str_replace(array('xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"','xsi:noNamespaceSchemaLocation="file:../core.ajaxplorer/ajxp_registry.xsd"'), "", $xml);
        $tab = array(&$xml);
        AJXP_Controller::applyIncludeHook("xml.filter", $tab);
		return $xml;
	}
	/**
     * Send a <reload> XML instruction for refreshing the list
     * @static
     * @param string $nodePath
     * @param string $pendingSelection
     * @param bool $print
     * @return string
     */
	static function reloadDataNode($nodePath="", $pendingSelection="", $print = true){
		$nodePath = AJXP_Utils::xmlEntities($nodePath, true);
		$pendingSelection = AJXP_Utils::xmlEntities($pendingSelection, true);
		return AJXP_XMLWriter::write("<reload_instruction object=\"data\" node=\"$nodePath\" file=\"$pendingSelection\"/>", $print);
	}
	/**
     * Send a <reload> XML instruction for refreshing the repositories list
     * @static
     * @param bool $print
     * @return string
     */
	static function reloadRepositoryList($print = true){
		return AJXP_XMLWriter::write("<reload_instruction object=\"repository_list\"/>", $print);
	}
	/**
     * Outputs a <require_auth/> tag
     * @static
     * @param bool $print
     * @return string
     */
	static function requireAuth($print = true)
	{
		return AJXP_XMLWriter::write("<require_auth/>", $print);
	}
	/**
     * Triggers a background action client side
     * @static
     * @param $actionName
     * @param $parameters
     * @param $messageId
     * @param bool $print
     * @param int $delay
     * @return string
     */
	static function triggerBgAction($actionName, $parameters, $messageId, $print=true, $delay = 0){
        $messageId = AJXP_Utils::xmlEntities($messageId);
		$data = AJXP_XMLWriter::write("<trigger_bg_action name=\"$actionName\" messageId=\"$messageId\" delay=\"$delay\">", $print);
		foreach ($parameters as $paramName=>$paramValue){
            $paramValue = AJXP_Utils::xmlEntities($paramValue);
			$data .= AJXP_XMLWriter::write("<param name=\"$paramName\" value=\"$paramValue\"/>", $print);
		}
		$data .= AJXP_XMLWriter::write("</trigger_bg_action>", $print);
		return $data;		
	}

    static function triggerBgJSAction($jsCode, $messageId, $print=true, $delay = 0){
   		$data = AJXP_XMLWriter::write("<trigger_bg_action name=\"javascript_instruction\" messageId=\"$messageId\" delay=\"$delay\">", $print);
        $data .= AJXP_XMLWriter::write("<clientCallback><![CDATA[".$jsCode."]]></clientCallback>", $print);
   		$data .= AJXP_XMLWriter::write("</trigger_bg_action>", $print);
   		return $data;
   	}

	/**
     * List all bookmmarks as XML
     * @static
     * @param $allBookmarks
     * @param bool $print
     * @return string
     */
	static function writeBookmarks($allBookmarks, $print = true)
	{
		$buffer = "";
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
			$buffer .= "<bookmark path=\"".AJXP_Utils::xmlEntities($path)."\" title=\"".AJXP_Utils::xmlEntities($title)."\"/>";
		}
		if($print) print $buffer;
		else return $buffer;
	}
	/**
     * Utilitary for generating a <component_config> tag for the FilesList component
     * @static
     * @param $config
     * @return void
     */
	static function sendFilesListComponentConfig($config){
		if(is_string($config)){
			print("<client_configs><component_config className=\"FilesList\">$config</component_config></client_configs>");
		}
	}
	/**
     * Send a success or error message to the client.
     * @static
     * @param $logMessage
     * @param $errorMessage
     * @param bool $print
     * @return string
     */
	static function sendMessage($logMessage, $errorMessage, $print = true)
	{
		$messageType = ""; 
		$message = "";
		if($errorMessage == null)
		{
			$messageType = "SUCCESS";
			$message = AJXP_Utils::xmlContentEntities($logMessage);
		}
		else
		{
			$messageType = "ERROR";
			$message = AJXP_Utils::xmlContentEntities($errorMessage);
		}
		return AJXP_XMLWriter::write("<message type=\"$messageType\">".$message."</message>", $print);
	}
    /**
     * Writes the user data as XML
     * @static
     * @param null $userObject
     * @param bool $details
     * @return void
     */
	static function sendUserData($userObject = null, $details=false){
		print(AJXP_XMLWriter::getUserXML($userObject, $details));
	}
	/**
     * Extract all the user data and put it in XML
     * @static
     * @param null $userObject
     * @param bool $details
     * @return string
     */
	static function getUserXML($userObject = null, $details=false)
	{
		$buffer = "";
		$loggedUser = AuthService::getLoggedUser();
        $confDriver = ConfService::getConfStorageImpl();
		if($userObject != null) $loggedUser = $userObject;
		if(!AuthService::usersEnabled()){
			$buffer.="<user id=\"shared\">";
			if(!$details){
				$buffer.="<active_repo id=\"".ConfService::getCurrentRootDirIndex()."\" write=\"1\" read=\"1\"/>";
			}
			$buffer.= AJXP_XMLWriter::writeRepositoriesData(null, $details);
			$buffer.="</user>";	
		}else if($loggedUser != null){
			$buffer.="<user id=\"".$loggedUser->id."\">";
			if(!$details){
				$buffer.="<active_repo id=\"".ConfService::getCurrentRootDirIndex()."\" write=\"".($loggedUser->canWrite(ConfService::getCurrentRootDirIndex())?"1":"0")."\" read=\"".($loggedUser->canRead(ConfService::getCurrentRootDirIndex())?"1":"0")."\"/>";
			}else{
				$buffer .= "<ajxp_roles>";
				foreach ($loggedUser->getRoles() as $roleId => $boolean){
					if($boolean === true) $buffer.= "<role id=\"$roleId\"/>";
				}
				$buffer .= "</ajxp_roles>";
			}
			$buffer.= AJXP_XMLWriter::writeRepositoriesData($loggedUser, $details);
			$buffer.="<preferences>";
            $preferences = $confDriver->getExposedPreferences($loggedUser);
            foreach($preferences as $prefName => $prefData){
                if($prefData["type"] == "string"){
                    $buffer.="<pref name=\"$prefName\" value=\"".$prefData["value"]."\"/>";
                }else if($prefData["type"] == "json"){
                    $buffer.="<pref name=\"$prefName\"><![CDATA[".$prefData["value"]."]]></pref>";
                }
            }
			$buffer.="</preferences>";
			$buffer.="<special_rights is_admin=\"".($loggedUser->isAdmin()?"1":"0")."\"/>";
			$bMarks = $loggedUser->getBookmarks();
			if(count($bMarks)){
				$buffer.= "<bookmarks>".AJXP_XMLWriter::writeBookmarks($bMarks, false)."</bookmarks>";
			}
			$buffer.="</user>";
		}
		return $buffer;		
	}
	/**
     * Write the repositories access rights in XML format
     * @static
     * @param $loggedUser
     * @param bool $details
     * @return string
     */
	static function writeRepositoriesData($loggedUser, $details=false){
		$st = "";
		$st .= "<repositories>";
		$streams = ConfService::detectRepositoryStreams(false);
		foreach (ConfService::getRepositoriesList() as $rootDirIndex => $rootDirObject)
		{		
			if($rootDirObject->isTemplate) continue;
			$toLast = false;
			if($rootDirObject->getAccessType()=="ajxp_conf"){
				if(AuthService::usersEnabled() && !$loggedUser->isAdmin()){
					continue;
				}else{
					$toLast = true;
				}				
			}
			if($rootDirObject->getAccessType() == "ajxp_shared" && !AuthService::usersEnabled()){
				continue;
			}
            if($rootDirObject->getUniqueUser() && (!AuthService::usersEnabled() || $loggedUser->getId() == "shared" || $loggedUser->getId() != $rootDirObject->getUniqueUser() )){
                continue;
            }
			if($loggedUser == null || $loggedUser->canRead($rootDirIndex) || $loggedUser->canWrite($rootDirIndex) || $details) {
				// Do not display standard repositories even in details mode for "sub"users
				if($loggedUser != null && $loggedUser->hasParent() && !($loggedUser->canRead($rootDirIndex) || $loggedUser->canWrite($rootDirIndex) )) continue;
				// Do not display shared repositories otherwise.
				if($loggedUser != null && $rootDirObject->hasOwner() && !$loggedUser->hasParent()){
                    // Display the repositories if allow_crossusers is ok
                    if(ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING") !== true) continue;
                    // But still do not display its own shared repositories!
                    if($rootDirObject->getOwner() == $loggedUser->getId()) continue;
                }
                if($rootDirObject->hasOwner() && $loggedUser != null &&  $details && !($loggedUser->canRead($rootDirIndex) || $loggedUser->canWrite($rootDirIndex) ) ){
                	continue;
                }
				
				$rightString = "";
				if($details){
					$rightString = " r=\"".($loggedUser->canRead($rootDirIndex)?"1":"0")."\" w=\"".($loggedUser->canWrite($rootDirIndex)?"1":"0")."\"";
				}
				$streamString = "";
				if(in_array($rootDirObject->accessType, $streams)){
					$streamString = "allowCrossRepositoryCopy=\"true\"";
				}
                if($rootDirObject->getUniqueUser()){
                    $streamString .= " user_editable_repository=\"true\" ";
                }
				$slugString = "";
				$slug = $rootDirObject->getSlug();
				if(!empty($slug)){
					$slugString = "repositorySlug=\"$slug\"";
				}
                $isSharedString = ($rootDirObject->hasOwner() ? "owner='".$rootDirObject->getOwner()."'" : "");
                
                $xmlString = "<repo access_type=\"".$rootDirObject->accessType."\" id=\"".$rootDirIndex."\"$rightString $streamString $slugString $isSharedString><label>".SystemTextEncoding::toUTF8(AJXP_Utils::xmlEntities($rootDirObject->getDisplay()))."</label>".$rootDirObject->getClientSettings()."</repo>";
				if($toLast){
					$lastString = $xmlString;
				}else{
					$st .= $xmlString;
				}
			}
		}
		if(isSet($lastString)){
			$st.= $lastString;
		}
		$st .= "</repositories>";
		return $st;
	}
	
	/**
	 * Send repositories access for given role as XML
	 *
	 * @param AjxpRole $role
	 * @return string
	 */
	static function writeRoleRepositoriesData($role){
		$st = "<repositories>";
		foreach (ConfService::getRepositoriesList() as $repoId => $repoObject)
		{		
			$toLast = false;
			if($repoObject->getAccessType() == "ajxp_conf") continue;
			if($repoObject->isTemplate) continue;
			if($repoObject->getAccessType() == "ajxp_shared" && !AuthService::usersEnabled()){
				continue;
			}
            if($repoObject->hasOwner()) continue;
			$rightString = " r=\"".($role->canRead($repoId)?"1":"0")."\" w=\"".($role->canWrite($repoId)?"1":"0")."\"";
			$string = "<repo access_type=\"".$repoObject->accessType."\" id=\"".$repoId."\"$rightString><label>".SystemTextEncoding::toUTF8(AJXP_Utils::xmlEntities($repoObject->getDisplay()))."</label></repo>";
			if($toLast){
				$lastString = $string;
			}else{
				$st .= $string;
			}
		}
		if(isSet($lastString)){
			$st.= $lastString;
		}
		$st .= "</repositories>";
		$st .= "<actions_rights>";
		foreach ($role->getSpecificActionsRights("ajxp.all") as $actionId => $actionValue){
			$st.="<action name=\"$actionId\" value=\"".($actionValue?"true":"false")."\"/>";
		}
		$st .= "</actions_rights>";
        $st .= "<role is_default=\"".($role->isDefault()?"true":"false")."\"/>";
		return $st;
	}
	/**
     * Writes a <logging_result> tag
     * @static
     * @param integer $result
     * @param string $rememberLogin
     * @param string $rememberPass
     * @param string $secureToken
     * @return void
     */
	static function loggingResult($result, $rememberLogin="", $rememberPass = "", $secureToken="")
	{
		$remString = "";
		if($rememberPass != "" && $rememberLogin!= ""){
			$remString = " remember_login=\"$rememberLogin\" remember_pass=\"$rememberPass\"";
		}
		if($secureToken != ""){
			$remString .= " secure_token=\"$secureToken\"";
		}
		print("<logging_result value=\"$result\"$remString/>");
	}
	
}

?>
