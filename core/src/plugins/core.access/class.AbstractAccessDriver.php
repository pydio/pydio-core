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
 * @class AbstractAccessDriver
 * Abstract representation of an action driver. Must be implemented.
 */
class AbstractAccessDriver extends AJXP_Plugin {
	
	/**
	* @var Repository
	*/
	public $repository;
	public $driverType = "access";
		
	public function init($repository, $options = null){
		//$this->loadActionsFromManifest();
		parent::init($options);
		$this->repository = $repository;
	}
	
	function initRepository(){
		// To be implemented by subclasses
	}
	
	
	function accessPreprocess($actionName, &$httpVars, &$filesVar)
	{
		if($actionName == "cross_copy"){
			$this->crossRepositoryCopy($httpVars);
			return ;
		}
		if($actionName == "ls"){
			// UPWARD COMPATIBILTY
			if(isSet($httpVars["options"])){
				if($httpVars["options"] == "al") $httpVars["mode"] = "file_list";
				else if($httpVars["options"] == "a") $httpVars["mode"] = "search";
				else if($httpVars["options"] == "d") $httpVars["skipZip"] = "true";
				// skip "complete" mode that was in fact quite the same as standard tree listing (dz)
			}
			/*
			if(!isSet($httpVars["skip_history"])){
				if(AuthService::usersEnabled() && AuthService::getLoggedUser()!=null){
					$user = AuthService::getLoggedUser();
					$user->setArrayPref("history", $this->repository->getId(), ((isSet($httpVars["dir"])&&trim($httpVars["dir"])!="")?$httpVars["dir"]:"/"));
					$user->save();
				}
			}
			*/
		}
	}

	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if($this->detectStreamWrapper() !== false){
			$this->actions["cross_copy"] = array();
		}
	}


    /**
     * Backward compatibility, now moved to SharedCenter::loadPubliclet();
     * @param $data
     * @return void
     */
    function loadPubliclet($data){
        require_once(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/action.share/class.ShareCenter.php");
        ShareCenter::loadPubliclet($data);
    }

    /**
     * Populate publiclet options
     * @param String $filePath The path to the file to share
     * @param String $password optionnal password
     * @param String $downloadlimit optional limit for downloads
     * @param String $expires optional expiration date
     * @param Repository $repository
     * @return Array
     */
    function makePublicletOptions($filePath, $password, $expires, $downloadlimit, $repository) {}

    /**
     * Populate shared repository options
     * @param Array $httpVars
     * @param Repository $repository
     * @return Array
     */
    function makeSharedRepositoryOptions($httpVars, $repository){}

       
    function crossRepositoryCopy($httpVars){
    	
    	ConfService::detectRepositoryStreams(true);
    	$mess = ConfService::getMessages();
		$selection = new UserSelection();
		$selection->initFromHttpVars($httpVars);
    	$files = $selection->getFiles();
    	
    	$accessType = $this->repository->getAccessType();    	
    	$repositoryId = $this->repository->getId();
    	$plugin = AJXP_PluginsService::findPlugin("access", $accessType);
    	$origWrapperData = $plugin->detectStreamWrapper(true);
    	$origStreamURL = $origWrapperData["protocol"]."://$repositoryId";    	
    	
    	$destRepoId = $httpVars["dest_repository_id"];
    	$destRepoObject = ConfService::getRepositoryById($destRepoId);
    	$destRepoAccess = $destRepoObject->getAccessType();
    	$plugin = AJXP_PluginsService::findPlugin("access", $destRepoAccess);
    	$destWrapperData = $plugin->detectStreamWrapper(true);
    	$destStreamURL = $destWrapperData["protocol"]."://$destRepoId";
    	// Check rights
    	if(AuthService::usersEnabled()){
	    	$loggedUser = AuthService::getLoggedUser();
	    	if(!$loggedUser->canRead($repositoryId) || !$loggedUser->canWrite($destRepoId)
	    		|| (isSet($httpVars["moving_files"]) && !$loggedUser->canWrite($repositoryId))
	    	){
	    		throw new Exception($mess[364]);
	    	}
    	}
    	
    	$messages = array();
    	foreach ($files as $file){
    		$origFile = $origStreamURL.$file;
    		$localName = "";
    		AJXP_Controller::applyHook("dl.localname", array($origFile, &$localName, $origWrapperData["classname"]));
    		$bName = basename($file);
    		if($localName != ""){
    			$bName = $localName;
    		}    		
    		$destFile = $destStreamURL.SystemTextEncoding::fromUTF8($httpVars["dest"])."/".$bName;    		
    		AJXP_Logger::debug("Copying $origFile to $destFile");    		
    		if(!is_file($origFile)){
    			throw new Exception("Cannot find $origFile");
    		}
			$origHandler = fopen($origFile, "r");
			$destHandler = fopen($destFile, "w");
			if($origHandler === false || $destHandler === false) {
				$errorMessages[] = AJXP_XMLWriter::sendMessage(null, $mess[114]." ($origFile to $destFile)", false);
				continue;
			}
			while(!feof($origHandler)){
				fwrite($destHandler, fread($origHandler, 4096));
			}
			fflush($destHandler);
			fclose($origHandler); 
			fclose($destHandler);			
			$messages[] = $mess[34]." ".SystemTextEncoding::toUTF8(basename($origFile))." ".(isSet($httpVars["moving_files"])?$mess[74]:$mess[73])." ".SystemTextEncoding::toUTF8($destFile);
    	}
    	AJXP_XMLWriter::header();    	
    	if(count($errorMessages)){
    		AJXP_XMLWriter::sendMessage(null, join("\n", $errorMessages), true);
    	}
    	AJXP_XMLWriter::sendMessage(join("\n", $messages), null, true);
    	AJXP_XMLWriter::close();
    }
    
    /**
     * 
     * Try to reapply correct permissions
     * @param oct $mode
     * @param Repository $repoObject
     * @param Function $remoteDetectionCallback
     */
    public static function fixPermissions(&$stat, $repoObject, $remoteDetectionCallback = null){
    	
        $fixPermPolicy = $repoObject->getOption("FIX_PERMISSIONS");    	
    	$loggedUser = AuthService::getLoggedUser();
    	if($loggedUser == null){
    		return;
    	}
    	$sessionKey = md5($repoObject->getId()."-".$loggedUser->getId()."-fixPermData");

    	
    	if(!isSet($_SESSION[$sessionKey])){			
    	    if($fixPermPolicy == "detect_remote_user_id" && $remoteDetectionCallback != null){
    	    	list($uid, $gid) = call_user_func($remoteDetectionCallback, $repoObject);
    	    	if($uid != null && $gid != null){
    	    		$_SESSION[$sessionKey] = array("uid" => $uid, "gid" => $gid);
    	    	} 
		    	
	    	}else if(substr($fixPermPolicy, 0, strlen("file:")) == "file:"){
	    		$filePath = AJXP_VarsFilter::filter(substr($fixPermPolicy, strlen("file:")));
	    		if(file_exists($filePath)){
	    			// GET A GID/UID FROM FILE
	    			$lines = file($filePath);
	    			foreach($lines as $line){
	    				$res = explode(":", $line);
	    				if($res[0] == $loggedUser->getId()){
	    					$uid = $res[1];
	    					$gid = $res[2];
	    					$_SESSION[$sessionKey] = array("uid" => $uid, "gid" => $gid);
	    					break;
	    				}
	    			}
	    		}
	    	}
	    	// If not set, set an empty anyway
	    	if(!isSet($_SESSION[$sessionKey])){
	    		$_SESSION[$sessionKey] = array(null, null);
	    	}
    		
    	}else{
    		$data = $_SESSION[$sessionKey];
    		if(!empty($data)){
    			if(isSet($data["uid"])) $uid = $data["uid"];
    			if(isSet($data["gid"])) $gid = $data["gid"];
    		}    		
    	}
	    	
    	$p = $stat["mode"];
    	$st = sprintf("%07o", ($p & 7777770));
    	AJXP_Logger::debug("FIX PERM DATA ($fixPermPolicy, $st)".$p,sprintf("%o", ($p & 000777)));
    	if($p != NULL){
            $isdir = ($p&0040000?true:false);
            $changed = false;
	    	if( ( isSet($uid) && $stat["uid"] == $uid ) || $fixPermPolicy == "user"  ) {
    			AJXP_Logger::debug("upgrading abit to ubit");
                $changed = true;
    			$p  = $p&7777770;
    			if( $p&0x0100 ) $p += 04;
	    		if( $p&0x0080 ) $p += 02;
	    		if( $p&0x0040 ) $p += 01;
	    	}else if( ( isSet($gid) && $stat["gid"] == $gid )  || $fixPermPolicy == "group"  ) {
	    		AJXP_Logger::debug("upgrading abit to gbit");
                $changed = true;
    			$p  = $p&7777770;
	    		if( $p&0x0020 ) $p += 04;
	    		if( $p&0x0010 ) $p += 02;
	    		if( $p&0x0008 ) $p += 01;
	    	}
            if($isdir && $changed){
                $p += 0040000;
            } 
			$stat["mode"] = $stat[2] = $p;
    		AJXP_Logger::debug("FIXED PERM DATA ($fixPermPolicy)",sprintf("%o", ($p & 000777)));
    	}
    }
    
    protected function resetAllPermission($value){
    	
    }

}

?>
