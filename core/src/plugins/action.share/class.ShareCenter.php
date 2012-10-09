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

require_once("class.PublicletCounter.php");

class ShareCenter extends AJXP_Plugin{

    /**
     * @var AbstractAccessDriver
     */
    private $accessDriver;
    /**
     * @var Repository
     */
    private $repository;
    private $urlBase;
    private $baseProtocol;

    /**
     * @var MetaStoreProvider
     */
    private $metaStore;

    /**
     * @var MetaWatchRegister
     */
    private $watcher = false;

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
					if($loggedUser != null && AuthService::isReservedUserId($loggedUser->getId())){
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

    function init($options){
        parent::init($options);
        $pServ = AJXP_PluginsService::getInstance();
        $aPlugs = $pServ->getActivePlugins();
        $accessPlugs = $pServ->getPluginsByType("access");
        $this->repository = ConfService::getRepository();
        foreach($accessPlugs as $pId => $plug){
            if(array_key_exists("access.".$pId, $aPlugs) && $aPlugs["access.".$pId] === true){
                $this->accessDriver = $plug;
                if(!isSet($this->accessDriver->repository)){
                    $this->accessDriver->init($this->repository);
                    $this->accessDriver->initRepository();
                    $wrapperData = $this->accessDriver->detectStreamWrapper(true);
                }else{
                    $wrapperData = $this->accessDriver->detectStreamWrapper(false);
                }
                $this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
                $this->baseProtocol = $wrapperData["protocol"];
            }
        }
        $this->metaStore = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if($this->metaStore !== false){
            $this->metaStore->initMeta($this->accessDriver);
            if(array_key_exists("meta.watch", AJXP_PluginsService::getInstance()->getActivePlugins())){
                $this->watcher = AJXP_PluginsService::getInstance()->getPluginById("meta.watch");
            }
        }
    }

    function switchAction($action, $httpVars, $fileVars){

        if(!isSet($this->accessDriver)){
            throw new Exception("Cannot find access driver!");
        }


        if($this->accessDriver->getId() == "access.demo"){
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
					$result = $this->createSharedRepository($httpVars, $this->repository, $this->accessDriver);
					print($result);
            	}else if($subAction == "list_shared_users"){
            		header("Content-type:text/html");
            		if(!ConfService::getAuthDriverImpl()->usersEditable()){
            			break;
            		}
            		$loggedUser = AuthService::getLoggedUser();
                    $crtValue = $httpVars["value"];
                    if(!empty($crtValue)) $regexp = '^'.preg_quote($crtValue);
                    else $regexp = null;
                    $limit = min($this->pluginConf["SHARED_USERS_LIST_LIMIT"], 20);
                    $allUsers = AuthService::listUsers("/", $regexp, 0, $limit, false);
                    $allGroups = AuthService::listChildrenGroups("/");
                    $users = "";
                    $index = 0;
                    if($regexp != null && !count($allUsers)){
                        $users .= "<li class='complete_user_entry_temp' data-temporary='true' data-label='$crtValue'><span class='user_entry_label'>$crtValue (create user)</span></li>";
                    }
                    if(count($allGroups)){
                        if($regexp == null) $users .= "<li class='complete_group_entry' data-group='/' data-label='My Group'><span class='user_entry_label'>My Group</span></li>";
                        foreach($allGroups as $groupId => $groupLabel){
                            if($regexp == null ||  preg_match("/$regexp/i", $groupLabel)){
                                $users .= "<li class='complete_group_entry' data-group='$groupId' data-label='$groupLabel'><span class='user_entry_label'>".$groupLabel."</span></li>";
                            }
                        }
                    }
                    foreach ($allUsers as $userId => $userObject){
                        if( ( !$userObject->hasParent() &&  ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING", "conf")) || $userObject->getParent() == $loggedUser->getId() ){
                            if($regexp != null && !preg_match("/$regexp/i", $userId)) continue;
            				$users .= "<li class='complete_user_entry' data-label='$userId'><span class='user_entry_label'>".$userId."</span></li>";
                            $index ++;
                        }
                        if($index == $limit) break;
            		}
            		if(strlen($users)) {
            			print("<ul>".$users."</ul>");
            		}
            	}else{
					$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                    if(!isSet($httpVars["downloadlimit"])){
                        $httpVars["downloadlimit"] = 0;
                    }
	                $data = $this->accessDriver->makePublicletOptions($file, $httpVars["password"], $httpVars["expiration"], $httpVars["downloadlimit"], $this->repository);
                    $customData = array();
                    foreach($httpVars as $key => $value){
                        if(substr($key, 0, strlen("PLUGINS_DATA_")) == "PLUGINS_DATA_"){
                            $customData[substr($key, strlen("PLUGINS_DATA_"))] = $value;
                        }
                    }
                    if(count($customData)){
                        $data["PLUGINS_DATA"] = $customData;
                    }
                    $url = $this->writePubliclet($data, $this->accessDriver, $this->repository);
                    if($this->metaStore != null){
                        $ar = explode(".", basename($url));
                        $this->metaStore->setMetadata(
                            new AJXP_Node($this->urlBase.$file),
                            "ajxp_shared",
                            array("element"     => array_shift($ar)),
                            true,
                            AJXP_METADATA_SCOPE_REPOSITORY
                        );
                    }
                    $hash = md5(serialize($data));
	                header("Content-type:text/plain");
	                echo $url;
            	}
            break;

            case "toggle_link_watch":

                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $watchValue = $httpVars["set_watch"] == "true" ? true : false;
                $node = new AJXP_Node($this->urlBase.$file);
                $metadata = $this->metaStore->retrieveMetadata(
                    $node,
                    "ajxp_shared",
                    true,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                $elementId = $metadata["element"];

                if($this->watcher !== false){
                    if($watchValue){
                        $this->watcher->setWatchOnFolder(
                            $node,
                            AuthService::getLoggedUser()->getId(),
                            MetaWatchRegister::$META_WATCH_USERS,
                            array($elementId)
                        );
                    }else{
                        $this->watcher->removeWatchFromFolder(
                            $node,
                            AuthService::getLoggedUser()->getId(),
                            true
                        );
                    }
                }
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage("Successfully updated watch status", null);
                AJXP_XMLWriter::close();

            break;

            case "load_shared_element_data":

                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $elementType = $httpVars["element_type"];
                $messages = ConfService::getMessages();
                $node = new AJXP_Node($this->urlBase.$file);

                if($this->metaStore != null){
                    $metadata = $this->metaStore->retrieveMetadata(
                        $node,
                        "ajxp_shared",
                        true,
                        AJXP_METADATA_SCOPE_REPOSITORY
                    );
                }
                $elementWatch = false;
                if(count($metadata)){
                    header("Content-type:application/json");

                    if($elementType == "file"){
                        $pData = self::loadPublicletData($metadata["element"]);
                        if($pData["OWNER_ID"] != AuthService::getLoggedUser()->getId()){
                            throw new Exception("You are not allowed to access this data");
                        }
                        if(isSet($metadata["short_form_url"])){
                            $link = $metadata["short_form_url"];
                        }else{
                            $link = $this->buildPublicletLink($metadata["element"]);
                        }
                        if($this->watcher != false){
                            $elementWatch = $this->watcher->hasWatchOnNode(
                                $node,
                                AuthService::getLoggedUser()->getId(),
                                MetaWatchRegister::$META_WATCH_USERS
                            );
                        }
                        $jsonData = array(
                             "publiclet_link"   => $link,
                             "download_counter" => PublicletCounter::getCount($metadata["element"]),
                             "download_limit"   => $pData["DOWNLOAD_LIMIT"],
                             "expire_time"      => ($pData["EXPIRE_TIME"]!=0?date($messages["date_format"], $pData["EXPIRE_TIME"]):0),
                             "has_password"     => (!empty($pData["PASSWORD"])),
                             "element_watch"    => $elementWatch
                             );
                    }else if( $elementType == "repository"){
                        $repoId = $metadata["element"];
                        $repo = ConfService::getRepositoryById($repoId);
                        if($repo->getOwner() != AuthService::getLoggedUser()->getId()){
                            throw new Exception("You are not allowed to access this data");
                        }
                        if($this->watcher != false){
                            $elementWatch = $this->watcher->hasWatchOnNode(
                                new AJXP_Node($this->baseProtocol."://".$repoId."/"),
                                AuthService::getLoggedUser()->getId(),
                                MetaWatchRegister::$META_WATCH_NAMESPACE
                            );
                        }
                        $sharedEntries = $this->computeSharedRepositoryAccessRights($repoId, true, $this->urlBase.$file);

                        $jsonData = array(
                            "repositoryId"  => $repoId,
                            "label"         => $repo->getDisplay(),
                            "entries"       => $sharedEntries,
                            "element_watch" => $elementWatch
                        );
                    }
                    echo json_encode($jsonData);
                }


            break;

            case "unshare":
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $ajxpNode = new AJXP_Node($this->urlBase.$file);
                $metadata = $this->metaStore->retrieveMetadata(
                    $ajxpNode,
                    "ajxp_shared",
                    true,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                if(count($metadata)){
                    self::deleteSharedElement($httpVars["element_type"], $metadata["element"], AuthService::getLoggedUser());
                    $this->metaStore->removeMetadata($ajxpNode, "ajxp_shared", true);
                }
            break;

            case "reset_counter":
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $metadata = $this->metaStore->retrieveMetadata(
                    new AJXP_Node($this->urlBase.$file),
                    "ajxp_shared",
                    true,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                if(isSet($metadata["element"])){
                    PublicletCounter::reset($metadata["element"]);
                }
            break;

            default:
            break;
        }


    }


    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    function nodeSharedMetadata(&$ajxpNode){
        if($this->accessDriver->getId() == "access.imap") return;
        if($this->metaStore  == null) return;
        $metadata = $this->metaStore->retrieveMetadata($ajxpNode, "ajxp_shared", true);
        if(count($metadata)){
            if(!self::sharedElementExists($ajxpNode->isLeaf()?"file":"repository", $metadata["element"], AuthService::getLoggedUser())){
                $this->metaStore->removeMetadata($ajxpNode, "ajxp_shared", true);
                return;
            }
            $ajxpNode->mergeMetadata(array(
                     "ajxp_shared"      => "true",
                     "overlay_icon"     => "shared.png"
                ), true);
        }
    }

	/**
	 *
	 * Hooked to node.change, this will update the index
	 * if $oldNode = null => create node $newNode
	 * if $newNode = null => delete node $oldNode
	 * Else copy or move oldNode to newNode.
	 *
	 * @param AJXP_Node $oldNode
	 */
	public function updateNodeSharedData($oldNode/*, $newNode = null, $copy = false*/){
        if($this->accessDriver->getId() == "access.imap") return;
        if($this->metaStore  == null) return;
        if($oldNode == null) return;
        $metadata = $this->metaStore->retrieveMetadata($oldNode, "ajxp_shared", true);
        if(count($metadata) && !empty($metadata["element"])){
            // TODO
            // Make sure node info is loaded, to check if it's a dir or a file.
            // Maybe could be directly embedded in metadata, to avoid having to load here.
            $oldNode->loadNodeInfo();
            try{
                self::deleteSharedElement(
                    ($oldNode->isLeaf()?"file":"repository"),
                    $metadata["element"],
                    AuthService::getLoggedUser()
                );
                $this->metaStore->removeMetadata($oldNode, "ajxp_shared", true);
            }catch(Exception $e){
                AJXP_Logger::logAction("ERROR : ".$e->getMessage(), $e->getTrace() );
            }
        }
    }

    /** Cypher the publiclet object data and write to disk.
     * @param Array $data The publiclet data array to write
                     The data array must have the following keys:
                     - DRIVER      The driver used to get the file's content
                     - OPTIONS     The driver options to be successfully constructed (usually, the user and password)
                     - FILE_PATH   The path to the file's content
                     - PASSWORD    If set, the written publiclet will ask for this password before sending the content
                     - ACTION      If set, action to perform
                     - USER        If set, the AJXP user
                     - EXPIRE_TIME If set, the publiclet will deny downloading after this time, and probably self destruct.
     *               - AUTHOR_WATCH If set, will post notifications for the publiclet author each time the file is loaded
     * @param AbstractAccessDriver $accessDriver
     * @param Repository $repository
     * @return the URL to the downloaded file
    */
    function writePubliclet(&$data, $accessDriver, $repository)
    {
    	$downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
    	if(!is_dir($downloadFolder)){
    		return "ERROR : Public URL folder does not exist!";
    	}
    	if(!function_exists("mcrypt_create_iv")){
    		return "ERROR : MCrypt must be installed to use publiclets!";
    	}
        $this->initPublicFolder($downloadFolder);
        $data["PLUGIN_ID"] = $accessDriver->getId();
        $data["BASE_DIR"] = $accessDriver->getBaseDir();
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
        @chmod($downloadFolder."/".$hash.".php", 0755);
        PublicletCounter::reset($hash);
        return $this->buildPublicletLink($hash);
    }

    function buildPublicDlURL(){
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $dlURL = ConfService::getCoreConf("PUBLIC_DOWNLOAD_URL");
        $langSuffix = "?lang=".ConfService::getLanguage();
        if($dlURL != ""){
        	return rtrim($dlURL, "/");
        }else{
	        $fullUrl = AJXP_Utils::detectServerURL() . dirname($_SERVER['REQUEST_URI']);
	        return str_replace("\\", "/", $fullUrl.rtrim(str_replace(AJXP_INSTALL_PATH, "", $downloadFolder), "/"));
        }
    }

    function buildPublicletLink($hash){
        return $this->buildPublicDlURL()."/".$hash.".php?lang=".ConfService::getLanguage();
    }

    function initPublicFolder($downloadFolder){
        if(is_file($downloadFolder."/down.png")){
            return;
        }
        $language = ConfService::getLanguage();
        $pDir = dirname(__FILE__);
        $messages = array();
        if(is_file($pDir."/res/i18n/".$language.".php")){
            include($pDir."/res/i18n/".$language.".php");
            if(isSet($mess)){
                $messages = $mess;
            }
        }else{
            include($pDir."/res/i18n/en.php");
        }
        $sTitle = sprintf($messages[1], ConfService::getCoreConf("APPLICATION_TITLE"));
        $sLegend = $messages[20];

        @copy($pDir."/res/down.png", $downloadFolder."/down.png");
        @copy($pDir."/res/button_cancel.png", $downloadFolder."/button_cancel.png");
        @copy($pDir."/res/drive_harddisk.png", $downloadFolder."/drive_harddisk.png");
        @copy(AJXP_INSTALL_PATH."/server/index.html", $downloadFolder."/index.html");
        file_put_contents($downloadFolder."/.htaccess", "ErrorDocument 404 ".$this->buildPublicDlURL()."/404.html\n<Files \".ajxp_*\">\ndeny from all\n</Files>");
        $content404 = file_get_contents($pDir."/res/404.html");
        $content404 = str_replace(array("AJXP_MESSAGE_TITLE", "AJXP_MESSAGE_LEGEND"), array($sTitle, $sLegend), $content404);
        file_put_contents($downloadFolder."/404.html", $content404);

    }

    /**
     * @static
     * @param Array $data
     * @return void
     */
    static function loadPubliclet($data)
    {
        // create driver from $data
        $className = $data["DRIVER"]."AccessDriver";
        $hash = md5(serialize($data));
        if ( ($data["EXPIRE_TIME"] && time() > $data["EXPIRE_TIME"]) || 
            ($data["DOWNLOAD_LIMIT"] && $data["DOWNLOAD_LIMIT"]> 0 && $data["DOWNLOAD_LIMIT"] <= PublicletCounter::getCount($hash)) )
        {
            // Remove the publiclet, it's done
            if (strstr(realpath($_SERVER["SCRIPT_FILENAME"]),realpath(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"))) !== FALSE){
		        PublicletCounter::delete($hash);
                unlink($_SERVER["SCRIPT_FILENAME"]);
            }

            echo "Link is expired, sorry.";
            exit();
        }
        // Load language messages
        $language = "en";
        if(isSet($_GET["lang"])){
            $language = $_GET["lang"];
        }
        $messages = array();
        if(is_file(dirname(__FILE__)."/res/i18n/".$language.".php")){
            include(dirname(__FILE__)."/res/i18n/".$language.".php");
            if(isSet($mess)) $messages = $mess;
        }else{
            include(dirname(__FILE__)."/res/i18n/en.php");
        }

        $AJXP_LINK_HAS_PASSWORD = false;
        $AJXP_LINK_BASENAME = SystemTextEncoding::toUTF8(basename($data["FILE_PATH"]));

        // Check password
        if (strlen($data["PASSWORD"]))
        {
            if (!isSet($_POST['password']) || ($_POST['password'] != $data["PASSWORD"]))
            {
                $AJXP_LINK_HAS_PASSWORD = true;
                $AJXP_LINK_WRONG_PASSWORD = (isSet($_POST['password']) && ($_POST['password'] != $data["PASSWORD"]));
                include (AJXP_INSTALL_PATH."/plugins/action.share/res/public_links.php");
                return;
            }
        }else{
            if (!isSet($_GET["dl"])){
                include (AJXP_INSTALL_PATH."/plugins/action.share/res/public_links.php");
                return;
            }
        }
        $filePath = AJXP_INSTALL_PATH."/plugins/access.".$data["DRIVER"]."/class.".$className.".php";
        if(!is_file($filePath)){
                die("Warning, cannot find driver for conf storage! ($className, $filePath)");
        }
        require_once($filePath);
        $driver = new $className($data["PLUGIN_ID"], $data["BASE_DIR"]);
        $driver->loadManifest();

        $hash = md5(serialize($data));
        PublicletCounter::increment($hash);

        //AuthService::logUser($data["OWNER_ID"], "", true);
        AuthService::logTemporaryUser($data["OWNER_ID"], $hash);
        if($driver->hasMixin("credentials_consumer") && isSet($data["SAFE_USER"]) && isSet($data["SAFE_PASS"])){
            // FORCE SESSION MODE
            AJXP_Safe::getInstance()->forceSessionCredentialsUsage();
            AJXP_Safe::storeCredentials($data["SAFE_USER"], $data["SAFE_PASS"]);
        }

        $repoObject = $data["REPOSITORY"];
        ConfService::switchRootDir($repoObject->getId());
        ConfService::loadRepositoryDriver();
        ConfService::initActivePlugins();
        try{
            $params = array("file" => SystemTextEncoding::toUTF8($data["FILE_PATH"]));
            if(isSet($data["PLUGINS_DATA"])){
                $params["PLUGINS_DATA"] = $data["PLUGINS_DATA"];
            }
            AJXP_Controller::findActionAndApply($data["ACTION"], $params, null);
            register_shutdown_function(array("AuthService", "clearTemporaryUser"), $hash);
        }catch (Exception $e){
            AuthService::clearTemporaryUser($hash);
        	die($e->getMessage());
        }
    }

    /**
     * @param String $repoId
     * @return array
     */
    function computeSharedRepositoryAccessRights($repoId, $mixUsersAndGroups, $currentFileUrl){

        $loggedUser = AuthService::getLoggedUser();
        $users = AuthService::listUsers();
        $groups = AuthService::listChildrenGroups("/");
        $sharedEntries = array();
        if(!$mixUsersAndGroups){
            $sharedGroups = array();
        }

        foreach($groups as $gId => $gLabel){
            $r = AuthService::getRole("AJXP_GRP_".AuthService::filterBaseGroup($gId));
            if($r != null){
                $right = $r->getAcl($repoId);
                if(!empty($right)){
                    $entry = array(
                        "ID"    => $gId,
                        "TYPE"  => "group",
                        "LABEL" => $gLabel,
                        "RIGHT" => $right);
                    if(!$mixUsersAndGroups){
                        $sharedGroups[$gId] = $entry;
                    }else{
                        $sharedEntries[] = $entry;
                    }
                }
            }
        }

        foreach ($users as $userId => $userObject) {
            if($userObject->getId() == $loggedUser->getId()) continue;
            $ri = $userObject->personalRole->getAcl($repoId);
            if(!empty($ri)){
                $entry =  array(
                    "ID"    => $userId,
                    "TYPE"  => $userObject->hasParent()?"tmp_user":"user",
                    "LABEL" => $userId,
                    "RIGHT" => $userObject->personalRole->getAcl($repoId)
                );
                if($this->watcher !== false){
                    $entry["WATCH"] = $this->watcher->hasWatchOnNode(
                        new AJXP_Node($currentFileUrl),
                        $userId,
                        MetaWatchRegister::$META_WATCH_USERS
                    );
                }
                if(!$mixUsersAndGroups){
                    $sharedEntries[$userId] = $entry;
                }else{
                    $sharedEntries[] = $entry;
                }
            }
        }

        if(!$mixUsersAndGroups){
            return array("USERS" => $sharedEntries, "GROUPS" => $sharedGroups);
        }
        return $sharedEntries;

    }

    /**
     * @param Array $httpVars
     * @param Repository $repository
     * @param AbstractAccessDriver $accessDriver
     * @return int
     * @throws Exception
     */
    function createSharedRepository($httpVars, $repository, $accessDriver){
		// ERRORS
		// 100 : missing args
		// 101 : repository label already exists
		// 102 : user already exists
		// 103 : current user is not allowed to share
		// SUCCESS
		// 200

		if(!isSet($httpVars["repo_label"]) || $httpVars["repo_label"] == ""){
			return 100;
		}
		$loggedUser = AuthService::getLoggedUser();
		$actRights = $loggedUser->mergedRole->listActionsStatesFor($repository);
		if(isSet($actRights["share"]) && $actRights["share"] === false){
			return 103;
		}
        $users = array();
        $uRights = array();
        $uPasses = array();
        $groups = array();

        $index = 0;
        while(isSet($httpVars["user_".$index])){
            $eType = $httpVars["entry_type_".$index];
            $rightString = ($httpVars["right_read_".$index]=="true"?"r":"").($httpVars["right_write_".$index]=="true"?"w":"");
            if($this->watcher !== false) $uWatch = $httpVars["right_watch_".$index] == "true" ? true : false;
            if(empty($rightString)) {
                $index++;
                continue;
            }
            if($eType == "user"){
                $u = AJXP_Utils::decodeSecureMagic($httpVars["user_".$index], AJXP_SANITIZE_ALPHANUM);
                if(!AuthService::userExists($u) && !isSet($httpVars["user_pass_".$index])){
                    return 100;
                }else if(AuthService::userExists($u) && isSet($httpVars["user_pass_".$index])){
                    throw new Exception("User $u already exists, please choose another name.");
                }
                if(!AuthService::userExists($u) && !empty($this->pluginConf["SHARED_USERS_TMP_PREFIX"])
                && strpos($u, $this->pluginConf["SHARED_USERS_TMP_PREFIX"])!==0 ){
                    $u = $this->pluginConf["SHARED_USERS_TMP_PREFIX"] . $u;
                }
                $users[] = $u;
            }else{
                $u = AJXP_Utils::decodeSecureMagic($httpVars["user_".$index]);
                $groups[] = $u;
            }
            $uRights[$u] = $rightString;
            $uPasses[$u] = isSet($httpVars["user_pass_".$index])?$httpVars["user_pass_".$index]:"";
            if($this->watcher !== false){
                $uWatches[$u] = $uWatch;
            }
            $index ++;
        }

		$label = AJXP_Utils::decodeSecureMagic($httpVars["repo_label"]);
        if(isSet($httpVars["repository_id"])){
            $editingRepo = ConfService::getRepositoryById($httpVars["repository_id"]);
        }

		// CHECK USER & REPO DOES NOT ALREADY EXISTS
		$repos = ConfService::getRepositoriesList();
		foreach ($repos as $obj){
			if($obj->getDisplay() == $label && (!isSet($editingRepo) || $editingRepo != $obj)){
				return 101;
			}
		}
		$confDriver = ConfService::getConfStorageImpl();
        foreach($users as $userName){
            if(AuthService::userExists($userName)){
                // check that it's a child user
                $userObject = $confDriver->createUserObject($userName);
                if( ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING", "conf") != true && ( !$userObject->hasParent() || $userObject->getParent() != $loggedUser->id ) ){
                    return 102;
                }
            }else{
                if(AuthService::isReservedUserId($userName)){
                    return 102;
                }
                if(!isSet($httpVars["shared_pass"]) || $httpVars["shared_pass"] == "") return 100;
            }
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
        if(isSet($editingRepo)){
            $newRepo = $editingRepo;
            $newRepo->setDisplay($label);
            $newRepo->options = array_merge($newRepo->options, $options);
            ConfService::replaceRepository($httpVars["repository_id"], $newRepo);
        }else{
            if($repository->getOption("META_SOURCES")){
                $options["META_SOURCES"] = $repository->getOption("META_SOURCES");
                foreach($options["META_SOURCES"] as $index => $data){
                    if(isSet($data["USE_SESSION_CREDENTIALS"]) && $data["USE_SESSION_CREDENTIALS"] === true){
                        $options["META_SOURCES"][$index]["ENCODED_CREDENTIALS"] = AJXP_Safe::getEncodedCredentialString();
                    }
                }
            }
            $newRepo = $repository->createSharedChild(
                $label,
                $options,
                $repository->id,
                $loggedUser->id,
                null
            );
            $gPath = $loggedUser->getGroupPath();
            if(!empty($gPath)){
                $newRepo->setGroupPath($gPath);
            }
            ConfService::addRepository($newRepo);
        }

        $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);

        if(isSet($editingRepo)){

            $currentRights = $this->computeSharedRepositoryAccessRights($httpVars["repository_id"], false, $this->urlBase.$file);
            $originalUsers = array_keys($currentRights["USERS"]);
            $removeUsers = array_diff($originalUsers, $users);
            if(count($removeUsers)){
                foreach($removeUsers as $user){
                    if(AuthService::userExists($user)){
                        $userObject = $confDriver->createUserObject($user);
                        $userObject->personalRole->setAcl($newRepo->getUniqueId(), "");
                        $userObject->save("superuser");
                    }
                }
            }
            $originalGroups = array_keys($currentRights["GROUPS"]);
            $removeGroups = array_diff($originalGroups, $groups);
            if(count($removeGroups)){
                foreach($removeGroups as $groupId){
                    $role = AuthService::getRole("AJXP_GRP_".AuthService::filterBaseGroup($groupId));
                    if($role !== false){
                        $role->setAcl($newRepo->getUniqueId(), "");
                        AuthService::updateRole($role);
                    }
                }
            }
        }

        foreach($users as $userName){
            if(AuthService::userExists($userName)){
                // check that it's a child user
                $userObject = $confDriver->createUserObject($userName);
            }else{
                if(ConfService::getAuthDriverImpl()->getOption("TRANSMIT_CLEAR_PASS")){
                    $pass = $uPasses[$userName];
                }else{
                    $pass = md5($uPasses[$userName]);
                }
                AuthService::createUser($userName, $pass);
                $userObject = $confDriver->createUserObject($userName);
                $userObject->personalRole->clearAcls();
                $userObject->setParent($loggedUser->id);
                $userObject->setGroupPath($loggedUser->getGroupPath());
                AJXP_Controller::applyHook("user.after_create", array($userObject));
            }
            // CREATE USER WITH NEW REPO RIGHTS
            $userObject->personalRole->setAcl($newRepo->getUniqueId(), $uRights[$userName]);
            $userObject->setProfile("shared");
            $userObject->save("superuser");
            if($this->watcher !== false){
                // Register a watch on the current folder for shared user
                if($uWatches[$userName] == "true"){
                    $this->watcher->setWatchOnFolder(
                        new AJXP_Node($this->urlBase.$file),
                        $userName,
                        MetaWatchRegister::$META_WATCH_USERS,
                        array(AuthService::getLoggedUser()->getId())
                    );
                }else{
                    $this->watcher->removeWatchFromFolder(
                        new AJXP_Node($this->urlBase.$file),
                        $userName,
                        true
                    );
                }
            }
        }

        if($this->watcher !== false){
            // Register a watch on the new repository root for current user
            if($httpVars["self_watch_folder"] == "true"){
                $this->watcher->setWatchOnFolder(
                    new AJXP_Node($this->baseProtocol."://".$newRepo->getUniqueId()."/"),
                    AuthService::getLoggedUser()->getId(),
                    MetaWatchRegister::$META_WATCH_BOTH);
            }else{
                $this->watcher->removeWatchFromFolder(
                    new AJXP_Node($this->baseProtocol."://".$newRepo->getUniqueId()."/"),
                    AuthService::getLoggedUser()->getId());
            }
        }

        foreach($groups as $group){
            $grRole = AuthService::getRole("AJXP_GRP_".AuthService::filterBaseGroup($group), true);
            $grRole->setAcl($newRepo->getUniqueId(), $uRights[$group]);
            AuthService::updateRole($grRole);
        }

        // METADATA
        if(!isSet($editingRepo) && $this->metaStore != null){
            $this->metaStore->setMetadata(
                new AJXP_Node($this->urlBase.$file),
                "ajxp_shared",
                array("element" => $newRepo->getUniqueId()),
                true,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        }

    	return 200;
    }


    /**
     * @static
     * @param String $type
     * @param String $element
     * @param AbstractAjxpUser $loggedUser
     * @throws Exception
     */
    public static function deleteSharedElement($type, $element, $loggedUser){
        $mess = ConfService::getMessages();
        AJXP_Logger::debug($type."-".$element);
        if($type == "repository"){
            $repo = ConfService::getRepositoryById($element);
            if(!$repo->hasOwner() || $repo->getOwner() != $loggedUser->getId()){
                throw new Exception($mess["ajxp_shared.12"]);
            }else{
                $res = ConfService::deleteRepository($element);
                if($res == -1){
                    throw new Exception($mess["ajxp_conf.51"]);
                }
            }
        }else if( $type == "user" ){
            $confDriver = ConfService::getConfStorageImpl();
            $object = $confDriver->createUserObject($element);
            if(!$object->hasParent() || $object->getParent() != $loggedUser->getId()){
                throw new Exception($mess["ajxp_shared.12"]);
            }else{
                AuthService::deleteUser($element);
            }
        }else if( $type == "file" ){
            $publicletData = self::loadPublicletData($element);
            if(isSet($publicletData["OWNER_ID"]) && $publicletData["OWNER_ID"] == $loggedUser->getId()){
                PublicletCounter::delete($element);
                unlink($publicletData["PUBLICLET_PATH"]);
            }else{
                throw new Exception($mess["ajxp_shared.12"]);
            }
        }
    }

    public static function sharedElementExists($type, $element, $loggedUser){
        if($type == "repository"){
            return (ConfService::getRepositoryById($element) != null);
        }else if($type == "file"){
            $dlFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
            return is_file($dlFolder."/".$element.".php");
        }
    }


    public static function loadPublicletData($id){
        $dlFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $file = $dlFolder."/".$id.".php";
        if(!is_file($file)) return array();
        $lines = file($file);
        $inputData = '';
        $code = $lines[3] . $lines[4] . $lines[5];
        eval($code);
        $dataModified = (md5($inputData) != $id);
        $publicletData = unserialize($inputData);
        $publicletData["SECURITY_MODIFIED"] = $dataModified;
        $publicletData["DOWNLOAD_COUNT"] = PublicletCounter::getCount($id);
        $publicletData["PUBLICLET_PATH"] = $file;
        return $publicletData;
    }

}
