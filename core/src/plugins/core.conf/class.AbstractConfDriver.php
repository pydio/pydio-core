<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 * @class AbstractConfDriver
 * Abstract representation of a conf driver. Must be implemented by the "conf" plugin
 */
abstract class AbstractConfDriver extends AJXP_Plugin
{
    public $options;
    public $driverType = "conf";


    public function init($options)
    {
        parent::init($options);
        $options = $this->options;

        // BACKWARD COMPATIBILIY PREVIOUS CONFIG VIA OPTIONS
        if (isSet($options["CUSTOM_DATA"])) {
            $custom = $options["CUSTOM_DATA"];
            $serverSettings = $this->xPath->query('//server_settings')->item(0);
            foreach ($custom as $key => $value) {
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

    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if ($contribNode->nodeName == 'client_configs' && !ConfService::getCoreConf("WEBDAV_ENABLE")) {
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $webdavCompNodeList = $actionXpath->query('component_config/additional_tab[@id="webdav_pane"]', $contribNode);
            if ($webdavCompNodeList->length) {
                $contribNode->removeChild($webdavCompNodeList->item(0)->parentNode);
            }
        }

        if($contribNode->nodeName != "actions") return;

        // WEBDAV ACTION
        if (!ConfService::getCoreConf("WEBDAV_ENABLE")) {
            unset($this->actions["webdav_preferences"]);
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="webdav_preferences"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
        }

        // SWITCH TO DASHBOARD ACTION
        $u = AuthService::getLoggedUser();
        $access = true;
        if($u == null) $access = false;
        else {
            $acl = $u->mergedRole->getAcl("ajxp_user");
            if(empty($acl)) $access = false;
        }
        if(!$access){
            unset($this->actions["switch_to_user_dashboard"]);
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="switch_to_user_dashboard"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
        }

        // PERSONAL INFORMATIONS
        $hasExposed = false;
        $cacheHasExposed = AJXP_PluginsService::getInstance()->loadFromPluginQueriesCache("//server_settings/param[contains(@scope,'user') and @expose='true']");
        if ($cacheHasExposed !== null) {
            $hasExposed = $cacheHasExposed;
        } else {
            $paramNodes = AJXP_PluginsService::searchAllManifests("//server_settings/param[contains(@scope,'user') and @expose='true']", "node", false, false, true);
            if (is_array($paramNodes) && count($paramNodes)) {
                $hasExposed = true;
            }
            AJXP_PluginsService::getInstance()->storeToPluginQueriesCache("//server_settings/param[contains(@scope,'user') and @expose='true']", $hasExposed);
        }
        //$hasExposed = true;


        if (!$hasExposed) {
            unset($this->actions["custom_data_edit"]);
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="custom_data_edit"]', $contribNode);
            $publicUrlNode = $publicUrlNodeList->item(0);
            $contribNode->removeChild($publicUrlNode);
        }

        // CREATE A NEW REPOSITORY
        if (!ConfService::getCoreConf("USER_CREATE_REPOSITORY", "conf")) {
            unset($this->actions["user_create_repository"]);
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="user_create_repository"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
            unset($this->actions["user_delete_repository"]);
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="user_delete_repository"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
        }

        // CREATE A NEW USER
        if (!ConfService::getCoreConf("USER_CREATE_USERS", "conf")) {
            unset($this->actions["user_create_user"]);
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="user_create_user"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
            unset($this->actions["user_update_user"]);
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="user_update_user"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
            unset($this->actions["user_delete_user"]);
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="user_delete_user"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
        }

    }

    // NEW FUNCTIONS FOR  LOADING/SAVING PLUGINS CONFIGS
    /**
     * Returns an array of options=>values merged from various sources (.inc.php, implementation source)
     * @return Array
     * @param String $pluginType
     * @param String $pluginName
     */
    public function loadPluginConfig($pluginType, $pluginName)
    {
        $options = array();
        if (is_file(AJXP_CONF_PATH."/conf.$pluginType.inc")) {
            include AJXP_CONF_PATH."/conf.$pluginType.inc";
            if (!empty($DRIVER_CONF)) {
                foreach ($DRIVER_CONF as $key=>$value) {
                    $options[$key] = $value;
                }
                unset($DRIVER_CONF);
            }
        }
        if (is_file(AJXP_CONF_PATH."/conf.$pluginType.$pluginName.inc")) {
            include AJXP_CONF_PATH."/conf.$pluginType.$pluginName.inc";
            if (!empty($DRIVER_CONF)) {
                foreach ($DRIVER_CONF as $key=>$value) {
                    $options[$key] = $value;
                }
                unset($DRIVER_CONF);
            }
        }
        if ($this->pluginUsesBootConf($pluginType.".".$pluginName)) {
            ConfService::getBootConfStorageImpl()->_loadPluginConfig($pluginType.".".$pluginName, $options);
        } else {
            $this->_loadPluginConfig($pluginType.".".$pluginName, $options);
        }
        return $options;
    }

    abstract public function _loadPluginConfig($pluginId, &$options);

    /**
     * Intercept CONF and AUTH configs to use the BootConf Storage
        * @param String $pluginId
        * @param String $options
        */
    public function savePluginConfig($pluginId, $options)
    {
        if ($this->pluginUsesBootConf($pluginId)) {
            ConfService::getBootConfStorageImpl()->_savePluginConfig($pluginId, $options);
        } else {
            $this->_savePluginConfig($pluginId, $options);
        }
    }

    /**
     * @param String $pluginId
     * @return bool
     */
    protected function pluginUsesBootConf($pluginId)
    {
        return ($pluginId == "core.conf" || strpos($pluginId, "conf.") === 0
            || $pluginId == "core.auth" || strpos($pluginId, "auth.") === 0);
    }

    /**
     * @param String $pluginId
     * @param String $options
     */
    abstract public function _savePluginConfig($pluginId, $options);


    // SAVE / EDIT / CREATE / DELETE REPOSITORY
    /**
     * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
     * @param AbstractAjxpUser $user
     * @return Repository[]
     */
    abstract public function listRepositories($user = null);

    /**
     * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
     * @param Array $criteria This parameter can take the following keys
     *
     *      - Search keys "uuid", "parent_uuid", "owner_user_id", "display", "accessType", "isTemplate", "slug", "groupPath",
     *        Search values can be either string, array of string, AJXP_FILTER_EMPTY, AJXP_FILTER_NOT_EMPTY or regexp:RegexpString
     *      - or "role" => AJXP_Role object: will search repositories accessible to this role
     *      - ORDERBY = array("KEY"=>"", "DIR"=>""), GROUPBY, CURSOR = array("OFFSET" => 0, "LIMIT", 30)
     *      - COUNT_ONLY
     *
     * @return Repository[]
     */
    abstract public function listRepositoriesWithCriteria($criteria);


    /**
     * Retrieve a Repository given its unique ID.
     *
     * @param String $repositoryId
     * @return Repository
     */
    abstract public function getRepositoryById($repositoryId);
    /**
     * Retrieve a Repository given its alias.
     *
     * @param String $repositorySlug
     * @return Repository
     */
    abstract public function getRepositoryByAlias($repositorySlug);
    /**
     * Stores a repository, new or not.
     *
     * @param Repository $repositoryObject
     * @param Boolean $update
     * @return -1 if failed
     */
    abstract public function saveRepository($repositoryObject, $update = false);
    /**
     * Delete a repository, given its unique ID.
     *
     * @param String $repositoryId
     */
    abstract public function deleteRepository($repositoryId);

    /**
     * Must return an associative array of roleId => AjxpRole objects.
     * @param array $roleIds
     * @param boolean $excludeReserved,
     * @return array AjxpRole[]
     */
    abstract public function listRoles($roleIds = array(), $excludeReserved = false);
    abstract public function saveRoles($roles);

    /**
     * @abstract
     * @param AJXP_Role $role
     * @return void
     */
    abstract public function updateRole($role);

    /**
     * @abstract
     * @param AJXP_Role|String $role
     * @return void
     */
    abstract public function deleteRole($role);

    /**
     * Specific queries
     */
    abstract public function countAdminUsers();

    /**
     * @abstract
     * @param array $context
     * @param String $fileName
     * @param String $ID
     * @return String $ID
     */
    abstract public function saveBinary($context, $fileName, $ID = null);

    /**
     * @abstract
     * @param array $context
     * @param String $ID
     * @param Resource $outputStream
     * @return boolean
     */
    abstract public function loadBinary($context, $ID, $outputStream = null);

    /**
     * @abstract
     * @param array $context
     * @param String $ID
     * @return boolean
     */
    abstract public function deleteBinary($context, $ID);

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @param String $userId
     * @param Array $data
     * @return boolean
     */
    abstract public function saveTemporaryKey($keyType, $keyId, $userId, $data);

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @return array
     */
    abstract public function loadTemporaryKey($keyType, $keyId);

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @return boolean
     */
    abstract public function deleteTemporaryKey($keyType, $keyId);

    /**
     * @abstract
     * @param String $keyType
     * @param String $expiration
     * @return null
     */
    abstract public function pruneTemporaryKeys($keyType, $expiration);

    /**
     * Instantiate a new AbstractAjxpUser
     *
     * @param String $userId
     * @return AbstractAjxpUser
     */
    public function createUserObject($userId)
    {
        $userId = AuthService::filterUserSensitivity($userId);
        $abstractUser = $this->instantiateAbstractUserImpl($userId);
        if (!$abstractUser->storageExists()) {
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
    abstract public function deleteUser($userId, &$deletedSubUsers);


    /**
     * Instantiate the right class
     *
     * @param string $userId
     * @return AbstractAjxpUser
     */
    abstract public function instantiateAbstractUserImpl($userId);

    abstract public function getUserClassFileName();

    /**
     * @abstract
     * @param $userId
     * @return AbstractAjxpUser[]
     */
    abstract public function getUserChildren($userId);

    /**
     * @abstract
     * @param string $repositoryId
     * @return AbstractAjxpUser[]
     */
    abstract public function getUsersForRepository($repositoryId);

    /**
     * @abstract
     * @param string $repositoryId
     * @param string $rolePrefix
     * @param bool $countOnly
     * @return AJXP_Role[]
     */
    abstract public function getRolesForRepository($repositoryId, $rolePrefix = '', $countOnly = false);
    /**
     * @abstract
     * @param string $repositoryId
     * @param boolean $details
     * @return Integer|Array
     */
    abstract public function countUsersForRepository($repositoryId, $details = false);


    /**
     * @param AbstractAjxpUser[] $flatUsersList
     * @param string $baseGroup
     * @param bool $fullTree
     * @return void
     */
    abstract public function filterUsersByGroup(&$flatUsersList, $baseGroup = "/", $fullTree = false);

    /**
     * @param string $groupPath
     * @param string $groupLabel
     * @return mixed
     */
    abstract public function createGroup($groupPath, $groupLabel);

    /**
     * @abstract
     * @param $groupPath
     * @return void
     */
    abstract public function deleteGroup($groupPath);


    /**
     * @abstract
     * @param string $groupPath
     * @param string $groupLabel
     * @return void
     */
    abstract public function relabelGroup($groupPath, $groupLabel);


        /**
     * @param string $baseGroup
     * @return string[]
     */
    abstract public function getChildrenGroups($baseGroup = "/");

    public function getOption($optionName)
    {
        return (isSet($this->options[$optionName])?$this->options[$optionName]:"");
    }

    /**
     * @param AbstractAjxpUser $userObject
     * @return array()
     */
    public function getExposedPreferences($userObject)
    {
        $stringPrefs = array("lang","history/last_repository","pending_folder","plugins_preferences");
        $jsonPrefs = array("ls_history","gui_preferences");
        $prefs = array();
        if ( $userObject->getId()=="guest" && ConfService::getCoreConf("SAVE_GUEST_PREFERENCES", "conf") === false) {
            return array();
        }
        if ( ConfService::getCoreConf("SKIP_USER_HISTORY", "conf") === true ) {
            $stringPrefs = array("lang","pending_folder", "plugins_preferences");
            $jsonPrefs = array("gui_preferences");
            $prefs["SKIP_USER_HISTORY"] = array("value" => "true", "type" => "string" );
        }
        foreach ($stringPrefs as $pref) {
            if (strstr($pref, "/")!==false) {
                $parts = explode("/", $pref);
                $value = $userObject->getArrayPref($parts[0], $parts[1]);
                $pref = str_replace("/", "_", $pref);
            } else {
                $value = $userObject->getPref($pref);
            }
            $prefs[$pref] = array("value" => $value, "type" => "string" );
        }
        foreach ($jsonPrefs as $pref) {
            $prefs[$pref] = array("value" => $userObject->getPref($pref), "type" => "json" );
        }

        $paramNodes = AJXP_PluginsService::searchAllManifests("//server_settings/param[contains(@scope,'user') and @expose='true']", "node", false, false, true);
        if (is_array($paramNodes) && count($paramNodes)) {
            foreach ($paramNodes as $xmlNode) {
                if ($xmlNode->getAttribute("expose") == "true") {
                    $parentNode = $xmlNode->parentNode->parentNode;
                    $pluginId = $parentNode->getAttribute("id");
                    if (empty($pluginId)) {
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

    public function switchAction($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return;
        $xmlBuffer = "";
        foreach ($httpVars as $getName=>$getValue) {
            $$getName = AJXP_Utils::securePath($getValue);
        }
        if(isSet($dir) && $action != "upload") $dir = SystemTextEncoding::fromUTF8($dir);
        $mess = ConfService::getMessages();

        switch ($action) {
            //------------------------------------
            //	SWITCH THE ROOT REPOSITORY
            //------------------------------------
            case "switch_repository":

                if (!isSet($repository_id)) {
                    break;
                }
                $dirList = ConfService::getRepositoriesList();
                /** @var $repository_id string */
                if (!isSet($dirList[$repository_id])) {
                    $errorMessage = "Trying to switch to an unkown repository!";
                    break;
                }
                ConfService::switchRootDir($repository_id);
                // Load try to init the driver now, to trigger an exception
                // if it's not loading right.
                ConfService::loadRepositoryDriver();
                if (AuthService::usersEnabled() && AuthService::getLoggedUser()!=null) {
                    $user = AuthService::getLoggedUser();
                    $activeRepId = ConfService::getCurrentRepositoryId();
                    $user->setArrayPref("history", "last_repository", $activeRepId);
                    $user->save("user");
                }
                //$logMessage = "Successfully Switched!";
                $this->logInfo("Switch Repository", array("rep. id"=>$repository_id));

            break;

            //------------------------------------
            //	SEND XML REGISTRY
            //------------------------------------
            case "get_xml_registry" :
            case "state" :

                $regDoc = AJXP_PluginsService::getXmlRegistry();
                $changes = AJXP_Controller::filterRegistryFromRole($regDoc);
                if($changes) AJXP_PluginsService::updateXmlRegistry($regDoc);

                $clone = $regDoc->cloneNode(true);
                $clonePath = new DOMXPath($clone);
                $serverCallbacks = $clonePath->query("//serverCallback|hooks");
                foreach ($serverCallbacks as $callback) {
                    $callback->parentNode->removeChild($callback);
                }
                $xPath = '';
                if (isSet($httpVars["xPath"])) {
                    $xPath = ltrim(AJXP_Utils::securePath($httpVars["xPath"]), "/");
                }
                if (!empty($xPath)) {
                    $nodes = $clonePath->query($xPath);
                    if($httpVars["format"] == "json"){
                        $data = AJXP_XMLWriter::xmlToArray($nodes->item(0));
                        HTMLWriter::charsetHeader("application/json");
                        echo json_encode($data);
                    }else{
                        AJXP_XMLWriter::header("ajxp_registry_part", array("xPath"=>$xPath));
                        if ($nodes->length) {
                            print(AJXP_XMLWriter::replaceAjxpXmlKeywords($clone->saveXML($nodes->item(0))));
                        }
                        AJXP_XMLWriter::close("ajxp_registry_part");
                    }
                } else {
                    AJXP_Utils::safeIniSet("zlib.output_compression", "4096");
                    if($httpVars["format"] == "json"){
                        $data = AJXP_XMLWriter::xmlToArray($clone);
                        HTMLWriter::charsetHeader("application/json");
                        echo json_encode($data);
                    }else{
                        header('Content-Type: application/xml; charset=UTF-8');
                        print(AJXP_XMLWriter::replaceAjxpXmlKeywords($clone->saveXML()));
                    }
                }

            break;

            //------------------------------------
            //	BOOKMARK BAR
            //------------------------------------
            case "get_bookmarks":

                $bmUser = null;
                if (AuthService::usersEnabled() && AuthService::getLoggedUser() != null) {
                    $bmUser = AuthService::getLoggedUser();
                } else if (!AuthService::usersEnabled()) {
                    $confStorage = ConfService::getConfStorageImpl();
                    $bmUser = $confStorage->createUserObject("shared");
                }
                if ($bmUser == null) {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::close();
                }
                $driver = ConfService::loadRepositoryDriver();
                if (!is_a($driver, "AjxpWrapperProvider")) {
                    $driver = false;
                }


                if (isSet($httpVars["bm_action"]) && isset($httpVars["bm_path"])) {
                    $bmPath = AJXP_Utils::decodeSecureMagic($httpVars["bm_path"]);

                    if ($httpVars["bm_action"] == "add_bookmark") {
                        $title = "";
                        if(isSet($httpVars["bm_title"])) $title = AJXP_Utils::decodeSecureMagic($httpVars["bm_title"]);
                        if($title == "" && $bmPath=="/") $title = ConfService::getCurrentRootDirDisplay();
                        $bmUser->addBookMark($bmPath, $title);
                        if ($driver) {
                            $node = new AJXP_Node($driver->getResourceUrl($bmPath));
                            $node->setMetadata("ajxp_bookmarked", array("ajxp_bookmarked" => "true"), true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                        }
                    } else if ($httpVars["bm_action"] == "delete_bookmark") {
                        $bmUser->removeBookmark($bmPath);
                        if ($driver) {
                            $node = new AJXP_Node($driver->getResourceUrl($bmPath));
                            $node->removeMetadata("ajxp_bookmarked", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                        }
                    } else if ($httpVars["bm_action"] == "rename_bookmark" && isset($httpVars["bm_title"])) {
                        $title = AJXP_Utils::decodeSecureMagic($httpVars["bm_title"]);
                        $bmUser->renameBookmark($bmPath, $title);
                    }
                    AJXP_Controller::applyHook("msg.instant", array("<reload_bookmarks/>", ConfService::getRepository()->getId()));

                    if (AuthService::usersEnabled() && AuthService::getLoggedUser() != null) {
                        $bmUser->save("user");
                        AuthService::updateUser($bmUser);
                    } else if (!AuthService::usersEnabled()) {
                        $bmUser->save("user");
                    }
                }
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::writeBookmarks($bmUser->getBookmarks(), true, isset($httpVars["format"])?$httpVars["format"]:"legacy");
                AJXP_XMLWriter::close();

            break;

            //------------------------------------
            //	SAVE USER PREFERENCE
            //------------------------------------
            case "save_user_pref":

                $userObject = AuthService::getLoggedUser();
                $i = 0;
                while (isSet($httpVars["pref_name_".$i]) && isSet($httpVars["pref_value_".$i])) {
                    $prefName = AJXP_Utils::sanitize($httpVars["pref_name_".$i], AJXP_SANITIZE_ALPHANUM);
                    $prefValue = AJXP_Utils::sanitize(SystemTextEncoding::magicDequote($httpVars["pref_value_".$i]));
                    if($prefName == "password") continue;
                    if ($prefName != "pending_folder" && $userObject == null) {
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

            break;

            //------------------------------------
            //	SAVE USER PREFERENCE
            //------------------------------------
            case "custom_data_edit":
            case "user_create_user":

                $data = array();

                if ($action == "user_create_user" && isSet($httpVars["NEW_new_user_id"])) {
                    $updating = false;
                    AJXP_Utils::parseStandardFormParameters($httpVars, $data, null, "NEW_");
                    $original_id = AJXP_Utils::decodeSecureMagic($data["new_user_id"]);
                    $data["new_user_id"] = AJXP_Utils::decodeSecureMagic($data["new_user_id"], AJXP_SANITIZE_EMAILCHARS);
                    if($original_id != $data["new_user_id"]){
                        throw new Exception(str_replace("%s", $data["new_user_id"], $mess["ajxp_conf.127"]));
                    }
                    if (AuthService::userExists($data["new_user_id"],"w")) {
                        throw new Exception($mess["ajxp_conf.43"]);
                    }
                    $loggedUser = AuthService::getLoggedUser();
                    $limit = $loggedUser->personalRole->filterParameterValue("core.conf", "USER_SHARED_USERS_LIMIT", AJXP_REPO_SCOPE_ALL, "");
                    if (!empty($limit) && intval($limit) > 0) {
                        $count = count($this->getUserChildren($loggedUser->getId()));
                        if ($count >= $limit) {
                            throw new Exception($mess['483']);
                        }
                    }

                    AuthService::createUser($data["new_user_id"], $data["new_password"]);
                    $userObject = ConfService::getConfStorageImpl()->createUserObject($data["new_user_id"]);
                    $userObject->setParent($loggedUser->getId());
                    $userObject->save('superuser');
                    $userObject->personalRole->clearAcls();
                    $userObject->setGroupPath($loggedUser->getGroupPath());
                    $userObject->setProfile("shared");

                } else if($action == "user_create_user" && isSet($httpVars["NEW_existing_user_id"])){

                    $updating = true;
                    AJXP_Utils::parseStandardFormParameters($httpVars, $data, null, "NEW_");
                    $userId = $data["existing_user_id"];
                    if(!AuthService::userExists($userId)){
                        throw new Exception("Cannot find user");
                    }
                    $userObject = ConfService::getConfStorageImpl()->createUserObject($userId);
                    if($userObject->getParent() != AuthService::getLoggedUser()->getId()){
                        throw new Exception("Cannot find user");
                    }
                    if(!empty($data["new_password"])){
                        AuthService::updatePassword($userId, $data["new_password"]);
                    }

                } else {
                    $updating = false;
                    $userObject = AuthService::getLoggedUser();
                    AJXP_Utils::parseStandardFormParameters($httpVars, $data, null, "PREFERENCES_");
                }

                $paramNodes = AJXP_PluginsService::searchAllManifests("//server_settings/param[contains(@scope,'user') and @expose='true']", "node", false, false, true);
                $rChanges = false;
                if (is_array($paramNodes) && count($paramNodes)) {
                    foreach ($paramNodes as $xmlNode) {
                        if ($xmlNode->getAttribute("expose") == "true") {
                            $parentNode = $xmlNode->parentNode->parentNode;
                            $pluginId = $parentNode->getAttribute("id");
                            if (empty($pluginId)) {
                                $pluginId = $parentNode->nodeName.".".$parentNode->getAttribute("name");
                            }
                            $name = $xmlNode->getAttribute("name");
                            if (isSet($data[$name]) || $data[$name] === "") {
                                if($data[$name] == "__AJXP_VALUE_SET__") continue;
                                if ($data[$name] === "" || $userObject->parentRole == null
                                    || $userObject->parentRole->filterParameterValue($pluginId, $name, AJXP_REPO_SCOPE_ALL, "") != $data[$name]
                                    || $userObject->personalRole->filterParameterValue($pluginId, $name, AJXP_REPO_SCOPE_ALL, "") != $data[$name]) {
                                    $userObject->personalRole->setParameterValue($pluginId, $name, $data[$name]);
                                    $rChanges = true;
                                }
                            }
                        }
                    }
                }
                if ($rChanges) {
                    AuthService::updateRole($userObject->personalRole, $userObject);
                    $userObject->recomputeMergedRole();
                    if ($action == "custom_data_edit") {
                        AuthService::updateUser($userObject);
                    }
                }

                if ($action == "user_create_user") {

                    AJXP_Controller::applyHook($updating?"user.after_update":"user.after_create", array($userObject));
                    if (isset($data["send_email"]) && $data["send_email"] == true && !empty($data["email"])) {
                        $mailer = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("mailer");
                        if ($mailer !== false) {
                            $mess = ConfService::getMessages();
                            $link = AJXP_Utils::detectServerURL();
                            $apptitle = ConfService::getCoreConf("APPLICATION_TITLE");
                            $subject = str_replace("%s", $apptitle, $mess["507"]);
                            $body = str_replace(array("%s", "%link", "%user", "%pass"), array($apptitle, $link, $data["new_user_id"], $data["new_password"]), $mess["508"]);
                            $mailer->sendMail(array($data["email"]), $subject, $body);
                        }
                    }

                    echo "SUCCESS";
                } else {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage($mess["241"], null);
                    AJXP_XMLWriter::close();
                }

            break;

            case "user_update_user":

                if(!isSet($httpVars["user_id"])) {
                    throw new Exception("invalid arguments");
                }
                $userId = $httpVars["user_id"];
                if(!AuthService::userExists($userId)){
                    throw new Exception("Cannot find user");
                }
                $userObject = ConfService::getConfStorageImpl()->createUserObject($userId);
                if($userObject->getParent() != AuthService::getLoggedUser()->getId()){
                    throw new Exception("Cannot find user");
                }
                $paramsString = ConfService::getCoreConf("NEWUSERS_EDIT_PARAMETERS", "conf");
                $result = array();
                $params = explode(",", $paramsString);
                foreach($params as $p){
                    $result[$p] = $userObject->personalRole->filterParameterValue("core.conf", $p, AJXP_REPO_SCOPE_ALL, "");
                }
                HTMLWriter::charsetHeader("application/json");
                echo json_encode($result);

            break;

            //------------------------------------
            // WEBDAV PREFERENCES
            //------------------------------------
            case "webdav_preferences" :

                $userObject = AuthService::getLoggedUser();
                $webdavActive = false;
                $passSet = false;
                $digestSet = false;
                // Detect http/https and host
                if (ConfService::getCoreConf("WEBDAV_BASEHOST") != "") {
                    $baseURL = ConfService::getCoreConf("WEBDAV_BASEHOST");
                } else {
                    $baseURL = AJXP_Utils::detectServerURL();
                }
                $webdavBaseUrl = $baseURL.ConfService::getCoreConf("WEBDAV_BASEURI")."/";
                $davData = $userObject->getPref("AJXP_WEBDAV_DATA");
                $digestSet = isSet($davData["HA1"]);
                if (isSet($httpVars["activate"]) || isSet($httpVars["webdav_pass"])) {
                    if (!empty($httpVars["activate"])) {
                        $activate = ($httpVars["activate"]=="true" ? true:false);
                        if (empty($davData)) {
                            $davData = array();
                        }
                        $davData["ACTIVE"] = $activate;
                    }
                    if (!empty($httpVars["webdav_pass"])) {
                        $password = $httpVars["webdav_pass"];
                        if (function_exists('mcrypt_encrypt')) {
                            $user = $userObject->getId();
                            $secret = (defined("AJXP_SAFE_SECRET_KEY")? AJXP_SAFE_SECRET_KEY:"\1CDAFx¨op#");
                            $password = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($user.$secret), $password, MCRYPT_MODE_ECB));
                        }
                        $davData["PASS"] = $password;
                    }
                    $userObject->setPref("AJXP_WEBDAV_DATA", $davData);
                    $userObject->save("user");
                }
                if (!empty($davData)) {
                    $webdavActive = (isSet($davData["ACTIVE"]) && $davData["ACTIVE"]===true);
                    $passSet = (isSet($davData["PASS"]));
                }
                $repoList = ConfService::getRepositoriesList();
                $davRepos = array();
                $loggedUser = AuthService::getLoggedUser();
                foreach ($repoList as $repoIndex => $repoObject) {
                    $accessType = $repoObject->getAccessType();
                    $driver = AJXP_PluginsService::getInstance()->getPluginByTypeName("access", $accessType);
            if (is_a($driver, "AjxpWrapperProvider") && !$repoObject->getOption("AJXP_WEBDAV_DISABLED") && ($loggedUser->canRead($repoIndex) || $loggedUser->canWrite($repoIndex))) {
                        $davRepos[$repoIndex] = $webdavBaseUrl ."".($repoObject->getSlug()==null?$repoObject->getId():$repoObject->getSlug());
                    }
                }
                $prefs = array(
                    "webdav_active"  => $webdavActive,
                    "password_set"   => $passSet,
                    "digest_set"    => $digestSet,
                    "webdav_force_basic" => (ConfService::getCoreConf("WEBDAV_FORCE_BASIC") === true),
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
                if (isSet($logo) && is_file(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo)) {
                    header("Content-Type: ".AJXP_Utils::getImageMimeType($logo)."; name=\"".$logo."\"");
                    header("Content-Length: ".filesize(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo));
                    header('Pragma:');
                    header('Cache-Control: public');
                    header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                    header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
                    readfile(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo);
                } else {
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
                $repositories = ConfService::getConfStorageImpl()->listRepositoriesWithCriteria(array(
                    "isTemplate" => 1
                ));
                $pServ = AJXP_PluginsService::getInstance();
                foreach ($repositories as $repo) {
                    if(!$repo->isTemplate) continue;
                    if(!$repo->getOption("TPL_USER_CAN_CREATE")) continue;
                    $repoId = $repo->getId();
                    $repoLabel = $repo->getDisplay();
                    $repoType = $repo->getAccessType();
                    print("<template repository_id=\"$repoId\" repository_label=\"$repoLabel\" repository_type=\"$repoType\">");
                    $driverPlug = $pServ->getPluginByTypeName("access", $repoType);
                    $params = $driverPlug->getManifestRawContent("//param", "node");
                    $tplDefined = $repo->getOptionsDefined();
                    $defaultLabel = '';
                    foreach ($params as $paramNode) {
                        $name = $paramNode->getAttribute("name");
                        if ( strpos($name, "TPL_") === 0 ) {
                            if ($name == "TPL_DEFAULT_LABEL") {
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
                if (!empty($gPath)) {
                    $newRep->setGroupPath($gPath);
                }
                $res = ConfService::addRepository($newRep);
                AJXP_XMLWriter::header();
                if ($res == -1) {
                    AJXP_XMLWriter::sendMessage(null, $mess[426]);
                } else {
                    // Make sure we do not overwrite otherwise loaded rights.
                    $loggedUser->load();
                    $loggedUser->personalRole->setAcl($newRep->getUniqueId(), "rw");
                    $loggedUser->save("superuser");
                    $loggedUser->recomputeMergedRole();
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
                if (!$repository->getUniqueUser()||$repository->getUniqueUser()!=AuthService::getLoggedUser()->getId()) {
                    throw new Exception("You are not allowed to perform this operation!");
                }
                $res = ConfService::deleteRepository($repoId);
                AJXP_XMLWriter::header();
                if ($res == -1) {
                    AJXP_XMLWriter::sendMessage(null, $mess[427]);
                } else {
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

            case "user_delete_user":

                $userId = $httpVars["user_id"];
                $userObject = ConfService::getConfStorageImpl()->createUserObject($userId);
                if ($userObject == null || !$userObject->hasParent() || $userObject->getParent() != AuthService::getLoggedUser()->getId()) {
                    throw new Exception("You are not allowed to edit this user");
                }
                AuthService::deleteUser($userId);
                echo "SUCCESS";

            break;

            case "user_list_authorized_users" :

                $defaultFormat = "html";

                HTMLWriter::charsetHeader();
                if (!ConfService::getAuthDriverImpl()->usersEditable()) {
                    break;
                }
                $loggedUser = AuthService::getLoggedUser();
                $crtValue = $httpVars["value"];
                $usersOnly = isSet($httpVars["users_only"]) && $httpVars["users_only"] == "true";
                $existingOnly = isSet($httpVars["existing_only"]) && $httpVars["existing_only"] == "true";
                if(!empty($crtValue)) $regexp = '^'.$crtValue;
                else $regexp = null;
                $skipDisplayWithoutRegexp = ConfService::getCoreConf("USERS_LIST_REGEXP_MANDATORY", "conf");
                if($skipDisplayWithoutRegexp && $regexp == null){
                    print("<ul></ul>");
                    break;
                }
                $limit = intval(ConfService::getCoreConf("USERS_LIST_COMPLETE_LIMIT", "conf"));
                $searchAll = ConfService::getCoreConf("CROSSUSERS_ALLGROUPS", "conf");
                $displayAll = ConfService::getCoreConf("CROSSUSERS_ALLGROUPS_DISPLAY", "conf");
                $baseGroup = "/";
                if( ($regexp == null && !$displayAll) || ($regexp != null && !$searchAll) ){
                    $baseGroup = AuthService::filterBaseGroup("/");
                }
                AuthService::setGroupFiltering(false);
                $allUsers = AuthService::listUsers($baseGroup, $regexp, 0, $limit, false);

                if (!$usersOnly) {
                    $allGroups = array();

                    $roleOrGroup = ConfService::getCoreConf("GROUP_OR_ROLE", "conf");
                    $rolePrefix = $excludeString = $includeString = null;
                    if(!is_array($roleOrGroup)){
                        $roleOrGroup = array("group_switch_value" => $roleOrGroup);
                    }

                    $listRoleType = false;

                    if(isSet($roleOrGroup["PREFIX"])){
                        $rolePrefix    = $loggedUser->mergedRole->filterParameterValue("core.conf", "PREFIX", null, $roleOrGroup["PREFIX"]);
                        $excludeString = $loggedUser->mergedRole->filterParameterValue("core.conf", "EXCLUDED", null, $roleOrGroup["EXCLUDED"]);
                        $includeString = $loggedUser->mergedRole->filterParameterValue("core.conf", "INCLUDED", null, $roleOrGroup["INCLUDED"]);
                        $listUserRolesOnly = $loggedUser->mergedRole->filterParameterValue("core.conf", "LIST_ROLE_BY", null, $roleOrGroup["LIST_ROLE_BY"]);
                        if (is_array($listUserRolesOnly) && isset($listUserRolesOnly["group_switch_value"])) {
                            switch ($listUserRolesOnly["group_switch_value"]) {
                                case "userroles":
                                    $listRoleType = true;
                                    break;
                                case "allroles":
                                    $listRoleType = false;
                                    break;
                                default;
                                    break;
                            }
                        }
                    }

                    switch (strtolower($roleOrGroup["group_switch_value"])) {
                        case 'user':
                            // donothing
                            break;
                        case 'group':
                            $authGroups = AuthService::listChildrenGroups($baseGroup);
                            foreach ($authGroups as $gId => $gName) {
                                $allGroups["AJXP_GRP_" . rtrim($baseGroup, "/")."/".ltrim($gId, "/")] = $gName;
                            }
                            break;
                        case 'role':
                            $allGroups = $this->getUserRoleList($loggedUser, $rolePrefix, $includeString, $excludeString, $listRoleType);
                            break;
                        case 'rolegroup';
                            $groups = array();
                            $authGroups = AuthService::listChildrenGroups($baseGroup);
                            foreach ($authGroups as $gId => $gName) {
                                $groups["AJXP_GRP_" . rtrim($baseGroup, "/")."/".ltrim($gId, "/")] = $gName;
                            }
                            $roles = $this->getUserRoleList($loggedUser, $rolePrefix, $includeString, $excludeString, $listRoleType);

                            empty($groups) ? $allGroups = $roles : (empty($roles) ? $allGroups = $groups : $allGroups = array_merge($groups, $roles));
                            //$allGroups = array_merge($groups, $roles);
                            break;
                        default;
                            break;
                    }
                }


                $users = "";
                $index = 0;
                if ($regexp != null && (!count($allUsers) || (!empty($crtValue) && !array_key_exists(strtolower($crtValue), $allUsers)))  && ConfService::getCoreConf("USER_CREATE_USERS", "conf") && !$existingOnly) {
                    $users .= "<li class='complete_user_entry_temp' data-temporary='true' data-label='$crtValue'><span class='user_entry_label'>$crtValue (".$mess["448"].")</span></li>";
                } else if ($existingOnly && !empty($crtValue)) {
                    $users .= "<li class='complete_user_entry_temp' data-temporary='true' data-label='$crtValue' data-entry_id='$crtValue'><span class='user_entry_label'>$crtValue</span></li>";
                }
                $mess = ConfService::getMessages();
                if ($regexp == null && !$usersOnly) {
                    $users .= "<li class='complete_group_entry' data-group='AJXP_GRP_/' data-label='".$mess["447"]."'><span class='user_entry_label'>".$mess["447"]."</span></li>";
                }
                $indexGroup = 0;
                if (!$usersOnly && is_array($allGroups)) {
                    foreach ($allGroups as $groupId => $groupLabel) {
                        if ($regexp == null ||  preg_match("/$regexp/i", $groupLabel)) {
                            $users .= "<li class='complete_group_entry' data-group='$groupId' data-label='$groupLabel' data-entry_id='$groupId'><span class='user_entry_label'>".$groupLabel."</span></li>";
                            $indexGroup++;
                        }
                        if($indexGroup == $limit) break;
                    }
                }
                if ($regexp == null && method_exists($this, "listUserTeams")) {
                    $teams = $this->listUserTeams();
                    foreach ($teams as $tId => $tData) {
                        $users.= "<li class='complete_group_entry' data-group='/AJXP_TEAM/$tId' data-label='[team] ".$tData["LABEL"]."'><span class='user_entry_label'>[team] ".$tData["LABEL"]."</span></li>";
                    }
                }
                foreach ($allUsers as $userId => $userObject) {
                    if($userObject->getId() == $loggedUser->getId()) continue;
                    if ( ( !$userObject->hasParent() &&  ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING", "conf")) || $userObject->getParent() == $loggedUser->getId() ) {
                        $userLabel = $userObject->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, $userId);
                        //if($regexp != null && ! (preg_match("/$regexp/i", $userId) || preg_match("/$regexp/i", $userLabel)) ) continue;
                        if(empty($userLabel)) $userLabel = $userId;
                        $userDisplay = ($userLabel == $userId ? $userId : $userLabel . " ($userId)");
                        if (ConfService::getCoreConf("USERS_LIST_HIDE_LOGIN", "conf") == true && $userLabel != $userId) {
                            $userDisplay = $userLabel;
                        }
                        $users .= "<li class='complete_user_entry' data-label='$userLabel' data-entry_id='$userId'><span class='user_entry_label'>".$userDisplay."</span></li>";
                        $index ++;
                    }
                    if($index == $limit) break;
                }
                if (strlen($users)) {
                    print("<ul>".$users."</ul>");
                }
                AuthService::setGroupFiltering(true);

                break;

            case "load_repository_info":

                $data = array();
                $repo = ConfService::getRepository();
                if($repo != null){
                    $users = AuthService::countUsersForRepository(ConfService::getRepository()->getId(), true);
                    $data["core.users"] = $users;
                    if(isSet($httpVars["collect"]) && $httpVars["collect"] == "true"){
                        AJXP_Controller::applyHook("repository.load_info", array(&$data));
                    }
                }
                HTMLWriter::charsetHeader("application/json");
                echo json_encode($data);

                break;

            case "get_binary_param" :

                if (isSet($httpVars["tmp_file"])) {
                    $file = AJXP_Utils::getAjxpTmpDir()."/".AJXP_Utils::securePath($httpVars["tmp_file"]);
                    if (isSet($file)) {
                        header("Content-Type:image/png");
                        readfile($file);
                    }
                } else if (isSet($httpVars["binary_id"])) {
                    if (isSet($httpVars["user_id"]) && AuthService::getLoggedUser() != null && AuthService::getLoggedUser()->isAdmin()) {
                        $context = array("USER" => $httpVars["user_id"]);
                    } else {
                        $context = array("USER" => AuthService::getLoggedUser()->getId());
                    }
                    $this->loadBinary($context, $httpVars["binary_id"]);
                }
            break;

            case "get_global_binary_param" :

                if (isSet($httpVars["tmp_file"])) {
                    $file = AJXP_Utils::getAjxpTmpDir()."/".AJXP_Utils::securePath($httpVars["tmp_file"]);
                    if (isSet($file)) {
                        header("Content-Type:image/png");
                        readfile($file);
                    }
                } else if (isSet($httpVars["binary_id"])) {
                    $this->loadBinary(array(), $httpVars["binary_id"]);
                }
            break;

            case "store_binary_temp" :

                if (count($fileVars)) {
                    $keys = array_keys($fileVars);
                    $boxData = $fileVars[$keys[0]];
                    $err = AJXP_Utils::parseFileDataErrors($boxData);
                    if ($err != null) {

                    } else {
                        $rand = substr(md5(time()), 0, 6);
                        $tmp = $rand."-". $boxData["name"];
                        @move_uploaded_file($boxData["tmp_name"], AJXP_Utils::getAjxpTmpDir()."/". $tmp);
                    }
                }
                if (isSet($tmp) && file_exists(AJXP_Utils::getAjxpTmpDir()."/".$tmp)) {
                    print('<script type="text/javascript">');
                    print('parent.formManagerHiddenIFrameSubmission("'.$tmp.'");');
                    print('</script>');
                }

                break;
            default;
            break;
        }
        if (isset($logMessage) || isset($errorMessage)) {
            $xmlBuffer .= AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null), false);
        }

        if (isset($requireAuth)) {
            $xmlBuffer .= AJXP_XMLWriter::requireAuth(false);
        }

        return $xmlBuffer;
    }

    /**
     * @param $userObject AbstractAjxpUser
     * @param $rolePrefix get all roles with prefix
     * @param $includeString get roles in this string
     * @param $excludeString eliminate roles in this string
     * @param bool $byUserRoles
     * @return array
     */
    public function getUserRoleList($userObject, $rolePrefix, $includeString, $excludeString, $byUserRoles = false)
    {
        if ($userObject) {
            if ($byUserRoles) {
                $allUserRoles = $userObject->getRoles();
            } else {
                $allUserRoles = AuthService::getRolesList(array(), true);
            }
            $allRoles = array();
            if (isset($allUserRoles)) {

                // Exclude
                if ($excludeString) {
                    if (strpos($excludeString, "preg:") !== false) {
                        $matchFilterExclude = "/" . str_replace("preg:", "", $excludeString) . "/i";
                    } else {
                        $valueFiltersExclude = array_map("trim", explode(",", $excludeString));
                        $valueFiltersExclude = array_map("strtolower", $valueFiltersExclude);
                    }
                }

                // Include
                if ($includeString) {
                    if (strpos($includeString, "preg:") !== false) {
                        $matchFilterInclude = "/" . str_replace("preg:", "", $includeString) . "/i";
                    } else {
                        $valueFiltersInclude = array_map("trim", explode(",", $includeString));
                        $valueFiltersInclude = array_map("strtolower", $valueFiltersInclude);
                    }
                }

                foreach ($allUserRoles as $roleId => $role) {
                    if (!empty($rolePrefix) && strpos($roleId, $rolePrefix) === false) continue;
                    if (isSet($matchFilterExclude) && preg_match($matchFilterExclude, substr($roleId, strlen($rolePrefix)))) continue;
                    if (isSet($valueFiltersExclude) && in_array(strtolower(substr($roleId, strlen($rolePrefix))), $valueFiltersExclude)) continue;
                    if (isSet($matchFilterInclude) && !preg_match($matchFilterInclude, substr($roleId, strlen($rolePrefix)))) continue;
                    if (isSet($valueFiltersInclude) && !in_array(strtolower(substr($roleId, strlen($rolePrefix))), $valueFiltersInclude)) continue;
                    if(is_a($role, "AJXP_Role")) $roleObject = $role;
                    else $roleObject = AuthService::getRole($roleId);
                    $label = $roleObject->getLabel();
                    $label = !empty($label) ? $label : substr($roleId, strlen($rolePrefix));
                    $allRoles[$roleId] = $label;
                }
            }
            return $allRoles;
        }
    }
}
