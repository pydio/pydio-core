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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Conf\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Pydio\Access\Core\IAjxpWrapperProvider;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Exception\UserNotFoundException;
use Pydio\Core\Http\Message\RegistryMessage;
use Pydio\Core\Http\Message\ReloadMessage;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Message\XMLDocMessage;
use Pydio\Core\Http\Message\XMLMessage;
use Pydio\Core\Http\Response\AsyncResponseStream;
use Pydio\Core\Http\Response\SerializableResponseStream;

use Pydio\Core\Model\AddressBookItem;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\FilteredUsersList;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Serializer\UserXML;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Crypto;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\OptionsHelper;
use Pydio\Core\Utils\Vars\StatHelper;

use Pydio\Core\Utils\Vars\XMLFilter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Vars\StringHelper;
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

    /**
     * @inheritdoc
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if ($contribNode->nodeName == 'client_configs' && !ConfService::getContextConf($ctx, "WEBDAV_ENABLE")) {
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
            $webdavCompNodeList = $actionXpath->query('component_config/additional_tab[@id="webdav_pane"]', $contribNode);
            if ($webdavCompNodeList->length) {
                $contribNode->removeChild($webdavCompNodeList->item(0)->parentNode);
            }
        }

        if($contribNode->nodeName != "actions") return;

        // WEBDAV ACTION
        if (!ConfService::getContextConf($ctx, "WEBDAV_ENABLE")) {
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

        $exposed = UsersService::getUsersExposedParameters();
        if (!count($exposed)) {
            $actionXpath=new \DOMXPath($contribNode->ownerDocument);
            $publicUrlNodeList = $actionXpath->query('action[@name="custom_data_edit"]', $contribNode);
            $publicUrlNode = $publicUrlNodeList->item(0);
            $contribNode->removeChild($publicUrlNode);
        }

        // CREATE A NEW REPOSITORY
        if (!ConfService::getContextConf($ctx, "USER_CREATE_REPOSITORY", "conf")) {
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
        if (!ConfService::getContextConf($ctx, "USER_CREATE_USERS", "conf")) {
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
        $options = [];
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

    /**
     * @param string $pluginId
     * @param array $options
     * @return mixed
     */
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
     * @param AbstractUser $user
     * @return RepositoryInterface[]
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
     * @return RepositoryInterface[]
     */
    abstract public function listRepositoriesWithCriteria($criteria, &$count=null);


    /**
     * Retrieve a Repository given its unique ID.
     *
     * @param String $repositoryId
     * @return RepositoryInterface
     */
    abstract public function getRepositoryById($repositoryId);
    /**
     * Retrieve a Repository given its alias.
     *
     * @param String $repositorySlug
     * @return RepositoryInterface
     */
    abstract public function getRepositoryByAlias($repositorySlug);
    /**
     * Stores a repository, new or not.
     *
     * @param RepositoryInterface $repositoryObject
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
     * @param boolean $excludeReserved
     * @param $includeOwnedRoles boolean
     * @return array AJXP_Role[]
     */
    abstract public function listRoles($roleIds = [], $excludeReserved = false, $includeOwnedRoles = false);


    /**
     * Get Roles owned by a given user ( = teams )
     * @param $ownerId
     * @return AJXP_Role[]
     */
    abstract public function listRolesOwnedBy($ownerId);

    /**
     * @param $roleId string
     * @param bool $countOnly
     * @return string[]
     */
    public function getUsersForRole($roleId, $countOnly = false){
        return [];
    }

    /**
     * @abstract
     * @param AJXP_Role $role
     * @param AbstractUser $userObject
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
     * Instantiate a new UserInterface
     *
     * @param String $userId
     * @return UserInterface
     */
    public function createUserObject($userId)
    {
        $userId = UsersService::filterUserSensitivity($userId);
        $abstractUser = $this->instantiateAbstractUserImpl($userId);
        if (!$abstractUser->storageExists()) {
            RolesService::updateDefaultRights($abstractUser);
        }
        RolesService::updateAutoApplyRole($abstractUser);
        RolesService::updateAuthProvidedData($abstractUser);
        $args = [&$abstractUser];
        Controller::applyIncludeHook("include.user.updateUserObject", $args);
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
     * @return AbstractUser
     */
    abstract public function instantiateAbstractUserImpl($userId);

    /**
     * @return string
     */
    abstract public function getUserClassFileName();

    /**
     * @abstract
     * @param $userId
     * @return AbstractUser[]
     */
    abstract public function getUserChildren($userId);

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
     * @param ContextInterface $ctx
     * @param string $repositoryId
     * @param boolean $details
     * @param boolean $admin
     * @return Integer|array
     */
    abstract public function countUsersForRepository(ContextInterface $ctx, $repositoryId, $details = false, $admin = false);


    /**
     * @param AbstractUser[] $flatUsersList
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

    /**
     * @inheritdoc
     */
    public function getOption($optionName)
    {
        return (isSet($this->options[$optionName])?$this->options[$optionName]:"");
    }

    /**
     * @param UserInterface $userObject
     * @return array()
     */
    public function getExposedPreferences($userObject)
    {
        $stringPrefs = ["lang","history/last_repository","pending_folder","plugins_preferences"];
        $jsonPrefs = ["ls_history","gui_preferences"];
        $prefs = [];
        if ( $userObject->getId()=="guest" && ConfService::getGlobalConf("SAVE_GUEST_PREFERENCES", "conf") === false) {
            return [];
        }
        if ( ConfService::getGlobalConf("SKIP_USER_HISTORY", "conf") === true ) {
            $stringPrefs = ["lang","pending_folder", "plugins_preferences"];
            $jsonPrefs = ["gui_preferences"];
            $prefs["SKIP_USER_HISTORY"] = ["value" => "true", "type" => "string"];
        }
        foreach ($stringPrefs as $pref) {
            if (strstr($pref, "/")!==false) {
                $parts = explode("/", $pref);
                $value = $userObject->getArrayPref($parts[0], $parts[1]);
                $pref = str_replace("/", "_", $pref);
            } else {
                $value = $userObject->getPref($pref);
            }
            $prefs[$pref] = ["value" => $value, "type" => "string"];
        }
        foreach ($jsonPrefs as $pref) {
            $prefs[$pref] = ["value" => $userObject->getPref($pref), "type" => "json"];
        }

        $exposed = UsersService::getUsersExposedParameters();
        foreach ($exposed as $exposedProp) {
            $value = $userObject->getMergedRole()->filterParameterValue($exposedProp["PLUGIN_ID"], $exposedProp["NAME"], AJXP_REPO_SCOPE_ALL, "");
            $prefs[$exposedProp["NAME"]] = ["value" => $value, "type" => "string", "pluginId" => $exposedProp["PLUGIN_ID"]];
        }

        return $prefs;
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @throws PydioException
     * @throws \Exception
     * @throws \Pydio\Core\Exception\UserNotFoundException
     */
    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        /** @var ContextInterface $ctx */
        $ctx            = $requestInterface->getAttribute("ctx");
        $httpVars       = $requestInterface->getParsedBody();
        $action         = $requestInterface->getAttribute("action");
        $loggedUser     = $ctx->getUser();
        $mess           = LocaleService::getMessages();
        $temporaryUploadFolder = ApplicationState::getTemporaryBinariesFolder();

        switch ($action) {
            //------------------------------------
            //	SWITCH THE ROOT REPOSITORY
            //------------------------------------
            case "switch_repository":


                if (!isSet($httpVars['repository_id'])) {
                    break;
                }
                $repository_id = InputFilter::sanitize($httpVars['repository_id'], InputFilter::SANITIZE_ALPHANUM);
                UsersService::getRepositoryWithPermission($ctx->getUser(), $repository_id);
                SessionService::switchSessionRepositoryId($repository_id);
                PluginsService::getInstance($ctx->withRepositoryId($repository_id));
                if (UsersService::usersEnabled() && $loggedUser !== null) {
                    $loggedUser->setArrayPref("repository_last_connected", $repository_id, time());
                    $loggedUser->save("user");
                }

                $this->logInfo("Switch Repository", ["rep. id"=>$repository_id]);

            break;

            //------------------------------------
            //	SEND XML REGISTRY
            //------------------------------------
            case "get_xml_registry" :
            case "state" :
            case "user_state" :

                if($action === "user_state"){
                    // Build xPath manually
                    $uri = $requestInterface->getServerParams()["REQUEST_URI"];
                    if(strpos($uri, "/user/workspaces") !== false) $xPath = "user/repositories";
                    else if(strpos($uri, "/user/preferences") !== false) $xPath = "user/preferences";
                    else $xPath = "user";
                    $httpVars["xPath"] = $xPath;
                }
                
                $clone = PluginsService::getInstance($ctx)->getFilteredXMLRegistry(true, true);
                $clonePath = new \DOMXPath($clone);
                if(!AJXP_SERVER_DEBUG){
                    $serverCallbacks = $clonePath->query("//serverCallback|hooks");
                    foreach ($serverCallbacks as $callback) {
                        $callback->parentNode->removeChild($callback);
                    }
                }
                $xPath = null;
                if (isSet($httpVars["xPath"])) {
                    $xPath = ltrim(InputFilter::securePath($httpVars["xPath"]), "/");
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
                ApplicationState::safeIniSet("zlib.output_compression", "4096");
                $x = new SerializableResponseStream();
                $responseInterface = $responseInterface->withBody($x);
                $x->addChunk($message);

            break;

            //------------------------------------
            //	BOOKMARK BAR
            //------------------------------------
            case "get_bookmarks":

                $bmUser = null;
                if (UsersService::usersEnabled() && $loggedUser != null) {
                    $bmUser = $loggedUser;
                } else if (!UsersService::usersEnabled()) {
                    $bmUser = UsersService::getUserById("shared", false);
                }
                $x = new SerializableResponseStream();
                $responseInterface = $responseInterface->withBody($x);
                if ($bmUser == null) {
                    break;
                }
                /** @var ContextInterface $ctx */
                $ctx = $requestInterface->getAttribute("ctx");
                $driver = $ctx->getRepository()->getDriverInstance($ctx);
                if (!($driver instanceof IAjxpWrapperProvider)) {
                    $driver = false;
                }


                $repositoryId = $ctx->getRepositoryId();
                if (isSet($httpVars["bm_action"]) && isset($httpVars["bm_path"])) {
                    $bmPath = InputFilter::decodeSecureMagic($httpVars["bm_path"]);
                    if ($httpVars["bm_action"] == "add_bookmark") {
                        $title = "";
                        if(isSet($httpVars["bm_title"])) $title = InputFilter::decodeSecureMagic($httpVars["bm_title"]);
                        if($title == "" && $bmPath=="/") $title = $ctx->getRepository()->getDisplay();
                        $bmUser->addBookmark($repositoryId, $bmPath, $title);
                        if ($driver) {
                            $node = new AJXP_Node($ctx->getUrlBase().$bmPath);
                            $node->setMetadata("ajxp_bookmarked", ["ajxp_bookmarked" => "true"], true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                        }
                    } else if ($httpVars["bm_action"] == "delete_bookmark") {
                        $bmUser->removeBookmark($repositoryId, $bmPath);
                        if ($driver) {
                            $node = new AJXP_Node($ctx->getUrlBase().$bmPath);
                            $node->removeMetadata("ajxp_bookmarked", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                        }
                    } else if ($httpVars["bm_action"] == "rename_bookmark" && isset($httpVars["bm_title"])) {
                        $title = InputFilter::decodeSecureMagic($httpVars["bm_title"]);
                        $bmUser->renameBookmark($repositoryId, $bmPath, $title);
                    }
                    Controller::applyHook("msg.instant", [$ctx, "<reload_bookmarks/>", $loggedUser->getId()]
                    );

                    if (UsersService::usersEnabled() && $loggedUser != null) {
                        $bmUser->save("user");
                        AuthService::updateSessionUser($bmUser);
                    } else if (!UsersService::usersEnabled()) {
                        $bmUser->save("user");
                    }
                }
                $doc = new XMLMessage(UserXML::writeBookmarks($bmUser->getBookmarks($repositoryId), $ctx, false, isset($httpVars["format"]) ? $httpVars["format"] : "legacy"));
                $x = new SerializableResponseStream([$doc]);
                $responseInterface = $responseInterface->withBody($x);

            break;

            //------------------------------------
            //	SAVE USER PREFERENCE
            //------------------------------------
            case "save_user_pref":

                $i = 0;
                while (isSet($httpVars["pref_name_".$i]) && isSet($httpVars["pref_value_".$i])) {
                    $prefName = InputFilter::sanitize($httpVars["pref_name_" . $i], InputFilter::SANITIZE_ALPHANUM);
                    $prefValue = InputFilter::sanitize(InputFilter::magicDequote($httpVars["pref_value_" . $i]));
                    if($prefName == "password") continue;
                    if ($prefName != "pending_folder" && $loggedUser == null) {
                        $i++;
                        continue;
                    }
                    $loggedUser->setPref($prefName, $prefValue);
                    $loggedUser->save("user");
                    AuthService::updateSessionUser($loggedUser);
                    $i++;
                }

                $responseInterface = $responseInterface->withHeader("Content-type", "text/plain");
                $responseInterface->getBody()->write("SUCCESS");

            break;

            //------------------------------------
            // TEAMS MANAGEMENT
            //------------------------------------
            case "user_team_create":

                $crtUser = $ctx->getUser()->getId();
                $teamLabel = InputFilter::sanitize($httpVars["team_label"], InputFilter::SANITIZE_HTML_STRICT);
                if(empty($teamLabel)){
                    throw new PydioException("Empty Team Label!");
                }
                $teamId = StringHelper::slugify($teamLabel) ."-".intval(rand(0,1000));
                $roleObject = RolesService::getOrCreateOwnedRole($teamId, $crtUser);
                $roleObject->setLabel($teamLabel);
                RolesService::updateRole($roleObject);

                $userIds = isSet($httpVars["user_ids"]) ? $httpVars["user_ids"] : [];
                foreach ($userIds as $userId) {
                    $id = InputFilter::sanitize($userId, InputFilter::SANITIZE_EMAILCHARS);
                    $uObject = UsersService::getUserById($id);
                    $uObject->addRole($roleObject);
                    $uObject->save();
                }
                $responseInterface = new JsonResponse(["message" => "Created Team with id " . $teamId, "insertId" => $teamId]);

                break;

            case "user_team_delete":

                $tId = InputFilter::sanitize($httpVars["team_id"], InputFilter::SANITIZE_ALPHANUM);
                $crtUser = $ctx->getUser()->getId();
                // Role ownership is already checked inside deleteRole() function.
                RolesService::deleteRole($tId, $crtUser);
                break;

            case "user_team_update_label":

                $tId = InputFilter::sanitize($httpVars["team_id"], InputFilter::SANITIZE_ALPHANUM);
                $roleObject = RolesService::getOwnedRole($tId, $ctx->getUser()->getId());
                if($roleObject === null){
                    throw new PydioException("Cannot find team!");
                }
                $teamLabel = InputFilter::sanitize($httpVars["team_label"], InputFilter::SANITIZE_HTML_STRICT);
                if(empty($teamLabel)){
                    throw new PydioException("Empty Team Label!");
                }
                $roleObject->setLabel($teamLabel);
                RolesService::updateRole($roleObject);
                $responseInterface = new JsonResponse(["message" => "Team $tId was updated"]);
                break;

            case "user_team_add_user":

                $id = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                $tId = InputFilter::sanitize($httpVars["team_id"], InputFilter::SANITIZE_ALPHANUM);
                $uObject = UsersService::getUserById($id);
                $roleObject = RolesService::getOwnedRole($tId, $ctx->getUser()->getId());
                if($roleObject === null){
                    throw new PydioException("Cannot find team!");
                }
                $uObject->addRole($roleObject);
                $uObject->save("superuser");
                $responseInterface = new JsonResponse(["message" => "User $id added to team " . $tId]);
                break;

            case "user_team_delete_user":

                $id = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                $tId = InputFilter::sanitize($httpVars["team_id"], InputFilter::SANITIZE_ALPHANUM);
                $roleObject = RolesService::getOwnedRole($tId, $ctx->getUser()->getId());
                if($roleObject === null){
                    throw new PydioException("Cannot find team!");
                }
                $uObject = UsersService::getUserById($id);
                $uObject->removeRole($tId);
                $uObject->save("superuser");
                $responseInterface = new JsonResponse(["message" => "User $id deleted from team " . $tId]);
                break;

            case "user_team_list_users":

                $tId = InputFilter::sanitize($httpVars["team_id"], InputFilter::SANITIZE_ALPHANUM);
                $roleObject = RolesService::getOwnedRole($tId, $ctx->getUser()->getId());
                if($roleObject === null){
                    throw new PydioException("Cannot find team!");
                }
                $users = $this->getUsersForRole($tId);
                $data = [];
                foreach($users as $userId){
                    try{
                        $userObject = UsersService::getUserById($userId);
                    }catch(UserNotFoundException $e){
                        continue;
                    }
                    $userLabel = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $userObject, "core.conf", $userId);
                    $userAvatar = UsersService::getUserPersonalParameter("avatar", $userObject, "core.conf", "");
                    $data[$userId] = [
                        "label"     =>  $userLabel,
                        "avatar"    =>  $userAvatar
                    ];
                }
                $responseInterface = new JsonResponse($data);

                break;

            case "user_team_edit_users":

                throw new PydioException("Deprecated user_team_edit_users. Please use add / remove instead");
                break;

            //------------------------------------
            //	USERS MANAGEMENT
            //------------------------------------
            case "custom_data_edit":
            case "user_create_user":

                $data = [];

                if ($action == "user_create_user" && isSet($httpVars["NEW_new_user_id"])) {
                    $updating = false;
                    OptionsHelper::parseStandardFormParameters($ctx, $httpVars, $data, "NEW_");
                    $originalId = InputFilter::decodeSecureMagic($data["new_user_id"]);
                    $newUserId = InputFilter::decodeSecureMagic($data["new_user_id"], InputFilter::SANITIZE_EMAILCHARS);
                    if($originalId != $newUserId){
                        throw new PydioException(str_replace("%s", $newUserId, $mess["ajxp_conf.127"]));
                    }
                    $prefix = '';
                    $sharePlugin = PluginsService::getInstance($ctx)->getPluginById("action.share");
                    if($sharePlugin !== null){
                        $prefix = $sharePlugin->getContextualOption($ctx, "SHARED_USERS_TMP_PREFIX");
                    }
                    if(!empty($prefix) && strpos($newUserId, $prefix) !== 0){
                        $newUserId = $prefix . $newUserId;
                    }
                    if (UsersService::userExists($newUserId, "w")) {
                        throw new PydioException($mess["ajxp_conf.43"]);
                    }
                    $limit = $loggedUser->getMergedRole()->filterParameterValue("core.conf", "USER_SHARED_USERS_LIMIT", AJXP_REPO_SCOPE_ALL, "");
                    if (!empty($limit) && intval($limit) > 0) {
                        $count = count($this->getUserChildren($loggedUser->getId()));
                        if ($count >= $limit) {
                            throw new \Exception($mess['483']);
                        }
                    }
                    $userObject = UsersService::createUser($newUserId, $data["new_password"]);
                    $userObject->setParent($loggedUser->getId());
                    $userObject->save('superuser');
                    $userObject->getPersonalRole()->clearAcls();
                    $userObject->setGroupPath($loggedUser->getGroupPath());
                    $userObject->setProfile("shared");

                } else if($action == "user_create_user" && isSet($httpVars["NEW_existing_user_id"])){

                    $updating = true;
                    OptionsHelper::parseStandardFormParameters($ctx, $httpVars, $data, "NEW_");
                    $userId = InputFilter::sanitize($data["existing_user_id"], InputFilter::SANITIZE_EMAILCHARS);
                    $userObject = UsersService::getUserById($userId);
                    if($userObject->getParent() !== $loggedUser->getId()){
                        throw new \Exception("Cannot find user");
                    }
                    if(!empty($data["new_password"])){
                        UsersService::updatePassword($userId, $data["new_password"]);
                    }

                } else {
                    $updating = false;
                    $userObject = $loggedUser;
                    OptionsHelper::parseStandardFormParameters($ctx, $httpVars, $data, "PREFERENCES_");
                }

                $rChanges = false;
                $exposed = UsersService::getUsersExposedParameters();
                foreach($exposed as $parameter){
                    $pluginId = $parameter["PLUGIN_ID"];
                    $name     = $parameter["NAME"];
                    if (isSet($data[$name]) || $data[$name] === "") {
                        if($data[$name] === "__AJXP_VALUE_SET__") continue;
                        $pRole = null;
                        $persRole = $userObject->getPersonalRole();
                        if($userObject instanceof AbstractUser) $pRole = $userObject->parentRole;
                        if ($data[$name] === ""
                            || $pRole === null || $pRole->filterParameterValue($pluginId, $name, AJXP_REPO_SCOPE_ALL, "") != $data[$name]
                            || $persRole->filterParameterValue($pluginId, $name, AJXP_REPO_SCOPE_ALL, "") != $data[$name])
                        {
                            $persRole->setParameterValue($pluginId, $name, $data[$name]);
                            $rChanges = true;
                        }
                    }
                }
                if ($rChanges) {
                    RolesService::updateRole($userObject->getPersonalRole(), $userObject);
                    $userObject->recomputeMergedRole();
                    if ($action == "custom_data_edit") {
                        AuthService::updateSessionUser($userObject);
                        $crtLang = LocaleService::getLanguage();
                        $newLang = $userObject->getPersonalRole()->filterParameterValue("core.conf", "lang", AJXP_REPO_SCOPE_ALL, $crtLang);
                        if($newLang !== $crtLang){
                            LocaleService::setLanguage($newLang);
                            $mess = LocaleService::getMessages(true);
                        }
                    }
                    UsersService::updateUser($userObject);
                }

                if ($action == "user_create_user") {

                    if(isSet($newUserId)){

                        Controller::applyHook($updating?"user.after_update":"user.after_create", [$ctx, $userObject]);
                        if (isset($data["send_email"]) && $data["send_email"] == true && !empty($data["email"])) {
                            $mailer = PluginsService::getInstance($ctx)->getUniqueActivePluginForType("mailer");
                            if ($mailer !== false) {
                                $mess = LocaleService::getMessages();
                                $link = ApplicationState::detectServerURL();
                                $apptitle = ConfService::getGlobalConf("APPLICATION_TITLE");
                                $subject = str_replace("%s", $apptitle, $mess["507"]);
                                $body = str_replace(["%s", "%link", "%user", "%pass"], [$apptitle, $link, $newUserId, $data["new_password"]], $mess["508"]);
                                $mailer->sendMail($ctx, [$data["email"]], $subject, $body);
                            }
                        }
                        $responseInterface = new JsonResponse(["result" => "SUCCESS", "createdUserId" => $newUserId]);

                    }else{

                        $responseInterface = new JsonResponse(["result" => "SUCCESS", "createdUserId" => $userId]);

                    }

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
                $userId = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                $userObject = UsersService::getUserById($userId);

                if($userObject->getParent() != $loggedUser->getId()){
                    throw new \Exception("Cannot find user");
                }
                $paramsString = ConfService::getContextConf($ctx, "NEWUSERS_EDIT_PARAMETERS", "conf");
                $result = [];
                $params = explode(",", $paramsString);
                foreach($params as $p){
                    $result[$p] = $userObject->getPersonalRole()->filterParameterValue("core.conf", $p, AJXP_REPO_SCOPE_ALL, "");
                }

                $responseInterface = $responseInterface->withHeader("Content-type", "application/json");
                $responseInterface->getBody()->write(json_encode($result));

            break;

            case "user_public_data":

                $userId = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                $responseInterface = $responseInterface->withHeader("Content-type", "application/json");
                try{
                    $userObject = UsersService::getUserById($userId);
                }catch(UserNotFoundException $e){
                    $responseInterface->getBody()->write(json_encode(["error"=>"not_found"]));
                    break;
                }
                $userLabel = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $userObject, "core.conf", $userId);
                $userAvatar = UsersService::getUserPersonalParameter("avatar", $userObject, "core.conf", "");
                $email = UsersService::getUserPersonalParameter("email", $userObject, "core.conf", "");

                $addressBookItem = new AddressBookItem('user', $userId, $userLabel, false, $userObject->hasParent(), $userAvatar);
                $addressBookItem->appendData('hasEmail', !empty($email));
                if($userObject->hasParent() && $userObject->getParent() === $ctx->getUser()->getId()){
                    // This user belongs to current user, we can display more data
                    if(!empty($email)) $addressBookItem->appendData('email', $email);
                    $addressBookItem->appendData('USER_DISPLAY_NAME', $userLabel);
                    $lang = UsersService::getUserPersonalParameter("lang", $userObject, "core.conf", "");
                    $addressBookItem->appendData('lang', $lang);
                }

                $data = [ 'user'      => $addressBookItem ];
                if(isSet($httpVars['graph']) && $httpVars['graph'] === 'true'){
                    $data['graph'] = ['target' => [], 'source' => []];
                    $ctxUser = $ctx->getUser();
                    // My repositories shared with this user
                    $targetRepos = UsersService::getRepositoriesForUser($userObject);
                    foreach($targetRepos as $repository){
                        if($repository->getOwner() === $ctxUser->getId()){
                            $data['graph']['target'][$repository->getId()] = $repository->getDisplay();
                        }
                    }
                    // User repositories shared with me
                    $sourceRepos = UsersService::getRepositoriesForUser($ctxUser);
                    foreach($sourceRepos as $repository){
                        if($repository->getOwner() === $userObject->getId()){
                            $data['graph']['source'][$repository->getId()] = $repository->getDisplay();
                        }
                    }
                    // My teams the user is belonging to
                    $list = new FilteredUsersList($ctx);
                    $teams = $list->listTeams();
                    $roles = $userObject->getRolesKeys();
                    $data['graph']['teams'] = [];
                    foreach($teams as $t){
                        if(in_array(substr($t->getId(), strlen('/AJXP_TEAM/')), $roles)){
                            $data['graph']['teams'][] = $t;
                        }
                    }
                }
                $responseInterface->getBody()->write(json_encode($data));

            break;

            //------------------------------------
            // WEBDAV PREFERENCES
            //------------------------------------
            case "webdav_preferences" :

                $webdavActive = false;
                $passSet = false;
                // Detect http/https and host
                if (ConfService::getGlobalConf("WEBDAV_BASEHOST") != "") {
                    $baseURL = ConfService::getGlobalConf("WEBDAV_BASEHOST");
                } else {
                    $baseURL = ApplicationState::detectServerURL(true);
                }
                $webdavBaseUrl = rtrim($baseURL,"/")."/".trim(ConfService::getGlobalConf("WEBDAV_BASEURI"), "/")."/";
                $davData = $loggedUser->getPref("AJXP_WEBDAV_DATA");
                $digestSet = isSet($davData["HA1"]);
                if (isSet($httpVars["activate"]) || isSet($httpVars["webdav_pass"])) {
                    if (!empty($httpVars["activate"])) {
                        $activate = ($httpVars["activate"]==="true" ? true:false);
                        if (empty($davData)) {
                            $davData = [];
                        }
                        $davData["ACTIVE"] = $activate;
                    }
                    if (!empty($httpVars["webdav_pass"])) {
                        $davData["PASS"] = Crypto::encrypt($httpVars["webdav_pass"], Crypto::buildKey($loggedUser->getId(),Crypto::getApplicationSecret()));
                    }
                    $loggedUser->setPref("AJXP_WEBDAV_DATA", $davData);
                    $loggedUser->save("user");
                }
                if (!empty($davData)) {
                    $webdavActive = ConfService::getGlobalConf("WEBDAV_ACTIVE_ALL");
                    // override with local value if set.
                    if(isSet($davData["ACTIVE"]) && is_bool($davData["ACTIVE"])){
                        $webdavActive = $davData["ACTIVE"];
                    }
                    $passSet = (isSet($davData["PASS"]));
                }
                $repoList = UsersService::getRepositoriesForUser($ctx->getUser());
                $davRepos = [];
                foreach ($repoList as $repoIndex => $repoObject) {
                    $accessType = $repoObject->getAccessType();
                    $driver = PluginsService::getInstance($ctx)->getPluginByTypeName("access", $accessType);
                    if (($driver instanceof IAjxpWrapperProvider) && !$repoObject->getContextOption($ctx, "AJXP_WEBDAV_DISABLED") && ($loggedUser->canRead($repoIndex) || $loggedUser->canWrite($repoIndex))) {
                        $davRepos[$repoIndex] = $webdavBaseUrl . "" . ($repoObject->getSlug() == null ? $repoObject->getId() : $repoObject->getSlug());
                    }
                }
                $prefs = [
                    "webdav_active"  => $webdavActive,
                    "password_set"   => $passSet,
                    "digest_set"    => $digestSet,
                    "webdav_force_basic" => (ConfService::getGlobalConf("WEBDAV_FORCE_BASIC") === true),
                    "webdav_base_url"  => $webdavBaseUrl,
                    "webdav_repositories" => $davRepos
                ];

                $responseInterface = $responseInterface->withHeader("Content-type", "application/json");
                $responseInterface->getBody()->write(json_encode($prefs));

            break;

            case  "get_user_template_logo":

                $tplId = $httpVars["template_id"];
                $iconFormat = $httpVars["icon_format"];
                $repo = RepositoryService::getRepositoryById($tplId);
                $logo = $repo->getContextOption($ctx, "TPL_ICON_".strtoupper($iconFormat));

                $async = new AsyncResponseStream(function() use($logo, $iconFormat){
                    if (isSet($logo) && is_file(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo)) {
                        header("Content-Type: ". StatHelper::getImageMimeType($logo) ."; name=\"".$logo."\"");
                        header("Content-Length: ".filesize(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo));
                        header('Pragma:');
                        header('Cache-Control: public');
                        header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
                        header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
                        readfile(AJXP_DATA_PATH."/plugins/core.conf/tpl_logos/".$logo);
                    } else {
                        $logo = "default_template_logo-".($iconFormat == "small"?16:22).".png";
                        header("Content-Type: ". StatHelper::getImageMimeType($logo) ."; name=\"".$logo."\"");
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
                $repositories = RepositoryService::listRepositoriesWithCriteria([
                    "isTemplate" => 1
                ], $count);
                $pServ = PluginsService::getInstance($ctx);
                foreach ($repositories as $repo) {
                    if(!$repo->isTemplate) continue;
                    if(!$repo->getContextOption($ctx, "TPL_USER_CAN_CREATE")) continue;
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
                                $defaultLabel = str_replace("AJXP_USER", $loggedUser->getId(), $repo->getContextOption($ctx, $name));
                            }
                            continue;
                        }
                        if( in_array($paramNode->getAttribute("name"), $tplDefined) ) continue;
                        if($paramNode->getAttribute('no_templates') == 'true') continue;
                        $xml.= XMLFilter::resolveKeywords($paramNode->ownerDocument->saveXML($paramNode));
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
                $tplRepo = RepositoryService::getRepositoryById($tplId);
                $options = [];
                OptionsHelper::parseStandardFormParameters($ctx, $httpVars, $options);
                $display = InputFilter::sanitize($httpVars["DISPLAY"]);
                if(empty($display)){
                    throw new PydioException("Cannot create repository with empty label");
                }
                $newRep = $tplRepo->createTemplateChild($display, $options, $loggedUser->getId(), $loggedUser->getId());
                $gPath = $loggedUser->getGroupPath();
                if (!empty($gPath)) {
                    $newRep->setGroupPath($gPath);
                }
                $res = RepositoryService::addRepository($newRep);
                if ($res == -1) {
                    throw new PydioException($mess[426], 426);
                }

                // Make sure we do not overwrite otherwise loaded rights.
                $loggedUser->load();
                $loggedUser->getPersonalRole()->setAcl($newRep->getUniqueId(), "rw");
                $loggedUser->save("superuser");
                $loggedUser->recomputeMergedRole();
                AuthService::updateSessionUser($loggedUser);

                $x = new SerializableResponseStream();
                $responseInterface = $responseInterface->withBody($x);
                $x->addChunk(new UserMessage($mess[425]));
                $x->addChunk(new ReloadMessage("", $newRep->getUniqueId()));
                $x->addChunk(new XMLMessage("<reload_instruction object=\"repository_list\"/>"));

            break;

            case "user_delete_repository" :

                $repoId = $httpVars["repository_id"];
                $repository = RepositoryService::getRepositoryById($repoId);
                if (!$repository->getUniqueUser()||$repository->getUniqueUser()!= $loggedUser->getId()) {
                    throw new \Exception("You are not allowed to perform this operation!");
                }
                $res = RepositoryService::deleteRepository($repoId);

                if ($res == -1) {
                    throw new PydioException($mess[427]);
                }

                // Make sure we do not override remotely set rights
                $loggedUser->load();
                $loggedUser->getPersonalRole()->setAcl($repoId, "");
                $loggedUser->save("superuser");
                AuthService::updateSessionUser($loggedUser);

                $x = new SerializableResponseStream();
                $responseInterface = $responseInterface->withBody($x);
                $x->addChunk(new UserMessage($mess[428]));
                $x->addChunk(new XMLMessage("<reload_instruction object=\"repository_list\"/>"));

            break;

            case "user_delete_user":

                $userId = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                $userObject = UsersService::getUserById($userId);
                if ($userObject == null || !$userObject->hasParent() || $userObject->getParent() != $loggedUser->getId()) {
                    throw new \Exception("You are not allowed to edit this user");
                }
                UsersService::deleteUser($userId);
                $responseInterface = $responseInterface->withHeader("Content-type", "text/plain");
                $responseInterface->getBody()->write("SUCCESS");

                break;

            case "user_list_authorized_users" :

                if(isSet($httpVars["processed"])){
                    break;
                }

                $alphaPages     = isSet($httpVars["alpha_pages"]) && $httpVars["alpha_pages"] === "true" ? true : false;
                $crtValue       = InputFilter::sanitize($httpVars['value'], InputFilter::SANITIZE_HTML_STRICT);
                $groupPath      = isSet($httpVars["group_path"]) ? InputFilter::sanitize($httpVars['group_path'], InputFilter::SANITIZE_DIRNAME) : '';
                $existingOnly   = isSet($httpVars["existing_only"]) && $httpVars["existing_only"] === "true";
                $excludeCurrent = isSet($httpVars["exclude_current"]) && ($httpVars["exclude_current"] === "false" || $httpVars["exclude_current"] === false) ? false : true;
                if($alphaPages){
                    if($crtValue === '') $crtValue = 'a';
                    $existingOnly = true;
                }

                if(isSet($httpVars["filter_value"])){
                    $filterValue = intval(InputFilter::sanitize($httpVars["filter_value"], InputFilter::SANITIZE_ALPHANUM));
                }else{
                    $usersOnly      = isSet($httpVars["users_only"]) && $httpVars["users_only"] === "true";
                    $filterValue = FilteredUsersList::FILTER_USERS_INTERNAL | FilteredUsersList::FILTER_USERS_EXTERNAL;
                    if(!$usersOnly){
                        $filterValue |= FilteredUsersList::FILTER_GROUPS | FilteredUsersList::FILTER_TEAMS;
                    }
                }

                $list = new FilteredUsersList($ctx, $excludeCurrent, $alphaPages);
                $items = $list->load($filterValue, !$existingOnly, $crtValue, $groupPath);
                $format = $httpVars["format"];
                if(!isSet($format)) $format = 'json';
                switch($format){
                    case 'xml':
                    case 'json':
                        $x = new SerializableResponseStream($items);
                        $x->forceArray();
                        $responseInterface = $responseInterface->withBody($x);
                        break;
                    case 'html':
                        $responseInterface = $responseInterface->withHeader('Content-type', 'text/html; charset=UTF-8');
                        $responseInterface->getBody()->write('<ul>');
                        foreach($items as $chunk){
                            $responseInterface->getBody()->write($chunk->toXml());
                        }
                        $responseInterface->getBody()->write('</ul>');
                        break;
                    default:
                        break;
                };

                break;

            case "load_repository_info":

                SessionService::close();
                $data = [];
                $repo = $ctx->getRepository();
                if($repo != null){
                    $users = UsersService::countUsersForRepository($ctx, $repo->getId(), true);
                    $data["core.users"] = $users;
                    if(isSet($httpVars["collect"]) && $httpVars["collect"] == "true"){
                        Controller::applyHook("repository.load_info", [$ctx, &$data]);
                    }
                }

                $responseInterface = $responseInterface->withHeader("Content-type", "application/json");
                $responseInterface->getBody()->write(json_encode($data));
                break;

            case "get_binary_param" :

                if (isSet($httpVars["tmp_file"])) {
                    $file = $temporaryUploadFolder ."/". InputFilter::securePath($httpVars["tmp_file"]);
                    if (file_exists($file)) {
                        session_write_close();
                        header("Content-Type:image/png");
                        readfile($file);
                    }else{
                        $responseInterface = $responseInterface->withStatus(401, 'Forbidden');
                    }
                } else if (isSet($httpVars["binary_id"])) {
                    if (isSet($httpVars["user_id"])) {
                        $context = ["USER" => InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS)];
                    } else if($loggedUser !== null) {
                        $context = ["USER" => $loggedUser->getId()];
                    } else {
                        $context = [];
                    }
                    session_write_close();
                    $this->loadBinary($context, InputFilter::sanitize($httpVars["binary_id"], InputFilter::SANITIZE_ALPHANUM));
                }
            break;

            case "get_global_binary_param" :

                session_write_close();
                if (isSet($httpVars["tmp_file"])) {
                    $file = $temporaryUploadFolder ."/". InputFilter::securePath($httpVars["tmp_file"]);
                    if (file_exists($file)) {
                        header("Content-Type:image/png");
                        readfile($file);
                    }else{
                        $responseInterface = $responseInterface->withStatus(401, 'Forbidden');
                    }
                } else if (isSet($httpVars["binary_id"])) {
                    $this->loadBinary([], InputFilter::sanitize($httpVars["binary_id"], InputFilter::SANITIZE_ALPHANUM));
                }
            break;

            case "store_binary_temp" :

                $uploadedFiles = $requestInterface->getUploadedFiles();
                if (count($uploadedFiles)) {
                    /**
                     * @var UploadedFileInterface $boxData
                     */
                    $boxData = array_shift($uploadedFiles);
                    $err = InputFilter::parseFileDataErrors($boxData);
                    if ($err != null) {

                    } else {
                        $rand = substr(md5(time()), 0, 6);
                        $tmp = $rand."-". $boxData->getClientFilename();
                        if(!file_exists($temporaryUploadFolder)){
                            mkdir($temporaryUploadFolder);
                        }
                        $boxData->moveTo($temporaryUploadFolder . "/" . $tmp);
                    }
                }
                if (isSet($tmp) && file_exists($temporaryUploadFolder ."/".$tmp)) {
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
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface
     */
    public function publishPermissionsMask(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface){
        $mask = [];
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $user = $ctx->getUser();

        if(!UsersService::usersEnabled() || $user == null){
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
                $mask[$path] = [
                    "read" => $permission->canRead(),
                    "write" => $permission->canWrite()
                ];
            }
        }
        $responseInterface = new JsonResponse($mask);
        return $responseInterface;

    }
}
