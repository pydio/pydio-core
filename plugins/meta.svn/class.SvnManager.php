<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2010 Charles du Jeu
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
 * Description : Exif extractor
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once("svn_lib.inc.php");

class SvnManager extends AJXP_Plugin {
	
	private static $svnListDir;
	private static $svnListCache;
	private static $svnCommandRunning = false;
	
	protected $accessDriver;
	
	public function init($options){
		$this->options = $options;		
		// Do nothing
	}
	
	public function initMeta($accessDriver){
		$this->accessDriver = $accessDriver;		
		parent::init($this->options);
	
	}
	
	protected function initDirAndSelection($httpVars, $additionnalPathes = array()){
		$userSelection = new UserSelection();
		$userSelection->initFromHttpVars($httpVars);
		$repo = ConfService::getRepository();
		$repo->detectStreamWrapper();
		$wrapperData = $repo->streamData;
		$urlBase = $wrapperData["protocol"]."://".$repo->getId();		
		$result = array();
		$result["DIR"] = call_user_func(array($wrapperData["classname"], "getRealFSReference"), $urlBase.AJXP_Utils::decodeSecureMagic($httpVars["dir"]));
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
		
	public function switchAction($actionName, $httpVars, $filesVars){
		$init = $this->initDirAndSelection($httpVars);
		if($actionName == "svnlog"){
			$command = 'svn log';
			$switches = '--xml -rHEAD:0';
			$arg = $init["SELECTION"][0];
			$res = ExecSvnCmd($command, $arg, $switches);
			AJXP_XMLWriter::header();	
			$lines = explode("\r\n", $res[IDX_STDOUT]);
			array_shift($lines);
			print_r(implode("", $lines));
			AJXP_XMLWriter::close();
		}else if($actionName == "svndownload"){
			$revision = $httpVars["revision"];
			$realFile = $init["SELECTION"][0];
			$entries = $this->svnListNode($realFile, $revision);
			$keys = array_keys($entries); 
			$localName = $keys[0];
			$contentSize = $entries[$localName]["last_revision_size"];
			
			// output directly the file!
			header("Content-Type: application/force-download; name=\"".$localName."\"");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".$contentSize);
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
				//print_r($res);
			break;
			case "mkfile":
				$init = $this->initDirAndSelection($httpVars, array("NEW_FILE" => AJXP_Utils::decodeSecureMagic($httpVars["dir"]."/".$httpVars["filename"])));
				$res = ExecSvnCmd("svn add", $init["NEW_FILE"]);
				//print_r($res);
			break;
			case "upload":
				
			break;
		}
		if(isSet($res)){
			$this->commitChanges($actionName, $httpVars, $filesVars);
		}
	}
	
	public function copyOrMoveSelection($actionName, &$httpVars, $filesVars){
		if($actionName != "rename"){
			$init = $this->initDirAndSelection($httpVars, array("DEST_DIR" => AJXP_Utils::decodeSecureMagic($httpVars["dest"])));
		}else{
			$init = $this->initDirAndSelection($httpVars);
		}
		$action = 'copy';
		if($actionName == "move" || $actionName == "rename"){
			$action = 'move';
		}
		foreach ($init["SELECTION"] as $selectedFile){
			if($actionName == "rename"){
				$destFile = dirname($selectedFile)."/".AJXP_Utils::decodeSecureMagic($httpVars["filename_new"]);
			}else{
				$destFile = $init["DEST_DIR"]."/".basename($selectedFile);			
			}
			$res = ExecSvnCmd("svn $action", "$selectedFile $destFile", '');
		}
		$this->commitChanges($actionName, $httpVars, $filesVars);		
		AJXP_Logger::logAction("CopyMove/Rename (svn delegate)", array("files"=>$init["SELECTION"]));
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("The selected files/folders have been copied/moved (by SVN)", null);
		AJXP_XMLWriter::reloadDataNode();
		AJXP_XMLWriter::close();		
	}
	
	public function deleteSelection($actionName, &$httpVars, $filesVars){
		$init = $this->initDirAndSelection($httpVars);
		foreach ($init["SELECTION"] as $selectedFile){
			$res = ExecSvnCmd('svn delete', $selectedFile, '--force');
		}
		$this->commitChanges($actionName, $httpVars, $filesVars);
		AJXP_Logger::logAction("Delete (svn delegate)", array("files"=>$init["SELECTION"]));
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("The selected files/folders have been deleted (by SVN)", null);
		AJXP_XMLWriter::reloadDataNode();
		AJXP_XMLWriter::close();
	}
		
	public function commitChanges($actionName, $httpVars, $filesVars){
		$init = $this->initDirAndSelection($httpVars);
		$command = "svn commit";
		$user = AuthService::getLoggedUser()->getId();
		$args = $init["DIR"];
		$switches = "-m \"AjaXplorer Auto Commit - $user\"";
		$res = ExecSvnCmd($command, $args, $switches);		
		$res2 = ExecSvnCmd('svn update', dirname($args), '');
	}		
	
	public function extractMeta($currentFile, &$metadata, $wrapperClassName, &$realFile){
		if(isSet($_SESSION["SVN_COMMAND_RUNNING"]) && $_SESSION["SVN_COMMAND_RUNNING"] === true) return ;
		$realDir = dirname(call_user_func(array($wrapperClassName, "getRealFSReference"), $currentFile));
		if(SvnManager::$svnListDir == $realDir){
			$entries = SvnManager::$svnListCache;
		}else{
			SvnManager::$svnListDir = $realDir;
			$entries = $this->svnListNode($realDir);
			SvnManager::$svnListCache = $entries;
		}
		$fileId = SystemTextEncoding::toUTF8(basename($currentFile));
		if(isSet($entries[$fileId])){
			$metadata = array_merge($metadata, $entries[$fileId]);
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