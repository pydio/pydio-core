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
namespace Pydio\Conf\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Pydio\Access\Core\IAjxpWrapperProvider;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Message\RegistryMessage;
use Pydio\Core\Http\Message\ReloadMessage;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Message\XMLDocMessage;
use Pydio\Core\Http\Message\XMLMessage;
use Pydio\Core\Http\Response\AsyncResponseStream;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\CacheService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\TextEncoder;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 * @class AbstractConfDriver
 * Abstract representation of a conf driver. Must be implemented by the "conf" plugin
 */
abstract class AbstractConfDriver extends Plugin
{
    public $options;
    public $driverType = "conf";

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        $options = $this->options;

        // BACKWARD COMPATIBILIY PREVIOUS CONFIG VIA OPTIONS
        if (isSet($options["CUSTOM_DATA"])) {
            $custom = $options["CUSTOM_DATA"];
            $serverSettings = $this->getXPath()->query('//server_settings')->item(0);
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

    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if ($contribNode->nodeName == 'client_configs' && !ConfService::getCoreConf("WEBDAV_ENABLE")) {
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
            $webdavCompNodeList = $actionXpath->query('component_config/additional_tab[@id="webdav_pane"]', $contribNode);
            if ($webdavCompNodeList->length) {
                $contribNode->removeChild($webdavCompNodeList->item(0)->parentNode);
            }
        }

        if($contribNode->nodeName != "actions") return;

        // WEBDAV ACTION
        if (!ConfService::getCoreConf("WEBDAV_ENABLE")) {
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="webdav_preferences"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
        }

        // SWITCH TO DASHBOARD ACTION
        $u = $ctx->getUser();
        $access = true;
        if($u == null) $access = false;
        else {
            $acl = $u->getMergedRole()->getAcl("ajxp_user");
            if(empty($acl)) $access = false;
        }
        if(!$access){
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="switch_to_user_dashboard"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
        } 

        $hasExposed = PluginsService::searchManifestsWithCache("//server_settings/param[contains(@scope,'user') and @expose='true']", function ($nodes) {
            return (is_array($nodes) && count($nodes));
        }, function($test){
            return ($test !== null);
        });

        if (!$hasExposed) {
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="custom_data_edit"]', $contribNode);
            $publicUrlNode = $publicUrlNodeList->item(0);
            $contribNode->removeChild($publicUrlNode);
        }

        // CREATE A NEW REPOSITORY
        if (!ConfService::getCoreConf("USER_CREATE_REPOSITORY", "conf")) {
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="user_create_repository"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="user_delete_repository"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
        }

        // CREATE A NEW USER
        if (!ConfService::getCoreConf("USER_CREATE_USERS", "conf")) {
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="user_create_user"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="user_update_user"]', $contribNode);
            if ($publicUrlNodeList->length) {
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
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
     * @return array
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
        * @param array $options
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
          || $pluginId == "core.auth" || strpos($pluginId, "auth.") === 0
          || $pluginId == "core.cache" || strpos($pluginId, "cache.") === 0);
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
     * @return \Pydio\Access\Core\Model\Repository[]
     */
    abstract public function listRepositories($user = null);

    /**
     * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
     * @param array $criteria This parameter can take the following keys
     *      - Search keys "uuid", "parent_uuid", "owner_user_id", "display", "accessType", "isTemplate", "slug", "groupPath",
     *        Search values can be either string, array of string, AJXP_FILTER_EMPTY, AJXP_FILTER_NOT_EMPTY or regexp:RegexpString
     *      - or "role" => AJXP_Role object: will search repositories accessible to this role
     *      - ORDERBY = array("KEY"=>"", "DIR"=>""), GROUPBY, CURSOR = array("OFFSET" => 0, "LIMIT", 30)
     *      - COUNT_ONLY
     * @param $count int fill this integer with a count
     * @return \Pydio\Access\Core\Model\Repository[]
     */
    abstract public function listRepositoriesWithCriteria($criteria, &$count=null);


    /**
     * Retrieve a Repository given its unique ID.
     *
     * @param String $repositoryId
     * @return \Pydio\Access\Core\Model\Repository
     */
    abstract public function getRepositoryById($repositoryId);
    /**
     * Retrieve a Repository given its alias.
     *
     * @param String $repositorySlug
     * @return \Pydio\Access\Core\Model\Repository
     */
    abstract public function getRepositoryByAlias($repositorySlug);
    /**
     * Stores a repository, new or not.
     *
     * @param \Pydio\Access\Core\Model\Repository $repositoryObject
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
     * @param AbstractAjxpUser $userObject
     * @return void
     */
    abstract public function updateRole($role, $userObject = null);

    /**
     * @abstract
     * @param AJXP_Role|String $role
     * @return void
     */
    abstract public function deleteRole($role);

    /**
     * Compute the most recent date where one of these roles where updated.
     *
     * @param $rolesIdsList
     * @return int
     */
    public function rolesLastUpdated($rolesIdsList){
        return 0;
    }

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
     * @param array $data
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
        $test=CacheService::fetch("shared", "pydio:user:" . $userId);
        if($test !== false && $test instanceof AbstractAjxpUser){
            if($test->getPersonalRole() === null){
                $test->personalRole = $test->roles["AJXP_USR_/".$userId];
            }
            $test->recomputeMergedRole();
            return $test;
        }
        $userId = AuthService::filterUserSensitivity($userId);
        $abstractUser = $this->instantiateAbstractUserImpl($userId);
        if (!$abstractUser->storageExists()) {
            AuthService::updateDefaultRights($abstractUser);
        }
        AuthService::updateAutoApplyRole($abstractUser);
        AuthService::updateAuthProvidedData($abstractUser);
        $args = array(&$abstractUser);
        Controller::applyIncludeHook("include.user.updateUserObject", $args);
        CacheService::save("shared", "pydio:user:" . $userId, $abstractUser);
        return $abstractUser;
    }

    /**
     * Function for deleting a user
     *
     * @param String $userId
     * @param array $deletedSubUsers
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
     * @param bool $splitByType
     * @return array An array of role ids
     */
    abstract public function getRolesForRepository($repositoryId, $rolePrefix = '', $splitByType = false);
    /**
     * @abstract
     * @param string $repositoryId
     * @param boolean $details
     * @param boolean $admin
     * @return Integer|array
     */
    abstract public function countUsersForRepository($repositoryId, $details = false, $admin = false);


    /**
     * @param AbstractAjxpUser[] $flatUsersList
     * @param string $baseGroup
     * @param bool $fullTree
     * @return void
     */
    abstract public function filterUsersByGroup(&$flatUsersList, $baseGroup = "/", $fullTree = false);

    /**
     * Check if group already exists
     * @param string $groupPath
     * @return boolean
     */
    abstract public function groupExists($groupPath);

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

        $exposed = PluginsService::searchManifestsWithCache("//server_settings/param[contains(@scope,'user') and @expose='true']", function($nodes){
            $result = [];
            foreach($nodes as $exposed_prop){
                $parentNode = $exposed_prop->parentNode->parentNode;
                $pluginId = $parentNode->getAttribute("id");
                if (empty($pluginId)) {
                    $pluginId = $parentNode->nodeName.".".$parentNode->getAttribute("name");
                }
                $paramName = $exposed_prop->getAttribute("name");
                $result[] = array("PLUGIN_ID" => $pluginId, "NAME" => $paramName);
            }
            return $result;
        });

        foreach ($exposed as $exposedProp) {
            $value = $userObject->mergedRole->filterParameterValue($exposedProp["PLUGIN_ID"], $exposedProp["NAME"], AJXP_REPO_SCOPE_ALL, "");
            $prefs[$exposedProp["NAME"]] = array("value" => $value, "type" => "string", "pluginId" => $exposedProp["PLUGIN_ID"]);
        }

        return $prefs;
    }

    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $httpVars = $requestInterface->getParsedBody();
        $action = $requestInterface->getAttribute("action");
        /** @var ContextInterface $ctx */
        $ctx    = $requestInterface->getAttribute("ctx");
        $loggedUser = $ctx->getUser();

        foreach ($httpVars as $getName=>$getValue) {
            $$getName = Utils::securePath($getValue);
        }

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
                    throw new PydioException("Trying to switch to an unkown repository!");
                }
                ConfService::switchRootDir($repository_id);
                PluginsService::getInstance();
                if (AuthService::usersEnabled() && $loggedUser !== null) {
                    $loggedUser->setArrayPref("repository_last_connected", $repository_id, time());
                    $loggedUser->save("user");
                }

                $this->logInfo("Switch Repository", array("rep. id"=>$repository_id));

            break;

            //------------------------------------
            //	SEND XML REGISTRY
            //------------------------------------
            case "get_xml_registry" :
            case "state" :
                
                
                $clone = PluginsService::getInstance(Context::fromGlobalServices())->getFilteredXMLRegistry(true, true);
                $clonePath = new \DOMXPath($clone);
                if(!AJXP_SERVER_DEBUG){
                    $serverCallbacks = $clonePath->query("//serverCallback|hooks");
                    foreach ($serverCallbacks as $callback) {
                        $callback->parentNode->removeChild($callback);
                    }
                }
                $xPath = null;
                if (isSet($httpVars["xPath"])) {
                    $xPath = ltrim(Utils::securePath($httpVars["xPath"]), "/");
                }
                $json = (isSet($httpVars["format"]) && $httpVars["format"] == "json");
                $message = new RegistryMessage($clone, $xPath, $clonePath);
                if(empty($xPath) && !$json){
                    $string = $message->toXML();
                    $etag = md5($string);
                    $match = isSet($requestInterface->getServerParams()["HTTP_IF_NONE_MATCH"])?$requestInterface->getServerParams()["HTTP_IF_NONE_MATCH"]:'';
                    if($match == $etag){
                        header('HTTP/1.1 304 Not Modified');
                        $responseInterface = $responseInterface->withStatus(304);
                        break;
                    }else{
                        $responseInterface = $responseInterface
                            ->withHeader("Cache-Control", "public, max-age=31536000")
                            ->withHeader("ETag", $etag);
                    }
                }
                Utils::safeIniSet("zlib.output_compression", "4096");
                $x = new SerializableResponseStream();
                $responseInterface = $responseInterface->withBody($x);
                $x->addChunk($message);

            break;

            //------------------------------------
            //	BOOKMARK BAR
            //------------------------------------
            case "get_bookmarks":

                $bmUser = null;
                if (AuthService::usersEnabled() && $loggedUser != null) {
                    $bmUser = $loggedUser;
                } else if (!AuthService::usersEnabled()) {
                    $confStorage = ConfService::getConfStorageImpl();
                    $bmUser = $confStorage->createUserObject("shared");
                }
                $x = new SerializableResponseStream();
                $responseInterface = $responseInterface->withBody($x);
                if ($bmUser == null) {
                    break;
                }
                /** @var ContextInterface $ctx */
                $ctx = $requestInterface->getAttribute("ctx");
                $driver = $ctx->getRepository()->getDriverInstance();
                if (!($driver instanceof IAjxpWrapperProvider)) {
                    $driver = false;
                }


                $repositoryId = $ctx->getRepositoryId();
                if (isSet($httpVars["bm_action"]) && isset($httpVars["bm_path"])) {
                    $bmPath = Utils::decodeSecureMagic($httpVars["bm_path"]);
                    if ($httpVars["bm_action"] == "add_bookmark") {
                        $title = "";
                        if(isSet($httpVars["bm_title"])) $title = Utils::decodeSecureMagic($httpVars["bm_title"]);
                        if($title == "" && $bmPath=="/") $title = ConfService::getCurrentRootDirDisplay();
                        $bmUser->addBookmark($repositoryId, $bmPath, $title);
                        if ($driver) {
                            $node = new \Pydio\Access\Core\Model\AJXP_Node($driver->getResourceUrl($bmPath));
                            $node->setMetadata("ajxp_bookmarked", array("ajxp_bookmarked" => "true"), true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                        }
                    } else if ($httpVars["bm_action"] == "delete_bookmark") {
                        $bmUser->removeBookmark($repositoryId, $bmPath);
                        if ($driver) {
                            $node = new \Pydio\Access\Core\Model\AJXP_Node($driver->getResourceUrl($bmPath));
                            $node->removeMetadata("ajxp_bookmarked", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                        }
                    } else if ($httpVars["bm_action"] == "rename_bookmark" && isset($httpVars["bm_title"])) {
                        $title = Utils::decodeSecureMagic($httpVars["bm_title"]);
                        $bmUser->renameBookmark($repositoryId, $bmPath, $title);
                    }
                    Controller::applyHook("msg.instant", array("<reload_bookmarks/>",
                            $repositoryId,
                            $loggedUser->getId())
                    );

                    if (AuthService::usersEnabled() && $loggedUser != null) {
                        $bmUser->save("user");
                        AuthService::updateUser($bmUser);
                    } else if (!AuthService::usersEnabled()) {
                        $bmUser->save("user");
                    }
                }
                $doc = new XMLMessage(XMLWriter::writeBookmarks($bmUser->getBookmarks($repositoryId), $ctx->getRepository(), false, isset($httpVars["format"])?$httpVars["format"]:"legacy"));
                $x = new SerializableResponseStream([$doc]);
                $responseInterface = $responseInterface->withBody($x);

            break;

            //------------------------------------
            //	SAVE USER PREFERENCE
            //------------------------------------
            case "save_user_pref":

                $i = 0;
                while (isSet($httpVars["pref_name_".$i]) && isSet($httpVars["pref_value_".$i])) {
                    $prefName = Utils::sanitize($httpVars["pref_name_".$i], AJXP_SANITIZE_ALPHANUM);
                    $prefValue = \Pydio\Core\Utils\Utils::sanitize(TextEncoder::magicDequote($httpVars["pref_value_".$i]));
                    if($prefName == "password") continue;
                    if ($prefName != "pending_folder" && $loggedUser == null) {
                        $i++;
                        continue;
                    }
                    $loggedUser->setPref($prefName, $prefValue);
                    $loggedUser->save("user");
                    AuthService::updateUser($loggedUser);
                    $i++;
                }

                $responseInterface = $responseInterface->withHeader("Content-type", "text/plain");
                $responseInterface->getBody()->write("SUCCESS");

            break;

            //------------------------------------
            //	SAVE USER PREFERENCE
            //------------------------------------
            case "custom_data_edit":
            case "user_create_user":

                $data = array();

                if ($action == "user_create_user" && isSet($httpVars["NEW_new_user_id"])) {
                    $updating = false;
                    Utils::parseStandardFormParameters($ctx, $httpVars, $data, "NEW_");
                    $original_id = Utils::decodeSecureMagic($data["new_user_id"]);
                    $data["new_user_id"] = Utils::decodeSecureMagic($data["new_user_id"], AJXP_SANITIZE_EMAILCHARS);
                    if($original_id != $data["new_user_id"]){
                        throw new \Exception(str_replace("%s", $data["new_user_id"], $mess["ajxp_conf.127"]));
                    }
                    if (AuthService::userExists($data["new_user_id"],"w")) {
                        throw new \Exception($mess["ajxp_conf.43"]);
                    }
                    $limit = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "USER_SHARED_USERS_LIMIT", AJXP_REPO_SCOPE_ALL, "");
                    if (!empty($limit) && intval($limit) > 0) {
                        $count = count($this->getUserChildren($loggedUser->getId()));
                        if ($count >= $limit) {
                            throw new \Exception($mess['483']);
                        }
                    }

                    AuthService::createUser($data["new_user_id"], $data["new_password"]);
                    $loggedUser = ConfService::getConfStorageImpl()->createUserObject($data["new_user_id"]);
                    $loggedUser->setParent($loggedUser->getId());
                    $loggedUser->save('superuser');
                    $loggedUser->getPersonalRole()->clearAcls();
                    $loggedUser->setGroupPath($loggedUser->getGroupPath());
                    $loggedUser->setProfile("shared");

                } else if($action == "user_create_user" && isSet($httpVars["NEW_existing_user_id"])){

                    $updating = true;
                    \Pydio\Core\Utils\Utils::parseStandardFormParameters($ctx, $httpVars, $data, "NEW_");
                    $userId = $data["existing_user_id"];
                    if(!AuthService::userExists($userId)){
                        throw new \Exception("Cannot find user");
                    }
                    $loggedUser = ConfService::getConfStorageImpl()->createUserObject($userId);
                    if($loggedUser->getParent() != $loggedUser->getId()){
                        throw new \Exception("Cannot find user");
                    }
                    if(!empty($data["new_password"])){
                        AuthService::updatePassword($userId, $data["new_password"]);
                    }

                } else {
                    $updating = false;
                    Utils::parseStandardFormParameters($ctx, $httpVars, $data, "PREFERENCES_");
                }

                $paramNodes = PluginsService::getInstance()->searchAllManifests("//server_settings/param[contains(@scope,'user') and @expose='true']", "node", false, false, true);
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
                                $pRole = null;
                                $persRole = $loggedUser->getPersonalRole();
                                if($loggedUser instanceof AbstractAjxpUser) $pRole = $loggedUser->parentRole;
                                if ($data[$name] === ""
                                    || $pRole === null || $pRole->filterParameterValue($pluginId, $name, AJXP_REPO_SCOPE_ALL, "") != $data[$name]
                                    || $persRole->filterParameterValue($pluginId, $name, AJXP_REPO_SCOPE_ALL, "") != $data[$name])
                                {
                                    $persRole->setParameterValue($pluginId, $name, $data[$name]);
                                    $rChanges = true;
                                }
                            }
                        }
                    }
                }
                if ($rChanges) {
                    AuthService::updateRole($loggedUser->getPersonalRole(), $loggedUser);
                    $loggedUser->recomputeMergedRole();
                    if ($action == "custom_data_edit") {
                        AuthService::updateUser($loggedUser);
                    }
                }

                if ($action == "user_create_user") {

                    Controller::applyHook($updating?"user.after_update":"user.after_create", array($loggedUser));
                    if (isset($data["send_email"]) && $data["send_email"] == true && !empty($data["email"])) {
                        $mailer = PluginsService::getInstance()->getUniqueActivePluginForType("mailer");
                        if ($mailer !== false) {
                            $mess = ConfService::getMessages();
                            $link = Utils::detectServerURL();
                            $apptitle = ConfService::getCoreConf("APPLICATION_TITLE");
                            $subject = str_replace("%s", $apptitle, $mess["507"]);
                            $body = str_replace(array("%s", "%link", "%user", "%pass"), array($apptitle, $link, $data["new_user_id"], $data["new_password"]), $mess["508"]);
                            $mailer->sendMail(array($data["email"]), $subject, $body);
                        }
                    }

                    $responseInterface = $responseInterface->withHeader("Content-type", "text/plain");
                    $responseInterface->getBody()->write("SUCCESS");

                } else {

                    $x = new SerializableResponseStream();
                    $responseInterface = $responseInterface->withBody($x);
                    $x->addChunk(new UserMessage($mess["241"]));

                }

            break;

            case "user_update_user":

                if(!isSet($httpVars["user_id"])) {
                    throw new \Exception("invalid arguments");
                }
                $userId = $httpVars["user_id"];
                if(!\Pydio\Core\Services\AuthService::userExists($userId)){
                    throw new \Exception("Cannot find user");
                }
                $loggedUser = ConfService::getConfStorageImpl()->createUserObject($userId);
                if($loggedUser->getParent() != $loggedUser->getId()){
                    throw new \Exception("Cannot find user");
                }
                $paramsString = ConfService::getCoreConf("NEWUSERS_EDIT_PARAMETERS", "conf");
                $result = array();
                $params = explode(",", $paramsString);
                foreach($params as $p){
                    $result[$p] = $loggedUser->getPersonalRole()->filterParameterValue("core.conf", $p, AJXP_REPO_SCOPE_ALL, "");
                }

                $responseInterface = $responseInterface->withHeader("Content-type", "application/json");
                $responseInterface->getBody()->write(json_encode($result));

            break;

            //------------------------------------
            // WEBDAV PREFERENCES
            //------------------------------------
            case "webdav_preferences" :

                $webdavActive = false;
                $passSet = false;
                $digestSet = false;
                // Detect http/https and host
                if (ConfService::getCoreConf("WEBDAV_BASEHOST") != "") {
                    $baseURL = ConfService::getCoreConf("WEBDAV_BASEHOST");
                } else {
                    $baseURL = \Pydio\Core\Utils\Utils::detectServerURL();
                }
                $webdavBaseUrl = $baseURL.ConfService::getCoreConf("WEBDAV_BASEURI")."/";
                $davData = $loggedUser->getPref("AJXP_WEBDAV_DATA");
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
                            $user = $loggedUser->getId();
                            $secret = (defined("AJXP_SAFE_SECRET_KEY")? AJXP_SAFE_SECRET_KEY:"\1CDAFx¨op#");
                            $password = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($user.$secret), $password, MCRYPT_MODE_ECB));
                        }
                        $davData["PASS"] = $password;
                    }
                    $loggedUser->setPref("AJXP_WEBDAV_DATA", $davData);
                    $loggedUser->save("user");
                }
                if (!empty($davData)) {
                    $webdavActive = (isSet($davData["ACTIVE"]) && $davData["ACTIVE"]===true);
                    $passSet = (isSet($davData["PASS"]));
                }
                $repoList = ConfService::getRepositoriesList();
                $davRepos = array();
                foreach ($repoList as $repoIndex => $repoObject) {
                    $accessType = $repoObject->getAccessType();
                    $driver = PluginsService::getInstance()->getPluginByTypeName("access", $accessType);
                    if (($driver instanceof IAjxpWrapperProvider) && !$repoObject->getOption("AJXP_WEBDAV_DISABLED") && ($loggedUser->canRead($repoIndex) || $loggedUser->canWrite($repoIndex))) {
                        $davRepos[$repoIndex] = $webdavBaseUrl . "" . ($repoObject->getSlug() == null ? $repoObject->getId() : $repoObject->getSlug());
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

                $responseInterface = $responseInterface->withHeader("Content-type", "application/json");
                $responseInterface->getBody()->write(json_encode($prefs));

            break;

            case  "get_user_template_logo":

                $tplId = $httpVars["template_id"];
                $iconFormat = $httpVars["icon_format"];
                $repo = ConfService::getRepositoryById($tplId);
                $logo = $repo->getOption("TPL_ICON_".strtoupper($iconFormat));

                $async = new AsyncResponseStream(function() use($logo, $iconFormat){
                    if (isSet($logo) && is_file(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo)) {
                        header("Content-Type: ".Utils::getImageMimeType($logo)."; name=\"".$logo."\"");
                        header("Content-Length: ".filesize(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo));
                        header('Pragma:');
                        header('Cache-Control: public');
                        header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                        header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
                        readfile(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo);
                    } else {
                        $logo = "default_template_logo-".($iconFormat == "small"?16:22).".png";
                        header("Content-Type: ".Utils::getImageMimeType($logo)."; name=\"".$logo."\"");
                        header("Content-Length: ".filesize(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/core.conf/".$logo));
                        header('Pragma:');
                        header('Cache-Control: public');
                        header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                        header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
                        readfile(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/core.conf/".$logo);
                    }
                });
                $responseInterface = $responseInterface->withBody($async);

            break;

            case  "get_user_templates_definition":

                $xml = "<repository_templates>";
                $count = 0;
                $repositories = ConfService::listRepositoriesWithCriteria(array(
                    "isTemplate" => 1
                ), $count);
                $pServ = PluginsService::getInstance();
                foreach ($repositories as $repo) {
                    if(!$repo->isTemplate) continue;
                    if(!$repo->getOption("TPL_USER_CAN_CREATE")) continue;
                    $repoId = $repo->getId();
                    $repoLabel = $repo->getDisplay();
                    $repoType = $repo->getAccessType();
                    $xml .= "<template repository_id=\"$repoId\" repository_label=\"$repoLabel\" repository_type=\"$repoType\">";
                    $driverPlug = $pServ->getPluginByTypeName("access", $repoType);
                    $params = $driverPlug->getManifestRawContent("//param", "node");
                    $tplDefined = $repo->getOptionsDefined();
                    $defaultLabel = '';
                    foreach ($params as $paramNode) {
                        $name = $paramNode->getAttribute("name");
                        if ( strpos($name, "TPL_") === 0 ) {
                            if ($name == "TPL_DEFAULT_LABEL") {
                                $defaultLabel = str_replace("AJXP_USER", $loggedUser->getId(), $repo->getOption($name));
                            }
                            continue;
                        }
                        if( in_array($paramNode->getAttribute("name"), $tplDefined) ) continue;
                        if($paramNode->getAttribute('no_templates') == 'true') continue;
                        $xml.= XMLWriter::replaceAjxpXmlKeywords($paramNode->ownerDocument->saveXML($paramNode));
                    }
                    // ADD LABEL
                    $xml .= '<param name="DISPLAY" type="string" label="'.$mess[359].'" description="'.$mess[429].'" mandatory="true" default="'.$defaultLabel.'"/>';
                    $xml .= "</template>";
                }

                $xml.= "</repository_templates>";
                $doc = new XMLDocMessage($xml);
                $x = new SerializableResponseStream([$doc]);
                $responseInterface = $responseInterface->withBody($x);


            break;

            case "user_create_repository" :

                $tplId = $httpVars["template_id"];
                $tplRepo = ConfService::getRepositoryById($tplId);
                $options = array();
                Utils::parseStandardFormParameters($ctx, $httpVars, $options);
                $newRep = $tplRepo->createTemplateChild(Utils::sanitize($httpVars["DISPLAY"]), $options, $loggedUser->getId(), $loggedUser->getId());
                $gPath = $loggedUser->getGroupPath();
                if (!empty($gPath)) {
                    $newRep->setGroupPath($gPath);
                }
                $res = ConfService::addRepository($newRep);
                if ($res == -1) {
                    throw new PydioException($mess[426], 426);
                }

                // Make sure we do not overwrite otherwise loaded rights.
                $loggedUser->load();
                $loggedUser->getPersonalRole()->setAcl($newRep->getUniqueId(), "rw");
                $loggedUser->save("superuser");
                $loggedUser->recomputeMergedRole();
                AuthService::updateUser($loggedUser);

                $x = new SerializableResponseStream();
                $responseInterface = $responseInterface->withBody($x);
                $x->addChunk(new UserMessage($mess[425]));
                $x->addChunk(new ReloadMessage("", $newRep->getUniqueId()));
                $x->addChunk(new XMLMessage("<reload_instruction object=\"repository_list\"/>"));

            break;

            case "user_delete_repository" :

                $repoId = $httpVars["repository_id"];
                $repository = ConfService::getRepositoryById($repoId);
                if (!$repository->getUniqueUser()||$repository->getUniqueUser()!= $loggedUser->getId()) {
                    throw new \Exception("You are not allowed to perform this operation!");
                }
                $res = ConfService::deleteRepository($repoId);

                if ($res == -1) {
                    throw new PydioException($mess[427]);
                }

                // Make sure we do not override remotely set rights
                $loggedUser->load();
                $loggedUser->getPersonalRole()->setAcl($repoId, "");
                $loggedUser->save("superuser");
                AuthService::updateUser($loggedUser);

                $x = new SerializableResponseStream();
                $responseInterface = $responseInterface->withBody($x);
                $x->addChunk(new UserMessage($mess[428]));
                $x->addChunk(new XMLMessage("<reload_instruction object=\"repository_list\"/>"));

            break;

            case "user_delete_user":

                $userId = $httpVars["user_id"];
                $userObject = ConfService::getConfStorageImpl()->createUserObject($userId);
                if ($userObject == null || !$userObject->hasParent() || $userObject->getParent() != $loggedUser->getId()) {
                    throw new \Exception("You are not allowed to edit this user");
                }
                AuthService::deleteUser($userId);
                $responseInterface = $responseInterface->withHeader("Content-type", "text/plain");
                $responseInterface->getBody()->write("SUCCESS");

                break;

            case "user_list_authorized_users" :

                $defaultFormat = "html";
                if(isSet($httpVars["format"]) && $httpVars["format"] == "xml"){
                    header('Content-Type: text/xml; charset=UTF-8');
                    header('Cache-Control: no-cache');
                    print('<?xml version="1.0" encoding="UTF-8"?>');
                }else{
                    HTMLWriter::charsetHeader();
                }
                if (!ConfService::getAuthDriverImpl()->usersEditable()) {
                    break;
                }

                $crtValue = $httpVars["value"];
                $usersOnly = isSet($httpVars["users_only"]) && $httpVars["users_only"] == "true";
                $existingOnly = isSet($httpVars["existing_only"]) && $httpVars["existing_only"] == "true";
                if(!empty($crtValue)) $regexp = '^'.$crtValue;
                else $regexp = null;
                $skipDisplayWithoutRegexp = ConfService::getCoreConf("USERS_LIST_REGEXP_MANDATORY", "conf");
                if($skipDisplayWithoutRegexp && $regexp == null){
                    $users = "";
                    if (method_exists($this, "listUserTeams")) {
                        $teams = $this->listUserTeams();
                        foreach ($teams as $tId => $tData) {
                            $users.= "<li class='complete_group_entry' data-group='/AJXP_TEAM/$tId' data-label=\"[team] ".$tData["LABEL"]."\"><span class='user_entry_label'>[team] ".$tData["LABEL"]."</span></li>";
                        }
                    }
                    print("<ul>$users</ul>");
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
                        $rolePrefix    = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "PREFIX", null, $roleOrGroup["PREFIX"]);
                        $excludeString = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "EXCLUDED", null, $roleOrGroup["EXCLUDED"]);
                        $includeString = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "INCLUDED", null, $roleOrGroup["INCLUDED"]);
                        $listUserRolesOnly = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "LIST_ROLE_BY", null, $roleOrGroup["LIST_ROLE_BY"]);
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
                    $users .= "<li class='complete_group_entry' data-group='AJXP_GRP_/' data-label=\"".$mess["447"]."\"><span class='user_entry_label'>".$mess["447"]."</span></li>";
                }
                $indexGroup = 0;
                if (!$usersOnly && isset($allGroups) && is_array($allGroups)) {
                    foreach ($allGroups as $groupId => $groupLabel) {
                        if ($regexp == null ||  preg_match("/$regexp/i", $groupLabel)) {
                            $users .= "<li class='complete_group_entry' data-group='$groupId' data-label=\"$groupLabel\" data-entry_id='$groupId'><span class='user_entry_label'>".$groupLabel."</span></li>";
                            $indexGroup++;
                        }
                        if($indexGroup == $limit) break;
                    }
                }
                if ($regexp == null && method_exists($this, "listUserTeams") && !$usersOnly) {
                    $teams = $this->listUserTeams();
                    foreach ($teams as $tId => $tData) {
                        $users.= "<li class='complete_group_entry' data-group='/AJXP_TEAM/$tId' data-label=\"[team] ".$tData["LABEL"]."\"><span class='user_entry_label'>[team] ".$tData["LABEL"]."</span></li>";
                    }
                }
                foreach ($allUsers as $userId => $userObject) {
                    if($userObject->getId() == $loggedUser->getId()) continue;
                    if ( ( !$userObject->hasParent() &&  ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING", "conf")) || $userObject->getParent() == $loggedUser->getId() ) {
                        $userLabel = ConfService::getUserPersonalParameter("USER_DISPLAY_NAME", $userObject, "core.conf", $userId);
                        $userAvatar = ConfService::getUserPersonalParameter("avatar", $userObject, "core.conf", "");
                        //if($regexp != null && ! (preg_match("/$regexp/i", $userId) || preg_match("/$regexp/i", $userLabel)) ) continue;
                        $userDisplay = ($userLabel == $userId ? $userId : $userLabel . " ($userId)");
                        if (ConfService::getCoreConf("USERS_LIST_HIDE_LOGIN", "conf") == true && $userLabel != $userId) {
                            $userDisplay = $userLabel;
                        }
                        $userIsExternal = $userObject->hasParent() ? "true":"false";
                        $users .= "<li class='complete_user_entry' data-external=\"$userIsExternal\" data-label=\"$userLabel\" data-avatar='$userAvatar' data-entry_id='$userId'><span class='user_entry_label'>".$userDisplay."</span></li>";
                        $index ++;
                    }
                    if($index == $limit) break;
                }
                print("<ul>".$users."</ul>");
                AuthService::setGroupFiltering(true);

                break;

            case "load_repository_info":

                $data = array();
                $repo = $ctx->getRepository();
                if($repo != null){
                    $users = AuthService::countUsersForRepository($repo->getId(), true);
                    $data["core.users"] = $users;
                    if(isSet($httpVars["collect"]) && $httpVars["collect"] == "true"){
                        Controller::applyHook("repository.load_info", array(&$data));
                    }
                }

                $responseInterface = $responseInterface->withHeader("Content-type", "application/json");
                $responseInterface->getBody()->write(json_encode($data));
                break;

            case "get_binary_param" :

                if (isSet($httpVars["tmp_file"])) {
                    $file = \Pydio\Core\Utils\Utils::getAjxpTmpDir()."/".Utils::securePath($httpVars["tmp_file"]);
                    if (isSet($file)) {
                        session_write_close();
                        header("Content-Type:image/png");
                        readfile($file);
                    }
                } else if (isSet($httpVars["binary_id"])) {
                    if (isSet($httpVars["user_id"]) && $loggedUser != null
                        && ( $loggedUser->getId() == $httpVars["user_id"] || $loggedUser->isAdmin() )) {
                        $context = array("USER" => \Pydio\Core\Utils\Utils::sanitize($httpVars["user_id"], AJXP_SANITIZE_EMAILCHARS));
                    } else if($loggedUser !== null) {
                        $context = array("USER" => $loggedUser->getId());
                    } else {
                        $context = array();
                    }
                    session_write_close();
                    $this->loadBinary($context, \Pydio\Core\Utils\Utils::sanitize($httpVars["binary_id"], AJXP_SANITIZE_ALPHANUM));
                }
            break;

            case "get_global_binary_param" :

                session_write_close();
                if (isSet($httpVars["tmp_file"])) {
                    $file = \Pydio\Core\Utils\Utils::getAjxpTmpDir()."/". \Pydio\Core\Utils\Utils::securePath($httpVars["tmp_file"]);
                    if (isSet($file)) {
                        header("Content-Type:image/png");
                        readfile($file);
                    }
                } else if (isSet($httpVars["binary_id"])) {
                    $this->loadBinary(array(), Utils::sanitize($httpVars["binary_id"], AJXP_SANITIZE_ALPHANUM));
                }
            break;

            case "store_binary_temp" :

                $uploadedFiles = $requestInterface->getUploadedFiles();
                if (count($uploadedFiles)) {
                    /**
                     * @var UploadedFileInterface $boxData
                     */
                    $boxData = array_shift($uploadedFiles);
                    $err = Utils::parseFileDataErrors($boxData);
                    if ($err != null) {

                    } else {
                        $rand = substr(md5(time()), 0, 6);
                        $tmp = $rand."-". $boxData->getClientFilename();
                        $boxData->moveTo(Utils::getAjxpTmpDir() . "/" . $tmp);
                    }
                }
                if (isSet($tmp) && file_exists(Utils::getAjxpTmpDir()."/".$tmp)) {
                    print('<script type="text/javascript">');
                    print('parent.formManagerHiddenIFrameSubmission("'.$tmp.'");');
                    print('</script>');
                }

                break;
            default;
            break;
        }

    }

    /**
     * @param UserInterface $userObject
     * @param string $rolePrefix get all roles with prefix
     * @param string $includeString get roles in this string
     * @param string $excludeString eliminate roles in this string
     * @param bool $byUserRoles
     * @return array
     */
    public function getUserRoleList($userObject, $rolePrefix, $includeString, $excludeString, $byUserRoles = false)
    {
        if (!$userObject){
            return array();
        }
        if ($byUserRoles) {
            $allUserRoles = $userObject->getRoles();
        } else {
            $allUserRoles = \Pydio\Core\Services\AuthService::getRolesList(array(), true);
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
                if($role instanceof AJXP_Role) $roleObject = $role;
                else $roleObject = AuthService::getRole($roleId);
                $label = $roleObject->getLabel();
                $label = !empty($label) ? $label : substr($roleId, strlen($rolePrefix));
                $allRoles[$roleId] = $label;
            }
        }
        return $allRoles;
    }


    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function publishPermissionsMask(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface){
        $mask = array();
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $user = $ctx->getUser();

        if(!AuthService::usersEnabled() || $user == null){
            $responseInterface = new JsonResponse($mask);
            return $responseInterface;
        }
        $repoId = $ctx->getRepositoryId();
        $role = $user->getMergedRole();
        if($role->hasMask($repoId)){
            $fullMask = $role->getMask($repoId);
            foreach($fullMask->flattenTree() as $path => $permission){
                // Do not show if "deny".
                if($permission->denies()) continue;
                $mask[$path] = array(
                    "read" => $permission->canRead(),
                    "write" => $permission->canWrite()
                );
            }
        }
        $responseInterface = new JsonResponse($mask);
        return $responseInterface;

    }
}
