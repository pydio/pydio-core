<?php
/**
 * @package info.ajaxplorer.plugins
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
 * Description : The "admin" driver, to make use of the GUI to manage AjaXplorer settings.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class ajxpSharedAccessDriver extends AbstractAccessDriver 
{	
	
	function switchAction($action, $httpVars, $fileVars){
		if(!isSet($this->actions[$action])) return;
		parent::accessPreprocess($action, $httpVars, $fileVars);
		$loggedUser = AuthService::getLoggedUser();
		if(!ENABLE_USERS) return ;
		
		if($action == "edit"){
			if(isSet($httpVars["sub_action"])){
				$action = $httpVars["sub_action"];
			}
		}
		$mess = ConfService::getMessages();
		
		switch($action)
		{			
			//------------------------------------
			//	BASIC LISTING
			//------------------------------------
			case "ls":
				$rootNodes = array(
					"files" => array("LABEL" => $mess["ajxp_shared.3"], "ICON" => "html.png"),
					"repositories" => array("LABEL" => $mess["ajxp_shared.2"], "ICON" => "document_open_remote.png"),
					"users" => array("LABEL" => $mess["ajxp_shared.1"], "ICON" => "user_shared.png")
				);
				$dir = (isset($httpVars["dir"])?$httpVars["dir"]:"");
				$splits = explode("/", $dir);
				if(count($splits)){
					if($splits[0] == "") array_shift($splits);
					if(count($splits)) $strippedDir = strtolower(urldecode($splits[0]));
					else $strippedDir = "";
				}				
				if(array_key_exists($strippedDir, $rootNodes)){
					AJXP_XMLWriter::header();
					if($strippedDir == "users"){
						$this->listUsers();
					}else if($strippedDir == "repositories"){
						$this->listRepositories();
					}else if($strippedDir == "files"){
						$this->listSharedFiles();
					}
					AJXP_XMLWriter::close();
					exit(1);
				}else{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_shared.8" attributeName="ajxp_label" sortType="String"/></columns>');
					foreach ($rootNodes as $key => $data){
						$src = '';
						if($key == "logs"){
							$src = 'src="content.php?get_action=ls&amp;dir='.$key.'"';
						}
						print '<tree text="'.$data["LABEL"].'" icon="'.$data["ICON"].'" filename="/'.$key.'" parentname="/" '.$src.' />';
					}
					AJXP_XMLWriter::close();
					exit(1);
				}
			break;
			
			case "stat" :
				
				header("Content-type:application/json");
				print '{"mode":true}';
				exit(1);
				
			break;			
						
			case "delete" : 
				$mime = $httpVars["ajxp_mime"];
				$element = basename($httpVars["file"]);
				if($mime == "shared_repository"){
					$repo = ConfService::getRepositoryById($element);
					AJXP_XMLWriter::header();
					if(!$repo->hasOwner() || $repo->getOwner() != $loggedUser->getId()){
						AJXP_XMLWriter::sendMessage(null, $mess["ajxp_shared.12"]);
					}else{
						$res = ConfService::deleteRepository($element);
						if($res == -1){
							AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.51"]);
						}else{
							AJXP_XMLWriter::sendMessage($mess["ajxp_conf.59"], null);						
							AJXP_XMLWriter::reloadDataNode();
						}
					}
					AJXP_XMLWriter::close();
				}else if( $mime == "shared_user" ){
					$confDriver = ConfService::getConfStorageImpl();
					$object = $confDriver->createUserObject($element);
					AJXP_XMLWriter::header();
					if(!$object->hasParent() || $object->getParent() != $loggedUser->getId()){
						AJXP_XMLWriter::sendMessage(null, $mess["ajxp_shared.12"]);
					}else{
						$res = AuthService::deleteUser($element);
						AJXP_XMLWriter::sendMessage($mess["ajxp_conf.60"], null);
						AJXP_XMLWriter::reloadDataNode();
					}
					AJXP_XMLWriter::close();					
				}else if( $mime == "shared_file" ){					
					AJXP_XMLWriter::header();
					$publicletData = $this->loadPublicletData(PUBLIC_DOWNLOAD_FOLDER."/".$element.".php");
					if(isSet($publicletData["OWNER_ID"]) && $publicletData["OWNER_ID"] == $loggedUser->getId()){
						unlink(PUBLIC_DOWNLOAD_FOLDER."/".$element.".php");
						AJXP_XMLWriter::sendMessage($mess["ajxp_shared.13"], null);
						AJXP_XMLWriter::reloadDataNode();
					}else{
						AJXP_XMLWriter::sendMessage(null, $mess["ajxp_shared.12"]);
					}
					AJXP_XMLWriter::close();
				}
			
			break;
			
			case "clear_expired" :
				
				$deleted = $this->clearExpiredFiles();
				AJXP_XMLWriter::header();
				if(count($deleted)){
					AJXP_XMLWriter::sendMessage(sprintf($mess["ajxp_shared.23"], count($deleted).""), null);
					AJXP_XMLWriter::reloadDataNode();					
				}else{
					AJXP_XMLWriter::sendMessage($mess["ajxp_shared.24"], null);
				}
				AJXP_XMLWriter::close();
				
			break;
			
			
			default:
			break;
		}

		return;
	}
	
	function listSharedFiles(){
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist">
				<column messageId="ajxp_shared.4" attributeName="ajxp_label" sortType="String" width="20%"/>
				<column messageId="ajxp_shared.17" attributeName="download_url" sortType="String" width="20%"/>
				<column messageId="ajxp_shared.6" attributeName="password" sortType="String" width="5%"/>
				<column messageId="ajxp_shared.7" attributeName="expiration" sortType="String" width="5%"/>
				<column messageId="ajxp_shared.20" attributeName="expired" sortType="String" width="5%"/>
				<column messageId="ajxp_shared.14" attributeName="integrity" sortType="String" width="5%" hidden="true"/>
			</columns>');
		if(!is_dir(PUBLIC_DOWNLOAD_FOLDER)) return ;		
		$files = glob(PUBLIC_DOWNLOAD_FOLDER."/*.php");
		$mess = ConfService::getMessages();
		$loggedUser = AuthService::getLoggedUser();
		$userId = $loggedUser->getId();
        if(defined('PUBLIC_DOWNLOAD_URL') && PUBLIC_DOWNLOAD_URL != ""){
        	$downloadBase = rtrim(PUBLIC_DOWNLOAD_URL, "/");
        }else{
	        $http_mode = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
	        $fullUrl = $http_mode . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);    
	        $downloadBase = str_replace("\\", "/", $fullUrl.rtrim(str_replace(INSTALL_PATH, "", PUBLIC_DOWNLOAD_FOLDER), "/"));
        }
		
		foreach ($files as $file){
			$publicletData = $this->loadPublicletData($file);			
			if(isset($publicletData["OWNER_ID"]) && $publicletData["OWNER_ID"] != $userId){
				continue;
			}
			AJXP_XMLWriter::renderNode(str_replace(".php", "", basename($file)), "".$publicletData["REPOSITORY"]->getDisplay().":/".$publicletData["FILE_PATH"], true, array(
				"icon"		=> "html.png",
				"password" => ($publicletData["PASSWORD"]!=""?$publicletData["PASSWORD"]:"-"), 
				"expiration" => ($publicletData["EXPIRE_TIME"]!=0?date($mess["date_format"], $publicletData["EXPIRE_TIME"]):"-"), 
				"expired" => ($publicletData["EXPIRE_TIME"]!=0?($publicletData["EXPIRE_TIME"]<time()?$mess["ajxp_shared.21"]:$mess["ajxp_shared.22"]):"-"), 
				"integrity"  => (!$publicletData["SECURITY_MODIFIED"]?$mess["ajxp_shared.15"]:$mess["ajxp_shared.16"]),
				"download_url" => $downloadBase . "/".basename($file),
				"ajxp_mime" => "shared_file")
			);			
		}
	}
	
	function clearExpiredFiles(){
		$files = glob(PUBLIC_DOWNLOAD_FOLDER."/*.php");
		$loggedUser = AuthService::getLoggedUser();
		$userId = $loggedUser->getId();
		$deleted = array();
		foreach ($files as $file){
			$publicletData = $this->loadPublicletData($file);			
			if(!isSet($publicletData["OWNER_ID"]) || $publicletData["OWNER_ID"] != $userId){
				continue;
			}
			if(isSet($publicletData["EXPIRATION_TIME"]) && is_numeric($publicletData["EXPIRATION_TIME"]) && $publicletData["EXPIRATION_TIME"] > 0 && $publicletData["EXPIRATION_TIME"] < time()){
				unlink($file);
				$deleted[] = basename($file);
			}
		}
		return $deleted;
	}
	
	protected function loadPublicletData($file){
		$lines = file($file);
		$id = str_replace(".php", "", basename($file));
		$code = $lines[3] . $lines[4] . $lines[5];
		eval($code);
		$dataModified = (md5($inputData) != $id);
		$publicletData = unserialize($inputData);
		$publicletData["SECURITY_MODIFIED"] = $dataModified;		
		return $publicletData;
	}
	
	function listUsers(){
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_shared.10" attributeName="repo_accesses" sortType="String"/></columns>');		
		if(!ENABLE_USERS) return ;
		$users = AuthService::listUsers();
		$mess = ConfService::getMessages();
		$loggedUser = AuthService::getLoggedUser();		
		$repoList = ConfService::getRepositoriesList();
        $userArray = array();
		foreach ($users as $userIndex => $userObject){
			$label = $userObject->getId();
			if(!$userObject->hasParent() || $userObject->getParent() != $loggedUser->getId()) continue;
			if($userObject->hasParent()){
				$label = $userObject->getParent()."000".$label;
			}
            $userArray[$label] = $userObject;
        }        
        ksort($userArray);
        foreach($userArray as $userObject) {
			$isAdmin = $userObject->isAdmin();
			$userId = AJXP_Utils::xmlEntities($userObject->getId());
			$repoAccesses = array();
			foreach ($repoList as $repoObject) {
				if($repoObject->hasOwner() && $repoObject->getOwner() == $loggedUser->getId()){
					if($userObject->canWrite($repoObject->getId())){
						$repoAccesses[] = $repoObject->getDisplay()." (rw)";
					}else if($userObject->canRead($repoObject->getId())){
						$repoAccesses[] = $repoObject->getDisplay()." (r)";
					}
				}
			}			
			print '<tree 
				text="'.$userId.'"
				isAdmin="'.$mess[($isAdmin?"ajxp_conf.14":"ajxp_conf.15")].'" 
				icon="user_shared.png" 
				openicon="user_shared.png" 
				filename="/users/'.$userId.'" 
				repo_accesses="'.implode(", ", $repoAccesses).'"
				parentname="/users" 
				is_file="1" 
				ajxp_mime="shared_user"
				/>';
		}
	}
	
	function listRepositories(){
		$repos = ConfService::getRepositoriesList();
		AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.8" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_conf.9" attributeName="accessType" sortType="String"/><column messageId="ajxp_shared.9" attributeName="repo_accesses" sortType="String"/></columns>');		
        $repoArray = array();
        $childRepos = array();
        $loggedUser = AuthService::getLoggedUser();        
        $users = AuthService::listUsers();
		foreach ($repos as $repoIndex => $repoObject){
			if($repoObject->getAccessType() == "ajxp_conf") continue;			
			if(!$repoObject->hasOwner() || $repoObject->getOwner() != $loggedUser->getId()){				
				continue;
			}
			if(is_numeric($repoIndex)) $repoIndex = "".$repoIndex;
            $name = AJXP_Utils::xmlEntities(SystemTextEncoding::toUTF8($repoObject->getDisplay()));
            $repoArray[$name] = $repoIndex;
        }
        // Sort the list now by name        
        ksort($repoArray);
        // Append child repositories
        $sortedArray = array();
        foreach ($repoArray as $name => $repoIndex) {
        	$sortedArray[$name] = $repoIndex;
        	if(isSet($childRepos[$repoIndex]) && is_array($childRepos[$repoIndex])){
        		foreach ($childRepos[$repoIndex] as $childData){
        			$sortedArray[$childData["name"]] = $childData["index"];
        		}
        	}
        }
        foreach ($sortedArray as $name => $repoIndex) {
            $repoObject =& $repos[$repoIndex];
            $repoAccesses = array();
			foreach ($users as $userId => $userObject) {
				if(!$userObject->hasParent()) continue;
				if($userObject->canWrite($repoIndex)){
					$repoAccesses[] = $userId." (rw)";
				}else if($userObject->canRead($repoIndex)){
					$repoAccesses[] = $userId." (r)";
				}
			}			
            
            $metaData = array(
            	"repository_id" => $repoIndex,
            	"accessType"	=> $repoObject->getAccessType(),
            	"icon"			=> "document_open_remote.png",
            	"openicon"		=> "document_open_remote.png",
            	"parentname"	=> "/repositories",
            	"repo_accesses" => implode(", ", $repoAccesses),
				"ajxp_mime" 	=> "shared_repository"
            );
            AJXP_XMLWriter::renderNode("/repositories/$repoIndex", $name, true, $metaData);
		}
	}
		    
}

?>
