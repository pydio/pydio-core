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
 
class ShareCenter extends AJXP_Plugin{


	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if(isSet($this->actions["share"])){
			$disableSharing = false;
			$downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
			if($downloadFolder == ""){
				$disableSharing = true;
			}else if((!is_dir($downloadFolder) || !is_writable($downloadFolder))){
				AJXP_Logger::debug("Disabling Public links, $downloadFolder is not writeable!", array("folder" => $downloadFolder, "is_dir" => is_dir($downloadFolder),"is_writeable" => is_writable($downloadFolder)));
				$disableSharing = true;
			}else{
				if(AuthService::usersEnabled()){
					$loggedUser = AuthService::getLoggedUser();
					if($loggedUser != null && $loggedUser->getId() == "guest" || $loggedUser == "shared"){
						$disableSharing = true;
					}
				}else{
					$disableSharing = true;
				}
			}
			if($disableSharing){
				unset($this->actions["share"]);
				$actionXpath=new DOMXPath($contribNode->ownerDocument);
				$publicUrlNodeList = $actionXpath->query('action[@name="share"]', $contribNode);
				$publicUrlNode = $publicUrlNodeList->item(0);
				$contribNode->removeChild($publicUrlNode);
			}
		}
	}

    function switchAction($action, $httpVars, $fileVars){

        $pServ = AJXP_PluginsService::getInstance();
        $aPlugs = $pServ->getActivePlugins();
        $accessPlugs = $pServ->getPluginsByType("access");
        $accessDriver = null;
        foreach($accessPlugs as $pId => $plug){
            if(array_key_exists("access.".$pId, $aPlugs) && $aPlugs["access.".$pId] === true){
                $accessDriver = $plug;
            }
        }
        if($accessDriver == null){
            throw new Exception("Cannot find access driver!");
        }
        $repository = ConfService::getRepository();

        if($accessDriver->getId() == "access.demo"){
            $errorMessage = "This is a demo, all 'write' actions are disabled!";
            if($httpVars["sub_action"] == "delegate_repo"){
                return AJXP_XMLWriter::sendMessage(null, $errorMessage, false);
            }else{
                print($errorMessage);
            }
            return;
        }


        switch($action){

            //------------------------------------
            // SHARING FILE OR FOLDER
            //------------------------------------
            case "share":
            	$subAction = (isSet($httpVars["sub_action"])?$httpVars["sub_action"]:"");
            	if($subAction == "delegate_repo"){
					header("Content-type:text/plain");
					$result = $this->createSharedRepository($httpVars, $repository, $accessDriver);
					print($result);
            	}else if($subAction == "list_shared_users"){
            		header("Content-type:text/html");
            		if(!ConfService::getAuthDriverImpl()->usersEditable()){
            			break;
            		}
            		$loggedUser = AuthService::getLoggedUser();
            		$allUsers = AuthService::listUsers();
            		$crtValue = $httpVars["value"];
            		$users = "";
            		foreach ($allUsers as $userId => $userObject){
            			if($crtValue != "" && (strstr($userId, $crtValue) === false || strstr($userId, $crtValue) != 0)) continue;
            			if( ( $userObject->hasParent() && $userObject->getParent() == $loggedUser->getId() ) || ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING") === true  ){
            				$users .= "<li>".$userId."</li>";
            			}
            		}
            		if(strlen($users)) {
            			print("<ul>".$users."</ul>");
            		}
            	}else{
					$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
	                $data = $accessDriver->makePublicletOptions($file, $httpVars["password"], $httpVars["expiration"], $repository);
                    $url = $this->writePubliclet($data, $accessDriver, $repository);
	                header("Content-type:text/plain");
	                echo $url;
            	}
            break;

            default:
            break;
        }


    }


    /** Cypher the publiclet object data and write to disk.
     * @param $data The publiclet data array to write
                     The data array must have the following keys:
                     - DRIVER      The driver used to get the file's content
                     - OPTIONS     The driver options to be successfully constructed (usually, the user and password)
                     - FILE_PATH   The path to the file's content
                     - PASSWORD    If set, the written publiclet will ask for this password before sending the content
                     - ACTION      If set, action to perform
                     - USER        If set, the AJXP user
                     - EXPIRE_TIME If set, the publiclet will deny downloading after this time, and probably self destruct.
     * @param AbstractAccessDriver $accessDriver
     * @param Repository $repository
     * @return the URL to the downloaded file
    */
    function writePubliclet($data, $accessDriver, $repository)
    {
    	$downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
    	if(!is_dir($downloadFolder)){
    		return "ERROR : Public URL folder does not exist!";
    	}
    	if(!function_exists("mcrypt_create_iv")){
    		return "ERROR : MCrypt must be installed to use publiclets!";
    	}
    	if($data["PASSWORD"] && !is_file($downloadFolder."/allz.css")){
            if(!defined("AJXP_THEME_FOLDER")) $th = "plugins/gui.ajax/res/themes/oxygen";
            else $th = AJXP_THEME_FOLDER ;
    		@copy(AJXP_INSTALL_PATH."/".$th."/css/allz.css", $downloadFolder."/allz.css");
    		@copy(AJXP_INSTALL_PATH."/".$th."/images/actions/22/dialog_ok_apply.png", $downloadFolder."/dialog_ok_apply.png");
    		@copy(AJXP_INSTALL_PATH."/".$th."/images/actions/16/public_url.png", $downloadFolder."/public_url.png");
    	}
    	if(!is_file($downloadFolder."/index.html")){
    		@copy(AJXP_INSTALL_PATH."/server/index.html", $downloadFolder."/index.html");
    	}
        $data["PLUGIN_ID"] = $accessDriver->id;
        $data["BASE_DIR"] = $accessDriver->baseDir;
        $data["REPOSITORY"] = $repository;
        if(AuthService::usersEnabled()){
        	$data["OWNER_ID"] = AuthService::getLoggedUser()->getId();
        }
        if($accessDriver->hasMixin("credentials_consumer")){
        	$cred = AJXP_Safe::tryLoadingCredentialsFromSources(array(), $repository);
        	if(isSet($cred["user"]) && isset($cred["password"])){
        		$data["SAFE_USER"] = $cred["user"];
        		$data["SAFE_PASS"] = $cred["password"];
        	}
        }
        // Force expanded path in publiclet
        $data["REPOSITORY"]->addOption("PATH", $repository->getOption("PATH"));
        if ($data["ACTION"] == "") $data["ACTION"] = "download";
        // Create a random key
        $data["FINAL_KEY"] = md5(mt_rand().time());
        // Cypher the data with a random key
        $outputData = serialize($data);
        // Hash the data to make sure it wasn't modified
        $hash = md5($outputData);
        // The initialisation vector is only required to avoid a warning, as ECB ignore IV
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
        // We have encoded as base64 so if we need to store the result in a database, it can be stored in text column
        $outputData = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $hash, $outputData, MCRYPT_MODE_ECB, $iv));
        // Okay, write the file:
        $fileData = "<"."?"."php \n".
        '   require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/publicLet.inc.php"); '."\n".
        '   $id = str_replace(".php", "", basename(__FILE__)); '."\n". // Not using "" as php would replace $ inside
        '   $cypheredData = base64_decode("'.$outputData.'"); '."\n".
        '   $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND); '."\n".
        '   $inputData = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $id, $cypheredData, MCRYPT_MODE_ECB, $iv));  '."\n".
        '   if (md5($inputData) != $id) { header("HTTP/1.0 401 Not allowed, script was modified"); exit(); } '."\n".
        '   // Ok extract the data '."\n".
        '   $data = unserialize($inputData); ShareCenter::loadPubliclet($data); ?'.'>';
        if (@file_put_contents($downloadFolder."/".$hash.".php", $fileData) === FALSE){
            return "Can't write to PUBLIC URL";
        }
        require_once(AJXP_BIN_FOLDER."/class.PublicletCounter.php");
        PublicletCounter::reset($hash);
        $dlURL = ConfService::getCoreConf("PUBLIC_DOWNLOAD_URL");
        if($dlURL != ""){
        	return rtrim($dlURL, "/")."/".$hash.".php";
        }else{
	        $fullUrl = AJXP_Utils::detectServerURL() . dirname($_SERVER['REQUEST_URI']);
	        return str_replace("\\", "/", $fullUrl.rtrim(str_replace(AJXP_INSTALL_PATH, "", $downloadFolder), "/")."/".$hash.".php");
        }
    }

    /** Load a uncyphered publiclet */
    static function loadPubliclet($data)
    {
        // create driver from $data
        $className = $data["DRIVER"]."AccessDriver";
        if ($data["EXPIRE_TIME"] && time() > $data["EXPIRE_TIME"])
        {
            // Remove the publiclet, it's done
            if (strstr(realpath($_SERVER["SCRIPT_FILENAME"]),realpath(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"))) !== FALSE){
		        $hash = md5(serialize($data));
		        require_once(AJXP_BIN_FOLDER."/class.PublicletCounter.php");
		        PublicletCounter::delete($hash);
                unlink($_SERVER["SCRIPT_FILENAME"]);
            }

            echo "Link is expired, sorry.";
            exit();
        }
        // Check password
        if (strlen($data["PASSWORD"]))
        {
            if (!isSet($_POST['password']) || ($_POST['password'] != $data["PASSWORD"]))
            {
            	$content = file_get_contents(AJXP_INSTALL_PATH."/plugins/core.access/res/public_links.html");
            	$language = "en";
            	if(isSet($_GET["lang"])){
            		$language = $_GET["lang"];
            	}
            	$messages = array();
            	if(is_file(AJXP_COREI18N_FOLDER."/".$language.".php")){
            		include(AJXP_COREI18N_FOLDER."/".$language.".php");
            		$messages = $mess;
            	}
				if(preg_match_all("/AJXP_MESSAGE(\[.*?\])/", $content, $matches, PREG_SET_ORDER)){
					foreach($matches as $match){
						$messId = str_replace("]", "", str_replace("[", "", $match[1]));
						if(isSet($messages[$messId])) $content = str_replace("AJXP_MESSAGE[$messId]", $messages[$messId], $content);
					}
				}
				echo $content;
                exit(1);
            }
        }
        $filePath = AJXP_INSTALL_PATH."/plugins/access.".$data["DRIVER"]."/class.".$className.".php";
        if(!is_file($filePath)){
                die("Warning, cannot find driver for conf storage! ($className, $filePath)");
        }
        require_once($filePath);
        $driver = new $className($data["PLUGIN_ID"], $data["BASE_DIR"]);
        $driver->loadManifest();
        if($driver->hasMixin("credentials_consumer") && isSet($data["SAFE_USER"]) && isSet($data["SAFE_PASS"])){
        	// FORCE SESSION MODE
        	AJXP_Safe::getInstance()->forceSessionCredentialsUsage();
        	AJXP_Safe::storeCredentials($data["SAFE_USER"], $data["SAFE_PASS"]);
        }
        $driver->init($data["REPOSITORY"], $data["OPTIONS"]);
        $driver->initRepository();
        ConfService::tmpReplaceRepository($data["REPOSITORY"]);
        // Increment counter
        $hash = md5(serialize($data));
        require_once(AJXP_BIN_FOLDER."/class.PublicletCounter.php");
        PublicletCounter::increment($hash);
        // Now call switchAction
        //@todo : switchAction should not be hard coded here!!!
        // Re-encode file-path as it will be decoded by the action.
        try{
	        $driver->switchAction($data["ACTION"], array("file"=>SystemTextEncoding::toUTF8($data["FILE_PATH"])), "");
        }catch (Exception $e){
        	die($e->getMessage());
        }
    }

    function createSharedRepository($httpVars, $repository, $accessDriver){
		// ERRORS
		// 100 : missing args
		// 101 : repository label already exists
		// 102 : user already exists
		// 103 : current user is not allowed to share
		// SUCCESS
		// 200

		if(!isSet($httpVars["repo_label"]) || $httpVars["repo_label"] == ""
			||  !isSet($httpVars["repo_rights"]) || $httpVars["repo_rights"] == ""
			||  !isSet($httpVars["shared_user"]) || $httpVars["shared_user"] == ""){
			return 100;
		}
		$loggedUser = AuthService::getLoggedUser();
		$actRights = $loggedUser->getSpecificActionsRights($repository->id);
		if(isSet($actRights["share"]) && $actRights["share"] === false){
			return 103;
		}
		$dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
		$userName = AJXP_Utils::decodeSecureMagic($httpVars["shared_user"], AJXP_SANITIZE_ALPHANUM);
		$label = AJXP_Utils::decodeSecureMagic($httpVars["repo_label"]);
		$rights = $httpVars["repo_rights"];
		if($rights != "r" && $rights != "w" && $rights != "rw") return 100;
		// CHECK USER & REPO DOES NOT ALREADY EXISTS
		$repos = ConfService::getRepositoriesList();
		foreach ($repos as $obj){
			if($obj->getDisplay() == $label){
				return 101;
			}
		}
		$confDriver = ConfService::getConfStorageImpl();
		if(AuthService::userExists($userName)){
			// check that it's a child user
			$userObject = $confDriver->createUserObject($userName);
			if( ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING") !== true && ( !$userObject->hasParent() || $userObject->getParent() != $loggedUser->id ) ){
				return 102;
			}
		}else{
			if(!isSet($httpVars["shared_pass"]) || $httpVars["shared_pass"] == "") return 100;
			AuthService::createUser($userName, md5($httpVars["shared_pass"]));
			$userObject = $confDriver->createUserObject($userName);
			$userObject->clearRights();
			$userObject->setParent($loggedUser->id);
		}

		// CREATE SHARED OPTIONS
        $options = $accessDriver->makeSharedRepositoryOptions($httpVars, $repository);
        $customData = array();
        foreach($httpVars as $key => $value){
            if(substr($key, 0, strlen("PLUGINS_DATA_")) == "PLUGINS_DATA_"){
                $customData[substr($key, strlen("PLUGINS_DATA_"))] = $value;
            }
        }
        if(count($customData)){
            $options["PLUGINS_DATA"] = $customData;
        }
		$newRepo = $repository->createSharedChild(
			$label,
			$options,
			$repository->id,
			$loggedUser->id,
			$userName
		);
		ConfService::addRepository($newRepo);

		// CREATE USER WITH NEW REPO RIGHTS
		$userObject->setRight($newRepo->getUniqueId(), $rights);
		$userObject->setSpecificActionRight($newRepo->getUniqueId(), "share", false);
		$userObject->save();

    	return 200;
    }

}
