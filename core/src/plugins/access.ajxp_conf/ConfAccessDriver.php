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
 *
 */
namespace Pydio\Access\Driver\DataProvider;

use DOMXPath;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Filter\AJXP_PermissionMask;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Conf\Core\AbstractUser;
use Pydio\Core\Exception\UserNotFoundException;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\AuthService;
use Pydio\Conf\Core\AJXP_Role;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Controller\ProgressBarCLI;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Reflection\DiagnosticRunner;
use Pydio\Core\Utils\Reflection\DocsParser;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\OptionsHelper;
use Pydio\Core\Utils\Vars\PathUtils;
use Pydio\Core\Utils\Vars\StatHelper;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Utils\Reflection\PydioSdkGenerator;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Plugin to access the configurations data
 * Class ConfAccessDriver
 * @package Pydio\Access\Driver\DataProvider
 */
class ConfAccessDriver extends AbstractAccessDriver
{

    protected $listSpecialRoles = AJXP_SERVER_DEBUG;
    protected $currentBookmarks = array();

    /**
     * @var ContextInterface
     */
    protected $currentContext;

    protected $rootNodes = array(
        "data" => array(
            "LABEL" => "ajxp_conf.110",
            "ICON" => "user.png",
            "DESCRIPTION" => "ajxp_conf.137",
            "CHILDREN" => array(
                "repositories" => array(
                    "AJXP_MIME" => "workspaces_zone",
                    "LABEL" => "ajxp_conf.3",
                    "DESCRIPTION" => "ajxp_conf.138",
                    "ICON" => "hdd_external_unmount.png",
                    "LIST" => "listRepositories"),
                "users" => array(
                    "AJXP_MIME" => "users_zone",
                    "LABEL" => "ajxp_conf.2",
                    "DESCRIPTION" => "ajxp_conf.139",
                    "ICON" => "users-folder.png",
                    "LIST" => "listUsers"
                ),
                "roles" => array(
                    "AJXP_MIME" => "roles_zone",
                    "LABEL" => "ajxp_conf.69",
                    "DESCRIPTION" => "ajxp_conf.140",
                    "ICON" => "user-acl.png",
                    "LIST" => "listRoles"),
            )
        ),
        "config" => array(
            "AJXP_MIME" => "plugins_zone",
            "LABEL" => "ajxp_conf.109",
            "ICON" => "preferences_desktop.png",
            "DESCRIPTION" => "ajxp_conf.136",
            "CHILDREN" => array(
                "core"	   	   => array(
                    "AJXP_MIME" => "plugins_zone",
                    "LABEL" => "ajxp_conf.98",
                    "DESCRIPTION" => "ajxp_conf.133",
                    "ICON" => "preferences_desktop.png",
                    "LIST" => "listPlugins"),
                "plugins"	   => array(
                    "AJXP_MIME" => "plugins_zone",
                    "LABEL" => "ajxp_conf.99",
                    "DESCRIPTION" => "ajxp_conf.134",
                    "ICON" => "folder_development.png",
                    "LIST" => "listPlugins"),
                "core_plugins" => array(
                    "AJXP_MIME" => "plugins_zone",
                    "LABEL" => "ajxp_conf.123",
                    "DESCRIPTION" => "ajxp_conf.135",
                    "ICON" => "folder_development.png",
                    "LIST" => "listPlugins"),
            )
        ),
        "admin" => array(
            "LABEL" => "ajxp_conf.111",
            "ICON" => "toggle_log.png",
            "DESCRIPTION" => "ajxp_conf.141",
            "CHILDREN" => array(
                "logs" => array(
                    "LABEL" => "ajxp_conf.4",
                    "DESCRIPTION" => "ajxp_conf.142",
                    "ICON" => "toggle_log.png",
                    "LIST" => "listLogFiles"),
                "diagnostic" => array(
                    "LABEL" => "ajxp_conf.5",
                    "DESCRIPTION" => "ajxp_conf.143",
                    "ICON" => "susehelpcenter.png", "LIST" => "printDiagnostic")
            )
        ),
        "developer" => array(
            "LABEL" => "ajxp_conf.144",
            "ICON" => "applications_engineering.png",
            "DESCRIPTION" => "ajxp_conf.145",
            "CHILDREN" => array(
                "actions" => array(
                    "LABEL" => "ajxp_conf.146",
                    "DESCRIPTION" => "ajxp_conf.147",
                    "ICON" => "book.png",
                    "LIST" => "listActions"),
                "hooks" => array(
                    "LABEL" => "ajxp_conf.148",
                    "DESCRIPTION" => "ajxp_conf.149",
                    "ICON" => "book.png",
                    "LIST" => "listHooks")
            )
        )
    );


    /**
     * Do not display AJXP_GRP_/ and AJXP_USR_/ roles if not in server debug mode
     * @param $key
     * @return bool
     */
    protected function filterReservedRoles($key){
        return (strpos($key, "AJXP_GRP_/") === FALSE && strpos($key, "AJXP_USR_/") === FALSE);
    }

    /**
     * @param ContextInterface $ctx
     * @param $currentUserIsGroupAdmin
     * @param bool $withLabel
     * @return array
     */
    protected function getEditableParameters($ctx, $currentUserIsGroupAdmin, $withLabel = false){

        $query = "//param|//global_param";
        if($currentUserIsGroupAdmin){
            $query = "//param[@scope]|//global_param[@scope]";
        }

        $nodes = PluginsService::getInstance($ctx)->searchAllManifests($query, "node", false, true, true);
        $actions = array();
        foreach ($nodes as $node) {
            if($node->parentNode->nodeName != "server_settings") continue;
            $parentPlugin = $node->parentNode->parentNode;
            $pId = $parentPlugin->attributes->getNamedItem("id")->nodeValue;
            if (empty($pId)) {
                $pId = $parentPlugin->nodeName .".";
                if($pId == "ajxpdriver.") $pId = "access.";
                $pId .= $parentPlugin->attributes->getNamedItem("name")->nodeValue;
            }
            if(!is_array($actions[$pId])) $actions[$pId] = array();
            $actionName = $node->attributes->getNamedItem("name")->nodeValue;
            $attributes = array();
            for( $i = 0; $i < $node->attributes->length; $i ++){
                $att = $node->attributes->item($i);
                $value = $att->nodeValue;
                if(in_array($att->nodeName, array("choices", "description", "group", "label"))) {
                    $value = XMLWriter::replaceAjxpXmlKeywords($value);
                }
                $attributes[$att->nodeName] = $value;
            }
            if($withLabel){
                $actions[$pId][$actionName] = array(
                    "parameter" => $actionName ,
                    "label" => $attributes["label"],
                    "attributes" => $attributes
                );
            }else{
                $actions[$pId][] = $actionName;
            }

        }
        foreach ($actions as $actPid => $actionGroup) {
            ksort($actionGroup, SORT_STRING);
            $actions[$actPid] = array();
            foreach ($actionGroup as $v) {
                $actions[$actPid][] = $v;
            }
        }
        return $actions;
    }

    /**
     * List actions
     * @param $action
     * @param $httpVars
     * @param $fileVars
     * @param ContextInterface $ctx
     */
    public function listAllActions($action, $httpVars, $fileVars, ContextInterface $ctx)
    {
        $loggedUser = $ctx->getUser();
        if(UsersService::usersEnabled() && !$loggedUser->isAdmin()) return ;
        switch ($action) {
            //------------------------------------
            //	BASIC LISTING
            //------------------------------------
            case "list_all_repositories_json":

                $repositories = RepositoryService::listAllRepositories();
                $repoOut = array();
                foreach ($repositories as $repoObject) {
                    $repoOut[$repoObject->getId()] = $repoObject->getDisplay();
                }
                HTMLWriter::charsetHeader("application/json");
                $mess = LocaleService::getMessages();
                echo json_encode(array("LEGEND" => $mess["ajxp_conf.150"], "LIST" => $repoOut));

            break;

            case "list_all_plugins_actions":

                $currentUserIsGroupAdmin = ($loggedUser != null && $loggedUser->getGroupPath() != "/");
                if($currentUserIsGroupAdmin){
                    // Group admin : do not allow actions edition
                    HTMLWriter::charsetHeader("application/json");
                    echo json_encode(array("LIST" => array(), "HAS_GROUPS" => true));
                    return;
                }
                if(isSet($_SESSION["ALL_ACTIONS_CACHE"])){
                    $actions = $_SESSION["ALL_ACTIONS_CACHE"];
                }else{

                    $nodes = PluginsService::getInstance($ctx)->searchAllManifests("//action", "node", false, true, true);
                    $actions = array();
                    foreach ($nodes as $node) {
                        $xPath = new DOMXPath($node->ownerDocument);
                        $proc = $xPath->query("processing", $node);
                        if(!$proc->length) continue;
                        $txt = $xPath->query("gui/@text", $node);
                        if ($txt->length) {
                            $messId = $txt->item(0)->nodeValue;
                        } else {
                            $messId = "";
                        }
                        $parentPlugin = $node->parentNode->parentNode->parentNode;
                        $pId = $parentPlugin->attributes->getNamedItem("id")->nodeValue;
                        if (empty($pId)) {
                            $pId = $parentPlugin->nodeName .".";
                            if($pId == "ajxpdriver.") $pId = "access.";
                            $pId .= $parentPlugin->attributes->getNamedItem("name")->nodeValue;
                        }
                        //echo($pId." : ". $node->attributes->getNamedItem("name")->nodeValue . " (".$messId.")<br>");
                        if(!is_array($actions[$pId])) $actions[$pId] = array();
                        $actionName = $node->attributes->getNamedItem("name")->nodeValue;
                        $actions[$pId][$actionName] = array( "action" => $actionName , "label" => $messId);

                    }
                    ksort($actions, SORT_STRING);
                    foreach ($actions as $actPid => $actionGroup) {
                        ksort($actionGroup, SORT_STRING);
                        $actions[$actPid] = array();
                        foreach ($actionGroup as $v) {
                            $actions[$actPid][] = $v;
                        }
                    }
                    $_SESSION["ALL_ACTIONS_CACHE"] = $actions;
                }
                HTMLWriter::charsetHeader("application/json");
                echo json_encode(array("LIST" => $actions, "HAS_GROUPS" => true));
                break;

            case "list_all_plugins_parameters":

                if(isSet($_SESSION["ALL_PARAMS_CACHE"])){
                    $actions = $_SESSION["ALL_PARAMS_CACHE"];
                }else{
                    $currentUserIsGroupAdmin = ($ctx->hasUser() && $ctx->getUser()->getGroupPath() != "/");
                    $actions = $this->getEditableParameters($ctx, $currentUserIsGroupAdmin, true);
                    $_SESSION["ALL_PARAMS_CACHE"] = $actions;
                }
                HTMLWriter::charsetHeader("application/json");
                echo json_encode(array("LIST" => $actions, "HAS_GROUPS" => true));
                break;

            case "parameters_to_form_definitions" :

                $data = json_decode(TextEncoder::magicDequote($httpVars["json_parameters"]), true);
                XMLWriter::header("standard_form");
                foreach ($data as $repoScope => $pluginsData) {
                    echo("<repoScope id='$repoScope'>");
                    foreach ($pluginsData as $pluginId => $paramData) {
                        foreach ($paramData as $paramId => $paramValue) {
                            $query = "//param[@name='$paramId']|//global_param[@name='$paramId']";
                            $nodes = PluginsService::getInstance($ctx)->searchAllManifests($query, "node", false, true, true);
                            if(!count($nodes)) continue;
                            $n = $nodes[0];
                            if ($n->attributes->getNamedItem("group") != null) {
                                $n->attributes->getNamedItem("group")->nodeValue = "$pluginId";
                            } else {
                                $n->appendChild($n->ownerDocument->createAttribute("group"));
                                $n->attributes->getNamedItem("group")->nodeValue = "$pluginId";
                            }
                            if(is_bool($paramValue)) $paramValue = ($paramValue ? "true" : "false");
                            if ($n->attributes->getNamedItem("default") != null) {
                                $n->attributes->getNamedItem("default")->nodeValue = $paramValue;
                            } else {
                                $n->appendChild($n->ownerDocument->createAttribute("default"));
                                $n->attributes->getNamedItem("default")->nodeValue = $paramValue;
                            }
                            echo(XMLWriter::replaceAjxpXmlKeywords($n->ownerDocument->saveXML($n)));
                        }
                    }
                    echo("</repoScope>");
                }
                XMLWriter::close("standard_form");
                break;

            default:
                break;
        }
    }

    /**
     * @inheritdoc
     */
    public function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if($contribNode->nodeName != "actions") return;
        $currentUserIsGroupAdmin = ($ctx->hasUser() && $ctx->getUser()->getGroupPath() != "/");
        if(!$currentUserIsGroupAdmin) return;
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $publicUrlNodeList = $actionXpath->query('action[@name="create_repository"]/subMenu', $contribNode);
        if ($publicUrlNodeList->length) {
            $publicUrlNode = $publicUrlNodeList->item(0);
            $publicUrlNode->parentNode->removeChild($publicUrlNode);
        }
    }

    /**
     * Bookmark any page for the admin interface
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    public function preProcessBookmarkAction(ServerRequestInterface &$request, ResponseInterface $response)
    {

        $httpVars = $request->getParsedBody();
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        if (isSet($httpVars["bm_action"]) && $httpVars["bm_action"] == "add_bookmark" && UsersService::usersEnabled()) {
            $bmUser = $ctx->getUser();
            $repositoryId = $ctx->getRepositoryId();
            $bookmarks = $bmUser->getBookmarks($repositoryId);
            foreach ($bookmarks as $bm) {
                if ($bm["PATH"] == $httpVars["bm_path"]) {
                    $httpVars["bm_action"] = "delete_bookmark";
                    $request = $request->withParsedBody($httpVars);
                    break;
                }
            }
        }

    }

    /**
     * @param ContextInterface $ctx
     * @param $baseGroup
     * @param $term
     * @param int $offset
     * @param int $limit
     */
    public function recursiveSearchGroups(ContextInterface $ctx, $baseGroup, $term, $offset=-1, $limit=-1)
    {
        if($ctx->hasUser()){
            $baseGroup = $ctx->getUser()->getRealGroupPath($baseGroup);
        }
        $groups = UsersService::listChildrenGroups($baseGroup);
        foreach ($groups as $groupId => $groupLabel) {

            if (preg_match("/$term/i", $groupLabel) == TRUE ) {
                $trimmedG = trim($baseGroup, "/");
                if(!empty($trimmedG)) $trimmedG .= "/";
                $nodeKey = "/data/users/".$trimmedG.ltrim($groupId,"/");
                $meta = array(
                    "icon" => "users-folder.png",
                    "ajxp_mime" => "group_editable"
                );
                if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                echo XMLWriter::renderNode($nodeKey, $groupLabel, false, $meta, true, false);
            }
            $this->recursiveSearchGroups($ctx, rtrim($baseGroup, "/")."/".ltrim($groupId, "/"), $term);

        }

        $users = UsersService::listUsers($baseGroup, $term, $offset, $limit);
        foreach ($users as $userId => $userObject) {
            $gPath = $userObject->getGroupPath();
            $realGroup = $ctx->getUser()->getRealGroupPath($ctx->getUser()->getGroupPath());
            if(strlen($realGroup) > 1 && strpos($gPath, $realGroup) === 0){
                $gPath = substr($gPath, strlen($realGroup));
            }
            $trimmedG = trim($gPath, "/");
            if(!empty($trimmedG)) $trimmedG .= "/";

            $nodeKey = "/data/users/".$trimmedG.$userId;
            $meta = array(
                "icon" => "user.png",
                "ajxp_mime" => "user_editable"
            );
            if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            $userDisplayName = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $userObject, "core.conf", $userId);
            echo XMLWriter::renderNode($nodeKey, $userDisplayName, true, $meta, true, false);
        }

    }

    /**
     * Search users
     * @param $action
     * @param $httpVars
     * @param $fileVars
     * @param ContextInterface $ctx
     */
    public function searchAction($action, $httpVars, $fileVars, ContextInterface $ctx)
    {
        if(!InputFilter::decodeSecureMagic($httpVars["dir"]) == "/data/users") return;
        $query = InputFilter::decodeSecureMagic($httpVars["query"]);
        $limit = $offset = -1;
        if(isSet($httpVars["limit"])) $limit = intval(InputFilter::sanitize($httpVars["limit"], InputFilter::SANITIZE_ALPHANUM));
        if(isSet($httpVars["offset"])) $offset = intval(InputFilter::sanitize($httpVars["offset"], InputFilter::SANITIZE_ALPHANUM));
        XMLWriter::header();
        $this->recursiveSearchGroups($ctx, "/", $query, $offset, $limit);
        XMLWriter::close();

    }

    /**
     * Called internally to populate left menu
     * @param ContextInterface $ctx
     * @return array
     * @throws \Exception
     */
    protected function getMainTree(ContextInterface $ctx){
        $rootNodes = $this->rootNodes;
        $user = $ctx->getUser();
        if ($user != null && $user->getGroupPath() != "/") {
            // Group Admin
            unset($rootNodes["config"]);
            unset($rootNodes["admin"]);
            unset($rootNodes["developer"]);
        }
        Controller::applyHook("ajxp_conf.list_config_nodes", array($ctx, &$rootNodes));
        return $rootNodes;
    }

    /**
     * Render a bookmark node
     * @param $path
     * @param $data
     * @param $messages
     */
    protected function renderNode($path, $data, $messages){
        if(isSet($messages[$data["LABEL"]])) $data["LABEL"] = $messages[$data["LABEL"]];
        if(isSet($messages[$data["DESCRIPTION"]])) $data["DESCRIPTION"] = $messages[$data["DESCRIPTION"]];
        $nodeLabel = $data["LABEL"];
        $attributes = array(
            "description" => $data["DESCRIPTION"],
            "icon"        => $data["ICON"]
        );
        if(in_array($path, $this->currentBookmarks)) {
            $attributes["ajxp_bookmarked"]="true";
            $attributes["overlay_icon"] = "bookmark.png";
        }
        if(basename($path) == "users") {
            $attributes["remote_indexation"] = "admin_search";
        }
        if(isSet($data["AJXP_MIME"])) {
            $attributes["ajxp_mime"] = $data["AJXP_MIME"];
        }
        if(isSet($data["METADATA"]) && is_array($data["METADATA"])){
            $attributes = array_merge($attributes, $data["METADATA"]);
        }
        $hasChildren = isSet($data["CHILDREN"]);
        XMLWriter::renderNode($path, $nodeLabel, false, $attributes, false);
        if($hasChildren){
            foreach($data["CHILDREN"] as $cKey => $cData){
                $this->renderNode($path."/".$cKey, $cData, $messages);
            }
        }
        XMLWriter::close();
    }

    /**
     * Main switch for actions
     * @param $action
     * @param $httpVars
     * @param $fileVars
     * @param ContextInterface $ctx
     * @throws UserNotFoundException
     * @throws \Exception
     */
    public function switchAction($action, $httpVars, $fileVars, ContextInterface $ctx)
    {
        //parent::accessPreprocess($action, $httpVars, $fileVars);
        $loggedUser = $ctx->getUser();
        if(UsersService::usersEnabled() && !$loggedUser->isAdmin()) {
            return ;
        }
        if (UsersService::usersEnabled()) {
            $currentBookmarks = $loggedUser->getBookmarks($ctx->getRepositoryId());
            // FLATTEN
            foreach ($currentBookmarks as $bm) {
                $this->currentBookmarks[] = $bm["PATH"];
            }
        }

        if ($action == "edit") {
            if (isSet($httpVars["sub_action"])) {
                $action = $httpVars["sub_action"];
            }
        }
        $mess = LocaleService::getMessages();
        $currentUserIsGroupAdmin = ($loggedUser != null && $loggedUser->getGroupPath() != "/");
        if ($currentUserIsGroupAdmin && ConfService::getAuthDriverImpl()->isAjxpAdmin($loggedUser->getId())) {
            $currentUserIsGroupAdmin = false;
        }
        $currentAdminBasePath = "/";
        if ($loggedUser!=null && $loggedUser->getGroupPath()!=null) {
            $currentAdminBasePath = $loggedUser->getGroupPath();
        }


        switch ($action) {
            //------------------------------------
            //	BASIC LISTING
            //------------------------------------
            case "ls":
                $this->currentContext = $ctx;
                $rootNodes = $this->getMainTree($ctx);
                $rootAttributes = array();
                if(isSet($rootNodes["__metadata__"])){
                    $rootAttributes = $rootNodes["__metadata__"];
                    unset($rootNodes["__metadata__"]);
                }
                $parentName = "";
                $dir = trim(InputFilter::decodeSecureMagic((isset($httpVars["dir"]) ? $httpVars["dir"] : "")), " /");
                if ($dir != "") {
                    $hash = null;
                    if (strstr(urldecode($dir), "#") !== false) {
                        list($dir, $hash) = explode("#", urldecode($dir));
                    }
                    $splits = explode("/", $dir);
                    $root = array_shift($splits);
                    if (count($splits)) {
                        $returnNodes = false;
                        if (isSet($httpVars["file"])) {
                            $returnNodes = true;
                        }
                        $child = $splits[0];
                        if (isSet($rootNodes[$root]["CHILDREN"][$child])) {
                            $atts = array();
                            if ($child == "users") {
                                $atts["remote_indexation"] = "admin_search";
                            }
                            $childData = $rootNodes[$root]["CHILDREN"][$child];
                            $callback = $childData["LIST"];
                            if(isSet($childData["ALIAS"])){
                                $reSplits = explode("/", ltrim($childData["ALIAS"], "/"));
                                $root = array_shift($reSplits);
                                // additional part?
                                array_shift($splits);
                                foreach($splits as $vS) $reSplits[] = $vS;
                                $splits = $reSplits;
                            }
                            if (is_string($callback) && method_exists($this, $callback)) {
                                if(!$returnNodes) XMLWriter::header("tree", $atts);
                                $res = call_user_func(array($this, $callback), implode("/", $splits), $root, $hash, $returnNodes, isSet($httpVars["file"])?$httpVars["file"]:'', "/".$dir, $httpVars);
                                if(!$returnNodes) XMLWriter::close();
                            } else if (is_array($callback)) {
                                $res = call_user_func($callback, implode("/", $splits), $root, $hash, $returnNodes, isSet($httpVars["file"])?$httpVars["file"]:'', "/".$dir, $httpVars);
                            }
                            if ($returnNodes) {
                                XMLWriter::header("tree", $atts);
                                if (isSet($res["/".$dir."/".$httpVars["file"]])) {
                                    print $res["/".$dir."/".$httpVars["file"]];
                                }
                                XMLWriter::close();
                            }
                            return;
                        }
                    } else {
                        $parentName = "/".$root."/";
                        $nodes = $rootNodes[$root]["CHILDREN"];
                    }
                } else {
                    $parentName = "/";
                    $nodes = $rootNodes;
                    if($currentUserIsGroupAdmin){
                        $rootAttributes["group_admin"]  = "1";
                    }
                }
                if (isSet($httpVars["file"])) {
                    $parentName = $httpVars["dir"]."/";
                    $nodes = array(basename($httpVars["file"]) =>  array("LABEL" => basename($httpVars["file"])));
                }
                if (isSet($nodes)) {
                    XMLWriter::header("tree", $rootAttributes);
                    if(!isSet($httpVars["file"])) XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="detail"><column messageId="ajxp_conf.1" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_conf.102" attributeName="description" sortType="String"/></columns>');
                    foreach ($nodes as $key => $data) {
                        $this->renderNode($parentName.$key, $data, $mess);
                    }
                    XMLWriter::close();

                }

            break;

            case "stat" :

                header("Content-type:application/json");
                print '{"mode":true}';
                return;

            break;

            case "clear_plugins_cache":
                XMLWriter::header();
                ConfService::clearAllCaches();
                XMLWriter::sendMessage($mess["ajxp_conf.".(AJXP_SKIP_CACHE?"132":"131")], null);
                XMLWriter::reloadDataNode();
                XMLWriter::close();
                break;


            case "create_group":

                if (isSet($httpVars["group_path"])) {
                    $basePath = PathUtils::forwardSlashDirname($httpVars["group_path"]);
                    if(empty($basePath)) $basePath = "/";
                    $gName = InputFilter::sanitize(InputFilter::decodeSecureMagic(basename($httpVars["group_path"])), InputFilter::SANITIZE_ALPHANUM);
                } else {
                    $basePath = substr($httpVars["dir"], strlen("/data/users"));
                    $gName    = InputFilter::sanitize(TextEncoder::magicDequote($httpVars["group_name"]), InputFilter::SANITIZE_ALPHANUM);
                }
                $gLabel   = InputFilter::decodeSecureMagic($httpVars["group_label"]);
                $basePath = ($ctx->hasUser() ? $ctx->getUser()->getRealGroupPath($basePath) : $basePath);

                UsersService::createGroup($basePath, $gName, $gLabel);
                
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.160"], null);
                XMLWriter::reloadDataNode();
                XMLWriter::close();

            break;

            case "create_role":
                $roleId = InputFilter::sanitize(TextEncoder::magicDequote($httpVars["role_id"]), InputFilter::SANITIZE_HTML_STRICT);
                if (!strlen($roleId)) {
                    throw new \Exception($mess[349]);
                }
                if (RolesService::getRole($roleId) !== false) {
                    throw new \Exception($mess["ajxp_conf.65"]);
                }
                $r = new AJXP_Role($roleId);
                $user = $ctx->getUser();
                if ($user !=null && $user->getGroupPath()!=null) {
                    $r->setGroupPath($user->getGroupPath());
                }
                RolesService::updateRole($r);
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.66"], null);
                XMLWriter::reloadDataNode("", $httpVars["role_id"]);
                XMLWriter::close();
            break;

            case "edit_role" :

                $roleId = TextEncoder::magicDequote($httpVars["role_id"]);
                $roleGroup = false;
                $userObject = null;
                $groupLabel = null;
                $currentMainUser = $ctx->getUser();

                if (strpos($roleId, "AJXP_GRP_") === 0) {
                    $groupPath = substr($roleId, strlen("AJXP_GRP_"));
                    $filteredGroupPath = (!empty($currentMainUser) ? $currentMainUser->getRealGroupPath($groupPath) : $groupPath);
                    if($filteredGroupPath == "/"){
                        $roleId = "AJXP_GRP_/";
                        $groupLabel = $mess["ajxp_conf.151"];
                        $roleGroup = true;
                    }else{
                        $groups = UsersService::listChildrenGroups(PathUtils::forwardSlashDirname($filteredGroupPath));
                        $key = "/".basename($groupPath);
                        if (!array_key_exists($key, $groups)) {
                            throw new \Exception("Cannot find group with this id!");
                        }
                        $roleId = "AJXP_GRP_".$filteredGroupPath;
                        $groupLabel = $groups[$key];
                        $roleGroup = true;
                    }
                }
                if (strpos($roleId, "AJXP_USR_") === 0) {
                    $usrId = str_replace("AJXP_USR_/", "", $roleId);
                    $userObject = UsersService::getUserById($usrId);
                    if(!empty($currentMainUser) && !$currentMainUser->canAdministrate($userObject)){
                        throw new \Exception("Cant find user!");
                    }
                    $role = $userObject->getPersonalRole();
                } else {
                    if($roleGroup){
                        $role = RolesService::getOrCreateRole($roleId, $ctx->hasUser() ? $ctx->getUser()->getGroupPath() : "/");
                    }else{
                        $role = RolesService::getRole($roleId);
                    }
                }
                if ($role === false) {
                    throw new \Exception("Cant find role! ");
                }
                if (isSet($httpVars["format"]) && $httpVars["format"] == "json") {
                    HTMLWriter::charsetHeader("application/json");
                    $roleData = $role->getDataArray(true);
                    $allReps = RepositoryService::listAllRepositories();
                    $sharedRepos = array();
                    if(isSet($userObject)){
                        // Add User shared Repositories as well
                        $acls = $userObject->getMergedRole()->listAcls();
                        if(count($acls)) {
                            $sharedRepos = RepositoryService::listRepositoriesWithCriteria(array(
                                "uuid" => array_keys($acls),
                                "parent_uuid" => AJXP_FILTER_NOT_EMPTY,
                                "owner_user_id" => AJXP_FILTER_NOT_EMPTY
                                ), $count);
                            $allReps = array_merge($allReps, $sharedRepos);
                        }
                    }

                    $repos = array();
                    $repoDetailed = array();
                    // USER
                    foreach ($allReps as $repositoryId => $repositoryObject) {
                        if (!empty($userObject) &&
                            (
                                !$userObject->canSee($repositoryObject) || $repositoryObject->isTemplate
                                || ($repositoryObject->getAccessType()=="ajxp_conf" && !$userObject->isAdmin())
                                || ($repositoryObject->getUniqueUser() != null && $repositoryObject->getUniqueUser() != $userObject->getId())
                            )
                        ){
                            continue;
                        }else if(empty($userObject) && (
                                (empty($currentMainUser) && !$currentMainUser->canAdministrate($repositoryObject)) || $repositoryObject->isTemplate
                            )){
                            continue;
                        }
                        $meta = array();
                        try{
                            $metaSources = $repositoryObject->getContextOption($ctx, "META_SOURCES");
                            if($metaSources !== null){
                                $meta = array_keys($metaSources);
                            }
                        }catch(\Exception $e){
                            if(isSet($sharedRepos[$repositoryId])) unset($sharedRepos[$repositoryId]);
                            $this->logError("Invalid Share", "Repository $repositoryId has no more parent. Should be deleted.");
                            continue;
                        }
                        $repoDetailed[$repositoryId] = array(
                            "label"  => TextEncoder::toUTF8($repositoryObject->getDisplay()),
                            "driver" => $repositoryObject->getAccessType(),
                            "scope"  => $repositoryObject->securityScope(),
                            "meta"   => $meta
                        );

                        if(array_key_exists($repositoryId, $sharedRepos)){
                            $sharedRepos[$repositoryId] = TextEncoder::toUTF8($repositoryObject->getDisplay());
                            $repoParentLabel = $repoParentId = $repositoryObject->getParentId();
                            $repoOwnerId = $repositoryObject->getOwner();
                            if(isSet($allReps[$repoParentId])){
                                $repoParentLabel = TextEncoder::toUTF8($allReps[$repoParentId]->getDisplay());
                            }
                            $repoOwnerLabel = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $repoOwnerId, "core.conf", $repoOwnerId);
                            $repoDetailed[$repositoryId]["share"] = array(
                                "parent_user" => $repoOwnerId,
                                "parent_user_label" => $repoOwnerLabel,
                                "parent_repository" => $repoParentId,
                                "parent_repository_label" => $repoParentLabel
                            );
                        }else{
                            $repos[$repositoryId] = TextEncoder::toUTF8($repositoryObject->getDisplay());
                        }

                    }
                    // Make sure it's utf8
                    $data = array(
                        "ROLE" => $roleData,
                        "ALL"  => array(
                            "PLUGINS_SCOPES" => array(
                                "GLOBAL_TYPES" => array("conf", "auth", "authfront", "log", "mq", "notifications", "gui", "sec"),
                                "GLOBAL_PLUGINS" => array("action.avatar", "action.disclaimer", "action.scheduler", "action.skeleton", "action.updater")
                            ),
                            "REPOSITORIES" => $repos,
                            "SHARED_REPOSITORIES" => $sharedRepos,
                            "REPOSITORIES_DETAILS" => $repoDetailed,
                            "PROFILES" => array("standard|".$mess["ajxp_conf.156"],"admin|".$mess["ajxp_conf.157"],"shared|".$mess["ajxp_conf.158"],"guest|".$mess["ajxp_conf.159"])
                        )
                    );
                    if (isSet($userObject)) {
                        $data["USER"] = array();
                        $data["USER"]["LOCK"] = $userObject->getLock();
                        $data["USER"]["PROFILE"] = $userObject->getProfile();
                        $data["USER"]["ROLES"] = array_keys($userObject->getRoles());
                        $rolesList = RolesService::getRolesList(array(), true);
                        $data["ALL"]["ROLES"] = array_keys($rolesList);
                        $data["ALL"]["ROLES_DETAILS"] = array();
                        foreach($rolesList as $rId => $rObj){
                            $data["ALL"]["ROLES_DETAILS"][$rId] = array("label" => $rObj->getLabel(), "sticky" => $rObj->alwaysOverrides());
                        }
                        if ($userObject instanceof AbstractUser && isSet($userObject->parentRole)) {
                            $data["PARENT_ROLE"] = $userObject->parentRole->getDataArray();
                        }
                    } else if (isSet($groupPath)) {
                        $data["GROUP"] = array("PATH" => $groupPath, "LABEL" => $groupLabel);
                        if($roleId != "AJXP_GRP_/"){
                            $parentGroupRoles = array();
                            $parentPath = PathUtils::forwardSlashDirname($roleId);
                            while($parentPath != "AJXP_GRP_"){
                                $parentRole = RolesService::getRole($parentPath);
                                if($parentRole != null) {
                                    array_unshift($parentGroupRoles, $parentRole);
                                }
                                $parentPath = PathUtils::forwardSlashDirname($parentPath);
                            }
                            $rootGroup = RolesService::getRole("AJXP_GRP_/");
                            if($rootGroup != null) array_unshift($parentGroupRoles, $rootGroup);
                            if(count($parentGroupRoles)){
                                $parentRole = clone array_shift($parentGroupRoles);
                                foreach($parentGroupRoles as $pgRole){
                                    $parentRole = $pgRole->override($parentRole);
                                }
                                $data["PARENT_ROLE"] = $parentRole->getDataArray();
                            }
                        }
                    }


                    $scope = "role";
                    if($roleGroup) {
                        $scope = "group";
                        if($roleId == "AJXP_GRP_/") $scope = "role";
                    }
                    else if(isSet($userObject)) $scope = "user";
                    $data["SCOPE_PARAMS"] = array();
                    $nodes = PluginsService::getInstance($ctx)->searchAllManifests("//param[contains(@scope,'".$scope."')]|//global_param[contains(@scope,'".$scope."')]", "node", false, true, true);
                    foreach ($nodes as $node) {
                        $pId = $node->parentNode->parentNode->attributes->getNamedItem("id")->nodeValue;
                        $origName = $node->attributes->getNamedItem("name")->nodeValue;
                        if($roleId == "AJXP_GRP_/" && strpos($origName, "ROLE_") ===0 ) continue;
                        $node->attributes->getNamedItem("name")->nodeValue = "AJXP_REPO_SCOPE_ALL/".$pId."/".$origName;
                        $nArr = array();
                        foreach ($node->attributes as $attrib) {
                            $nArr[$attrib->nodeName] = XMLWriter::replaceAjxpXmlKeywords($attrib->nodeValue);
                        }
                        $data["SCOPE_PARAMS"][] = $nArr;
                    }

                    echo json_encode($data);
                }
            break;

            case "post_json_role" :

                $roleId = TextEncoder::magicDequote($httpVars["role_id"]);
                $roleGroup = false;
                $currentMainUser = $ctx->getUser();
                $userObject = $usrId = $filteredGroupPath = null;
                if (strpos($roleId, "AJXP_GRP_") === 0) {
                    $groupPath = substr($roleId, strlen("AJXP_GRP_"));
                    $filteredGroupPath = (!empty($currentMainUser) ? $currentMainUser->getRealGroupPath($groupPath) : $groupPath);
                    $roleId = "AJXP_GRP_".$filteredGroupPath;
                    if($roleId != "AJXP_GRP_/"){
                        $groups = UsersService::listChildrenGroups(PathUtils::forwardSlashDirname($filteredGroupPath));
                        $key = "/".basename($groupPath);
                        if (!array_key_exists($key, $groups)) {
                            throw new \Exception("Cannot find group with this id!");
                        }
                        $groupLabel = $groups[$key];
                    }else{
                        $groupLabel = $mess["ajxp_conf.151"];
                    }
                    $roleGroup = true;
                }
                if (strpos($roleId, "AJXP_USR_") === 0) {
                    $usrId = str_replace("AJXP_USR_/", "", $roleId);
                    $userObject = UsersService::getUserById($usrId);
                    $ctxUser = $ctx->getUser();
                    if(!empty($ctxUser) && !$ctxUser->canAdministrate($userObject)){
                        throw new \Exception("Cannot post role for user ".$usrId);
                    }
                    $originalRole = $userObject->getPersonalRole();
                } else {
                    // second param = create if not exists.
                    if($roleGroup){
                        $originalRole = RolesService::getOrCreateRole($roleId, $ctx->hasUser() ? $ctx->getUser()->getGroupPath() : "/");
                    }else{
                        $originalRole = RolesService::getRole($roleId);
                    }
                }
                if ($originalRole === false) {
                    throw new \Exception("Cant find role! ");
                }

                $jsonData = TextEncoder::magicDequote($httpVars["json_data"]);
                $data = json_decode($jsonData, true);
                $roleData = $data["ROLE"];
                $binariesContext = array();
                $parseContext = $ctx;
                if (isset($userObject)) {
                    $parseContext = new Context(null, $ctx->getRepositoryId());
                    $parseContext->setUserObject($userObject);
                    $binariesContext = array("USER" => $userObject->getId());
                }
                if(isSet($data["FORMS"])){
                    $forms = $data["FORMS"];
                    foreach ($forms as $repoScope => $plugData) {
                        foreach ($plugData as $plugId => $formsData) {
                            $parsed = array();
                            OptionsHelper::parseStandardFormParameters(
                                $parseContext,
                                $formsData,
                                $parsed,
                                "ROLE_PARAM_",
                                $binariesContext,
                                AJXP_Role::$cypheredPassPrefix
                            );
                            $roleData["PARAMETERS"][$repoScope][$plugId] = $parsed;
                        }
                    }
                }else{
                    OptionsHelper::filterFormElementsFromMeta(
                        $data["METADATA"],
                        $roleData,
                        ($userObject != null ? $usrId : null),
                        $binariesContext,
                        AJXP_Role::$cypheredPassPrefix
                    );
                }
                $existingParameters = $originalRole->listParameters(true);
                $this->mergeExistingParameters($roleData["PARAMETERS"], $existingParameters);
                if (isSet($userObject) && isSet($data["USER"]) && isSet($data["USER"]["PROFILE"])) {
                    $userObject->setAdmin(($data["USER"]["PROFILE"] == "admin"));
                    $userObject->setProfile($data["USER"]["PROFILE"]);
                }
                if (isSet($data["GROUP_LABEL"]) && isSet($groupLabel) && $groupLabel != $data["GROUP_LABEL"]) {
                    ConfService::getConfStorageImpl()->relabelGroup($filteredGroupPath, $data["GROUP_LABEL"]);
                }

                if($currentUserIsGroupAdmin){
                    // FILTER DATA FOR GROUP ADMINS
                    $params = $this->getEditableParameters($ctx, true, false);
                    foreach($roleData["PARAMETERS"] as $scope => &$plugsParameters){
                        foreach($plugsParameters as $paramPlugin => &$parameters){
                            foreach($parameters as $pName => $pValue){
                                if(!isSet($params[$paramPlugin]) || !in_array($pName, $params[$paramPlugin])){
                                    unset($parameters[$pName]);
                                }
                            }
                            if(!count($parameters)){
                                unset($plugsParameters[$paramPlugin]);
                            }
                        }
                        if(!count($plugsParameters)){
                            unset($roleData["PARAMETERS"][$scope]);
                        }
                    }
                    // Remerge from parent
                    $roleData["PARAMETERS"] = $originalRole->array_merge_recursive2($originalRole->listParameters(), $roleData["PARAMETERS"]);
                    // Changing Actions is not allowed
                    $roleData["ACTIONS"] = $originalRole->listActionsStates();
                }

                if(isSet($roleData["MASKS"])){
                    foreach($roleData["MASKS"] as $repoId => $serialMask){
                        $roleData["MASKS"][$repoId] = new AJXP_PermissionMask($serialMask);
                    }
                }

                try {
                    $originalRole->bunchUpdate($roleData);
                    if (isSet($userObject)) {
                        $userObject->updatePersonalRole($originalRole);
                        $userObject->save("superuser");
                    } else {
                        RolesService::updateRole($originalRole);
                    }
                    // Reload Role
                    $savedValue = RolesService::getRole($originalRole->getId());
                    $output = array("ROLE" => $savedValue->getDataArray(true), "SUCCESS" => true);
                } catch (\Exception $e) {
                    $output = array("ERROR" => $e->getMessage());
                }
                HTMLWriter::charsetHeader("application/json");
                echo(json_encode($output));

            break;


            case "user_set_lock" :

                $userId = InputFilter::decodeSecureMagic($httpVars["user_id"]);
                $lock = ($httpVars["lock"] == "true" ? true : false);
                $lockType = $httpVars["lock_type"];
                $ctxUser = $ctx->getUser();
                if (UsersService::userExists($userId)) {
                    $userObject = UsersService::getUserById($userId, false);
                    if( !empty($ctxUser) && !$ctxUser->canAdministrate($userObject)){
                        throw new \Exception("Cannot update user data for ".$userId);
                    }
                    if ($lock) {
                        $userObject->setLock($lockType);
                        XMLWriter::header();
                        XMLWriter::sendMessage("Successfully set lock on user ($lockType)", null);
                        XMLWriter::close();
                    } else {
                        $userObject->removeLock();
                        XMLWriter::header();
                        XMLWriter::sendMessage("Successfully unlocked user", null);
                        XMLWriter::close();
                    }
                    $userObject->save("superuser");
                }

            break;

            case "create_user" :

                if (!isset($httpVars["new_user_login"]) || $httpVars["new_user_login"] == "" ||!isset($httpVars["new_user_pwd"]) || $httpVars["new_user_pwd"] == "") {
                    XMLWriter::header();
                    XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
                    XMLWriter::close();
                    return;
                }
                $original_login = TextEncoder::magicDequote($httpVars["new_user_login"]);
                $new_user_login = InputFilter::sanitize($original_login, InputFilter::SANITIZE_EMAILCHARS);
                if($original_login != $new_user_login){
                    throw new \Exception(str_replace("%s", $new_user_login, $mess["ajxp_conf.127"]));
                }
                if (UsersService::userExists($new_user_login, "w") || UsersService::isReservedUserId($new_user_login)) {
                    throw new \Exception($mess["ajxp_conf.43"]);
                }

                $newUser = UsersService::createUser($new_user_login, $httpVars["new_user_pwd"]);
                if (!empty($httpVars["group_path"])) {
                    $newUser->setGroupPath(rtrim($currentAdminBasePath, "/")."/".ltrim($httpVars["group_path"], "/"));
                } else {
                    $newUser->setGroupPath($currentAdminBasePath);
                }

                $newUser->save("superuser");
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.44"], null);
                XMLWriter::reloadDataNode("", $new_user_login);
                XMLWriter::close();

            break;

            case "change_admin_right" :
                $userId = $httpVars["user_id"];
                $user = UsersService::getUserById($userId);
                if($ctx->hasUser() && !$ctx->getUser()->canAdministrate($user)){
                    throw new \Exception("Cannot update user with id ".$userId);
                }
                $user->setAdmin(($httpVars["right_value"]=="1"?true:false));
                $user->save("superuser");
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.45"].$httpVars["user_id"], null);
                XMLWriter::reloadDataNode();
                XMLWriter::close();

            break;

            case "role_update_right" :
                if(!isSet($httpVars["role_id"])
                    || !isSet($httpVars["repository_id"])
                    || !isSet($httpVars["right"]))
                {
                    XMLWriter::header();
                    XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
                    XMLWriter::close();
                    break;
                }
                $rId = InputFilter::sanitize($httpVars["role_id"]);
                $role = RolesService::getRole($rId);
                if($role === false){
                    XMLWriter::header();
                    XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]."(".$rId.")");
                    XMLWriter::close();
                    break;
                }
                $role->setAcl(InputFilter::sanitize($httpVars["repository_id"], InputFilter::SANITIZE_ALPHANUM), InputFilter::sanitize($httpVars["right"], InputFilter::SANITIZE_ALPHANUM));
                RolesService::updateRole($role);
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.46"].$httpVars["role_id"], null);
                XMLWriter::close();

            break;

            case "user_update_right" :
                if(!isSet($httpVars["user_id"])
                    || !isSet($httpVars["repository_id"])
                    || !isSet($httpVars["right"])
                    || !UsersService::userExists($httpVars["user_id"])
                )
                {
                    XMLWriter::header();
                    XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
                    print("<update_checkboxes user_id=\"".$httpVars["user_id"]."\" repository_id=\"".$httpVars["repository_id"]."\" read=\"old\" write=\"old\"/>");
                    XMLWriter::close();
                    return;
                }
                $userId = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                $user = UsersService::getUserById($userId);
                if($ctx->hasUser() && !$ctx->getUser()->canAdministrate($user)){
                    throw new \Exception("Cannot update user with id ".$userId);
                }
                $user->getPersonalRole()->setAcl(InputFilter::sanitize($httpVars["repository_id"], InputFilter::SANITIZE_ALPHANUM), InputFilter::sanitize($httpVars["right"], InputFilter::SANITIZE_ALPHANUM));
                $user->save();
                $loggedUser = $ctx->getUser();
                if ($loggedUser->getId() == $user->getId()) {
                    AuthService::updateUser($user);
                }
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.46"].$httpVars["user_id"], null);
                print("<update_checkboxes user_id=\"".$httpVars["user_id"]."\" repository_id=\"".$httpVars["repository_id"]."\" read=\"".$user->canRead($httpVars["repository_id"])."\" write=\"".$user->canWrite($httpVars["repository_id"])."\"/>");
                XMLWriter::reloadRepositoryList();
                XMLWriter::close();
                return ;
            break;

            case "user_update_group":

                $userSelection = UserSelection::fromContext($ctx, $httpVars);
                $dir = $httpVars["dir"];
                $dest = $httpVars["dest"];
                if (isSet($httpVars["group_path"])) {
                    // API Case
                    $groupPath = $httpVars["group_path"];
                } else {
                    if (strpos($dir, "/data/users",0)!==0 || strpos($dest, "/data/users",0)!==0) {
                        break;
                    }
                    $groupPath = substr($dest, strlen("/data/users"));
                }

                $userId = null;
                $usersMoved = array();

                if (!empty($groupPath)) {
                    $targetPath = rtrim($currentAdminBasePath, "/")."/".ltrim($groupPath, "/");
                } else {
                    $targetPath = $currentAdminBasePath;
                }

                foreach ($userSelection->getFiles() as $selectedUser) {
                    $userId = basename($selectedUser);
                    try{
                        $user = UsersService::getUserById($userId);
                    }catch (UserNotFoundException $u){
                        continue;
                    }
                    if($ctx->hasUser() && !$ctx->getUser()->canAdministrate($user)){
                        continue;
                    }
                    $user->setGroupPath($targetPath, true);
                    $user->save("superuser");
                    $usersMoved[] = $user->getId();
                }
                XMLWriter::header();
                if(count($usersMoved)){
                    XMLWriter::sendMessage(count($usersMoved)." user(s) successfully moved to ".$targetPath, null);
                    XMLWriter::reloadDataNode($dest, $userId);
                    XMLWriter::reloadDataNode();
                }else{
                    XMLWriter::sendMessage(null, "No users moved, there must have been something wrong.");
                }
                XMLWriter::close();

                break;

            case "user_add_role" :
            case "user_delete_role":

                if (!isSet($httpVars["user_id"]) || !isSet($httpVars["role_id"]) || !UsersService::userExists($httpVars["user_id"]) || !RolesService::getRole($httpVars["role_id"])) {
                    throw new \Exception($mess["ajxp_conf.61"]);
                }
                if ($action == "user_add_role") {
                    $act = "add";
                    $messId = "73";
                } else {
                    $act = "remove";
                    $messId = "74";
                }
                $this->updateUserRole($ctx->getUser(), InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS), $httpVars["role_id"], $act);
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.".$messId].$httpVars["user_id"], null);
                XMLWriter::close();

            break;

            case "user_reorder_roles":

                if (!isSet($httpVars["user_id"]) || !UsersService::userExists($httpVars["user_id"]) || !isSet($httpVars["roles"])) {
                    throw new \Exception($mess["ajxp_conf.61"]);
                }
                $roles = json_decode($httpVars["roles"], true);
                $userId = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                $user = UsersService::getUserById($userId);
                if($ctx->hasUser() && !$ctx->getUser()->canAdministrate($user)){
                    throw new \Exception("Cannot update user data for ".$userId);
                }
                $user->updateRolesOrder($roles);
                $user->save("superuser");
                $loggedUser = $ctx->getUser();
                if ($loggedUser->getId() == $user->getId()) {
                    AuthService::updateUser($user);
                }
                XMLWriter::header();
                XMLWriter::sendMessage("Roles reordered for user ".$httpVars["user_id"], null);
                XMLWriter::close();

            break;

            case "users_bulk_update_roles":

                $data = json_decode($httpVars["json_data"], true);
                $userIds = $data["users"];
                $rolesOperations = $data["roles"];
                foreach($userIds as $userId){
                    $userId = InputFilter::sanitize($userId, InputFilter::SANITIZE_EMAILCHARS);
                    if(!UsersService::userExists($userId)) continue;
                    $userObject = UsersService::getUserById($userId, false);
                    if($ctx->hasUser() && !$ctx->getUser()->canAdministrate($userObject)) continue;
                    foreach($rolesOperations as $addOrRemove => $roles){
                        if(!in_array($addOrRemove, array("add", "remove"))) {
                            continue;
                        }
                        foreach($roles as $roleId){
                            if(strpos($roleId, "AJXP_USR_/") === 0 || strpos($roleId,"AJXP_GRP_/") === 0){
                                continue;
                            }
                            $roleId = InputFilter::sanitize($roleId, InputFilter::SANITIZE_FILENAME);
                            if ($addOrRemove == "add") {
                                $roleObject = RolesService::getRole($roleId);
                                $userObject->addRole($roleObject);
                            } else {
                                $userObject->removeRole($roleId);
                            }
                        }
                    }
                    $userObject->save("superuser");
                    $loggedUser = $ctx->getUser();
                    if ($loggedUser->getId() == $userObject->getId()) {
                        AuthService::updateUser($userObject);
                    }
                }
                XMLWriter::header();
                XMLWriter::sendMessage("Successfully updated roles", null);
                XMLWriter::close();

            break;

            case "user_update_role" :

                $selection = UserSelection::fromContext($ctx, $httpVars);
                $files = $selection->getFiles();
                $detectedRoles = array();
                $roleId = null;

                if (isSet($httpVars["role_id"]) && isset($httpVars["update_role_action"])) {
                    $update = $httpVars["update_role_action"];
                    $roleId = $httpVars["role_id"];
                    if (RolesService::getRole($roleId) === false) {
                        throw new \Exception("Invalid role id");
                    }
                }
                foreach ($files as $index => $file) {
                    $userId = basename($file);
                    if (isSet($update)) {
                        $userObject = $this->updateUserRole($ctx->getUser(), $userId, $roleId, $update);
                    } else {
                        try{
                            $userObject = UsersService::getUserById($userId);
                        }catch(UserNotFoundException $u){
                            continue;
                        }
                        if($ctx->hasUser() && !$ctx->getUser()->canAdministrate($userObject)){
                            continue;
                        }
                    }
                    if ($userObject->hasParent()) {
                        unset($files[$index]);
                        continue;
                    }
                    $userRoles = $userObject->getRoles();
                    foreach ($userRoles as $roleIndex => $bool) {
                        if(!isSet($detectedRoles[$roleIndex])) $detectedRoles[$roleIndex] = 0;
                        if($bool === true) $detectedRoles[$roleIndex] ++;
                    }
                }
                $count = count($files);
                XMLWriter::header("admin_data");
                print("<user><ajxp_roles>");
                foreach ($detectedRoles as $roleId => $roleCount) {
                    if($roleCount < $count) continue;
                    print("<role id=\"$roleId\"/>");
                }
                print("</ajxp_roles></user>");
                print("<ajxp_roles>");
                foreach (RolesService::getRolesList(array(), !$this->listSpecialRoles) as $roleId => $roleObject) {
                    print("<role id=\"$roleId\"/>");
                }
                print("</ajxp_roles>");
                XMLWriter::close("admin_data");

            break;

            case "save_custom_user_params" :

                $userId = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                if ($userId == $loggedUser->getId()) {
                    $user = $loggedUser;
                } else {
                    $user = UsersService::getUserById($userId);
                }
                if($ctx->hasUser() && !$ctx->getUser()->canAdministrate($user)){
                    throw new \Exception("Cannot update user with id ".$userId);
                }

                $custom = $user->getPref("CUSTOM_PARAMS");
                if(!is_array($custom)) $custom = array();

                $options = $custom;
                $newCtx = new Context($userId, $ctx->getRepositoryId());
                $this->parseParameters($newCtx, $httpVars, $options, false, $custom);
                $custom = $options;
                $user->setPref("CUSTOM_PARAMS", $custom);
                $user->save();

                if ($loggedUser->getId() == $user->getId()) {
                    AuthService::updateUser($user);
                }
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.47"].$httpVars["user_id"], null);
                XMLWriter::close();

            break;

            case "save_repository_user_params" :
                $userId = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                if ($userId == $loggedUser->getId()) {
                    $user = $loggedUser;
                } else {
                    $user = UsersService::getUserById($userId);
                }
                if($ctx->hasUser() && !$ctx->getUser()->canAdministrate($user)){
                    throw new \Exception("Cannot update user with id ".$userId);
                }

                $wallet = $user->getPref("AJXP_WALLET");
                if(!is_array($wallet)) $wallet = array();
                $repoID = $httpVars["repository_id"];
                if (!array_key_exists($repoID, $wallet)) {
                    $wallet[$repoID] = array();
                }
                $options = $wallet[$repoID];
                $existing = $options;
                $newCtx = new Context($userId, $ctx->getRepositoryId());
                $this->parseParameters($newCtx, $httpVars, $options, false, $existing);
                $wallet[$repoID] = $options;
                $user->setPref("AJXP_WALLET", $wallet);
                $user->save();

                if ($loggedUser->getId() == $user->getId()) {
                    AuthService::updateUser($user);
                }
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.47"].$httpVars["user_id"], null);
                XMLWriter::close();

            break;

            case "update_user_pwd" :
                if (!isSet($httpVars["user_id"]) || !isSet($httpVars["user_pwd"]) || !UsersService::userExists($httpVars["user_id"]) || trim($httpVars["user_pwd"]) == "") {
                    XMLWriter::header();
                    XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
                    XMLWriter::close();
                    return;
                }
                $userId = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                $user = UsersService::getUserById($userId);
                if($ctx->hasUser() && !$ctx->getUser()->canAdministrate($user)){
                    throw new \Exception("Cannot update user data for ".$userId);
                }
                $res = UsersService::updatePassword($userId, $httpVars["user_pwd"]);
                XMLWriter::header();
                if ($res === true) {
                    XMLWriter::sendMessage($mess["ajxp_conf.48"].$userId, null);
                } else {
                    XMLWriter::sendMessage(null, $mess["ajxp_conf.49"]." : $res");
                }
                XMLWriter::close();

            break;

            case "save_user_preference":

                if (!isSet($httpVars["user_id"]) || !UsersService::userExists($httpVars["user_id"])) {
                    throw new \Exception($mess["ajxp_conf.61"]);
                }
                $userId = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                if ($userId == $loggedUser->getId()) {
                    $userObject = $loggedUser;
                } else {
                    $userObject = UsersService::getUserById($userId);
                }
                if($ctx->hasUser() && !$ctx->getUser()->canAdministrate($userObject)){
                    throw new \Exception("Cannot update user data for ".$userId);
                }

                $i = 0;
                while (isSet($httpVars["pref_name_".$i]) && isSet($httpVars["pref_value_".$i])) {
                    $prefName = InputFilter::sanitize($httpVars["pref_name_" . $i], InputFilter::SANITIZE_ALPHANUM);
                    $prefValue = InputFilter::sanitize(TextEncoder::magicDequote($httpVars["pref_value_" . $i]));
                    if($prefName == "password") continue;
                    if ($prefName != "pending_folder" && $userObject == null) {
                        $i++;
                        continue;
                    }
                    $userObject->setPref($prefName, $prefValue);
                    $userObject->save("user");
                    $i++;
                }
                XMLWriter::header();
                XMLWriter::sendMessage("Succesfully saved user preference", null);
                XMLWriter::close();

            break;

            case  "get_drivers_definition":

                XMLWriter::header("drivers", array("allowed" => $currentUserIsGroupAdmin ? "false" : "true"));
                print(XMLWriter::replaceAjxpXmlKeywords(self::availableDriversToXML("param", "", true)));
                XMLWriter::close("drivers");


            break;

            case  "get_templates_definition":

                XMLWriter::header("repository_templates");
                $count = 0;
                $repositories = RepositoryService::listRepositoriesWithCriteria(array(
                    "isTemplate" => '1'
                ), $count);
                foreach ($repositories as $repo) {
                    if(!$repo->isTemplate) continue;
                    $repoId = $repo->getUniqueId();
                    $repoLabel = TextEncoder::toUTF8($repo->getDisplay());
                    $repoType = $repo->getAccessType();
                    print("<template repository_id=\"$repoId\" repository_label=\"$repoLabel\" repository_type=\"$repoType\">");
                    foreach ($repo->getOptionsDefined() as $optionName) {
                        print("<option name=\"$optionName\"/>");
                    }
                    print("</template>");
                }
                XMLWriter::close("repository_templates");


            break;

            case "create_repository" :

                $repDef = $httpVars;
                $isTemplate = isSet($httpVars["sf_checkboxes_active"]);
                unset($repDef["get_action"]);
                unset($repDef["sf_checkboxes_active"]);
                if (isSet($httpVars["json_data"])) {
                    $repDef = json_decode(TextEncoder::magicDequote($httpVars["json_data"]), true);
                    $options = $repDef["DRIVER_OPTIONS"];
                } else {
                    $options = array();
                    $this->parseParameters($ctx, $repDef, $options, true);
                }
                if (count($options)) {
                    $repDef["DRIVER_OPTIONS"] = $options;
                    unset($repDef["DRIVER_OPTIONS"]["AJXP_GROUP_PATH_PARAMETER"]);
                    if(isSet($options["AJXP_SLUG"])){
                        $repDef["AJXP_SLUG"] = $options["AJXP_SLUG"];
                        unset($repDef["DRIVER_OPTIONS"]["AJXP_SLUG"]);
                    }
                }
                if (strstr($repDef["DRIVER"], "ajxp_template_") !== false) {
                    $templateId = substr($repDef["DRIVER"], 14);
                    $templateRepo = RepositoryService::getRepositoryById($templateId);
                    $newRep = $templateRepo->createTemplateChild($repDef["DISPLAY"], $repDef["DRIVER_OPTIONS"], $loggedUser->getId());
                    if(isSet($repDef["AJXP_SLUG"])){
                        $newRep->setSlug($repDef["AJXP_SLUG"]);
                    }
                } else {
                    if ($currentUserIsGroupAdmin) {
                        throw new \Exception("You are not allowed to create a workspace from a driver. Use a template instead.");
                    }
                    $pServ = PluginsService::getInstance($ctx);
                    $driver = $pServ->getPluginByTypeName("access", $repDef["DRIVER"]);

                    $newRep = RepositoryService::createRepositoryFromArray(0, $repDef);
                    $testFile = $driver->getBaseDir()."/test.".$newRep->getAccessType()."Access.php";
                    if (!$isTemplate && is_file($testFile)) {
                        //chdir(AJXP_TESTS_FOLDER."/plugins");
                        $className = "\\Pydio\\Tests\\".$newRep->getAccessType()."AccessTest";
                        if (!class_exists($className))
                            include($testFile);
                        $class = new $className();
                        $result = $class->doRepositoryTest($newRep);
                        if (!$result) {
                            XMLWriter::header();
                            XMLWriter::sendMessage(null, $class->failedInfo);
                            XMLWriter::close();
                            return;
                        }
                    }
                    // Apply default metasource if any
                    if ($driver != null && $driver->getConfigs()!=null ) {
                        $confs = $driver->getConfigs();
                        if (!empty($confs["DEFAULT_METASOURCES"])) {
                            $metaIds = InputFilter::parseCSL($confs["DEFAULT_METASOURCES"]);
                            $metaSourceOptions = array();
                            foreach ($metaIds as $metaID) {
                                $metaPlug = $pServ->getPluginById($metaID);
                                if($metaPlug == null) continue;
                                $pNodes = $metaPlug->getManifestRawContent("//param[@default]", "nodes");
                                $defaultParams = array();
                                /** @var \DOMElement $domNode */
                                foreach ($pNodes as $domNode) {
                                    $defaultParams[$domNode->getAttribute("name")] = $domNode->getAttribute("default");
                                }
                                $metaSourceOptions[$metaID] = $defaultParams;
                            }
                            $newRep->addOption("META_SOURCES", $metaSourceOptions);
                        }
                    }
                }

                if ($this->repositoryExists($newRep->getDisplay())) {
                    XMLWriter::header();
                    XMLWriter::sendMessage(null, $mess["ajxp_conf.50"]);
                    XMLWriter::close();
                    return;
                }
                if ($isTemplate) {
                    $newRep->isTemplate = true;
                }
                if ($currentUserIsGroupAdmin) {
                    $newRep->setGroupPath($ctx->getUser()->getGroupPath());
                } else if (!empty($options["AJXP_GROUP_PATH_PARAMETER"])) {
                    $value = InputFilter::securePath(rtrim($currentAdminBasePath, "/") . "/" . ltrim($options["AJXP_GROUP_PATH_PARAMETER"], "/"));
                    $newRep->setGroupPath($value);
                }

                $res = RepositoryService::addRepository($newRep);
                XMLWriter::header();
                if ($res == -1) {
                    XMLWriter::sendMessage(null, $mess["ajxp_conf.51"]);
                } else {
                    $defaultRights = $newRep->getDefaultRight();
                    if(!empty($defaultRights)){
                        $groupRole = RolesService::getOrCreateRole("AJXP_GRP_" . $currentAdminBasePath, $currentAdminBasePath);
                        $groupRole->setAcl($newRep->getId(), $defaultRights);
                    }
                    $loggedUser = $ctx->getUser();
                    $loggedUser->getPersonalRole()->setAcl($newRep->getUniqueId(), "rw");
                    $loggedUser->recomputeMergedRole();
                    $loggedUser->save("superuser");
                    AuthService::updateUser($loggedUser);

                    XMLWriter::sendMessage($mess["ajxp_conf.52"], null);
                    XMLWriter::reloadDataNode("", $newRep->getUniqueId());
                    XMLWriter::reloadRepositoryList();
                }
                XMLWriter::close();



            break;

            case "edit_repository" :
                $repId = $httpVars["repository_id"];
                $repository = RepositoryService::getRepositoryById($repId);
                if ($repository == null) {
                    throw new \Exception("Cannot find workspace with id $repId");
                }
                if ($ctx->hasUser() && !$ctx->getUser()->canAdministrate($repository)) {
                    throw new \Exception("You are not allowed to edit this workspace!");
                }
                $pServ = PluginsService::getInstance($ctx);
                $plug = $pServ->getPluginById("access.".$repository->getAccessType());
                if ($plug == null) {
                    throw new \Exception("Cannot find access driver (".$repository->getAccessType().") for workspace!");
                }
                XMLWriter::header("admin_data");
                $slug = $repository->getSlug();
                if ($slug == "" && $repository->isWriteable()) {
                    $repository->setSlug();
                    RepositoryService::replaceRepository($repId, $repository);
                }
                $ctxUser = $ctx->getUser();
                if ($ctxUser!=null && $ctxUser->getGroupPath() != null) {
                    $rgp = $repository->getGroupPath();
                    if($rgp == null) $rgp = "/";
                    if (strlen($rgp) < strlen($ctxUser->getGroupPath())) {
                        $repository->setWriteable(false);
                    }
                }
                $nested = array();
                $definitions = $plug->getConfigsDefinitions();
                print("<repository index=\"$repId\" securityScope=\"".$repository->securityScope()."\"");
                foreach ($repository as $name => $option) {
                    if(strstr($name, " ")>-1) continue;
                    if ($name == "driverInstance") continue;
                    if (!is_array($option)) {
                        if (is_bool($option)) {
                            $option = ($option?"true":"false");
                        }
                        print(" $name=\"".TextEncoder::toUTF8(StringHelper::xmlEntities($option))."\" ");
                    } else if (is_array($option)) {
                        $nested[] = $option;
                    }
                }
                if (count($nested)) {
                    print(">");
                    foreach ($nested as $option) {
                        foreach ($option as $key => $optValue) {
                            if (is_array($optValue) && count($optValue)) {
                                print("<param name=\"$key\"><![CDATA[".json_encode($optValue)."]]></param>");
                            } else if (is_object($optValue)){
                                print("<param name=\"$key\"><![CDATA[".json_encode($optValue)."]]></param>");
                            } else {
                                if (is_bool($optValue)) {
                                    $optValue = ($optValue?"true":"false");
                                } else if(isSet($definitions[$key]) && $definitions[$key]["type"] == "password" && !empty($optValue)){
                                    $optValue = "__AJXP_VALUE_SET__";
                                }

                                $optValue = StringHelper::xmlEntities($optValue, true);
                                print("<param name=\"$key\" value=\"$optValue\"/>");
                            }
                        }
                    }
                    // Add SLUG
                    if(!$repository->isTemplate()) print("<param name=\"AJXP_SLUG\" value=\"".$repository->getSlug()."\"/>");
                    if ($repository->getGroupPath() != null) {
                        $groupPath = $repository->getGroupPath();
                        if($currentAdminBasePath != "/") $groupPath = substr($repository->getGroupPath(), strlen($currentAdminBasePath));
                        print("<param name=\"AJXP_GROUP_PATH_PARAMETER\" value=\"".$groupPath."\"/>");
                    }

                    print("</repository>");
                } else {
                    print("/>");
                }
                if ($repository->hasParent()) {
                    $parent = RepositoryService::getRepositoryById($repository->getParentId());
                    if (isSet($parent) && $parent->isTemplate()) {
                        $parentLabel = $parent->getDisplay();
                        $parentType = $parent->getAccessType();
                        print("<template repository_id=\"".$repository->getParentId()."\" repository_label=\"$parentLabel\" repository_type=\"$parentType\">");
                        foreach ($parent->getOptionsDefined() as $parentOptionName) {
                            print("<option name=\"$parentOptionName\"/>");
                        }
                        print("</template>");
                    }
                }
                $manifest = $plug->getManifestRawContent("server_settings/param");
                $manifest = XMLWriter::replaceAjxpXmlKeywords($manifest);
                $clientSettings = $plug->getManifestRawContent("client_settings", "xml");
                $iconClass = "";$descriptionTemplate = "";
                if($clientSettings->length){
                    $iconClass = $clientSettings->item(0)->getAttribute("iconClass");
                    $descriptionTemplate = $clientSettings->item(0)->getAttribute("description_template");
                }
                print("<ajxpdriver name=\"".$repository->getAccessType()."\" label=\"". StringHelper::xmlEntities($plug->getManifestLabel()) ."\" iconClass=\"$iconClass\" description_template=\"$descriptionTemplate\" description=\"". StringHelper::xmlEntities($plug->getManifestDescription()) ."\">$manifest</ajxpdriver>");
                print("<metasources>");
                $metas = $pServ->getPluginsByType("metastore");
                $metas = array_merge($metas, $pServ->getPluginsByType("meta"));
                $metas = array_merge($metas, $pServ->getPluginsByType("index"));
                /** @var Plugin $metaPlug */
                foreach ($metas as $metaPlug) {
                    print("<meta id=\"".$metaPlug->getId()."\" label=\"". StringHelper::xmlEntities($metaPlug->getManifestLabel()) ."\" description=\"". StringHelper::xmlEntities($metaPlug->getManifestDescription()) ."\">");
                    $manifest = $metaPlug->getManifestRawContent("server_settings/param");
                    $manifest = XMLWriter::replaceAjxpXmlKeywords($manifest);
                    print($manifest);
                    print("</meta>");
                }
                print("</metasources>");
                if(!$repository->isTemplate()){
                    print "<additional_info>";
                    $users = UsersService::countUsersForRepository($ctx, $repId, false, true);
                    $shares = ConfService::getConfStorageImpl()->simpleStoreList("share", null, "", "serial", '', $repId);
                    print('<users total="'.$users.'"/>');
                    print('<shares total="'.count($shares).'"/>');
                    $rootGroup = RolesService::getRole("AJXP_GRP_/");
                    if($rootGroup !== false && $rootGroup->hasMask($repId)){
                        print("<mask><![CDATA[".json_encode($rootGroup->getMask($repId))."]]></mask>");
                    }
                    print "</additional_info>";
                }
                XMLWriter::close("admin_data");

            break;

            case "edit_repository_label" :
            case "edit_repository_data" :
                $repId = $httpVars["repository_id"];
                $repo = RepositoryService::getRepositoryById($repId);
                $initialDefaultRights = $repo->getDefaultRight();
                if(!$repo->isWriteable()){
                    if (isSet($httpVars["permission_mask"]) && !empty($httpVars["permission_mask"])){
                        $mask = json_decode($httpVars["permission_mask"], true);
                        $rootGroup = RolesService::getRole("AJXP_GRP_/");
                        if(count($mask)){
                            $perm = new AJXP_PermissionMask($mask);
                            $rootGroup->setMask($repId, $perm);
                        }else{
                            $rootGroup->clearMask($repId);
                        }
                        RolesService::updateRole($rootGroup);
                        XMLWriter::header();
                        XMLWriter::sendMessage("The permission mask was updated for this workspace", null);
                        XMLWriter::close();
                        break;
                    }else{
                        throw new \Exception("This workspace is not writeable. Please edit directly the conf/bootstrap_repositories.php file.");
                    }
                }
                $res = 0;
                if (isSet($httpVars["newLabel"])) {
                    $newLabel = InputFilter::sanitize(InputFilter::securePath($httpVars["newLabel"]), InputFilter::SANITIZE_HTML);
                    if ($this->repositoryExists($newLabel)) {
                         XMLWriter::header();
                        XMLWriter::sendMessage(null, $mess["ajxp_conf.50"]);
                        XMLWriter::close();
                        return;
                    }
                    $repo->setDisplay($newLabel);
                    $res = RepositoryService::replaceRepository($repId, $repo);
                } else {
                    $options = array();
                    $existing = $repo->getOptionsDefined();
                    $existingValues = array();
                    if(!$repo->isTemplate()){
                        foreach($existing as $exK) {
                            $existingValues[$exK] = $repo->getSafeOption($exK);
                        }
                    }
                    $this->parseParameters($ctx, $httpVars, $options, true, $existingValues);
                    if (count($options)) {
                        foreach ($options as $key=>$value) {
                            if ($key == "AJXP_SLUG") {
                                $repo->setSlug($value);
                                continue;
                            } else if ($key == "WORKSPACE_LABEL" || $key == "TEMPLATE_LABEL"){
                                $newLabel = InputFilter::sanitize($value, InputFilter::SANITIZE_HTML);
                                if($repo->getDisplay() != $newLabel){
                                    if ($this->repositoryExists($newLabel)) {
                                        throw new \Exception($mess["ajxp_conf.50"]);
                                    }else{
                                        $repo->setDisplay($newLabel);
                                    }
                                }
                            } elseif ($key == "AJXP_GROUP_PATH_PARAMETER") {
                                $value = InputFilter::securePath(rtrim($currentAdminBasePath, "/") . "/" . ltrim($value, "/"));
                                $repo->setGroupPath($value);
                                continue;
                            }
                            $repo->addOption($key, $value);
                        }
                    }
                    if($repo->isTemplate()){
                        foreach($existing as $definedOption){
                            if($definedOption == "META_SOURCES" || $definedOption == "CREATION_TIME" || $definedOption == "CREATION_USER"){
                                continue;
                            }
                            if(!isSet($options[$definedOption]) && isSet($repo->options[$definedOption])){
                                unset($repo->options[$definedOption]);
                            }
                        }
                    }
                    if ($repo->getContextOption($ctx, "DEFAULT_RIGHTS")) {
                        $gp = $repo->getGroupPath();
                        if (empty($gp) || $gp == "/") {
                            $defRole = RolesService::getRole("AJXP_GRP_/");
                        } else {
                            $defRole = RolesService::getOrCreateRole("AJXP_GRP_" . $gp, $currentAdminBasePath);
                        }
                        if ($defRole !== false) {
                            $defRole->setAcl($repId, $repo->getContextOption($ctx, "DEFAULT_RIGHTS"));
                            RolesService::updateRole($defRole);
                        }
                    }
                    if (is_file(AJXP_TESTS_FOLDER."/plugins/test.ajxp_".$repo->getAccessType().".php")) {
                        chdir(AJXP_TESTS_FOLDER."/plugins");
                        include(AJXP_TESTS_FOLDER."/plugins/test.ajxp_".$repo->getAccessType().".php");
                        $className = "ajxp_".$repo->getAccessType();
                        $class = new $className();
                        $result = $class->doRepositoryTest($repo);
                        if (!$result) {
                            XMLWriter::header();
                            XMLWriter::sendMessage(null, $class->failedInfo);
                            XMLWriter::close();
                            return;
                        }
                    }

                    $rootGroup = RolesService::getOrCreateRole("AJXP_GRP_" . $currentAdminBasePath, $currentAdminBasePath);
                    if (isSet($httpVars["permission_mask"]) && !empty($httpVars["permission_mask"])){
                        $mask = json_decode($httpVars["permission_mask"], true);
                        if(count($mask)){
                            $perm = new AJXP_PermissionMask($mask);
                            $rootGroup->setMask($repId, $perm);
                        }else{
                            $rootGroup->clearMask($repId);
                        }
                        RolesService::updateRole($rootGroup);
                    }
                    $defaultRights = $repo->getDefaultRight();
                    if($defaultRights != $initialDefaultRights){
                        $currentDefaultRights = $rootGroup->getAcl($repId);
                        if(!empty($defaultRights) || !empty($currentDefaultRights)){
                            $rootGroup->setAcl($repId, empty($defaultRights) ? "" : $defaultRights);
                            RolesService::updateRole($rootGroup);
                        }
                    }
                    RepositoryService::replaceRepository($repId, $repo);
                }
                XMLWriter::header();
                if ($res == -1) {
                    XMLWriter::sendMessage(null, $mess["ajxp_conf.53"]);
                } else {
                    XMLWriter::sendMessage($mess["ajxp_conf.54"], null);
                    if (isSet($httpVars["newLabel"])) {
                        XMLWriter::reloadDataNode("", $repId);
                    }
                    XMLWriter::reloadRepositoryList();
                }
                XMLWriter::close();

            break;

            case "meta_source_add" :
                $repId = $httpVars["repository_id"];
                $repo = RepositoryService::getRepositoryById($repId);
                if (!is_object($repo)) {
                    throw new \Exception("Invalid workspace id! $repId");
                }
                $metaSourceType = InputFilter::sanitize($httpVars["new_meta_source"], InputFilter::SANITIZE_ALPHANUM);
                if (isSet($httpVars["json_data"])) {
                    $options = json_decode(TextEncoder::magicDequote($httpVars["json_data"]), true);
                } else {
                    $options = array();
                    $this->parseParameters($ctx, $httpVars, $options, true);
                }
                $repoOptions = $repo->getContextOption($ctx, "META_SOURCES");
                if (is_array($repoOptions) && isSet($repoOptions[$metaSourceType])) {
                    throw new \Exception($mess["ajxp_conf.55"]);
                }
                if (!is_array($repoOptions)) {
                    $repoOptions = array();
                }
                $repoOptions[$metaSourceType] = $options;
                uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
                $repo->addOption("META_SOURCES", $repoOptions);
                RepositoryService::replaceRepository($repId, $repo);
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.56"],null);
                XMLWriter::close();
            break;

            case "meta_source_delete" :

                $repId = $httpVars["repository_id"];
                $repo = RepositoryService::getRepositoryById($repId);
                if (!is_object($repo)) {
                    throw new \Exception("Invalid workspace id! $repId");
                }
                $metaSourceId = $httpVars["plugId"];
                $repoOptions = $repo->getContextOption($ctx, "META_SOURCES");
                if (is_array($repoOptions) && array_key_exists($metaSourceId, $repoOptions)) {
                    unset($repoOptions[$metaSourceId]);
                    uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
                    $repo->addOption("META_SOURCES", $repoOptions);
                    RepositoryService::replaceRepository($repId, $repo);
                }else{
                    throw new \Exception("Cannot find meta source ".$metaSourceId);
                }
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.57"],null);
                XMLWriter::close();

            break;

            case "meta_source_edit" :
                $repId = $httpVars["repository_id"];
                $repo = RepositoryService::getRepositoryById($repId);
                if (!is_object($repo)) {
                    throw new \Exception("Invalid workspace id! $repId");
                }
                if(isSet($httpVars["plugId"])){
                    $metaSourceId = $httpVars["plugId"];
                    $repoOptions = $repo->getContextOption($ctx, "META_SOURCES");
                    if (!is_array($repoOptions)) {
                        $repoOptions = array();
                    }
                    if (isSet($httpVars["json_data"])) {
                        $options = json_decode(TextEncoder::magicDequote($httpVars["json_data"]), true);
                    } else {
                        $options = array();
                        $this->parseParameters($ctx, $httpVars, $options, true);
                    }
                    if(isset($repoOptions[$metaSourceId])){
                        $this->mergeExistingParameters($options, $repoOptions[$metaSourceId]);
                    }
                    $repoOptions[$metaSourceId] = $options;
                }else if(isSet($httpVars["bulk_data"])){
                    $bulkData = json_decode(TextEncoder::magicDequote($httpVars["bulk_data"]), true);
                    $repoOptions = $repo->getContextOption($ctx, "META_SOURCES");
                    if (!is_array($repoOptions)) {
                        $repoOptions = array();
                    }
                    if(isSet($bulkData["delete"]) && count($bulkData["delete"])){
                        foreach($bulkData["delete"] as $key){
                            if(isSet($repoOptions[$key])) unset($repoOptions[$key]);
                        }
                    }
                    if(isSet($bulkData["add"]) && count($bulkData["add"])){
                        foreach($bulkData["add"] as $key => $value){
                            if(isSet($repoOptions[$key])) $this->mergeExistingParameters($value, $repoOptions[$key]);
                            $repoOptions[$key] = $value;
                        }
                    }
                    if(isSet($bulkData["edit"]) && count($bulkData["edit"])){
                        foreach($bulkData["edit"] as $key => $value){
                            if(isSet($repoOptions[$key])) $this->mergeExistingParameters($value, $repoOptions[$key]);
                            $repoOptions[$key] = $value;
                        }
                    }
                }
                uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
                $repo->addOption("META_SOURCES", $repoOptions);
                RepositoryService::replaceRepository($repId, $repo);
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.58"],null);
                XMLWriter::close();
            break;


            case "delete" :
                // REST API mapping
                if (isSet($httpVars["data_type"])) {
                    switch ($httpVars["data_type"]) {
                        case "repository":
                            $httpVars["repository_id"] = basename($httpVars["data_id"]);
                            break;
                        case "role":
                            $httpVars["role_id"] = basename($httpVars["data_id"]);
                            break;
                        case "user":
                            $httpVars["user_id"] = basename($httpVars["data_id"]);
                            break;
                        case "group":
                            $httpVars["group"] = "/data/users".$httpVars["data_id"];
                            break;
                        default:
                            break;
                    }
                    unset($httpVars["data_type"]);
                    unset($httpVars["data_id"]);
                }
                if (isSet($httpVars["repository_id"])) {
                    $repId = $httpVars["repository_id"];
                    $repo = RepositoryService::getRepositoryById($repId);
                    if(!is_object($repo)){
                        $res = -1;
                    }else{
                        $res = RepositoryService::deleteRepository($repId);
                    }
                    XMLWriter::header();
                    if ($res == -1) {
                        XMLWriter::sendMessage(null, $mess[427]);
                    } else {
                        XMLWriter::sendMessage($mess["ajxp_conf.59"], null);
                        XMLWriter::reloadDataNode();
                        XMLWriter::reloadRepositoryList();
                    }
                    XMLWriter::close();
                    return;
                } else if (isSet($httpVars["role_id"])) {
                    $roleId = $httpVars["role_id"];
                    if (RolesService::getRole($roleId) === false) {
                        throw new \Exception($mess["ajxp_conf.67"]);
                    }
                    RolesService::deleteRole($roleId);
                    XMLWriter::header();
                    XMLWriter::sendMessage($mess["ajxp_conf.68"], null);
                    XMLWriter::reloadDataNode();
                    XMLWriter::close();
                } else if (isSet($httpVars["group"])) {
                    $groupPath = $httpVars["group"];
                    $basePath = substr(PathUtils::forwardSlashDirname($groupPath), strlen("/data/users"));
                    $basePath = ($ctx->hasUser() ? $ctx->getUser()->getRealGroupPath($basePath) : $basePath);
                    $gName = basename($groupPath);
                    UsersService::deleteGroup($basePath, $gName);
                    XMLWriter::header();
                    XMLWriter::sendMessage($mess["ajxp_conf.128"], null);
                    XMLWriter::reloadDataNode();
                    XMLWriter::close();
                } else {
                    if(!isset($httpVars["user_id"]) || $httpVars["user_id"]==""
                        || UsersService::isReservedUserId($httpVars["user_id"])
                        || $loggedUser->getId() == $httpVars["user_id"])
                    {
                        XMLWriter::header();
                        XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
                        XMLWriter::close();
                    }
                    UsersService::deleteUser($httpVars["user_id"]);
                    XMLWriter::header();
                    XMLWriter::sendMessage($mess["ajxp_conf.60"], null);
                    XMLWriter::reloadDataNode();
                    XMLWriter::close();

                }
            break;

            case "get_plugin_manifest" :

                $ajxpPlugin = PluginsService::getInstance($ctx)->getPluginById($httpVars["plugin_id"]);
                XMLWriter::header("admin_data");

                $fullManifest = $ajxpPlugin->getManifestRawContent("", "xml");
                $xPath = new DOMXPath($fullManifest->ownerDocument);
                $addParams = "";
                $instancesDefinitions = array();
                $pInstNodes = $xPath->query("server_settings/global_param[contains(@type, 'plugin_instance:')]");
                /** @var \DOMElement $pInstNode */
                foreach ($pInstNodes as $pInstNode) {
                    $type = $pInstNode->getAttribute("type");
                    $instType = str_replace("plugin_instance:", "", $type);
                    $fieldName = $pInstNode->getAttribute("name");
                    $pInstNode->setAttribute("type", "group_switch:".$fieldName);
                    $typePlugs = PluginsService::getInstance($ctx)->getPluginsByType($instType);
                    foreach ($typePlugs as $typePlug) {
                        if(!$typePlug->isEnabled()) continue;
                        if($typePlug->getId() == "auth.multi") continue;
                        $checkErrorMessage = "";
                        try {
                            $typePlug->performChecks();
                        } catch (\Exception $e) {
                            $checkErrorMessage = " (Warning : ".$e->getMessage().")";
                        }
                        $tParams = XMLWriter::replaceAjxpXmlKeywords($typePlug->getManifestRawContent("server_settings/param[not(@group_switch_name)]"));
                        $addParams .= '<global_param group_switch_name="'.$fieldName.'" name="instance_name" group_switch_label="'.$typePlug->getManifestLabel().$checkErrorMessage.'" group_switch_value="'.$typePlug->getId().'" default="'.$typePlug->getId().'" type="hidden"/>';
                        $addParams .= str_replace("<param", "<global_param group_switch_name=\"${fieldName}\" group_switch_label=\"".$typePlug->getManifestLabel().$checkErrorMessage."\" group_switch_value=\"".$typePlug->getId()."\" ", $tParams);
                        $addParams .= str_replace("<param", "<global_param", XMLWriter::replaceAjxpXmlKeywords($typePlug->getManifestRawContent("server_settings/param[@group_switch_name]")));
                        $addParams .= XMLWriter::replaceAjxpXmlKeywords($typePlug->getManifestRawContent("server_settings/global_param"));
                        $instancesDefs = $typePlug->getConfigsDefinitions();
                        if(!empty($instancesDefs) && is_array($instancesDefs)) {
                            foreach($instancesDefs as $defKey => $defData){
                                $instancesDefinitions[$fieldName."/".$defKey] = $defData;
                            }
                        }
                    }
                }
                $allParams = XMLWriter::replaceAjxpXmlKeywords($fullManifest->ownerDocument->saveXML($fullManifest));
                $allParams = str_replace('type="plugin_instance:', 'type="group_switch:', $allParams);
                $allParams = str_replace("</server_settings>", $addParams."</server_settings>", $allParams);

                echo($allParams);
                $definitions = $instancesDefinitions;
                $configsDefs = $ajxpPlugin->getConfigsDefinitions();
                if(is_array($configsDefs)){
                    $definitions = array_merge($configsDefs, $instancesDefinitions);
                }
                $values = $ajxpPlugin->getConfigs();
                if(!is_array($values)) $values = array();
                echo("<plugin_settings_values>");
                // First flatten keys
                $flattenedKeys = array();
                foreach ($values as $key => $value){
                    $type = $definitions[$key]["type"];
                    if ((strpos($type, "group_switch:") === 0 || strpos($type, "plugin_instance:") === 0 ) && is_array($value)) {
                        $res = array();
                        $this->flattenKeyValues($res, $definitions, $value, $key);
                        $flattenedKeys += $res;
                        // Replace parent key by new flat value
                        $values[$key] = $flattenedKeys[$key];
                    }
                }
                $values += $flattenedKeys;

                foreach ($values as $key => $value) {
                    $attribute = true;
                    $type = $definitions[$key]["type"];
                    if ($type == "array" && is_array($value)) {
                        $value = implode(",", $value);
                    } else if ($type == "boolean") {
                        $value = ($value === true || $value === "true" || $value == 1?"true":"false");
                    } else if ($type == "textarea") {
                        $attribute = false;
                    } else if ($type == "password" && !empty($value)){
                        $value = "__AJXP_VALUE_SET__";
                    }
                    if ($attribute) {
                        echo("<param name=\"$key\" value=\"". StringHelper::xmlEntities($value) ."\"/>");
                    } else {
                        echo("<param name=\"$key\" cdatavalue=\"true\"><![CDATA[".$value."]]></param>");
                    }
                }
                if ($ajxpPlugin->getType() != "core") {
                    echo("<param name=\"AJXP_PLUGIN_ENABLED\" value=\"".($ajxpPlugin->isEnabled()?"true":"false")."\"/>");
                }
                echo("</plugin_settings_values>");
                echo("<plugin_doc><![CDATA[<p>".$ajxpPlugin->getPluginInformationHTML("Charles du Jeu", "https://pydio.com/en/docs/references/plugins")."</p>");
                if (file_exists($ajxpPlugin->getBaseDir()."/plugin_doc.html")) {
                    echo(file_get_contents($ajxpPlugin->getBaseDir()."/plugin_doc.html"));
                }
                echo("]]></plugin_doc>");
                XMLWriter::close("admin_data");

            break;

            case "run_plugin_action":

                $options = array();
                $this->parseParameters($ctx, $httpVars, $options, true);
                $pluginId = $httpVars["action_plugin_id"];
                if (isSet($httpVars["button_key"])) {
                    $options = $options[$httpVars["button_key"]];
                }
                $plugin = PluginsService::getInstance($ctx)->softLoad($pluginId, $options);
                if (method_exists($plugin, $httpVars["action_plugin_method"])) {
                    try {
                        $res = call_user_func(array($plugin, $httpVars["action_plugin_method"]), $options);
                    } catch (\Exception $e) {
                        echo("ERROR:" . $e->getMessage());
                        break;
                    }
                    echo($res);
                } else {
                    echo 'ERROR: Plugin '.$httpVars["action_plugin_id"].' does not implement '.$httpVars["action_plugin_method"].' method!';
                }

            break;

            case "edit_plugin_options":

                $options = array();
                $this->parseParameters($ctx, $httpVars, $options, true);
                $confStorage = ConfService::getConfStorageImpl();
                $pluginId = InputFilter::sanitize($httpVars["plugin_id"], InputFilter::SANITIZE_ALPHANUM);
                list($pType, $pName) = explode(".", $pluginId);
                $existing = $confStorage->loadPluginConfig($pType, $pName);
                $this->mergeExistingParameters($options, $existing);
                $confStorage->savePluginConfig($pluginId, $options);
                ConfService::clearAllCaches();
                XMLWriter::header();
                XMLWriter::sendMessage($mess["ajxp_conf.97"], null);
                XMLWriter::close();


            break;

            case "generate_api_docs":

                PydioSdkGenerator::analyzeRegistry(isSet($httpVars["version"])?$httpVars["version"]:AJXP_VERSION);

            break;


            // Action for update all Pydio's user from ldap in CLI mode
            case "cli_update_user_list":
                if((php_sapi_name() == "cli")){
                    $progressBar = new ProgressBarCLI();
                    $countCallback  = array($progressBar, "init");
                    $loopCallback   = array($progressBar, "update");
                    $bGroup = "/";
                    if($ctx->hasUser()) $bGroup = $ctx->getUser()->getGroupPath();
                    UsersService::listUsers($bGroup, null, -1, -1, true, true, $countCallback, $loopCallback);
                }
                break;

            default:
                break;
        }

        return;
    }

    /**
     * List available plugins
     * @param $dir
     * @param null $root
     * @param null $hash
     * @param bool $returnNodes
     * @param string $file
     * @param null $aliasedDir
     * @return array
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function listPlugins($dir, $root = NULL, $hash = null, $returnNodes = false, $file="", $aliasedDir=null)
    {
        $dir = "/$dir";
        if($aliasedDir != null && $aliasedDir != "/".$root.$dir){
            $baseDir = $aliasedDir;
        }else{
            $baseDir = "/".$root.$dir;
        }
        $allNodes = array();
        $this->logInfo("Listing plugins",""); // make sure that the logger is started!
        $pServ = PluginsService::getInstance($this->currentContext);
        $types = $pServ->getDetectedPlugins();
        $mess = LocaleService::getMessages();
        $uniqTypes = array("core");
        $coreTypes = array("auth", "conf", "boot", "feed", "log", "mailer", "mq");
        if ($dir == "/plugins" || $dir == "/core_plugins" || $dir=="/all") {
            if($dir == "/core_plugins") $uniqTypes = $coreTypes;
            else if($dir == "/plugins") $uniqTypes = array_diff(array_keys($types), $coreTypes);
            else if($dir == "/all") $uniqTypes = array_keys($types);
            if(!$returnNodes) XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" switchDisplayMode="detail"  template_name="ajxp_conf.plugins_folder">
            <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String"/>
            <column messageId="ajxp_conf.103" attributeName="plugin_description" sortType="String"/>
            <column messageId="ajxp_conf.102" attributeName="plugin_id" sortType="String"/>
            </columns>');
            ksort($types);
            foreach ($types as $t => $tPlugs) {
                if(!in_array($t, $uniqTypes))continue;
                if($t == "core") continue;
                $nodeKey = $baseDir."/".$t;
                $meta = array(
                    "icon" 		=> "folder_development.png",
                    "plugin_id" => $t,
                    "text" => $mess["plugtype.title.".$t],
                    "plugin_description" => $mess["plugtype.desc.".$t]
                );
                if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                $xml = XMLWriter::renderNode($nodeKey, ucfirst($t), false, $meta, true, false);
                if($returnNodes) $allNodes[$nodeKey] = $xml;
                else print($xml);
            }
        } else if ($dir == "/core") {
            if(!$returnNodes) XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" switchDisplayMode="detail"  template_name="ajxp_conf.plugins">
            <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String"/>
            <column messageId="ajxp_conf.102" attributeName="plugin_id" sortType="String"/>
            <column messageId="ajxp_conf.103" attributeName="plugin_description" sortType="String"/>
            </columns>');
            $all =  $first = "";
            foreach ($uniqTypes as $type) {
                if(!isset($types[$type])) continue;
                /** @var Plugin $pObject */
                foreach ($types[$type] as $pObject) {
                    $isMain = ($pObject->getId() == "core.ajaxplorer");
                    $meta = array(
                        "icon" 		=> ($isMain?"preferences_desktop.png":"desktop.png"),
                        "ajxp_mime" => "ajxp_plugin",
                        "plugin_id" => $pObject->getId(),
                        "plugin_description" => $pObject->getManifestDescription()
                    );
                    // Check if there are actually any parameters to display!
                    if($pObject->getManifestRawContent("server_settings", "xml")->length == 0) continue;
                    $label =  $pObject->getManifestLabel();
                    $nodeKey = $baseDir."/".$pObject->getId();
                    if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                    $nodeString =XMLWriter::renderNode($nodeKey, $label, true, $meta, true, false);
                    if($returnNodes) $allNodes[$nodeKey] = $nodeString;
                    if ($isMain) {
                        $first = $nodeString;
                    } else {
                        $all .= $nodeString;
                    }
                }
            }
            if(!$returnNodes) print($first.$all);
        } else {
            $split = explode("/", $dir);
            if(empty($split[0])) array_shift($split);
            $type = $split[1];
            if(!$returnNodes) XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" switchDisplayMode="full" template_name="ajxp_conf.plugin_detail">
            <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String" defaultWidth="10%"/>
            <column messageId="ajxp_conf.102" attributeName="plugin_id" sortType="String" defaultWidth="10%"/>
            <column messageId="ajxp_conf.103" attributeName="plugin_description" sortType="String" defaultWidth="60%"/>
            <column messageId="ajxp_conf.104" attributeName="enabled" sortType="String" defaultWidth="10%"/>
            <column messageId="ajxp_conf.105" attributeName="can_active" sortType="String" defaultWidth="10%"/>
            </columns>');
            $mess = LocaleService::getMessages();
            /** @var Plugin $pObject */
            foreach ($types[$type] as $pObject) {
                $errors = "OK";
                try {
                    $pObject->performChecks();
                } catch (\Exception $e) {
                    $errors = "ERROR : ".$e->getMessage();
                }
                $meta = array(
                    "icon" 		=> "preferences_plugin.png",
                    "ajxp_mime" => "ajxp_plugin",
                    "can_active"	=> $errors,
                    "enabled"	=> ($pObject->isEnabled()?$mess[440]:$mess[441]),
                    "plugin_id" => $pObject->getId(),
                    "plugin_description" => $pObject->getManifestDescription()
                );
                $nodeKey = $baseDir."/".$pObject->getId();
                if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                $xml = XMLWriter::renderNode($nodeKey, $pObject->getManifestLabel(), true, $meta, true, false);
                if($returnNodes) $allNodes[$nodeKey] = $xml;
                else print $xml;
            }
        }
        return $allNodes;
    }

    /**
     * List users for a given group
     * @param $root
     * @param $child
     * @param null $hashValue
     * @param bool $returnNodes
     * @param null $findNodePosition
     * @return array
     */
    public function listUsers($root, $child, $hashValue = null, $returnNodes = false, $findNodePosition=null)
    {
        $USER_PER_PAGE = 50;
        if($root == "users") $baseGroup = "/";
        else $baseGroup = substr($root, strlen("users"));
        if($this->currentContext->hasUser()){
            $baseGroup = $this->currentContext->getUser()->getRealGroupPath($baseGroup);
        }
        if ($findNodePosition != null && $hashValue == null) {

            // Add groups offset
            $groups = UsersService::listChildrenGroups($baseGroup);
            $offset = 0;
            if(count($groups)){
                $offset = count($groups);
            }
            $position = UsersService::findUserPage($baseGroup, $findNodePosition, $USER_PER_PAGE);
            if($position != -1){

                $key = "/data/".$root."/".$findNodePosition;
                $data =  array($key => XMLWriter::renderNode($key, $findNodePosition, true, array(
                        "page_position" => $position
                    ), true, false));
                return $data;

            }else{
                // Loop on each page to find the correct page.
                $count = UsersService::authCountUsers($baseGroup);
                $pages = ceil($count / $USER_PER_PAGE);
                for ($i = 0; $i < $pages ; $i ++) {

                    $tests = $this->listUsers($root, $child, $i+1, true, $findNodePosition);
                    if (is_array($tests) && isSet($tests["/data/".$root."/".$findNodePosition])) {
                        return array("/data/".$root."/".$findNodePosition => str_replace("ajxp_mime", "page_position='".($i+1)."' ajxp_mime", $tests["/data/".$root."/".$findNodePosition]));
                    }

                }
            }


            return array();

        }

        $allNodes = array();
        $columns = '<columns switchDisplayMode="list" switchGridMode="filelist" template_name="ajxp_conf.users">
                    <column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String" defaultWidth="40%"/>
                    <column messageId="ajxp_conf.102" attributeName="object_id" sortType="String" defaultWidth="10%"/>
                    <column messageId="ajxp_conf.7" attributeName="isAdmin" sortType="String" defaultWidth="10%"/>
                    <column messageId="ajxp_conf.70" attributeName="ajxp_roles" sortType="String" defaultWidth="15%"/>
                    <column messageId="ajxp_conf.62" attributeName="rights_summary" sortType="String" defaultWidth="15%"/>
                    </columns>';
        if (UsersService::driverSupportsAuthSchemes()) {
            $columns = '<columns switchDisplayMode="list" switchGridMode="filelist" template_name="ajxp_conf.users_authscheme">
                        <column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String" defaultWidth="40%"/>
                        <column messageId="ajxp_conf.102" attributeName="object_id" sortType="String" defaultWidth="10%"/>
                        <column messageId="ajxp_conf.115" attributeName="auth_scheme" sortType="String" defaultWidth="5%"/>
                        <column messageId="ajxp_conf.7" attributeName="isAdmin" sortType="String" defaultWidth="5%"/>
                        <column messageId="ajxp_conf.70" attributeName="ajxp_roles" sortType="String" defaultWidth="15%"/>
                        <column messageId="ajxp_conf.62" attributeName="rights_summary" sortType="String" defaultWidth="15%"/>
            </columns>';
        }
        if(!$returnNodes) XMLWriter::sendFilesListComponentConfig($columns);
        if(!UsersService::usersEnabled()) return array();
        if(empty($hashValue)) $hashValue = 1;

        //$ctxUser = $this->currentContext->getUser();
        //if(!empty($ctxUser)) $baseGroup = $ctxUser->getRealGroupPath($baseGroup);
        $count = UsersService::authCountUsers($baseGroup, "", null, null, false);
        if (UsersService::authSupportsPagination() && $count >= $USER_PER_PAGE) {
            $offset = ($hashValue - 1) * $USER_PER_PAGE;
            if(!$returnNodes) XMLWriter::renderPaginationData($count, $hashValue, ceil($count/$USER_PER_PAGE));
            $users = UsersService::listUsers($baseGroup, "", $offset, $USER_PER_PAGE, true, false);
            if ($hashValue == 1) {
                $groups = UsersService::listChildrenGroups($baseGroup);
            } else {
                $groups = array();
            }
        } else {
            $users = UsersService::listUsers($baseGroup, "", -1, -1, true, false);
            $groups = UsersService::listChildrenGroups($baseGroup);
        }
        $groupAdmin = ($this->currentContext->hasUser() && $this->currentContext->getUser()->getGroupPath() != "/");
        if($this->getName() == "ajxp_admin" && $baseGroup == "/" && $hashValue == 1 && !$groupAdmin){
            $nodeKey = "/data/".$root."/";
            $meta = array(
                "icon" => "users-folder.png",
                "icon_class" => "icon-home",
                "ajxp_mime" => "group",
                "object_id" => "/"
            );
            $mess = LocaleService::getMessages();
            $xml = XMLWriter::renderNode($nodeKey, $mess["ajxp_conf.151"], true, $meta, true, false);
            if(!$returnNodes) print($xml);
            else $allNodes[$nodeKey] = $xml;
        }

        foreach ($groups as $groupId => $groupLabel) {

            $nodeKey = "/data/".$root."/".ltrim($groupId,"/");
            $meta = array(
                "icon" => "users-folder.png",
                "icon_class" => "icon-folder-close",
                "ajxp_mime" => "group",
                "object_id" => $groupId
            );
            if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            $xml = XMLWriter::renderNode($nodeKey,
                $groupLabel, false, $meta, true, false);
            if(!$returnNodes) print($xml);
            else $allNodes[$nodeKey] = $xml;

        }
        $mess = LocaleService::getMessages();
        $loggedUser = $this->currentContext->getUser();
        $userArray = array();
        $logger = Logger::getInstance();
        if(method_exists($logger, "usersLastConnection")){
            $allUserIds = array();
        }
        foreach ($users as $userObject) {
            $label = $userObject->getId();
            if(isSet($allUserIds)) $allUserIds[] = $label;
            if ($userObject->hasParent()) {
                $label = $userObject->getParent()."000".$label;
            }else{
                $children = ConfService::getConfStorageImpl()->getUserChildren($label);
                foreach($children as $addChild){
                    $userArray[$label."000".$addChild->getId()] = $addChild;
                }
            }
            $userArray[$label] = $userObject;
        }
        if(isSet($allUserIds) && count($allUserIds)){
            $connections = $logger->usersLastConnection($allUserIds);
        }
        ksort($userArray);
        foreach ($userArray as $userObject) {
            $repos = ConfService::getConfStorageImpl()->listRepositories($userObject);
            $isAdmin = $userObject->isAdmin();
            $userId = $userObject->getId();
            $icon = "user".($userId=="guest"?"_guest":($isAdmin?"_admin":""));
            $iconClass = "icon-user";
            if ($userObject->hasParent()) {
                $icon = "user_child";
                $iconClass = "icon-angle-right";
            }
            if ($isAdmin) {
                $rightsString = $mess["ajxp_conf.63"];
            } else {
                $r = array();
                foreach ($repos as $repoId => $repository) {
                    if($repository->getAccessType() == "ajxp_shared") continue;
                    if(!$userObject->canRead($repoId) && !$userObject->canWrite($repoId)) continue;
                    $rs = ($userObject->canRead($repoId) ? "r" : "");
                    $rs .= ($userObject->canWrite($repoId) ? "w" : "");
                    $r[] = $repository->getDisplay()." (".$rs.")";
                }
                $rightsString = implode(", ", $r);
            }
            $nodeLabel = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $userObject, "core.conf", $userId);
            $scheme = UsersService::getAuthScheme($userId);
            $nodeKey = "/data/$root/".$userId;
            $roles = array_filter(array_keys($userObject->getRoles()), array($this, "filterReservedRoles"));
            $meta = array(
                "isAdmin" => $mess[($isAdmin?"ajxp_conf.14":"ajxp_conf.15")],
                "icon" => $icon.".png",
                "icon_class" => $iconClass,
                "object_id" => $userId,
                "auth_scheme" => ($scheme != null? $scheme : ""),
                "rights_summary" => $rightsString,
                "ajxp_roles" => implode(", ", $roles),
                "ajxp_mime" => "user".(($userId!="guest"&&$userId!=$loggedUser->getId())?"_editable":""),
                "json_merged_role" => json_encode($userObject->mergedRole->getDataArray(true))
            );
            if($userObject->hasParent()) $meta["shared_user"] = "true";
            if(isSet($connections) && isSet($connections[$userObject->getId()]) && !empty($connections[$userObject->getId()])) {
                $meta["last_connection"] = strtotime($connections[$userObject->getId()]);
                $meta["last_connection_readable"] = StatHelper::relativeDate($meta["last_connection"], $mess);
            }
            if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            $xml = XMLWriter::renderNode($nodeKey, $nodeLabel, true, $meta, true, false);
            if(!$returnNodes) print($xml);
            else $allNodes[$nodeKey] = $xml;
        }
        return $allNodes;
    }

    /**
     * List Roles
     * @param $root
     * @param $child
     * @param null $hashValue
     * @param bool $returnNodes
     * @return array
     */
    public function listRoles($root, $child, $hashValue = null, $returnNodes = false)
    {
        $allNodes = array();
        if(!$returnNodes) XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" template_name="ajxp_conf.roles">
            <column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String"/>
            <column messageId="ajxp_conf.114" attributeName="is_default" sortType="String"/>
            <column messageId="ajxp_conf.62" attributeName="rights_summary" sortType="String"/>
            </columns>');
        if(!UsersService::usersEnabled()) return array();
        $ctxUser = $this->currentContext->getUser();
        $currentUserIsGroupAdmin = ($ctxUser != null && $ctxUser->getGroupPath() != "/");
        $roles = RolesService::getRolesList(array(), !$this->listSpecialRoles);
        ksort($roles);
        $mess = LocaleService::getMessages();
        if(!$this->listSpecialRoles && $this->getName() != "ajxp_admin" && !$currentUserIsGroupAdmin){
            $rootGroupRole = RolesService::getOrCreateRole("AJXP_GRP_/", empty($ctxUser) ? "/" : $ctxUser->getGroupPath());
            if($rootGroupRole->getLabel() == "AJXP_GRP_/"){
                $rootGroupRole->setLabel($mess["ajxp_conf.151"]);
                RolesService::updateRole($rootGroupRole);
            }
            array_unshift($roles, $rootGroupRole);
        }
        foreach ($roles as $roleObject) {
            //if(strpos($roleId, "AJXP_GRP_") === 0 && !$this->listSpecialRoles) continue;
            $r = array();
            if(!empty($ctxUser) && !$ctxUser->canAdministrate($roleObject)) continue;
            $count = 0;
            $repos = RepositoryService::listRepositoriesWithCriteria(array("role" => $roleObject), $count);
            foreach ($repos as $repoId => $repository) {
                if($repository->getAccessType() == "ajxp_shared") continue;
                if(!$roleObject->canRead($repoId) && !$roleObject->canWrite($repoId)) continue;
                $rs = ($roleObject->canRead($repoId) ? "r" : "");
                $rs .= ($roleObject->canWrite($repoId) ? "w" : "");
                $r[] = $repository->getDisplay()." (".$rs.")";
            }
            $rightsString = implode(", ", $r);
            $nodeKey = "/data/roles/".$roleObject->getId();
            $appliesToDefault = implode(",", $roleObject->listAutoApplies());
            if($roleObject->getId() == "AJXP_GRP_/"){
                $appliesToDefault = $mess["ajxp_conf.153"];
            }
            $meta = array(
                "icon" => "user-acl.png",
                "rights_summary" => $rightsString,
                "is_default"    => $appliesToDefault, //($roleObject->autoAppliesTo("standard") ? $mess[440]:$mess[441]),
                "ajxp_mime" => "role",
                "role_id"   => $roleObject->getId(),
                "text"      => $roleObject->getLabel()
            );
            if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            $xml = XMLWriter::renderNode($nodeKey, $roleObject->getId(), true, $meta, true, false);
            if(!$returnNodes) echo $xml;
            else $allNodes[$nodeKey] = $xml;
        }
        return $allNodes;
    }

    /**
     * @param $name
     * @return bool
     */
    public function repositoryExists($name)
    {
        RepositoryService::listRepositoriesWithCriteria(array("display" => $name), $count);
        return $count > 0;
    }

    /**
     * @param Repository $a
     * @param Repository $b
     * @return integer
     */
    public function sortReposByLabel($a, $b)
    {
        return strcasecmp($a->getDisplay(), $b->getDisplay());
    }

    /**
     * Get label for an access.* plugin
     * @param $pluginId
     * @param $labels
     * @return mixed|string
     */
    protected function getDriverLabel($pluginId, &$labels){
        if(isSet($labels[$pluginId])){
            return $labels[$pluginId];
        }
        $plugin = PluginsService::getInstance(Context::emptyContext())->getPluginById("access.".$pluginId);
        if(!is_object($plugin)) {
            $label = "access.$plugin (plugin disabled!)";
        }else{
            $label = $plugin->getManifestLabel();
        }
        $labels[$pluginId] = $label;
        return $label;
    }


    /**
     * List all workspaces
     * @param $root
     * @param $child
     * @param null $hashValue
     * @param bool $returnNodes
     * @param string $file
     * @param null $aliasedDir
     * @param $httpVars
     */
    public function listRepositories($root, $child, $hashValue = null, $returnNodes = false, $file="", $aliasedDir=null, $httpVars){
        $REPOS_PER_PAGE = 50;
        $allNodes = array();
        if($hashValue == null) $hashValue = 1;
        $offset = ($hashValue - 1) * $REPOS_PER_PAGE;

        $count = null;
        // Load all repositories = normal, templates, and templates children
        $criteria = array(
            "ORDERBY"       => array("KEY" => "display", "DIR"=>"ASC"),
            "CURSOR"        => array("OFFSET" => $offset, "LIMIT" => $REPOS_PER_PAGE)
        );
        $ctxUser = $this->currentContext->getUser();
        $currentUserIsGroupAdmin = ($ctxUser != null && $ctxUser->getGroupPath() != "/");
        if($currentUserIsGroupAdmin){
            $criteria = array_merge($criteria, array(
                    "owner_user_id" => AJXP_FILTER_EMPTY,
                    "groupPath"     => "regexp:/^".str_replace("/", "\/", $ctxUser->getGroupPath()).'/',
                ));
        }else{
            $criteria["parent_uuid"] = AJXP_FILTER_EMPTY;
        }
        if(isSet($httpVars) && is_array($httpVars) && isSet($httpVars["template_children_id"])){
            $criteria["parent_uuid"] = InputFilter::sanitize($httpVars["template_children_id"], InputFilter::SANITIZE_ALPHANUM);
        }
        $repos = RepositoryService::listRepositoriesWithCriteria($criteria, $count);
        if(!$returnNodes){
            XMLWriter::renderPaginationData($count, $hashValue, ceil($count/$REPOS_PER_PAGE));
            XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="list" switchGridMode="filelist" template_name="ajxp_conf.repositories">
                <column messageId="ajxp_conf.8" attributeName="ajxp_label" sortType="String"/>
                <column messageId="ajxp_conf.9" attributeName="accessType" sortType="String"/>
                <column messageId="ajxp_conf.125" attributeName="slug" sortType="String"/>
            </columns>');
        }

        $driverLabels = array();

        foreach ($repos as $repoIndex => $repoObject) {

            if($repoObject->getAccessType() == "ajxp_conf" || $repoObject->getAccessType() == "ajxp_shared") continue;
            if (!empty($ctxUser) && !$ctxUser->canAdministrate($repoObject))continue;
            if(is_numeric($repoIndex)) $repoIndex = "".$repoIndex;

            $icon = "hdd_external_unmount.png";
            $editable = $repoObject->isWriteable();
            if ($repoObject->isTemplate) {
                $icon = "hdd_external_mount.png";
                if ($ctxUser != null && $ctxUser->getGroupPath() != "/") {
                    $editable = false;
                }
            }
            $accessType = $repoObject->getAccessType();
            $accessLabel = $this->getDriverLabel($accessType, $driverLabels);
            $meta = array(
                "repository_id" => $repoIndex,
                "accessType"	=> ($repoObject->isTemplate?"Template for ":"").$repoObject->getAccessType(),
                "accessLabel"	=> $accessLabel,
                "icon"			=> $icon,
                "owner"			=> ($repoObject->hasOwner()?$repoObject->getOwner():""),
                "openicon"		=> $icon,
                "slug"          => $repoObject->getSlug(),
                "parentname"	=> "/repositories",
                "ajxp_mime" 	=> "repository".($editable?"_editable":""),
                "is_template"   => ($repoObject->isTemplate?"true":"false")
            );

            $nodeKey = "/data/repositories/$repoIndex";
            $label = $repoObject->getDisplay();
            if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            $xml = XMLWriter::renderNode(
                $nodeKey,
                StringHelper::xmlEntities(TextEncoder::toUTF8($label)),
                true,
                $meta,
                true,
                false
            );
            if($returnNodes) $allNodes[$nodeKey] = $xml;
            else print($xml);

            if ($repoObject->isTemplate) {
                // Now Load children for template repositories
                $children = RepositoryService::listRepositoriesWithCriteria(array("parent_uuid" => $repoIndex . ""), $count);
                foreach($children as $childId => $childObject){
                    if (!empty($ctxUser) && !$ctxUser->canAdministrate($childObject))continue;
                    if(is_numeric($childId)) $childId = "".$childId;
                    $meta = array(
                        "repository_id" => $childId,
                        "accessType"	=> $childObject->getAccessType(),
                        "accessLabel"	=> $this->getDriverLabel($childObject->getAccessType(), $driverLabels),
                        "icon"			=> "repo_child.png",
                        "slug"          => $childObject->getSlug(),
                        "owner"			=> ($childObject->hasOwner()?$childObject->getOwner():""),
                        "openicon"		=> "repo_child.png",
                        "parentname"	=> "/repositories",
                        "ajxp_mime" 	=> "repository_editable",
                        "template_name" => $label
                    );
                    $cNodeKey = "/data/repositories/$childId";
                    if(in_array($cNodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                    $xml = XMLWriter::renderNode(
                        $cNodeKey,
                        StringHelper::xmlEntities(TextEncoder::toUTF8($childObject->getDisplay())),
                        true,
                        $meta,
                        true,
                        false
                    );
                    if($returnNodes) $allNodes[$cNodeKey] = $xml;
                    else print($xml);
                }
            }
        }

    }

    /**
     * List actions
     * @param $dir
     * @param null $root
     * @param null $hash
     * @param bool $returnNodes
     * @return array
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function listActions($dir, $root = NULL, $hash = null, $returnNodes = false)
    {
        $allNodes = array();
        $parts = explode("/",$dir);
        $pServ = PluginsService::getInstance($this->currentContext);
        //$activePlugins = $pServ->getActivePlugins();
        $types = $pServ->getDetectedPlugins();
        if (count($parts) == 1) {
            // list all types
            if(!$returnNodes) XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" template_name="ajxp_conf.plugins_folder">
            <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String"/>
            </columns>');
            ksort($types);
            foreach ($types as $t => $tPlugs) {
                $meta = array(
                    "icon" 		=> "folder_development.png",
                    "plugin_id" => $t
                );
                $nodeKey = "/$root/actions/".$t;
                if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                $xml = XMLWriter::renderNode($nodeKey, ucfirst($t), false, $meta, true, false);
                if($returnNodes) $allNodes[$nodeKey] = $xml;
                else print($xml);
            }

        } else if (count($parts) == 2) {
            // list plugs
            $type = $parts[1];
            if(!$returnNodes) XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="detail" template_name="ajxp_conf.plugins_folder">
                <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String"/>
                <column messageId="ajxp_conf.103" attributeName="actions" sortType="String"/>
            </columns>');
            /** @var Plugin $pObject */
            foreach ($types[$type] as $pObject) {
                $actions = $pObject->getManifestRawContent("//action/@name", "xml", true);
                $actLabel = array();
                if ($actions->length) {
                    foreach ($actions as $node) {
                        $actLabel[] = $node->nodeValue;
                    }
                }
                $meta = array(
                    "icon" 		=> "preferences_plugin.png",
                    "plugin_id" => $pObject->getId(),
                    "actions"   => implode(", ", $actLabel)
                );
                $nodeKey = "/$root/actions/$type/".$pObject->getName();
                if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                $xml = XMLWriter::renderNode($nodeKey, $pObject->getManifestLabel(), false, $meta, true, false);
                if($returnNodes) $allNodes[$nodeKey] = $xml;
                else print($xml);

            }

        } else if (count($parts) == 3) {
            // list actions
            $type = $parts[1];
            $name = $parts[2];
            $mess = LocaleService::getMessages();
            if(!$returnNodes) XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="full" template_name="ajxp_conf.plugins_folder">
                <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String" defaultWidth="10%"/>
                <column messageId="ajxp_conf.103" attributeName="parameters" sortType="String" fixedWidth="30%"/>
            </columns>');
            /** @var Plugin $pObject */
            $pObject = $types[$type][$name];

            $actions = $pObject->getManifestRawContent("//action", "xml", true);
            $allNodesAcc = array();
            if ($actions->length) {
                foreach ($actions as $node) {
                    $xPath = new DOMXPath($node->ownerDocument);
                    $callbacks = $xPath->query("processing/serverCallback", $node);
                    if(!$callbacks->length) continue;
                    /** @var \DOMElement $callback */
                    $callback = $callbacks->item(0);

                    $actName = $actLabel = $node->attributes->getNamedItem("name")->nodeValue;
                    $text = $xPath->query("gui/@text", $node);
                    if ($text->length) {
                        $actLabel = $actName ." (" . $mess[$text->item(0)->nodeValue].")";
                    }
                    $params = $xPath->query("processing/serverCallback/input_param", $node);
                    $paramLabel = array();
                    if ($callback->getAttribute("developerComment") != "") {
                        $paramLabel[] = "<span class='developerComment'>".$callback->getAttribute("developerComment")."</span>";
                    }
                    $restPath = "";
                    if ($callback->getAttribute("restParams")) {
                        $restPath = "/api/$actName/". ltrim($callback->getAttribute("restParams"), "/");
                    }
                    if ($restPath != null) {
                        $paramLabel[] = "<span class='developerApiAccess'>"."API Access : ".$restPath."</span>";
                    }
                    if ($params->length) {
                        $paramLabel[] = "Expected Parameters :";
                        /** @var \DOMElement $param */
                        foreach ($params as $param) {
                            $paramLabel[]= '. ['.$param->getAttribute("type").'] <b>'.$param->getAttribute("name").($param->getAttribute("mandatory") == "true" ? '*':'').'</b> : '.$param->getAttribute("description");
                        }
                    }
                    $meta = array(
                        "icon" 		=> "preferences_plugin.png",
                        "action_id" => $actName,
                        "parameters"=> '<div class="developerDoc">'.implode("<br/>", $paramLabel).'</div>',
                        "rest_params"=> $restPath
                    );
                    $nodeKey = "/$root/actions/$type/".$pObject->getName()."/$actName";
                    if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                    $allNodes[$nodeKey] = $allNodesAcc[$actName] = XMLWriter::renderNode(
                        $nodeKey,
                        $actLabel,
                        true,
                        $meta,
                        true,
                        false
                    );
                }
                ksort($allNodesAcc);
                if(!$returnNodes) print(implode("", array_values($allNodesAcc)));
            }

        }
        return $allNodes;
    }

    /**
     * @param $dir
     * @param null $root
     * @param null $hash
     * @param bool $returnNodes
     * @return array
     */
    public function listHooks($dir, $root = NULL, $hash = null, $returnNodes = false)
    {
        $jsonContent = json_decode(file_get_contents(DocsParser::getHooksFile()), true);
        $config = '<columns switchDisplayMode="full" template_name="hooks.list">
                <column messageId="ajxp_conf.17" attributeName="ajxp_label" sortType="String" defaultWidth="20%"/>
                <column messageId="ajxp_conf.18" attributeName="description" sortType="String" defaultWidth="20%"/>
                <column messageId="ajxp_conf.19" attributeName="triggers" sortType="String" defaultWidth="25%"/>
                <column messageId="ajxp_conf.20" attributeName="listeners" sortType="String" defaultWidth="25%"/>
                <column messageId="ajxp_conf.21" attributeName="sample" sortType="String" defaultWidth="10%"/>
            </columns>';
        if(!$returnNodes) XMLWriter::sendFilesListComponentConfig($config);
        $allNodes = array();
        foreach ($jsonContent as $hookName => $hookData) {
            $metadata = array(
                "icon"          => "preferences_plugin.png",
                "description"   => $hookData["DESCRIPTION"],
                "sample"        => $hookData["PARAMETER_SAMPLE"],
            );
            $trigs = array();
            foreach ($hookData["TRIGGERS"] as $trigger) {
                $trigs[] = "<span>".$trigger["FILE"]." (".$trigger["LINE"].")</span>";
            }
            $metadata["triggers"] = implode("<br/>", $trigs);
            $listeners = array();
            foreach ($hookData["LISTENERS"] as $listener) {
                $listeners[] = "<span>Plugin ".$listener["PLUGIN_ID"].", in method ".$listener["METHOD"]."</span>";
            }
            $metadata["listeners"] = implode("<br/>", $listeners);
            $nodeKey = "/$root/hooks/$hookName/$hookName";
            if(in_array($nodeKey, $this->currentBookmarks)) $metadata = array_merge($metadata, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            $xml = XMLWriter::renderNode($nodeKey, $hookName, true, $metadata, true, false);
            if($returnNodes) $allNodes[$nodeKey] = $xml;
            else print($xml);
        }
        return $allNodes;
    }

    /**
     * List all log files
     * @param $dir
     * @param null $root
     * @param null $hash
     * @param bool $returnNodes
     * @return array
     */
    public function listLogFiles($dir, $root = NULL, $hash = null, $returnNodes = false)
    {
        $dir = "/$dir";
        $allNodes = array();
        $logger = Logger::getInstance();
        $parts = explode("/", $dir);
        if (count($parts)>4) {
            $config = '<columns switchDisplayMode="list" switchGridMode="list" template_name="ajxp_conf.logs">
                <column messageId="ajxp_conf.17" attributeName="date" sortType="MyDate" defaultWidth="18%"/>
                <column messageId="ajxp_conf.18" attributeName="ip" sortType="String" defaultWidth="5%"/>
                <column messageId="ajxp_conf.19" attributeName="level" sortType="String" defaultWidth="10%"/>
                <column messageId="ajxp_conf.20" attributeName="user" sortType="String" defaultWidth="5%"/>
                <column messageId="ajxp_conf.124" attributeName="source" sortType="String" defaultWidth="5%"/>
                <column messageId="ajxp_conf.21" attributeName="action" sortType="String" defaultWidth="7%"/>
                <column messageId="ajxp_conf.22" attributeName="params" sortType="String" defaultWidth="50%"/>
            </columns>';
            if(!$returnNodes) XMLWriter::sendFilesListComponentConfig($config);
            $date = $parts[count($parts)-1];
            $logger->xmlLogs($dir, $date, "tree", "/".$root."/logs", isSet($_POST["cursor"])?intval($_POST["cursor"]):-1);
        } else {
            if(!$returnNodes) XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.16" attributeName="ajxp_label" sortType="String"/></columns>');
            $nodes = $logger->xmlListLogFiles("tree", (count($parts)>2?$parts[2]:null), (count($parts)>3?$parts[3]:null), "/".$root."/logs", false);
            foreach ($nodes as $last => $nodeXML) {
                if(is_numeric($last) && $last < 10) $last = "0".$last;
                $key = "/$root$dir/$last";
                if (in_array($key, $this->currentBookmarks)) {
                    $nodeXML = str_replace("/>", ' ajxp_bookmarked="true" overlay_icon="bookmark.png"/>', $nodeXML);
                }
                $allNodes[$key] = $nodeXML;
                if (!$returnNodes) {
                    print($nodeXML);
                }
            }
        }
        return $allNodes;
    }

    /**
     * Output the tests results
     * @param $dir
     * @param null $root
     * @param null $hash
     * @param bool $returnNodes
     * @return array
     */
    public function printDiagnostic($dir, $root = NULL, $hash = null, $returnNodes = false)
    {
        $outputArray = array();
        $testedParams = array();
        $allNodes = array();
        DiagnosticRunner::runTests($outputArray, $testedParams);
        DiagnosticRunner::testResultsToFile($outputArray, $testedParams);
        if(!$returnNodes) XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="list" switchGridMode="fileList" template_name="ajxp_conf.diagnostic" defaultWidth="20%"><column messageId="ajxp_conf.23" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_conf.24" attributeName="data" sortType="String"/></columns>');
        if (is_file(TESTS_RESULT_FILE)) {
            include_once(TESTS_RESULT_FILE);
            if (isset($diagResults)) {
                foreach ($diagResults as $id => $value) {
                    $value = StringHelper::xmlEntities($value);
                    $xml =  "<tree icon=\"susehelpcenter.png\" is_file=\"1\" filename=\"/$dir/$id\" text=\"$id\" data=\"$value\" ajxp_mime=\"testResult\"/>";
                    if(!$returnNodes) print($xml);
                    else $allNodes["/$dir/$id"] = $xml;
                }
            }
        }
        return $allNodes;
    }

    /**
     * Reorder meta sources 
     * @param $key1
     * @param $key2
     * @return int
     */
    public function metaSourceOrderingFunction($key1, $key2)
    {
        $a1 = explode(".", $key1);
        $t1 = array_shift($a1);
        $a2 = explode(".", $key2);
        $t2 = array_shift($a2);
        if($t1 == "index") return 1;
        if($t1 == "metastore") return -1;
        if($t2 == "index") return -1;
        if($t2 == "metastore") return 1;
        if($key1 == "meta.git" || $key1 == "meta.svn") return 1;
        if($key2 == "meta.git" || $key2 == "meta.svn") return -1;
        return strcmp($key1, $key2);
    }

    /**
     * @param UserInterface $ctxUser
     * @param $userId
     * @param $roleId
     * @param $addOrRemove
     * @param bool $updateSubUsers
     * @return UserInterface
     * @throws UserNotFoundException
     * @throws \Exception
     */
    public function updateUserRole(UserInterface $ctxUser, $userId, $roleId, $addOrRemove, $updateSubUsers = false)
    {
        $user = UsersService::getUserById($userId);
        if(!empty($ctxUser) && !$ctxUser->canAdministrate($user)){
            throw new \Exception("Cannot update user data for ".$userId);
        }
        if ($addOrRemove == "add") {
            $roleObject = RolesService::getRole($roleId);
            $user->addRole($roleObject);
        } else {
            $user->removeRole($roleId);
        }
        $user->save("superuser");
        if ($ctxUser->getId() == $user->getId()) {
            AuthService::updateUser($user);
        }
        return $user;

    }

    /**
     * @param ContextInterface $ctx
     * @param $repDef
     * @param $options
     * @param bool $globalBinaries
     * @param array $existingValues
     */
    protected function parseParameters(ContextInterface $ctx, &$repDef, &$options, $globalBinaries = false, $existingValues = array())
    {
        OptionsHelper::parseStandardFormParameters($ctx, $repDef, $options, "DRIVER_OPTION_", ($globalBinaries ? array() : null));
        if(!count($existingValues)){
            return;
        }
        $this->mergeExistingParameters($options, $existingValues);
    }

    /**
     * @param array $parsed
     * @param array $existing
     */
    protected function mergeExistingParameters(&$parsed, $existing){
        foreach($parsed as $k => &$v){
            if($v === "__AJXP_VALUE_SET__" && isSet($existing[$k])){
                $parsed[$k] = $existing[$k];
            }else if(is_array($v) && is_array($existing[$k])){
                $this->mergeExistingParameters($v, $existing[$k]);
            }
        }
    }

    /**
     * @param $result
     * @param $definitions
     * @param $values
     * @param string $parent
     */
    public function flattenKeyValues(&$result, &$definitions, $values, $parent = "")
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $this->flattenKeyValues($result, $definitions, $value, $parent."/".$key);
            } else {
                if ($key == "instance_name") {
                    $result[$parent] = $value;
                }
                if ($key == "group_switch_value") {
                    $result[$parent] = $value;
                } else {
                    $result[$parent.'/'.$key] = $value;
                    if(isSet($definitions[$key])){
                        $definitions[$parent.'/'.$key] = $definitions[$key];
                    }else if(isSet($definitions[dirname($parent)."/".$key])){
                        $definitions[$parent.'/'.$key] = $definitions[dirname($parent)."/".$key];
                    }
                }
            }
        }
    }

    /**
     * Search the manifests declaring ajxpdriver as their root node. Remove ajxp_* drivers
     * @static
     * @param string $filterByTagName
     * @param string $filterByDriverName
     * @param bool $limitToEnabledPlugins
     * @return string
     */
    public static function availableDriversToXML($filterByTagName = "", $filterByDriverName="", $limitToEnabledPlugins = false)
    {
        $nodeList = PluginsService::getInstance(Context::emptyContext())->searchAllManifests("//ajxpdriver", "node", false, $limitToEnabledPlugins);
        $xmlBuffer = "";
        /** @var \DOMElement $node */
        foreach ($nodeList as $node) {
            $dName = $node->getAttribute("name");
            if($filterByDriverName != "" && $dName != $filterByDriverName) continue;
            if(strpos($dName, "ajxp_") === 0) continue;
            if ($filterByTagName == "") {
                $xmlBuffer .= $node->ownerDocument->saveXML($node);
                continue;
            }
            $q = new DOMXPath($node->ownerDocument);
            $cNodes = $q->query("//".$filterByTagName, $node);
            $xmlBuffer .= "<ajxpdriver ";
            foreach($node->attributes as $attr) $xmlBuffer.= " $attr->name=\"$attr->value\" ";
            $xmlBuffer .=">";
            foreach ($cNodes as $child) {
                $xmlBuffer .= $child->ownerDocument->saveXML($child);
            }
            $xmlBuffer .= "</ajxpdriver>";
        }
        return $xmlBuffer;
    }

    /**
     * @param ContextInterface $ctx
     */
    protected function initRepository(ContextInterface $ctx){
    }
}