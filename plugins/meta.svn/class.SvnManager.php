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
 * @package info.ajaxplorer.plugins
 * Uses svn command lines to extract version infos. Autocommit on change.
 */
class SvnManager extends AJXP_Plugin {
	
	private static $svnListDir;
	private static $svnListCache;	
	private $commitMessageParams;
	
	protected $accessDriver;
	
	public function init($options){
		$this->options = $options;		
		// Do nothing
	}
	
	public function initMeta($accessDriver){
        require_once("svn_lib.inc.php");

		$this->accessDriver = $accessDriver;
		parent::init($this->options);
	
	}
	
	protected function initDirAndSelection($httpVars, $additionnalPathes = array(), $testRecycle = false){
		$userSelection = new UserSelection();
		$userSelection->initFromHttpVars($httpVars);
		$repo = ConfService::getRepository();
		$repo->detectStreamWrapper();
		$wrapperData = $repo->streamData;
		$urlBase = $wrapperData["protocol"]."://".$repo->getId();		
		$result = array();

		if($testRecycle){
			$recycle = $repo->getOption("RECYCLE_BIN");		
			if($recycle != ""){
				RecycleBinManager::init($urlBase, "/".$recycle);
				$result["RECYCLE"] = RecycleBinManager::filterActions($httpVars["get_action"], $userSelection, $httpVars["dir"], $httpVars);
				// if necessary, check recycle was checked.
				// We could use a hook instead here? Maybe the full recycle system
				// could be turned into a plugin
				$sessionKey = "AJXP_SVN_".$repo->getId()."_RECYCLE_CHECKED";
				if(isSet($_SESSION[$sessionKey])){
					$file = RecycleBinManager::getRelativeRecycle()."/".RecycleBinManager::getCacheFileName();
					$realFile = call_user_func(array($wrapperData["classname"], "getRealFSReference"), $urlBase.$file);
					$this->addIfNotVersionned($file, $realFile);
					$_SESSION[$sessionKey] = true;
				}
			}
		}
		
		$result["DIR"] = call_user_func(array($wrapperData["classname"], "getRealFSReference"), $urlBase.AJXP_Utils::decodeSecureMagic($httpVars["dir"]));		
		$result["ORIGINAL_SELECTION"] = $userSelection;
		$result["SELECTION"] = array();
		if(!$userSelection->isEmpty()){
			$files = $userSelection->getFiles();
			foreach ($files as $selected){
				$result["SELECTION"][] = call_user_func(array($wrapperData["classname"], "getRealFSReference"), $urlBase.$selected);
			}			
		}
		foreach ($additionnalPathes as $parameter => $path){
			$result[$parameter] = call_user_func(array($wrapperData["classname"], "getRealFSReference"), $urlBase.$path);
		}
		return $result;
	}
		
	protected function addIfNotVersionned($repoFile, $realFile){
		$res = ExecSvnCmd("svnversion", $realFile, "");
		if(!is_numeric(implode("", $res[IDX_STDOUT]))){
			$res2 = ExecSvnCmd("svn add", "$realFile");
			$this->commitMessageParams = "Recycle cache file";
			$this->commitChanges("ADD", array("dir" => dirname($repoFile)), array());
		}
	}
	
	public function commitFile($file){
		$repo = ConfService::getRepository();
		$repo->detectStreamWrapper();
		$wrapperData = $repo->streamData;
		$realFile = call_user_func(array($wrapperData["classname"], "getRealFSReference"), $file);
		
		$res = ExecSvnCmd("svn status ", $realFile);
		if(count($res[IDX_STDOUT]) && substr($res[IDX_STDOUT][0],0,1) == "?"){
			$res2 = ExecSvnCmd("svn add", "$realFile");
		}
		$this->commitChanges("COMMIT_META", $realFile, array());
	}
	
	public function switchAction($actionName, $httpVars, $filesVars){
		$init = $this->initDirAndSelection($httpVars);
		if($actionName == "svnlog"){
			$res1 = ExecSvnCmd("svnversion", $init["DIR"]);
			$test = trim(implode("", $res1[IDX_STDOUT]));
			if(is_numeric($test)){
				$currentRev = $test;
			}else if(strstr($test, ":")!==false && count(explode(":", $test))){
				$revRange = explode(":", $test);
			}
			$command = 'svn log';
			$switches = '--xml -rHEAD:0';
			$arg = $init["SELECTION"][0];
			$res = ExecSvnCmd($command, $arg, $switches);
			AJXP_XMLWriter::header();	
			$lines = explode(PHP_EOL, $res[IDX_STDOUT]);
			array_shift($lines);
			if(isSet($currentRev)){
				print("<current_revision>$currentRev</current_revision>");
			}else if(isSet($revRange)){
				print("<revision_range start='$revRange[0]' end='$revRange[1]'/>");
			}
			print(SystemTextEncoding::toUTF8(implode("", $lines)));
			AJXP_XMLWriter::close();
		}else if($actionName == "svndownload"){
			$revision = $httpVars["revision"];
			$realFile = $init["SELECTION"][0];
			$entries = $this->svnListNode($realFile, $revision);
			$keys = array_keys($entries); 
			$localName = $keys[0];
			$contentSize = 0;
			if(isSet($entries[$localName]["last_revision_size"])){
				$contentSize = intval($entries[$localName]["last_revision_size"]);
			}
			// output directly the file!
			header("Content-Type: application/force-download; name=\"".$localName."\"");
			header("Content-Transfer-Encoding: binary");
			if($contentSize > 0) header("Content-Length: ".$contentSize);
			header("Content-Disposition: attachment; filename=\"".$localName."\"");
			header("Expires: 0");
			header("Cache-Control: no-cache, must-revalidate");
			header("Pragma: no-cache");
			if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])){
                header("Pragma: public");
                header("Expires: 0");
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                header("Cache-Control: private",false);
            }
			
			system("svn cat -r$revision $realFile");
			exit(0);
		}else if($actionName == "svnswitch"){
			$revision = $httpVars["revision"];
			ExecSvnCmd("svn update -r$revision ".$init["DIR"]);
		}
	}
	
	public function addSelection($actionName, $httpVars, $filesVars){		
		switch ($actionName){
			case "mkdir":
				$init = $this->initDirAndSelection($httpVars, array("NEW_DIR" => AJXP_Utils::decodeSecureMagic($httpVars["dir"]."/".$httpVars["dirname"])));
				$res = ExecSvnCmd("svn add", $init["NEW_DIR"]);				
				$this->commitMessageParams = $httpVars["dirname"];
			break;
			case "mkfile":
				$init = $this->initDirAndSelection($httpVars, array("NEW_FILE" => AJXP_Utils::decodeSecureMagic($httpVars["dir"]."/".$httpVars["filename"])));
				$res = ExecSvnCmd("svn add", $init["NEW_FILE"]);
				$this->commitMessageParams = $httpVars["filename"];
			break;
			case "upload":
				if(isSet($filesVars) && isSet($filesVars["userfile_0"]) && isSet($filesVars["userfile_0"]["name"])){
					$init = $this->initDirAndSelection($httpVars, array("NEW_FILE" => SystemTextEncoding::fromUTF8($httpVars["dir"])."/".$filesVars["userfile_0"]["name"]));
					$res = ExecSvnCmd("svn add", $init["NEW_FILE"]);
					$this->commitMessageParams = $filesVars["userfile_0"]["name"];
				}
			break;
		}
		if(isSet($res)){
			$this->commitChanges($actionName, $httpVars, $filesVars);
		}
	}
	
	public function copyOrMoveSelection($actionName, &$httpVars, $filesVars){
		if($actionName != "rename"){
			$init = $this->initDirAndSelection($httpVars, array("DEST_DIR" => AJXP_Utils::decodeSecureMagic($httpVars["dest"])));
			$this->commitMessageParams = "To:".$httpVars["dest"].";items:";
		}else{
			$init = $this->initDirAndSelection($httpVars, array(), true);
		}
		$action = 'copy';
		if($actionName == "move" || $actionName == "rename"){
			$action = 'move';
		}		
		foreach ($init["SELECTION"] as $selectedFile){
			if($actionName == "rename"){
				$destFile = dirname($selectedFile)."/".AJXP_Utils::decodeSecureMagic($httpVars["filename_new"]);
				$this->commitMessageParams = "To:".$httpVars["filename_new"].";item:".$httpVars["file"];
			}else{
				$destFile = $init["DEST_DIR"]."/".basename($selectedFile);			
			}
			$res = ExecSvnCmd("svn $action", "$selectedFile $destFile", '');
		}
		if($actionName != "rename"){
			$this->commitMessageParams .= "[".implode(",",$init["SELECTION"])."]";
		}
		$this->commitChanges($actionName, $httpVars, $filesVars);
		if($action != "rename"){
			$this->commitChanges($actionName, array("dir" => $httpVars["dest"]), $filesVars);
		}
		AJXP_Logger::logAction("CopyMove/Rename (svn delegate)", array("files"=>$init["SELECTION"]));
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("The selected files/folders have been copied/moved (by SVN)", null);
		AJXP_XMLWriter::reloadDataNode();
		AJXP_XMLWriter::close();		
	}
	
	public function deleteSelection($actionName, &$httpVars, $filesVars){		
		$init = $this->initDirAndSelection($httpVars, array(), true);
		if(isSet($init["RECYCLE"]) && isSet($init["RECYCLE"]["action"]) && $init["RECYCLE"]["action"] != "delete"){
			$httpVars["dest"] = SystemTextEncoding::fromUTF8($init["RECYCLE"]["dest"]);
			$this->copyOrMoveSelection("move", $httpVars, $filesVars);
			$userSelection = $init["ORIGINAL_SELECTION"];
			$files = $userSelection->getFiles();
			if($actionName == "delete"){
				foreach ($files as $file){
					RecycleBinManager::fileToRecycle($file);
				}
			}else if($actionName == "restore"){
				foreach ($files as $file){
					RecycleBinManager::deleteFromRecycle($file);
				}				
			}
			$this->commitChanges($actionName, array("dir" => RecycleBinManager::getRelativeRecycle()), $filesVars);
			return ;
		}
		foreach ($init["SELECTION"] as $selectedFile){
			$res = ExecSvnCmd('svn delete', $selectedFile, '--force');
		}
		$this->commitMessageParams = "[".implode(",",$init["SELECTION"])."]";
		$this->commitChanges($actionName, $httpVars, $filesVars);
		AJXP_Logger::logAction("Delete (svn delegate)", array("files"=>$init["SELECTION"]));
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("The selected files/folders have been deleted (by SVN)", null);
		AJXP_XMLWriter::reloadDataNode();
		AJXP_XMLWriter::close();
	}
		
	public function commitChanges($actionName, $httpVars, $filesVars){
		if(is_array($httpVars)){
			$init = $this->initDirAndSelection($httpVars);
			$args = $init["DIR"];
		}else{
			$args = $httpVars;
		}
		$command = "svn commit";
		$user = AuthService::getLoggedUser()->getId();		
		$switches = "-m \"AjaXplorer||$user||$actionName".(isSet($this->commitMessageParams)?"||".$this->commitMessageParams:"")."\"";
		$res = ExecSvnCmd($command, $args, $switches);		
		$res2 = ExecSvnCmd('svn update', dirname($args), '');
	}		
	/**
	 *
	 * @param AJXP_Node $ajxpNode
	 */
	public function extractMeta(&$ajxpNode){
		if(isSet($_SESSION["SVN_COMMAND_RUNNING"]) && $_SESSION["SVN_COMMAND_RUNNING"] === true) return ;
		$realDir = dirname($ajxpNode->getRealFile());
		if(SvnManager::$svnListDir == $realDir){
			$entries = SvnManager::$svnListCache;
		}else{
			SvnManager::$svnListDir = $realDir;
			$entries = $this->svnListNode($realDir);
			SvnManager::$svnListCache = $entries;
		}
		$fileId = SystemTextEncoding::toUTF8(basename($ajxpNode->getUrl()));
		if(isSet($entries[$fileId])){
			$ajxpNode->mergeMetadata($entries[$fileId]);
		}
	}
	
	protected function svnListNode($realPath, $revision = null){
		$command = 'svn list';
		$switches = '--xml';
		if($revision != null){
			$switches = '--xml -r'.$revision;
		}
		$_SESSION["SVN_COMMAND_RUNNING"] = true;
		if(substr(strtolower(PHP_OS), 0, 3) == "win") session_write_close();
		$res = ExecSvnCmd($command, $realPath, $switches);
		if(substr(strtolower(PHP_OS), 0, 3) == "win") session_start();
		unset($_SESSION["SVN_COMMAND_RUNNING"]);
		$domDoc = DOMDocument::loadXML($res[IDX_STDOUT]);
		$xPath = new DOMXPath($domDoc);
		$entriesList = $xPath->query("list/entry");
		$entries = array();
		foreach ($entriesList as $entry){
			$logEntry = array();
			$name = $xPath->query("name", $entry)->item(0)->nodeValue;			
			$logEntry["last_revision"] = $xPath->query("commit/@revision", $entry)->item(0)->value;
			$logEntry["last_revision_author"] = $xPath->query("commit/author", $entry)->item(0)->nodeValue;
			$logEntry["last_revision_date"] = $xPath->query("commit/date", $entry)->item(0)->nodeValue;
			$logEntry["last_revision_date"] = $xPath->query("size", $entry)->item(0)->nodeValue;
			$entries[$name] = $logEntry;
		}
		return $entries;		
	}
	

		
}

?>