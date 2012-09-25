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
 * @class AbstractConfDriver
 * Abstract representation of a conf driver. Must be implemented by the "conf" plugin
 */
abstract class AbstractConfDriver extends AJXP_Plugin {
		
	var $options;
	var $driverType = "conf";


    public function init($options){
        parent::init($options);

        // BACKWARD COMPATIBILIY PREVIOUS CONFIG VIA OPTIONS
        if(isSet($options["CUSTOM_DATA"])){
            $custom = $options["CUSTOM_DATA"];
            $serverSettings = $this->xPath->query('//server_settings')->item(0);
            foreach($custom as $key => $value){
                $n = $this->manifestDoc->createElement("param");
                $n->setAttribute("name", $key);
                $n->setAttribute("label", $value);
                $n->setAttribute("description", $value);
                $n->setAttribute("type", "string");
                $n->setAttribute("scope", "user");
                $n->setAttribute("expose", "true");
                $serverSettings->appendChild($n);
            }
            $this->reloadXPath();
        }
    }


	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
        if($contribNode->nodeName != "actions") return;

        // WEBDAV ACTION
		if(!ConfService::getCoreConf("WEBDAV_ENABLE")){
			unset($this->actions["webdav_preferences"]);
			$actionXpath=new DOMXPath($contribNode->ownerDocument);
			$publicUrlNodeList = $actionXpath->query('action[@name="webdav_preferences"]', $contribNode);
			$publicUrlNode = $publicUrlNodeList->item(0);
			$contribNode->removeChild($publicUrlNode);			
		}

        // PERSONAL INFORMATIONS
        $hasExposed = false;
        $paramNodes = AJXP_PluginsService::searchAllManifests("//server_settings/param[contains(@scope,'user') and @expose='true']", "node", false, false, true);
        if(is_array($paramNodes) && count($paramNodes)) $hasExposed = true;

        if(!$hasExposed){
            unset($this->actions["custom_data_edit"]);
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="custom_data_edit"]', $contribNode);
            $publicUrlNode = $publicUrlNodeList->item(0);
            $contribNode->removeChild($publicUrlNode);
        }

        // CREATE A NEW REPOSITORY
		if(!ConfService::getCoreConf("USER_CREATE_REPOSITORY", "conf")){
			unset($this->actions["user_create_repository"]);
			$actionXpath=new DOMXPath($contribNode->ownerDocument);
			$publicUrlNodeList = $actionXpath->query('action[@name="user_create_repository"]', $contribNode);
			$publicUrlNode = $publicUrlNodeList->item(0);
			$contribNode->removeChild($publicUrlNode);
			unset($this->actions["user_delete_repository"]);
			$actionXpath=new DOMXPath($contribNode->ownerDocument);
			$publicUrlNodeList = $actionXpath->query('action[@name="user_delete_repository"]', $contribNode);
			$publicUrlNode = $publicUrlNodeList->item(0);
			$contribNode->removeChild($publicUrlNode);
		}

	}
	
	// NEW FUNCTIONS FOR  LOADING/SAVING PLUGINS CONFIGS
	/**
	 * Returns an array of options=>values merged from various sources (.inc.php, implementation source)
	 * @return Array
	 * @param String $pluginType
	 * @param String $pluginId
	 */
	function loadPluginConfig($pluginType, $pluginName){
		$options = array();
		if(is_file(AJXP_CONF_PATH."/conf.$pluginType.inc")){
			include AJXP_CONF_PATH."/conf.$pluginType.inc";
			if(!empty($DRIVER_CONF)){
				foreach($DRIVER_CONF as $key=>$value){
					$options[$key] = $value;
				}
				unset($DRIVER_CONF);
			}
		}
		if(is_file(AJXP_CONF_PATH."/conf.$pluginType.$pluginName.inc")){
			include AJXP_CONF_PATH."/conf.$pluginType.$pluginName.inc";
			if(!empty($DRIVER_CONF)){
				foreach($DRIVER_CONF as $key=>$value){
					$options[$key] = $value;
				}
				unset($DRIVER_CONF);
			}
		}
		$this->_loadPluginConfig($pluginType.".".$pluginName, $options);
		return $options;
	}

	abstract function _loadPluginConfig($pluginId, &$options);
	
	/**
	 * 
	 * @param String $pluginType
	 * @param String $pluginId
	 * @param String $configHash
	 */
	abstract function savePluginConfig($pluginId, $options);
	
	
	// SAVE / EDIT / CREATE / DELETE REPOSITORY
	/**
	 * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
	 * @return Array
	 */
	abstract function listRepositories();
	/**
	 * Retrieve a Repository given its unique ID.
	 *
	 * @param String $repositoryId
	 * @return Repository
	 */	
	abstract function getRepositoryById($repositoryId);
	/**
	 * Retrieve a Repository given its alias.
	 *
	 * @param String $repositorySlug
	 * @return Repository
	 */	
	abstract function getRepositoryByAlias($repositorySlug);
	/**
	 * Stores a repository, new or not.
	 *
	 * @param Repository $repositoryObject
	 * @param Boolean $update 
	 * @return -1 if failed
	 */	
	abstract function saveRepository($repositoryObject, $update = false);
	/**
	 * Delete a repository, given its unique ID.
	 *
	 * @param String $repositoryId
	 */
	abstract function deleteRepository($repositoryId);
		
	/**
	 * Must return an associative array of roleId => AjxpRole objects.
     * @param array $roleIds
     * @param boolean $excludeReserved,
     * @return array AjxpRole[]
     */
	abstract function listRoles($roleIds = array(), $excludeReserved = false);
	abstract function saveRoles($roles);

    /**
     * @abstract
     * @param AJXP_Role $role
     * @return void
     */
    abstract function updateRole($role);

    /**
     * @abstract
     * @param AJXP_Role|String $role
     * @return void
     */
    abstract function deleteRole($role);
	
	/**
	 * Specific queries
	 */
	abstract function countAdminUsers();

    /**
     * @abstract
     * @param array $context
     * @param String $fileName
     * @param String $ID
     * @return String $ID
     */
    abstract function saveBinary($context, $fileName, $ID = null);

    /**
     * @abstract
     * @param array $context
     * @param String $ID
     * @param Stream $outputStream
     * @return boolean
     */
    abstract function loadBinary($context, $ID, $outputStream = null);



	/**
	 * Instantiate a new AbstractAjxpUser
	 *
	 * @param String $userId
	 * @return AbstractAjxpUser
	 */
	function createUserObject($userId){
		$abstractUser = $this->instantiateAbstractUserImpl($userId);
		if(!$abstractUser->storageExists()){			
			AuthService::updateDefaultRights($abstractUser);
		}
        AuthService::updateAutoApplyRole($abstractUser);
        AuthService::updateAuthProvidedData($abstractUser);
		return $abstractUser;
	}

    /**
     * Function for deleting a user
     *
     * @param String $userId
     * @param Array $deletedSubUsers
     */
    abstract function deleteUser($userId, &$deletedSubUsers);


        /**
	 * Instantiate the right class
	 *
	 * @param AbstractAjxpUser $userId
	 */
	abstract function instantiateAbstractUserImpl($userId);
	
	abstract function getUserClassFileName();

    /**
     * @abstract
     * @param $userId
     * @return AbstractAjxpUser[]
     */
    abstract function getUserChildren($userId);

    /**
     * @abstract
     * @param string $repositoryId
     * @return array()
     */
    abstract function getUsersForRepository($repositoryId);


    /**
     * @param AbstractAjxpUser[] $flatUsersList
     * @param string $baseGroup
     * @param bool $fullTree
     * @return void
     */
    abstract function filterUsersByGroup(&$flatUsersList, $baseGroup = "/", $fullTree = false);

    /**
     * @param string $groupPath
     * @param string $groupLabel
     * @return mixed
     */
    abstract function createGroup($groupPath, $groupLabel);

    /**
     * @abstract
     * @param $groupPath
     * @return void
     */
    abstract function deleteGroup($groupPath);


    /**
     * @abstract
     * @param string $groupPath
     * @param string $groupLabel
     * @return void
     */
    abstract function relabelGroup($groupPath, $groupLabel);


        /**
     * @param string $baseGroup
     * @return string[]
     */
    abstract function getChildrenGroups($baseGroup = "/");

	function getOption($optionName){
		return (isSet($this->options[$optionName])?$this->options[$optionName]:"");	
	}

    /**
     * @param AbstractAjxpUser $userObject
     * @return array()
     */
    function getExposedPreferences($userObject){
        $stringPrefs = array("display","lang","diapo_autofit","sidebar_splitter_size","vertical_splitter_size","history/last_repository","pending_folder","thumb_size","plugins_preferences","upload_auto_send","upload_auto_close","upload_existing","action_bar_style", "force_default_repository");
        $jsonPrefs = array("ls_history","columns_size", "columns_visibility", "gui_preferences");
        $prefs = array();
        if( $userObject->getId()=="guest" && ConfService::getCoreConf("SAVE_GUEST_PREFERENCES", "conf") === false){
            return array();
        }
        foreach($stringPrefs as $pref){
            if(strstr($pref, "/")!==false){
                $parts = explode("/", $pref);
                $value = $userObject->getArrayPref($parts[0], $parts[1]);
                $pref = str_replace("/", "_", $pref);
            }else{
                $value = $userObject->getPref($pref);
            }
            $prefs[$pref] = array("value" => $value, "type" => "string" );
        }
        foreach ($jsonPrefs as $pref){
            $prefs[$pref] = array("value" => $userObject->getPref($pref), "type" => "json" );
        }

        $paramNodes = AJXP_PluginsService::searchAllManifests("//server_settings/param[contains(@scope,'user') and @expose='true']", "node", false, false, true);
        if(is_array($paramNodes) && count($paramNodes)){
            foreach($paramNodes as $xmlNode){
                if($xmlNode->getAttribute("expose") == "true"){
                    $parentNode = $xmlNode->parentNode->parentNode;
                    $pluginId = $parentNode->getAttribute("id");
                    if(empty($pluginId)){
                        $pluginId = $parentNode->nodeName.".".$parentNode->getAttribute("name");
                    }
                    $name = $xmlNode->getAttribute("name");
                    $value = $userObject->mergedRole->filterParameterValue($pluginId, $name, AJXP_REPO_SCOPE_ALL, "");
                    $prefs[$name] = array("value" => $value, "type" => "string", "pluginId" => $pluginId);
                }
            }
        }

        return $prefs;
    }
		
	function switchAction($action, $httpVars, $fileVars)
	{
		if(!isSet($this->actions[$action])) return;
		$xmlBuffer = "";
		foreach($httpVars as $getName=>$getValue){
			$$getName = AJXP_Utils::securePath($getValue);
		}
		if(isSet($dir) && $action != "upload") $dir = SystemTextEncoding::fromUTF8($dir);
		$mess = ConfService::getMessages();
		
		switch ($action){			
			//------------------------------------
			//	SWITCH THE ROOT REPOSITORY
			//------------------------------------	
			case "switch_repository":
			
				if(!isSet($repository_id))
				{
					break;
				}
				$dirList = ConfService::getRepositoriesList();
                /** @var $repository_id string */
                if(!isSet($dirList[$repository_id]))
				{
					$errorMessage = "Trying to switch to an unkown repository!";
					break;
				}
				ConfService::switchRootDir($repository_id);
				// Load try to init the driver now, to trigger an exception
				// if it's not loading right.
				ConfService::loadRepositoryDriver();
				if(AuthService::usersEnabled() && AuthService::getLoggedUser()!=null){
					$user = AuthService::getLoggedUser();
					$activeRepId = ConfService::getCurrentRootDirIndex();
					$user->setArrayPref("history", "last_repository", $activeRepId);
					$user->save("user");
				}
				//$logMessage = "Successfully Switched!";
				AJXP_Logger::logAction("Switch Repository", array("rep. id"=>$repository_id));
				
			break;	
									
			//------------------------------------
			//	BOOKMARK BAR
			//------------------------------------
			case "get_bookmarks":
				
				$bmUser = null;
				if(AuthService::usersEnabled() && AuthService::getLoggedUser() != null)
				{
					$bmUser = AuthService::getLoggedUser();
				}
				else if(!AuthService::usersEnabled())
				{
					$confStorage = ConfService::getConfStorageImpl();
					$bmUser = $confStorage->createUserObject("shared");
				}
				if($bmUser == null) exit(1);
				if(isSet($httpVars["bm_action"]) && isset($httpVars["bm_path"]))
				{
					if($httpVars["bm_action"] == "add_bookmark")
					{
						$title = "";
						if(isSet($httpVars["bm_title"])) $title = $httpVars["bm_title"];
						if($title == "" && $httpVars["bm_path"]=="/") $title = ConfService::getCurrentRootDirDisplay();
						$bmUser->addBookMark(SystemTextEncoding::magicDequote($httpVars["bm_path"]), SystemTextEncoding::magicDequote($title));
					}
					else if($httpVars["bm_action"] == "delete_bookmark")
					{
						$bmUser->removeBookmark($httpVars["bm_path"]);
					}
					else if($httpVars["bm_action"] == "rename_bookmark" && isset($httpVars["bm_title"]))
					{
						$bmUser->renameBookmark($httpVars["bm_path"], $httpVars["bm_title"]);
					}
				}
				if(AuthService::usersEnabled() && AuthService::getLoggedUser() != null)
				{
					$bmUser->save("user");
					AuthService::updateUser($bmUser);
				}
				else if(!AuthService::usersEnabled())
				{
					$bmUser->save("user");
				}		
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::writeBookmarks($bmUser->getBookmarks());
				AJXP_XMLWriter::close();
				exit(1);
			
			break;
					
			//------------------------------------
			//	SAVE USER PREFERENCE
			//------------------------------------
			case "save_user_pref":
				
				$userObject = AuthService::getLoggedUser();
				$i = 0;
				while(isSet($httpVars["pref_name_".$i]) && isSet($httpVars["pref_value_".$i]))
				{
					$prefName = AJXP_Utils::sanitize($httpVars["pref_name_".$i], AJXP_SANITIZE_ALPHANUM);
					$prefValue = AJXP_Utils::sanitize(SystemTextEncoding::magicDequote(($httpVars["pref_value_".$i])));
					if($prefName == "password") continue;
					if($prefName != "pending_folder" && $userObject == null){
						$i++;
						continue;
					}
					$userObject->setPref($prefName, $prefValue);
					$userObject->save("user");
					AuthService::updateUser($userObject);
					//setcookie("AJXP_$prefName", $prefValue);
					$i++;
				}
				header("Content-Type:text/plain");
				print "SUCCESS";
				exit(1);
				
			break;					
					
			//------------------------------------
			//	SAVE USER PREFERENCE
			//------------------------------------
			case "custom_data_edit":

                $userObject = AuthService::getLoggedUser();
                $data = array();
                AJXP_Utils::parseStandardFormParameters($httpVars, $data, null, "PREFERENCES_");

                $paramNodes = AJXP_PluginsService::searchAllManifests("//server_settings/param[contains(@scope,'user') and @expose='true']", "node", false, false, true);
                $rChanges = false;
                if(is_array($paramNodes) && count($paramNodes)){
                    foreach($paramNodes as $xmlNode){
                        if($xmlNode->getAttribute("expose") == "true"){
                            $parentNode = $xmlNode->parentNode->parentNode;
                            $pluginId = $parentNode->getAttribute("id");
                            if(empty($pluginId)){
                                $pluginId = $parentNode->nodeName.".".$parentNode->getAttribute("name");
                            }
                            $name = $xmlNode->getAttribute("name");
                            if(isSet($data[$name])){
                                if($userObject->parentRole == null || $userObject->parentRole->filterParameterValue($pluginId, $name, AJXP_REPO_SCOPE_ALL, "") != $data[$name]){
                                    $userObject->personalRole->setParameterValue($pluginId, $name, $data[$name]);
                                    $rChanges = true;
                                }
                            }
                        }
                    }
                }
                if($rChanges){
                    AuthService::updateRole($userObject->personalRole);
                    $userObject->recomputeMergedRole();
                    AuthService::updateUser($userObject);
                }


				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage("Successfully updated your account", null);
                AJXP_XMLWriter::close();

			break;

			//------------------------------------
			// WEBDAV PREFERENCES
			//------------------------------------
			case "webdav_preferences" :
				
				$userObject = AuthService::getLoggedUser();
				$webdavActive = false;
				$passSet = false;
				// Detect http/https and host
				if(ConfService::getCoreConf("WEBDAV_BASEHOST") != ""){
					$baseURL = ConfService::getCoreConf("WEBDAV_BASEHOST");
				}else{
					$baseURL = AJXP_Utils::detectServerURL();
				}
				$webdavBaseUrl = $baseURL.ConfService::getCoreConf("WEBDAV_BASEURI")."/";
				if(isSet($httpVars["activate"]) || isSet($httpVars["webdav_pass"])){
					$davData = $userObject->getPref("AJXP_WEBDAV_DATA");
					if(!empty($httpVars["activate"])){
						$activate = ($httpVars["activate"]=="true" ? true:false);
						if(empty($davData)){
							$davData = array();						
						}
						$davData["ACTIVE"] = $activate;
					}
					if(!empty($httpVars["webdav_pass"])){
						$password = $httpVars["webdav_pass"];
						if (function_exists('mcrypt_encrypt'))
				        {
				        	$user = $userObject->getId();
				        	$secret = (defined("AJXP_SECRET_KEY")? AJXP_SAFE_SECRET_KEY:"\1CDAFx¨op#");
					        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
					        $password = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($user.$secret), $password, MCRYPT_MODE_ECB, $iv));
				        }						
						$davData["PASS"] = $password;
					}
					$userObject->setPref("AJXP_WEBDAV_DATA", $davData);
					$userObject->save("user");
				}
				$davData = $userObject->getPref("AJXP_WEBDAV_DATA");				
				if(!empty($davData)){
					$webdavActive = (isSet($davData["ACTIVE"]) && $davData["ACTIVE"]===true); 
					$passSet = (isSet($davData["PASS"])); 
				}
				$repoList = ConfService::getRepositoriesList();
				$davRepos = array();
				$loggedUser = AuthService::getLoggedUser();
				foreach($repoList as $repoIndex => $repoObject){
					$accessType = $repoObject->getAccessType();
                    $driver = AJXP_PluginsService::getInstance()->getPluginByTypeName("access", $accessType);
					if(is_a($driver, "AjxpWebdavProvider") && ($loggedUser->canRead($repoIndex) || $loggedUser->canWrite($repoIndex))){
						$davRepos[$repoIndex] = $webdavBaseUrl ."".($repoObject->getSlug()==null?$repoObject->getId():$repoObject->getSlug());
					}
				}
				$prefs = array(
					"webdav_active"  => $webdavActive,
					"password_set"   => $passSet,
					"webdav_base_url"  => $webdavBaseUrl, 
					"webdav_repositories" => $davRepos
				);
				HTMLWriter::charsetHeader("application/json");
				print(json_encode($prefs));

			break;

			case  "get_user_template_logo":

                $tplId = $httpVars["template_id"];
                $iconFormat = $httpVars["icon_format"];
                $repo = ConfService::getRepositoryById($tplId);
                $logo = $repo->getOption("TPL_ICON_".strtoupper($iconFormat));
                if(isSet($logo) && is_file(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo)){
                    header("Content-Type: ".AJXP_Utils::getImageMimeType($logo)."; name=\"".$logo."\"");
                    header("Content-Length: ".filesize(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo));
                    header('Pragma:');
                    header('Cache-Control: public');
                    header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                    header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
                    readfile(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo);
                }else{
                    $logo = "default_template_logo-".($iconFormat == "small"?16:22).".png";
                    header("Content-Type: ".AJXP_Utils::getImageMimeType($logo)."; name=\"".$logo."\"");
                    header("Content-Length: ".filesize(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/core.conf/".$logo));
                    header('Pragma:');
                    header('Cache-Control: public');
                    header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                    header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
                    readfile(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/core.conf/".$logo);
                }

			break;
                    
			case  "get_user_templates_definition":

				AJXP_XMLWriter::header("repository_templates");
				$repositories = ConfService::getRepositoriesList();
                $pServ = AJXP_PluginsService::getInstance();
				foreach ($repositories as $repo){
					if(!$repo->isTemplate) continue;
                    if(!$repo->getOption("TPL_USER_CAN_CREATE")) continue;
					$repoId = $repo->getUniqueId();
					$repoLabel = $repo->getDisplay();
					$repoType = $repo->getAccessType();
					print("<template repository_id=\"$repoId\" repository_label=\"$repoLabel\" repository_type=\"$repoType\">");
                    $driverPlug = $pServ->getPluginByTypeName("access", $repoType);
                    $params = $driverPlug->getManifestRawContent("//param", "node");
                    $tplDefined = $repo->getOptionsDefined();
                    $defaultLabel = '';
                    foreach($params as $paramNode){
                        $name = $paramNode->getAttribute("name");
                        if( strpos($name, "TPL_") === 0 ) {
                            if($name == "TPL_DEFAULT_LABEL"){
                                $defaultLabel = str_replace("AJXP_USER", AuthService::getLoggedUser()->getId(), $repo->getOption($name));
                            }
                            continue;
                        }
                        if( in_array($paramNode->getAttribute("name"), $tplDefined) ) continue;
                        if($paramNode->getAttribute('no_templates') == 'true') continue;
                        print(AJXP_XMLWriter::replaceAjxpXmlKeywords($paramNode->ownerDocument->saveXML($paramNode)));
                    }
                    // ADD LABEL
                    echo '<param name="DISPLAY" type="string" label="'.$mess[359].'" description="'.$mess[429].'" mandatory="true" default="'.$defaultLabel.'"/>';
					print("</template>");
				}
				AJXP_XMLWriter::close("repository_templates");


			break;

            case "user_create_repository" :

                $tplId = $httpVars["template_id"];
                $tplRepo = ConfService::getRepositoryById($tplId);
                $options = array();
                AJXP_Utils::parseStandardFormParameters($httpVars, $options);
                $loggedUser = AuthService::getLoggedUser();
                $newRep = $tplRepo->createTemplateChild(AJXP_Utils::sanitize($httpVars["DISPLAY"]), $options, null, $loggedUser->getId());
                $gPath = $loggedUser->getGroupPath();
                if(!empty($gPath)){
                    $newRep->setGroupPath($gPath);
                }
                $res = ConfService::addRepository($newRep);
                AJXP_XMLWriter::header();
                if($res == -1){
                    AJXP_XMLWriter::sendMessage(null, $mess[426]);
                }else{
                    // Make sure we do not overwrite otherwise loaded rights.
                    $loggedUser->load();
                    $loggedUser->personalRole->setAcl($newRep->getUniqueId(), "rw");
                    $loggedUser->save("superuser");
                    AuthService::updateUser($loggedUser);

                    AJXP_XMLWriter::sendMessage($mess[425], null);
                    AJXP_XMLWriter::reloadDataNode("", $newRep->getUniqueId());
                    AJXP_XMLWriter::reloadRepositoryList();
                }
                AJXP_XMLWriter::close();

            break;

            case "user_delete_repository" :

                $repoId = $httpVars["repository_id"];
                $repository = ConfService::getRepositoryById($repoId);
                if(!$repository->getUniqueUser()||$repository->getUniqueUser()!=AuthService::getLoggedUser()->getId()){
                    throw new Exception("You are not allowed to perform this operation!");
                }
                $res = ConfService::deleteRepository($repoId);
                AJXP_XMLWriter::header();
                if($res == -1){
                    AJXP_XMLWriter::sendMessage(null, $mess[427]);
                }else{
                    $loggedUser = AuthService::getLoggedUser();
                    // Make sure we do not override remotely set rights
                    $loggedUser->load();
                    $loggedUser->personalRole->setAcl($repoId, "");
                    $loggedUser->save("superuser");
                    AuthService::updateUser($loggedUser);

                    AJXP_XMLWriter::sendMessage($mess[428], null);
                    AJXP_XMLWriter::reloadRepositoryList();
                }
                AJXP_XMLWriter::close();

            break;

            case "get_binary_param" :

                if(isSet($httpVars["tmp_file"])){
                    $file = AJXP_Utils::getAjxpTmpDir()."/".AJXP_Utils::securePath($httpVars["tmp_file"]);
                    if(isSet($file)){
                        header("Content-Type:image/png");
                        readfile($file);
                    }
                }else if(isSet($httpVars["binary_id"])){
                    if(isSet($httpVars["user_id"]) && AuthService::getLoggedUser() != null && AuthService::getLoggedUser()->isAdmin()){
                        $context = array("USER" => $httpVars["user_id"]);
                    }else{
                        $context = array("USER" => AuthService::getLoggedUser()->getId());
                    }
                    $this->loadBinary($context, $httpVars["binary_id"]);
                }
            break;

            case "store_binary_temp" :

                if(count($fileVars)){
                    $keys = array_keys($fileVars);
                    $boxData = $fileVars[$keys[0]];
                    $err = AJXP_Utils::parseFileDataErrors($boxData);
                    if($err != null){

                    }else{
                        $rand = substr(md5(time()), 0, 6);
                        $tmp = $rand."-". $boxData["name"];
                        @move_uploaded_file($boxData["tmp_name"], AJXP_Utils::getAjxpTmpDir()."/". $tmp);
                    }
                }
                if(isSet($tmp) && file_exists(AJXP_Utils::getAjxpTmpDir()."/".$tmp)) {
                    print('<script type="text/javascript">');
                    print('parent.formManagerHiddenIFrameSubmission("'.$tmp.'");');
                    print('</script>');
                }

                break;


            default;
			break;
		}
		if(isset($logMessage) || isset($errorMessage))
		{
			$xmlBuffer .= AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null), false);			
		}
		
		if(isset($requireAuth))
		{
			$xmlBuffer .= AJXP_XMLWriter::requireAuth(false);
		}
				
		return $xmlBuffer;		
	}

}