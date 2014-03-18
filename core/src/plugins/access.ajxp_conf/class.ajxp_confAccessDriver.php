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
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 * @class ajxp_confAccessDriver
 * AJXP_Plugin to access the configurations data
 */
class ajxp_confAccessDriver extends AbstractAccessDriver
{

    private $listSpecialRoles = AJXP_SERVER_DEBUG;
    private $currentBookmarks = array();

    private function filterReservedRoles($key){
        return (strpos($key, "AJXP_GRP_/") === FALSE && strpos($key, "AJXP_USR_/") === FALSE);
    }

    public function listAllActions($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return;
        parent::accessPreprocess($action, $httpVars, $fileVars);
        $loggedUser = AuthService::getLoggedUser();
        if(AuthService::usersEnabled() && !$loggedUser->isAdmin()) return ;
        switch ($action) {
            //------------------------------------
            //	BASIC LISTING
            //------------------------------------
            case "list_all_repositories_json":

                $repositories = ConfService::getRepositoriesList("all");
                $repoOut = array();
                foreach ($repositories as $repoObject) {
                    $repoOut[$repoObject->getId()] = $repoObject->getDisplay();
                }
                HTMLWriter::charsetHeader("application/json");
                echo json_encode(array("LEGEND" => "Select a repository", "LIST" => $repoOut));

            break;

            case "list_all_plugins_actions":
                $nodes = AJXP_PluginsService::getInstance()->searchAllManifests("//action", "node", false, true, true);
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
                foreach ($actions as $actPid => $actionGroup) {
                    ksort($actionGroup, SORT_STRING);
                    $actions[$actPid] = array();
                    foreach ($actionGroup as $k => $v) {
                        $actions[$actPid][] = $v;
                    }
                }
                HTMLWriter::charsetHeader("application/json");
                echo json_encode(array("LIST" => $actions, "HAS_GROUPS" => true));
                break;
            case "list_all_plugins_parameters":
                $nodes = AJXP_PluginsService::getInstance()->searchAllManifests("//param|//global_param", "node", false, true, true);
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
                    //echo($pId." : ". $node->attributes->getNamedItem("name")->nodeValue . " (".$messId.")<br>");
                    if(!is_array($actions[$pId])) $actions[$pId] = array();
                    $actionName = $node->attributes->getNamedItem("name")->nodeValue;
                    $messId = $node->attributes->getNamedItem("label")->nodeValue;
                    $actions[$pId][$actionName] = array( "parameter" => $actionName , "label" => AJXP_XMLWriter::replaceAjxpXmlKeywords($messId));

                }
                foreach ($actions as $actPid => $actionGroup) {
                    ksort($actionGroup, SORT_STRING);
                    $actions[$actPid] = array();
                    foreach ($actionGroup as $k => $v) {
                        $actions[$actPid][] = $v;
                    }
                }
                HTMLWriter::charsetHeader("application/json");
                echo json_encode(array("LIST" => $actions, "HAS_GROUPS" => true));
                break;
            case "parameters_to_form_definitions" :

                $data = json_decode(AJXP_Utils::decodeSecureMagic($httpVars["json_parameters"]), true);
                AJXP_XMLWriter::header("standard_form");
                foreach ($data as $repoScope => $pluginsData) {
                    echo("<repoScope id='$repoScope'>");
                    foreach ($pluginsData as $pluginId => $paramData) {
                        foreach ($paramData as $paramId => $paramValue) {
                            $query = "//param[@name='$paramId']|//global_param[@name='$paramId']";
                            $nodes = AJXP_PluginsService::getInstance()->searchAllManifests($query, "node", false, true, true);
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
                            echo(AJXP_XMLWriter::replaceAjxpXmlKeywords($n->ownerDocument->saveXML($n)));
                        }
                    }
                    echo("</repoScope>");
                }
                AJXP_XMLWriter::close("standard_form");
                break;

            default:
                break;
        }
    }

    public function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if($contribNode->nodeName != "actions") return;
        $currentUserIsGroupAdmin = (AuthService::getLoggedUser() != null && AuthService::getLoggedUser()->getGroupPath() != "/");
        if(!$currentUserIsGroupAdmin) return;
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $publicUrlNodeList = $actionXpath->query('action[@name="create_repository"]/subMenu', $contribNode);
        if ($publicUrlNodeList->length) {
            $publicUrlNode = $publicUrlNodeList->item(0);
            $publicUrlNode->parentNode->removeChild($publicUrlNode);
        }
    }

    public function preProcessBookmarkAction($action, &$httpVars, $fileVars)
    {
        if (isSet($httpVars["bm_action"]) && $httpVars["bm_action"] == "add_bookmark" && AuthService::usersEnabled()) {
            $bmUser = AuthService::getLoggedUser();
            $bookmarks = $bmUser->getBookmarks();
            $found = false;
            foreach ($bookmarks as $bm) {
                if ($bm["PATH"] == $httpVars["bm_path"]) {
                    $httpVars["bm_action"] = "delete_bookmark";
                    break;
                }
            }
        }

    }

    public function recursiveSearchGroups($baseGroup, $term)
    {
        $groups = AuthService::listChildrenGroups($baseGroup);
        foreach ($groups as $groupId => $groupLabel) {

            if (preg_match("/$term/i", $groupLabel) == TRUE ) {
                $nodeKey = "/data/users/".trim($baseGroup, "/")."/".ltrim($groupId,"/");
                $meta = array(
                    "icon" => "users-folder.png",
                    "ajxp_mime" => "group"
                );
                if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                echo AJXP_XMLWriter::renderNode($nodeKey, $groupLabel, false, $meta, true, false);
            }
            $this->recursiveSearchGroups(rtrim($baseGroup, "/")."/".ltrim($groupId, "/"), $term);

        }

        $users = AuthService::listUsers($baseGroup, "^".$term);
        foreach ($users as $userId => $userObject) {

            $nodeKey = "/data/users/".trim($userObject->getGroupPath(),"/")."/".$userId;
            $meta = array(
                "icon" => "user.png",
                "ajxp_mime" => "user"
            );
            if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            echo AJXP_XMLWriter::renderNode($nodeKey, $userId, false, $meta, true, false);

        }

    }


    public function searchAction($action, $httpVars, $fileVars)
    {
        if(! AJXP_Utils::decodeSecureMagic($httpVars["dir"]) == "/data/users") return;
        $query = AJXP_Utils::decodeSecureMagic($httpVars["query"]);
        AJXP_XMLWriter::header();

        $this->recursiveSearchGroups("/", $query);
        AJXP_XMLWriter::close();

    }

    public function switchAction($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return;
        parent::accessPreprocess($action, $httpVars, $fileVars);
        $loggedUser = AuthService::getLoggedUser();
        if(AuthService::usersEnabled() && !$loggedUser->isAdmin()) return ;
        if (AuthService::usersEnabled()) {
            $currentBookmarks = AuthService::getLoggedUser()->getBookmarks();
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
        $mess = ConfService::getMessages();
        $currentUserIsGroupAdmin = (AuthService::getLoggedUser() != null && AuthService::getLoggedUser()->getGroupPath() != "/");
        if ($currentUserIsGroupAdmin && ConfService::getAuthDriverImpl()->isAjxpAdmin(AuthService::getLoggedUser()->getId())) {
            $currentUserIsGroupAdmin = false;
        }

        switch ($action) {
            //------------------------------------
            //	BASIC LISTING
            //------------------------------------
            case "ls":

                $rootNodes = array(
                    "data" => array(
                        "LABEL" => $mess["ajxp_conf.110"],
                        "ICON" => "user.png",
                        "DESCRIPTION" => "Day-to-day administration of the application : who accesses to what, create roles, etc.",
                        "CHILDREN" => array(
                            "repositories" => array(
                                "AJXP_MIME" => "workspaces_zone",
                                "LABEL" => $mess["ajxp_conf.3"],
                                "DESCRIPTION" => "Create and delete workspaces, add features to them using meta sources.",
                                "ICON" => "hdd_external_unmount.png",
                                "LIST" => "listRepositories"),
                            "users" => array(
                                "AJXP_MIME" => "users_zone",
                                "LABEL" => $mess["ajxp_conf.2"],
                                "DESCRIPTION" => "Manage users and groups",
                                "ICON" => "users-folder.png",
                                "LIST" => "listUsers"
                            ),
                            "roles" => array(
                                "AJXP_MIME" => "roles_zone",
                                "LABEL" => $mess["ajxp_conf.69"],
                                "DESCRIPTION" => "Define profiles that can be applied at once to whole bunch of users.",
                                "ICON" => "user-acl.png",
                                "LIST" => "listRoles"),
                        )
                    ),
                    "config" => array(
                        "LABEL" => $mess["ajxp_conf.109"],
                        "ICON" => "preferences_desktop.png",
                        "DESCRIPTION" => "Global configurations of the application core and of each plugin. Enable/disable plugins",
                        "CHILDREN" => array(
                            "core"	   	   => array(
                                "LABEL" => $mess["ajxp_conf.98"],
                                "DESCRIPTION" => "Core application parameters",
                                "ICON" => "preferences_desktop.png",
                                "LIST" => "listPlugins"),
                            "plugins"	   => array(
                                "LABEL" => $mess["ajxp_conf.99"],
                                "DESCRIPTION" => "Enable/disable additional feature-oriented plugins, check if they are correctly working, set up global parameters of the plugins.",
                                "ICON" => "folder_development.png",
                                "LIST" => "listPlugins"),
                            "core_plugins" => array(
                                "LABEL" => $mess["ajxp_conf.123"],
                                "DESCRIPTION" => "Enable/disable core plugins (auth, conf, mail, etc), check if they are correctly working. Configuration of these plugins are generally done through the Main Options",
                                "ICON" => "folder_development.png",
                                "LIST" => "listPlugins"),
                        )
                    ),
                    "admin" => array(
                        "LABEL" => $mess["ajxp_conf.111"],
                        "ICON" => "toggle_log.png",
                        "DESCRIPTION" => "Administrator tasks to monitor the application state.",
                        "CHILDREN" => array(
                            "logs" => array(
                                "LABEL" => $mess["ajxp_conf.4"],
                                "DESCRIPTION" => "Monitor all activities happening on the server",
                                "ICON" => "toggle_log.png",
                                "LIST" => "listLogFiles"),
                            "files" => array(
                                "LABEL" => $mess["ajxp_shared.3"],
                                "DESCRIPTION" => "Monitor all files shared as public links by every users",
                                "ICON" => "html.png",
                                "LIST" => "listSharedFiles"),
                            "diagnostic" => array(
                                "LABEL" => $mess["ajxp_conf.5"],
                                "DESCRIPTION" => "Read the start-up diagnostic generated by Pydio",
                                "ICON" => "susehelpcenter.png", "LIST" => "printDiagnostic")
                        )
                    ),
                    "developer" => array(
                        "LABEL" => "Developer Resources",
                        "ICON" => "applications_engineering.png",
                        "DESCRIPTION" => "Generated documentations for developers",
                        "CHILDREN" => array(
                            "actions" => array(
                                "LABEL" => "Actions API",
                                "DESCRIPTION" => "List all actions contributed by all plugins and visualize their input parameters",
                                "ICON" => "book.png",
                                "LIST" => "listActions"),
                            "hooks" => array(
                                "LABEL" => "Hooks Definitions",
                                "DESCRIPTION" => "List all hooks triggered in the application, their documentation, where there are triggered and which plugin listen to them.",
                                "ICON" => "book.png",
                                "LIST" => "listHooks")
                        )
                    )
                );
                if ($currentUserIsGroupAdmin) {
                    unset($rootNodes["config"]);
                    unset($rootNodes["admin"]);
                    unset($rootNodes["developer"]);
                }
                AJXP_Controller::applyHook("ajxp_conf.list_config_nodes", array(&$rootNodes));
                $dir = trim(AJXP_Utils::decodeSecureMagic((isset($httpVars["dir"])?$httpVars["dir"]:"")), " /");
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
                            $callback = $rootNodes[$root]["CHILDREN"][$child]["LIST"];
                            if (is_string($callback) && method_exists($this, $callback)) {
                                if(!$returnNodes) AJXP_XMLWriter::header("tree", $atts);
                                $res = call_user_func(array($this, $callback), implode("/", $splits), $root, $hash, $returnNodes, isSet($httpVars["file"])?$httpVars["file"]:'');
                                if(!$returnNodes) AJXP_XMLWriter::close();
                            } else if (is_array($callback)) {
                                $res = call_user_func($callback, implode("/", $splits), $root, $hash, $returnNodes, isSet($httpVars["file"])?$httpVars["file"]:'');
                            }
                            if ($returnNodes) {
                                AJXP_XMLWriter::header("tree", $atts);
                                if (isSet($res["/".$dir."/".$httpVars["file"]])) {
                                    print $res["/".$dir."/".$httpVars["file"]];
                                }
                                AJXP_XMLWriter::close();
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
                }
                if (isSet($httpVars["file"])) {
                    $parentName = $httpVars["dir"]."/";
                    $nodes = array(basename($httpVars["file"]) =>  array("LABEL" => basename($httpVars["file"])));
                }
                if (isSet($nodes)) {
                    AJXP_XMLWriter::header();
                    if(!isSet($httpVars["file"])) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="detail"><column messageId="ajxp_conf.1" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_conf.102" attributeName="description" sortType="String"/></columns>');
                    foreach ($nodes as $key => $data) {
                        $bmString = '';
                        if(in_array($parentName.$key, $this->currentBookmarks)) $bmString = ' ajxp_bookmarked="true" overlay_icon="bookmark.png" ';
                        if($key == "users") $bmString .= ' remote_indexation="admin_search"';
                        if(isSet($data["AJXP_MIME"])) $bmString .= ' ajxp_mime="'.$data["AJXP_MIME"].'"';
                        if (empty($data["CHILDREN"])) {
                            print '<tree text="'.AJXP_Utils::xmlEntities($data["LABEL"]).'" description="'.AJXP_Utils::xmlEntities($data["DESCRIPTION"]).'" icon="'.$data["ICON"].'" filename="'.$parentName.$key.'" '.$bmString.'/>';
                        } else {
                            print '<tree text="'.AJXP_Utils::xmlEntities($data["LABEL"]).'" description="'.AJXP_Utils::xmlEntities($data["DESCRIPTION"]).'" icon="'.$data["ICON"].'" filename="'.$parentName.$key.'" '.$bmString.'>';
                            foreach ($data["CHILDREN"] as $cKey => $cData) {
                                $bmString = '';
                                if(in_array($parentName.$key."/".$cKey, $this->currentBookmarks)) $bmString = ' ajxp_bookmarked="true" overlay_icon="bookmark.png" ';
                                if($cKey == "users") $bmString .= ' remote_indexation="admin_search"';
                                if(isSet($cData["AJXP_MIME"])) $bmString .= ' ajxp_mime="'.$cData["AJXP_MIME"].'"';
                                print '<tree text="'.AJXP_Utils::xmlEntities($cData["LABEL"]).'" description="'.AJXP_Utils::xmlEntities($cData["DESCRIPTION"]).'" icon="'.$cData["ICON"].'" filename="'.$parentName.$key.'/'.$cKey.'" '.$bmString.'/>';
                            }
                            print '</tree>';
                        }
                    }
                    AJXP_XMLWriter::close();

                }

            break;

            case "stat" :

                header("Content-type:application/json");
                print '{"mode":true}';
                return;

            break;

            case "create_group":

                if (isSet($httpVars["group_path"])) {
                    $basePath = AJXP_Utils::forwardSlashDirname($httpVars["group_path"]);
                    if(empty($basePath)) $basePath = "/";
                    $gName = AJXP_Utils::sanitize(AJXP_Utils::decodeSecureMagic(basename($httpVars["group_path"])), AJXP_SANITIZE_ALPHANUM);
                } else {
                    $basePath = substr($httpVars["dir"], strlen("/data/users"));
                    $gName    = AJXP_Utils::sanitize(SystemTextEncoding::magicDequote($httpVars["group_name"]), AJXP_SANITIZE_ALPHANUM);
                }
                $gLabel   = AJXP_Utils::decodeSecureMagic($httpVars["group_label"]);
                AuthService::createGroup($basePath, $gName, $gLabel);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.124"], null);
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::close();

            break;

            case "create_role":
                $roleId = AJXP_Utils::sanitize(SystemTextEncoding::magicDequote($httpVars["role_id"]), AJXP_SANITIZE_HTML_STRICT);
                if (!strlen($roleId)) {
                    throw new Exception($mess[349]);
                }
                if (AuthService::getRole($roleId) !== false) {
                    throw new Exception($mess["ajxp_conf.65"]);
                }
                $r = new AJXP_Role($roleId);
                if (AuthService::getLoggedUser()!=null && AuthService::getLoggedUser()->getGroupPath()!=null) {
                    $r->setGroupPath(AuthService::getLoggedUser()->getGroupPath());
                }
                AuthService::updateRole($r);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.66"], null);
                AJXP_XMLWriter::reloadDataNode("", $httpVars["role_id"]);
                AJXP_XMLWriter::close();
            break;

            case "edit_role" :
                $roleId = SystemTextEncoding::magicDequote($httpVars["role_id"]);
                $roleGroup = false;
                if (strpos($roleId, "AJXP_GRP_") === 0) {
                    $groupPath = substr($roleId, strlen("AJXP_GRP_"));
                    $filteredGroupPath = AuthService::filterBaseGroup($groupPath);
                    $groups = AuthService::listChildrenGroups(AJXP_Utils::forwardSlashDirname($groupPath));
                    $key = "/".basename($groupPath);
                    if (!array_key_exists($key, $groups)) {
                        throw new Exception("Cannot find group with this id!");
                    }
                    $roleId = "AJXP_GRP_".$filteredGroupPath;
                    $groupLabel = $groups[$key];
                    $roleGroup = true;
                }
                if (strpos($roleId, "AJXP_USR_") === 0) {
                    $usrId = str_replace("AJXP_USR_/", "", $roleId);
                    $userObject = ConfService::getConfStorageImpl()->createUserObject($usrId);
                    if(!AuthService::canAdministrate($userObject)){
                        throw new Exception("Cant find user!");
                    }
                    $role = $userObject->personalRole;
                } else {
                    $role = AuthService::getRole($roleId, $roleGroup);
                }
                if ($role === false) {
                    throw new Exception("Cant find role! ");
                }
                if (isSet($httpVars["format"]) && $httpVars["format"] == "json") {
                    HTMLWriter::charsetHeader("application/json");
                    $roleData = $role->getDataArray();
                    $repos = ConfService::getAccessibleRepositories($userObject, true, true, ($userObject == null ? true:false));
                    $data = array(
                        "ROLE" => $roleData,
                        "ALL"  => array(
                            "REPOSITORIES" => $repos
                        )
                    );
                    if (isSet($userObject)) {
                        $data["USER"] = array();
                        $data["USER"]["LOCK"] = $userObject->getLock();
                        $data["USER"]["PROFILE"] = $userObject->getProfile();
                        $data["ALL"]["PROFILES"] = array("standard|Standard","admin|Administrator","shared|Shared","guest|Guest");
                        $data["USER"]["ROLES"] = array_keys($userObject->getRoles());
                        $data["ALL"]["ROLES"] = array_keys(AuthService::getRolesList(array(), true));
                        if (isSet($userObject->parentRole)) {
                            $data["PARENT_ROLE"] = $userObject->parentRole->getDataArray();
                        }
                    } else if (isSet($groupPath)) {
                        $data["GROUP"] = array("PATH" => $groupPath, "LABEL" => $groupLabel);
                    }

                    $scope = "role";
                    if($roleGroup) $scope = "group";
                    else if(isSet($userObject)) $scope = "user";
                    $data["SCOPE_PARAMS"] = array();
                    $nodes = AJXP_PluginsService::getInstance()->searchAllManifests("//param[contains(@scope,'".$scope."')]|//global_param[contains(@scope,'".$scope."')]", "node", false, true, true);
                    foreach ($nodes as $node) {
                        $pId = $node->parentNode->parentNode->attributes->getNamedItem("id")->nodeValue;
                        $origName = $node->attributes->getNamedItem("name")->nodeValue;
                        $node->attributes->getNamedItem("name")->nodeValue = "AJXP_REPO_SCOPE_ALL/".$pId."/".$origName;
                        $nArr = array();
                        foreach ($node->attributes as $attrib) {
                            $nArr[$attrib->nodeName] = AJXP_XMLWriter::replaceAjxpXmlKeywords($attrib->nodeValue);
                        }
                        $data["SCOPE_PARAMS"][] = $nArr;
                    }

                    echo json_encode($data);
                }
            break;

            case "post_json_role" :

                $roleId = SystemTextEncoding::magicDequote($httpVars["role_id"]);
                $roleGroup = false;
                if (strpos($roleId, "AJXP_GRP_") === 0) {
                    $groupPath = substr($roleId, strlen("AJXP_GRP_"));
                    $filteredGroupPath = AuthService::filterBaseGroup($groupPath);
                    $roleId = "AJXP_GRP_".$filteredGroupPath;
                    $groups = AuthService::listChildrenGroups(AJXP_Utils::forwardSlashDirname($groupPath));
                    $key = "/".basename($groupPath);
                    if (!array_key_exists($key, $groups)) {
                        throw new Exception("Cannot find group with this id!");
                    }
                    $groupLabel = $groups[$key];
                    $roleGroup = true;
                }
                if (strpos($roleId, "AJXP_USR_") === 0) {
                    $usrId = str_replace("AJXP_USR_/", "", $roleId);
                    $userObject = ConfService::getConfStorageImpl()->createUserObject($usrId);
                    if(!AuthService::canAdministrate($userObject)){
                        throw new Exception("Cannot post role for user ".$usrId);
                    }
                    $originalRole = $userObject->personalRole;
                } else {
                    // second param = create if not exists.
                    $originalRole = AuthService::getRole($roleId, $roleGroup);
                }
                if ($originalRole === false) {
                    throw new Exception("Cant find role! ");
                }

                $jsonData = AJXP_Utils::decodeSecureMagic($httpVars["json_data"]);
                $data = json_decode($jsonData, true);
                $roleData = $data["ROLE"];
                $forms = $data["FORMS"];
                $binariesContext = array();
                if (isset($userObject)) {
                    $binariesContext = array("USER" => $userObject->getId());
                }
                foreach ($forms as $repoScope => $plugData) {
                    foreach ($plugData as $plugId => $formsData) {
                        $parsed = array();
                        AJXP_Utils::parseStandardFormParameters(
                            $formsData,
                            $parsed,
                            ($userObject!=null?$usrId:null),
                            "ROLE_PARAM_",
                            $binariesContext
                        );
                        $roleData["PARAMETERS"][$repoScope][$plugId] = $parsed;
                    }
                }
                if (isSet($userObject) && isSet($data["USER"]) && isSet($data["USER"]["PROFILE"])) {
                    $userObject->setAdmin(($data["USER"]["PROFILE"] == "admin"));
                    $userObject->setProfile($data["USER"]["PROFILE"]);
                }
                if (isSet($data["GROUP_LABEL"]) && isSet($groupLabel) && $groupLabel != $data["GROUP_LABEL"]) {
                    ConfService::getConfStorageImpl()->relabelGroup($filteredGroupPath, $data["GROUP_LABEL"]);
                }

                $output = array();
                try {
                    $originalRole->bunchUpdate($roleData);
                    if (isSet($userObject)) {
                        $userObject->personalRole = $originalRole;
                        $userObject->save("superuser");
                        //AuthService::updateRole($originalRole, $userObject);
                    } else {
                        AuthService::updateRole($originalRole);
                    }
                    $output = array("ROLE" => $originalRole->getDataArray(), "SUCCESS" => true);
                } catch (Exception $e) {
                    $output = array("ERROR" => $e->getMessage());
                }
                HTMLWriter::charsetHeader("application/json");
                echo(json_encode($output));

            break;


            case "user_set_lock" :

                $userId = AJXP_Utils::decodeSecureMagic($httpVars["user_id"]);
                $lock = ($httpVars["lock"] == "true" ? true : false);
                $lockType = $httpVars["lock_type"];
                if (AuthService::userExists($userId)) {
                    $userObject = ConfService::getConfStorageImpl()->createUserObject($userId);
                    if(!AuthService::canAdministrate($userObject)){
                        throw new Exception("Cannot update user data for ".$userId);
                    }
                    if ($lock) {
                        $userObject->setLock($lockType);
                    } else {
                        $userObject->removeLock();
                    }
                    $userObject->save("superuser");
                }

            break;

            case "create_user" :

                if (!isset($httpVars["new_user_login"]) || $httpVars["new_user_login"] == "" ||!isset($httpVars["new_user_pwd"]) || $httpVars["new_user_pwd"] == "") {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
                    AJXP_XMLWriter::close();
                    return;
                }
                $new_user_login = AJXP_Utils::sanitize(SystemTextEncoding::magicDequote($httpVars["new_user_login"]), AJXP_SANITIZE_EMAILCHARS);
                if (AuthService::userExists($new_user_login, "w") || AuthService::isReservedUserId($new_user_login)) {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.43"]);
                    AJXP_XMLWriter::close();
                    return;
                }

                AuthService::createUser($new_user_login, $httpVars["new_user_pwd"]);
                $confStorage = ConfService::getConfStorageImpl();
                $newUser = $confStorage->createUserObject($new_user_login);
                $basePath = AuthService::getLoggedUser()->getGroupPath();
                if(empty ($basePath)) $basePath = "/";
                if (!empty($httpVars["group_path"])) {
                    $newUser->setGroupPath(rtrim($basePath, "/")."/".ltrim($httpVars["group_path"], "/"));
                } else {
                    $newUser->setGroupPath($basePath);
                }

                $newUser->save("superuser");
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.44"], null);
                AJXP_XMLWriter::reloadDataNode("", $new_user_login);
                AJXP_XMLWriter::close();

            break;

            case "change_admin_right" :
                $userId = $httpVars["user_id"];
                if (!AuthService::userExists($userId)) {
                    throw new Exception("Invalid user id!");
                }
                $confStorage = ConfService::getConfStorageImpl();
                $user = $confStorage->createUserObject($userId);
                if(!AuthService::canAdministrate($user)){
                    throw new Exception("Cannot update user with id ".$userId);
                }
                $user->setAdmin(($httpVars["right_value"]=="1"?true:false));
                $user->save("superuser");
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.45"].$httpVars["user_id"], null);
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::close();

            break;

            case "role_update_right" :
                if(!isSet($httpVars["role_id"])
                    || !isSet($httpVars["repository_id"])
                    || !isSet($httpVars["right"]))
                {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
                    AJXP_XMLWriter::close();
                    break;
                }
                $rId = AJXP_Utils::sanitize($httpVars["role_id"]);
                $role = AuthService::getRole($rId);
                if($role === false){
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]."(".$rId.")");
                    AJXP_XMLWriter::close();
                    break;
                }
                $role->setAcl(AJXP_Utils::sanitize($httpVars["repository_id"], AJXP_SANITIZE_ALPHANUM), AJXP_Utils::sanitize($httpVars["right"], AJXP_SANITIZE_ALPHANUM));
                AuthService::updateRole($role);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.46"].$httpVars["role_id"], null);
                AJXP_XMLWriter::close();

            break;

            case "user_update_right" :
                if(!isSet($httpVars["user_id"])
                    || !isSet($httpVars["repository_id"])
                    || !isSet($httpVars["right"])
                    || !AuthService::userExists($httpVars["user_id"]))
                {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
                    print("<update_checkboxes user_id=\"".$httpVars["user_id"]."\" repository_id=\"".$httpVars["repository_id"]."\" read=\"old\" write=\"old\"/>");
                    AJXP_XMLWriter::close();
                    return;
                }
                $confStorage = ConfService::getConfStorageImpl();
                $userId = AJXP_Utils::sanitize($httpVars["user_id"], AJXP_SANITIZE_EMAILCHARS);
                $user = $confStorage->createUserObject($userId);
                if(!AuthService::canAdministrate($user)){
                    throw new Exception("Cannot update user with id ".$userId);
                }
                $user->personalRole->setAcl(AJXP_Utils::sanitize($httpVars["repository_id"], AJXP_SANITIZE_ALPHANUM), AJXP_Utils::sanitize($httpVars["right"], AJXP_SANITIZE_ALPHANUM));
                $user->save();
                $loggedUser = AuthService::getLoggedUser();
                if ($loggedUser->getId() == $user->getId()) {
                    AuthService::updateUser($user);
                }
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.46"].$httpVars["user_id"], null);
                print("<update_checkboxes user_id=\"".$httpVars["user_id"]."\" repository_id=\"".$httpVars["repository_id"]."\" read=\"".$user->canRead($httpVars["repository_id"])."\" write=\"".$user->canWrite($httpVars["repository_id"])."\"/>");
                AJXP_XMLWriter::reloadRepositoryList();
                AJXP_XMLWriter::close();
                return ;
            break;

            case "user_update_group":

                $userSelection = new UserSelection();
                $userSelection->initFromHttpVars($httpVars);
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

                $confStorage = ConfService::getConfStorageImpl();

                foreach ($userSelection->getFiles() as $selectedUser) {
                    $userId = basename($selectedUser);
                    if (!AuthService::userExists($userId)) {
                        continue;
                    }
                    $user = $confStorage->createUserObject($userId);
                    if( ! AuthService::canAdministrate($user) ){
                        continue;
                    }
                    $basePath = (AuthService::getLoggedUser()!=null ? AuthService::getLoggedUser()->getGroupPath(): "/");
                    if(empty ($basePath)) $basePath = "/";
                    if (!empty($groupPath)) {
                        $user->setGroupPath(rtrim($basePath, "/")."/".ltrim($groupPath, "/"), true);
                    } else {
                        $user->setGroupPath($basePath, true);
                    }
                    $user->save("superuser");
                }
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::reloadDataNode($dest, $userId);
                AJXP_XMLWriter::close();

                break;

            case "user_add_role" :
            case "user_delete_role":

                if (!isSet($httpVars["user_id"]) || !isSet($httpVars["role_id"]) || !AuthService::userExists($httpVars["user_id"]) || !AuthService::getRole($httpVars["role_id"])) {
                    throw new Exception($mess["ajxp_conf.61"]);
                }
                if ($action == "user_add_role") {
                    $act = "add";
                    $messId = "73";
                } else {
                    $act = "remove";
                    $messId = "74";
                }
                $this->updateUserRole(AJXP_Utils::sanitize($httpVars["user_id"], AJXP_SANITIZE_EMAILCHARS), $httpVars["role_id"], $act);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.".$messId].$httpVars["user_id"], null);
                AJXP_XMLWriter::close();
                return ;

            break;

            case "user_update_role" :

                $confStorage = ConfService::getConfStorageImpl();
                $selection = new UserSelection();
                $selection->initFromHttpVars($httpVars);
                $files = $selection->getFiles();
                $detectedRoles = array();

                if (isSet($httpVars["role_id"]) && isset($httpVars["update_role_action"])) {
                    $update = $httpVars["update_role_action"];
                    $roleId = $httpVars["role_id"];
                    if (AuthService::getRole($roleId) === false) {
                        throw new Exception("Invalid role id");
                    }
                }
                foreach ($files as $index => $file) {
                    $userId = basename($file);
                    if (isSet($update)) {
                        $userObject = $this->updateUserRole($userId, $roleId, $update);
                    } else {
                        $userObject = $confStorage->createUserObject($userId);
                        if(!AuthService::canAdministrate($userObject)){
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
                AJXP_XMLWriter::header("admin_data");
                print("<user><ajxp_roles>");
                foreach ($detectedRoles as $roleId => $roleCount) {
                    if($roleCount < $count) continue;
                    print("<role id=\"$roleId\"/>");
                }
                print("</ajxp_roles></user>");
                print("<ajxp_roles>");
                foreach (AuthService::getRolesList(array(), !$this->listSpecialRoles) as $roleId => $roleObject) {
                    print("<role id=\"$roleId\"/>");
                }
                print("</ajxp_roles>");
                AJXP_XMLWriter::close("admin_data");

            break;

            case "save_custom_user_params" :
                $userId = AJXP_Utils::sanitize($httpVars["user_id"], AJXP_SANITIZE_EMAILCHARS);
                if ($userId == $loggedUser->getId()) {
                    $user = $loggedUser;
                } else {
                    $confStorage = ConfService::getConfStorageImpl();
                    $user = $confStorage->createUserObject($userId);
                }
                if(!AuthService::canAdministrate($user)){
                    throw new Exception("Cannot update user with id ".$userId);
                }

                $custom = $user->getPref("CUSTOM_PARAMS");
                if(!is_array($custom)) $custom = array();

                $options = $custom;
                $this->parseParameters($httpVars, $options, $userId);
                $custom = $options;
                $user->setPref("CUSTOM_PARAMS", $custom);
                $user->save();

                if ($loggedUser->getId() == $user->getId()) {
                    AuthService::updateUser($user);
                }
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.47"].$httpVars["user_id"], null);
                AJXP_XMLWriter::close();

            break;

            case "save_repository_user_params" :
                $userId = AJXP_Utils::sanitize($httpVars["user_id"], AJXP_SANITIZE_EMAILCHARS);
                if ($userId == $loggedUser->getId()) {
                    $user = $loggedUser;
                } else {
                    $confStorage = ConfService::getConfStorageImpl();
                    $user = $confStorage->createUserObject($userId);
                }
                if(!AuthService::canAdministrate($user)){
                    throw new Exception("Cannot update user with id ".$userId);
                }

                $wallet = $user->getPref("AJXP_WALLET");
                if(!is_array($wallet)) $wallet = array();
                $repoID = $httpVars["repository_id"];
                if (!array_key_exists($repoID, $wallet)) {
                    $wallet[$repoID] = array();
                }
                $options = $wallet[$repoID];
                $this->parseParameters($httpVars, $options, $userId);
                $wallet[$repoID] = $options;
                $user->setPref("AJXP_WALLET", $wallet);
                $user->save();

                if ($loggedUser->getId() == $user->getId()) {
                    AuthService::updateUser($user);
                }
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.47"].$httpVars["user_id"], null);
                AJXP_XMLWriter::close();

            break;

            case "update_user_pwd" :
                if (!isSet($httpVars["user_id"]) || !isSet($httpVars["user_pwd"]) || !AuthService::userExists($httpVars["user_id"]) || trim($httpVars["user_pwd"]) == "") {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
                    AJXP_XMLWriter::close();
                    return;
                }
                $userId = AJXP_Utils::sanitize($httpVars["user_id"], AJXP_SANITIZE_EMAILCHARS);
                $user = ConfService::getConfStorageImpl()->createUserObject($userId);
                if(!AuthService::canAdministrate($user)){
                    throw new Exception("Cannot update user data for ".$userId);
                }
                $res = AuthService::updatePassword($userId, $httpVars["user_pwd"]);
                AJXP_XMLWriter::header();
                if ($res === true) {
                    AJXP_XMLWriter::sendMessage($mess["ajxp_conf.48"].$userId, null);
                } else {
                    AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.49"]." : $res");
                }
                AJXP_XMLWriter::close();

            break;

            case "save_user_preference":

                if (!isSet($httpVars["user_id"]) || !AuthService::userExists($httpVars["user_id"]) ) {
                    throw new Exception($mess["ajxp_conf.61"]);
                }
                $userId = AJXP_Utils::sanitize($httpVars["user_id"], AJXP_SANITIZE_EMAILCHARS);
                if ($userId == $loggedUser->getId()) {
                    $userObject = $loggedUser;
                } else {
                    $confStorage = ConfService::getConfStorageImpl();
                    $userObject = $confStorage->createUserObject($userId);
                }
                if(!AuthService::canAdministrate($userObject)){
                    throw new Exception("Cannot update user data for ".$userId);
                }

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
                    $i++;
                }
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage("Succesfully saved user preference", null);
                AJXP_XMLWriter::close();

            break;

            case  "get_drivers_definition":

                AJXP_XMLWriter::header("drivers", array("allowed" => $currentUserIsGroupAdmin ? "false" : "true"));
                print(AJXP_XMLWriter::replaceAjxpXmlKeywords(ConfService::availableDriversToXML("param", "", true)));
                AJXP_XMLWriter::close("drivers");


            break;

            case  "get_templates_definition":

                AJXP_XMLWriter::header("repository_templates");
                $repositories = ConfService::getRepositoriesList("all");
                foreach ($repositories as $repo) {
                    if(!$repo->isTemplate) continue;
                    $repoId = $repo->getId();
                    $repoLabel = SystemTextEncoding::toUTF8($repo->getDisplay());
                    $repoType = $repo->getAccessType();
                    print("<template repository_id=\"$repoId\" repository_label=\"$repoLabel\" repository_type=\"$repoType\">");
                    foreach ($repo->getOptionsDefined() as $optionName) {
                        print("<option name=\"$optionName\"/>");
                    }
                    print("</template>");
                }
                AJXP_XMLWriter::close("repository_templates");


            break;

            case "create_repository" :

                $repDef = $httpVars;
                $isTemplate = isSet($httpVars["sf_checkboxes_active"]);
                unset($repDef["get_action"]);
                unset($repDef["sf_checkboxes_active"]);
                if (isSet($httpVars["json_data"])) {
                    $repDef = json_decode(SystemTextEncoding::magicDequote($httpVars["json_data"]), true);
                    $options = $repDef["DRIVER_OPTIONS"];
                } else {
                    $options = array();
                    $this->parseParameters($repDef, $options, null, true);
                }
                if (count($options)) {
                    $repDef["DRIVER_OPTIONS"] = $options;
                    unset($repDef["DRIVER_OPTIONS"]["AJXP_GROUP_PATH_PARAMETER"]);
                }
                if (strstr($repDef["DRIVER"], "ajxp_template_") !== false) {
                    $templateId = substr($repDef["DRIVER"], 14);
                    $templateRepo = ConfService::getRepositoryById($templateId);
                    $newRep = $templateRepo->createTemplateChild($repDef["DISPLAY"], $repDef["DRIVER_OPTIONS"]);
                    if(isSet($repDef["AJXP_SLUG"])){
                        $newRep->setSlug($repDef["AJXP_SLUG"]);
                    }
                } else {
                    if ($currentUserIsGroupAdmin) {
                        throw new Exception("You are not allowed to create a repository from a driver. Use a template instead.");
                    }
                    $pServ = AJXP_PluginsService::getInstance();
                    $driver = $pServ->getPluginByTypeName("access", $repDef["DRIVER"]);

                    $newRep = ConfService::createRepositoryFromArray(0, $repDef);
                    $testFile = $driver->getBaseDir()."/test.".$newRep->getAccessType()."Access.php";
                    if (!$isTemplate && is_file($testFile)) {
                        //chdir(AJXP_TESTS_FOLDER."/plugins");
                        $className = $newRep->getAccessType()."AccessTest";
                        if (!class_exists($className))
                            include($testFile);
                        $class = new $className();
                        $result = $class->doRepositoryTest($newRep);
                        if (!$result) {
                            AJXP_XMLWriter::header();
                            AJXP_XMLWriter::sendMessage(null, $class->failedInfo);
                            AJXP_XMLWriter::close();
                            return;
                        }
                    }
                    // Apply default metasource if any
                    if ($driver != null && $driver->getConfigs()!=null ) {
                        $confs = $driver->getConfigs();
                        if (!empty($confs["DEFAULT_METASOURCES"])) {
                            $metaIds = AJXP_Utils::parseCSL($confs["DEFAULT_METASOURCES"]);
                            $metaSourceOptions = array();
                            foreach ($metaIds as $metaID) {
                                $metaPlug = $pServ->getPluginById($metaID);
                                if($metaPlug == null) continue;
                                $pNodes = $metaPlug->getManifestRawContent("//param[@default]", "nodes");
                                $defaultParams = array();
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
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.50"]);
                    AJXP_XMLWriter::close();
                    return;
                }
                if ($isTemplate) {
                    $newRep->isTemplate = true;
                }
                if ($currentUserIsGroupAdmin) {
                    $newRep->setGroupPath(AuthService::getLoggedUser()->getGroupPath());
                } else if (!empty($options["AJXP_GROUP_PATH_PARAMETER"])) {
                    $basePath = "/";
                    if (AuthService::getLoggedUser()!=null && AuthService::getLoggedUser()->getGroupPath()!=null) {
                        $basePath = AuthService::getLoggedUser()->getGroupPath();
                    }
                    $value =  AJXP_Utils::securePath(rtrim($basePath, "/")."/".ltrim($options["AJXP_GROUP_PATH_PARAMETER"], "/"));
                    $newRep->setGroupPath($value);
                }

                $res = ConfService::addRepository($newRep);
                AJXP_XMLWriter::header();
                if ($res == -1) {
                    AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.51"]);
                } else {
                    $loggedUser = AuthService::getLoggedUser();
                    $loggedUser->personalRole->setAcl($newRep->getUniqueId(), "rw");
                    $loggedUser->recomputeMergedRole();
                    $loggedUser->save("superuser");
                    AuthService::updateUser($loggedUser);

                    AJXP_XMLWriter::sendMessage($mess["ajxp_conf.52"], null);
                    AJXP_XMLWriter::reloadDataNode("", $newRep->getUniqueId());
                    AJXP_XMLWriter::reloadRepositoryList();
                }
                AJXP_XMLWriter::close();



            break;

            case "edit_repository" :
                $repId = $httpVars["repository_id"];
                $repository = ConfService::getRepositoryById($repId);
                if ($repository == null) {
                    throw new Exception("Cannot find repository with id $repId");
                }
                if (!AuthService::canAdministrate($repository)) {
                    throw new Exception("You are not allowed to edit this repository!");
                }
                $pServ = AJXP_PluginsService::getInstance();
                $plug = $pServ->getPluginById("access.".$repository->accessType);
                if ($plug == null) {
                    throw new Exception("Cannot find access driver (".$repository->accessType.") for repository!");
                }
                AJXP_XMLWriter::header("admin_data");
                $slug = $repository->getSlug();
                if ($slug == "" && $repository->isWriteable()) {
                    $repository->setSlug();
                    ConfService::replaceRepository($repId, $repository);
                }
                if (AuthService::getLoggedUser()!=null && AuthService::getLoggedUser()->getGroupPath() != null) {
                    $rgp = $repository->getGroupPath();
                    if($rgp == null) $rgp = "/";
                    if (strlen($rgp) < strlen(AuthService::getLoggedUser()->getGroupPath())) {
                        $repository->setWriteable(false);
                    }
                }
                $nested = array();
                print("<repository index=\"$repId\"");
                foreach ($repository as $name => $option) {
                    if(strstr($name, " ")>-1) continue;
                    if (!is_array($option)) {
                        if (is_bool($option)) {
                            $option = ($option?"true":"false");
                        }
                        print(" $name=\"".SystemTextEncoding::toUTF8(AJXP_Utils::xmlEntities($option))."\" ");
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
                            } else {
                                if (is_bool($optValue)) {
                                    $optValue = ($optValue?"true":"false");
                                }
                                $optValue = AJXP_Utils::xmlEntities($optValue, true);
                                print("<param name=\"$key\" value=\"$optValue\"/>");
                            }
                        }
                    }
                    // Add SLUG
                    if(!$repository->isTemplate) print("<param name=\"AJXP_SLUG\" value=\"".$repository->getSlug()."\"/>");
                    if ($repository->getGroupPath() != null) {
                        $basePath = "/";
                        if (AuthService::getLoggedUser()!=null && AuthService::getLoggedUser()->getGroupPath()!=null) {
                            $basePath = AuthService::getLoggedUser()->getGroupPath();
                        }
                        $groupPath = $repository->getGroupPath();
                        if($basePath != "/") $groupPath = substr($repository->getGroupPath(), strlen($basePath));
                        print("<param name=\"AJXP_GROUP_PATH_PARAMETER\" value=\"".$groupPath."\"/>");
                    }

                    print("</repository>");
                } else {
                    print("/>");
                }
                if ($repository->hasParent()) {
                    $parent = ConfService::getRepositoryById($repository->getParentId());
                    if (isSet($parent) && $parent->isTemplate) {
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
                $manifest = AJXP_XMLWriter::replaceAjxpXmlKeywords($manifest);
                print("<ajxpdriver name=\"".$repository->accessType."\">$manifest</ajxpdriver>");
                print("<metasources>");
                $metas = $pServ->getPluginsByType("metastore");
                $metas = array_merge($metas, $pServ->getPluginsByType("meta"));
                $metas = array_merge($metas, $pServ->getPluginsByType("index"));
                foreach ($metas as $metaPlug) {
                    print("<meta id=\"".$metaPlug->getId()."\" label=\"".AJXP_Utils::xmlEntities($metaPlug->getManifestLabel())."\">");
                    $manifest = $metaPlug->getManifestRawContent("server_settings/param");
                    $manifest = AJXP_XMLWriter::replaceAjxpXmlKeywords($manifest);
                    print($manifest);
                    print("</meta>");
                }
                print("</metasources>");
                AJXP_XMLWriter::close("admin_data");
                return ;
            break;

            case "edit_repository_label" :
            case "edit_repository_data" :
                $repId = $httpVars["repository_id"];
                $repo = ConfService::getRepositoryById($repId);
                $res = 0;
                if (isSet($httpVars["newLabel"])) {
                    $newLabel = AJXP_Utils::decodeSecureMagic($httpVars["newLabel"]);
                    if ($this->repositoryExists($newLabel)) {
                         AJXP_XMLWriter::header();
                        AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.50"]);
                        AJXP_XMLWriter::close();
                        return;
                    }
                    $repo->setDisplay($newLabel);
                    $res = ConfService::replaceRepository($repId, $repo);
                } else {
                    $options = array();
                    $this->parseParameters($httpVars, $options, null, true);
                    if (count($options)) {
                        foreach ($options as $key=>$value) {
                            if ($key == "AJXP_SLUG") {
                                $repo->setSlug($value);
                                continue;
                            } elseif ($key == "AJXP_GROUP_PATH_PARAMETER") {
                                $basePath = "/";
                                if (AuthService::getLoggedUser()!=null && AuthService::getLoggedUser()->getGroupPath()!=null) {
                                    $basePath = AuthService::getLoggedUser()->getGroupPath();
                                }
                                $value =  AJXP_Utils::securePath(rtrim($basePath, "/")."/".ltrim($value, "/"));
                                $repo->setGroupPath($value);
                                continue;
                            }
                            $repo->addOption($key, $value);
                        }
                    }
                    if ($repo->getOption("DEFAULT_RIGHTS")) {
                        $gp = $repo->getGroupPath();
                        if (empty($gp) || $gp == "/") {
                            $defRole = AuthService::getRole("ROOT_ROLE");
                        } else {
                            $defRole = AuthService::getRole("AJXP_GRP_".$gp, true);
                        }
                        if ($defRole !== false) {
                            $defRole->setAcl($repId, $repo->getOption("DEFAULT_RIGHTS"));
                            AuthService::updateRole($defRole);
                        }
                    }
                    if (is_file(AJXP_TESTS_FOLDER."/plugins/test.ajxp_".$repo->getAccessType().".php")) {
                        chdir(AJXP_TESTS_FOLDER."/plugins");
                        include(AJXP_TESTS_FOLDER."/plugins/test.ajxp_".$repo->getAccessType().".php");
                        $className = "ajxp_".$repo->getAccessType();
                        $class = new $className();
                        $result = $class->doRepositoryTest($repo);
                        if (!$result) {
                            AJXP_XMLWriter::header();
                            AJXP_XMLWriter::sendMessage(null, $class->failedInfo);
                            AJXP_XMLWriter::close();
                            return;
                        }
                    }

                    ConfService::replaceRepository($repId, $repo);
                }
                AJXP_XMLWriter::header();
                if ($res == -1) {
                    AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.53"]);
                } else {
                    AJXP_XMLWriter::sendMessage($mess["ajxp_conf.54"], null);
                    if (isSet($httpVars["newLabel"])) {
                        AJXP_XMLWriter::reloadDataNode("", $repId);
                    }
                    AJXP_XMLWriter::reloadRepositoryList();
                }
                AJXP_XMLWriter::close();

            break;

            case "meta_source_add" :
                $repId = $httpVars["repository_id"];
                $repo = ConfService::getRepositoryById($repId);
                if (!is_object($repo)) {
                    throw new Exception("Invalid repository id! $repId");
                }
                $metaSourceType = AJXP_Utils::sanitize($httpVars["new_meta_source"], AJXP_SANITIZE_ALPHANUM);
                if (isSet($httpVars["json_data"])) {
                    $options = json_decode(SystemTextEncoding::magicDequote($httpVars["json_data"]), true);
                } else {
                    $options = array();
                    $this->parseParameters($httpVars, $options, null, true);
                }
                $repoOptions = $repo->getOption("META_SOURCES");
                if (is_array($repoOptions) && isSet($repoOptions[$metaSourceType])) {
                    throw new Exception($mess["ajxp_conf.55"]);
                }
                if (!is_array($repoOptions)) {
                    $repoOptions = array();
                }
                $repoOptions[$metaSourceType] = $options;
                uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
                $repo->addOption("META_SOURCES", $repoOptions);
                ConfService::replaceRepository($repId, $repo);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.56"],null);
                AJXP_XMLWriter::close();
            break;

            case "meta_source_delete" :

                $repId = $httpVars["repository_id"];
                $repo = ConfService::getRepositoryById($repId);
                if (!is_object($repo)) {
                    throw new Exception("Invalid repository id! $repId");
                }
                $metaSourceId = $httpVars["plugId"];
                $repoOptions = $repo->getOption("META_SOURCES");
                if (is_array($repoOptions) && array_key_exists($metaSourceId, $repoOptions)) {
                    unset($repoOptions[$metaSourceId]);
                    uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
                    $repo->addOption("META_SOURCES", $repoOptions);
                    ConfService::replaceRepository($repId, $repo);
                }
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.57"],null);
                AJXP_XMLWriter::close();

            break;

            case "meta_source_edit" :
                $repId = $httpVars["repository_id"];
                $repo = ConfService::getRepositoryById($repId);
                if (!is_object($repo)) {
                    throw new Exception("Invalid repository id! $repId");
                }
                $metaSourceId = $httpVars["plugId"];
                $repoOptions = $repo->getOption("META_SOURCES");
                if (!is_array($repoOptions)) {
                    $repoOptions = array();
                }
                if (isSet($httpVars["json_data"])) {
                    $options = json_decode(SystemTextEncoding::magicDequote($httpVars["json_data"]), true);
                } else {
                    $options = array();
                    $this->parseParameters($httpVars, $options, null, true);
                }
                $repoOptions[$metaSourceId] = $options;
                uksort($repoOptions, array($this,"metaSourceOrderingFunction"));
                $repo->addOption("META_SOURCES", $repoOptions);
                ConfService::replaceRepository($repId, $repo);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.58"],null);
                AJXP_XMLWriter::close();
            break;


            case "delete" :
                // REST API mapping
                if (isSet($httpVars["data_type"])) {
                    switch ($httpVars["data_type"]) {
                        case "repository":
                            $httpVars["repository_id"] = basename($httpVars["data_id"]);
                            break;
                        case "shared_file":
                            $httpVars["shared_file"] = basename($httpVars["data_id"]);
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
                    $res = ConfService::deleteRepository($repId);
                    AJXP_XMLWriter::header();
                    if ($res == -1) {
                        AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.51"]);
                    } else {
                        AJXP_XMLWriter::sendMessage($mess["ajxp_conf.59"], null);
                        AJXP_XMLWriter::reloadDataNode();
                        AJXP_XMLWriter::reloadRepositoryList();
                    }
                    AJXP_XMLWriter::close();
                    return;
                } else if (isSet($httpVars["shared_file"])) {
                    AJXP_XMLWriter::header();
                    $element = basename($httpVars["shared_file"]);
                    $dlFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
                    $publicletData = $this->loadPublicletData($dlFolder."/".$element.".php");
                    unlink($dlFolder."/".$element.".php");
                    AJXP_XMLWriter::sendMessage($mess["ajxp_shared.13"], null);
                    AJXP_XMLWriter::reloadDataNode();
                    AJXP_XMLWriter::close();
                } else if (isSet($httpVars["role_id"])) {
                    $roleId = $httpVars["role_id"];
                    if (AuthService::getRole($roleId) === false) {
                        throw new Exception($mess["ajxp_conf.67"]);
                    }
                    AuthService::deleteRole($roleId);
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage($mess["ajxp_conf.68"], null);
                    AJXP_XMLWriter::reloadDataNode();
                    AJXP_XMLWriter::close();
                } else if (isSet($httpVars["group"])) {
                    $groupPath = $httpVars["group"];
                    $basePath = substr(AJXP_Utils::forwardSlashDirname($groupPath), strlen("/data/users"));
                    $gName = basename($groupPath);
                    AuthService::deleteGroup($basePath, $gName);
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage($mess["ajxp_conf.125"], null);
                    AJXP_XMLWriter::reloadDataNode();
                    AJXP_XMLWriter::close();
                } else {
                    if(!isset($httpVars["user_id"]) || $httpVars["user_id"]==""
                        || AuthService::isReservedUserId($httpVars["user_id"])
                        || $loggedUser->getId() == $httpVars["user_id"])
                    {
                        AJXP_XMLWriter::header();
                        AJXP_XMLWriter::sendMessage(null, $mess["ajxp_conf.61"]);
                        AJXP_XMLWriter::close();
                    }
                    $res = AuthService::deleteUser($httpVars["user_id"]);
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage($mess["ajxp_conf.60"], null);
                    AJXP_XMLWriter::reloadDataNode();
                    AJXP_XMLWriter::close();

                }
            break;

            case "clear_expired" :

                $deleted = $this->clearExpiredFiles();
                AJXP_XMLWriter::header();
                if (count($deleted)) {
                    AJXP_XMLWriter::sendMessage(sprintf($mess["ajxp_shared.23"], count($deleted).""), null);
                    AJXP_XMLWriter::reloadDataNode();
                } else {
                    AJXP_XMLWriter::sendMessage($mess["ajxp_shared.24"], null);
                }
                AJXP_XMLWriter::close();

            break;

            case "get_plugin_manifest" :

                $ajxpPlugin = AJXP_PluginsService::getInstance()->getPluginById($httpVars["plugin_id"]);
                AJXP_XMLWriter::header("admin_data");

                $fullManifest = $ajxpPlugin->getManifestRawContent("", "xml");
                $xPath = new DOMXPath($fullManifest->ownerDocument);
                $addParams = "";
                $pInstNodes = $xPath->query("server_settings/global_param[contains(@type, 'plugin_instance:')]");
                foreach ($pInstNodes as $pInstNode) {
                    $type = $pInstNode->getAttribute("type");
                    $instType = str_replace("plugin_instance:", "", $type);
                    $fieldName = $pInstNode->getAttribute("name");
                    $pInstNode->setAttribute("type", "group_switch:".$fieldName);
                    $typePlugs = AJXP_PluginsService::getInstance()->getPluginsByType($instType);
                    foreach ($typePlugs as $typePlug) {
                        if($typePlug->getId() == "auth.multi") continue;
                        $checkErrorMessage = "";
                        try {
                            $typePlug->performChecks();
                        } catch (Exception $e) {
                            $checkErrorMessage = " (Warning : ".$e->getMessage().")";
                        }
                        $tParams = AJXP_XMLWriter::replaceAjxpXmlKeywords($typePlug->getManifestRawContent("server_settings/param[not(@group_switch_name)]"));
                        $addParams .= '<global_param group_switch_name="'.$fieldName.'" name="instance_name" group_switch_label="'.$typePlug->getManifestLabel().$checkErrorMessage.'" group_switch_value="'.$typePlug->getId().'" default="'.$typePlug->getId().'" type="hidden"/>';
                        $addParams .= str_replace("<param", "<global_param group_switch_name=\"${fieldName}\" group_switch_label=\"".$typePlug->getManifestLabel().$checkErrorMessage."\" group_switch_value=\"".$typePlug->getId()."\" ", $tParams);
                        $addParams .= str_replace("<param", "<global_param", AJXP_XMLWriter::replaceAjxpXmlKeywords($typePlug->getManifestRawContent("server_settings/param[@group_switch_name]")));
                        $addParams .= AJXP_XMLWriter::replaceAjxpXmlKeywords($typePlug->getManifestRawContent("server_settings/global_param"));
                    }
                }
                $allParams = AJXP_XMLWriter::replaceAjxpXmlKeywords($fullManifest->ownerDocument->saveXML($fullManifest));
                $allParams = str_replace('type="plugin_instance:', 'type="group_switch:', $allParams);
                $allParams = str_replace("</server_settings>", $addParams."</server_settings>", $allParams);

                echo($allParams);
                $definitions = $ajxpPlugin->getConfigsDefinitions();
                $values = $ajxpPlugin->getConfigs();
                if(!is_array($values)) $values = array();
                echo("<plugin_settings_values>");
                foreach ($values as $key => $value) {
                    $attribute = true;
                    $type = $definitions[$key]["type"];
                    if ($type == "array" && is_array($value)) {
                        $value = implode(",", $value);
                    } else if ((strpos($type, "group_switch:") === 0 || strpos($type, "plugin_instance:") === 0 ) && is_array($value)) {
                        $res = array();
                        $this->flattenKeyValues($res, $value, $key);
                        foreach ($res as $newKey => $newVal) {
                            echo("<param name=\"$newKey\" value=\"".AJXP_Utils::xmlEntities($newVal)."\"/>");
                        }
                        continue;
                    } else if ($type == "boolean") {
                        $value = ($value === true || $value === "true" || $value == 1?"true":"false");
                    } else if ($type == "textarea") {
                        $attribute = false;
                    }
                    if ($attribute) {
                        echo("<param name=\"$key\" value=\"".AJXP_Utils::xmlEntities($value)."\"/>");
                    } else {
                        echo("<param name=\"$key\" cdatavalue=\"true\"><![CDATA[".$value."]]></param>");
                    }
                }
                if ($ajxpPlugin->getType() != "core") {
                    echo("<param name=\"AJXP_PLUGIN_ENABLED\" value=\"".($ajxpPlugin->isEnabled()?"true":"false")."\"/>");
                }
                echo("</plugin_settings_values>");
                echo("<plugin_doc><![CDATA[<p>".$ajxpPlugin->getPluginInformationHTML("Charles du Jeu", "http://pyd.io/plugins/")."</p>");
                if (file_exists($ajxpPlugin->getBaseDir()."/plugin_doc.html")) {
                    echo(file_get_contents($ajxpPlugin->getBaseDir()."/plugin_doc.html"));
                }
                echo("]]></plugin_doc>");
                AJXP_XMLWriter::close("admin_data");

            break;

            case "run_plugin_action":

                $options = array();
                $this->parseParameters($httpVars, $options, null, true);
                $pluginId = $httpVars["action_plugin_id"];
                if (isSet($httpVars["button_key"])) {
                    $options = $options[$httpVars["button_key"]];
                }
                $plugin = AJXP_PluginsService::getInstance()->softLoad($pluginId, $options);
                if (method_exists($plugin, $httpVars["action_plugin_method"])) {
                    try {
                        $res = call_user_func(array($plugin, $httpVars["action_plugin_method"]), $options);
                    } catch (Exception $e) {
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
                $this->parseParameters($httpVars, $options, null, true);
                $confStorage = ConfService::getConfStorageImpl();
                $confStorage->savePluginConfig($httpVars["plugin_id"], $options);
                @unlink(AJXP_PLUGINS_CACHE_FILE);
                @unlink(AJXP_PLUGINS_REQUIRES_FILE);
                @unlink(AJXP_PLUGINS_MESSAGES_FILE);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["ajxp_conf.97"], null);
                AJXP_XMLWriter::close();


            break;

            case "generate_api_docs":

                PydioSdkGenerator::analyzeRegistry();

            break;

            default:
            break;
        }

        return;
    }


    public function listPlugins($dir, $root = NULL, $hash = null, $returnNodes = false)
    {
        $dir = "/$dir";
        $allNodes = array();
        $this->logInfo("Listing plugins",""); // make sure that the logger is started!
        $pServ = AJXP_PluginsService::getInstance();
        $types = $pServ->getDetectedPlugins();
        $uniqTypes = array("core");
        $coreTypes = array("auth", "conf", "boot", "feed", "log", "mailer", "mq");
        if ($dir == "/plugins" || $dir == "/core_plugins") {
            if($dir == "/core_plugins") $uniqTypes = $coreTypes;
            else $uniqTypes = array_diff(array_keys($types), $coreTypes);
            if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" template_name="ajxp_conf.plugins_folder">
            <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String"/>
            </columns>');
            ksort($types);
            foreach ($types as $t => $tPlugs) {
                if(!in_array($t, $uniqTypes))continue;
                if($t == "core") continue;
                $nodeKey = "/".$root.$dir."/".$t;
                $meta = array(
                    "icon" 		=> "folder_development.png",
                    "plugin_id" => $t
                );
                if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                $xml = AJXP_XMLWriter::renderNode($nodeKey, ucfirst($t), false, $meta, true, false);
                if($returnNodes) $allNodes[$nodeKey] = $xml;
                else print($xml);
            }
        } else if ($dir == "/core") {
            if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" switchDisplayMode="detail"  template_name="ajxp_conf.plugins">
            <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String"/>
            <column messageId="ajxp_conf.102" attributeName="plugin_id" sortType="String"/>
            <column messageId="ajxp_conf.103" attributeName="plugin_description" sortType="String"/>
            </columns>');
            $mess = ConfService::getMessages();
            $all =  $first = "";
            foreach ($uniqTypes as $type) {
                if(!isset($types[$type])) continue;
                foreach ($types[$type] as $pId => $pObject) {
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
                    $nodeKey = "/$root".$dir."/".$pObject->getId();
                    if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                    $nodeString =AJXP_XMLWriter::renderNode($nodeKey, $label, true, $meta, true, false);
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
            if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" switchDisplayMode="full" template_name="ajxp_conf.plugin_detail">
            <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String" defaultWidth="10%"/>
            <column messageId="ajxp_conf.102" attributeName="plugin_id" sortType="String" defaultWidth="10%"/>
            <column messageId="ajxp_conf.103" attributeName="plugin_description" sortType="String" defaultWidth="60%"/>
            <column messageId="ajxp_conf.104" attributeName="enabled" sortType="String" defaultWidth="10%"/>
            <column messageId="ajxp_conf.105" attributeName="can_active" sortType="String" defaultWidth="10%"/>
            </columns>');
            $mess = ConfService::getMessages();
            foreach ($types[$type] as $pId => $pObject) {
                $errors = "OK";
                try {
                    $pObject->performChecks();
                } catch (Exception $e) {
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
                $nodeKey = "/$root".$dir."/".$pObject->getId();
                if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                $xml = AJXP_XMLWriter::renderNode($nodeKey, $pObject->getManifestLabel(), true, $meta, true, false);
                if($returnNodes) $allNodes[$nodeKey] = $xml;
                else print $xml;
            }
        }
        return $allNodes;
    }

    public function listUsers($root, $child, $hashValue = null, $returnNodes = false, $findNodePosition=null)
    {
        $USER_PER_PAGE = 50;
        if($root == "users") $baseGroup = "/";
        else $baseGroup = substr($root, strlen("users"));

        if ($findNodePosition != null && $hashValue == null) {

            // Loop on each page to find the correct page.
            $count = AuthService::authCountUsers($baseGroup);
            $pages = ceil($count / $USER_PER_PAGE);
            for ($i = 0; $i < $pages ; $i ++) {

                $tests = $this->listUsers($root, $child, $i+1, true, $findNodePosition);
                if (is_array($tests) && isSet($tests["/data/".$root."/".$findNodePosition])) {
                    return array("/data/".$root."/".$findNodePosition => str_replace("ajxp_mime", "page_position='".($i+1)."' ajxp_mime", $tests["/data/".$root."/".$findNodePosition]));
                }

            }

            return array();

        }

        $allNodes = array();
        $columns = '<columns switchDisplayMode="list" switchGridMode="filelist" template_name="ajxp_conf.users">
                    <column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String" defaultWidth="40%"/>
                    <column messageId="ajxp_conf.7" attributeName="isAdmin" sortType="String" defaultWidth="10%"/>
                    <column messageId="ajxp_conf.70" attributeName="ajxp_roles" sortType="String" defaultWidth="15%"/>
                    <column messageId="ajxp_conf.62" attributeName="rights_summary" sortType="String" defaultWidth="15%"/>
                    </columns>';
        if (AuthService::driverSupportsAuthSchemes()) {
            $columns = '<columns switchDisplayMode="list" switchGridMode="filelist" template_name="ajxp_conf.users_authscheme">
                        <column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String" defaultWidth="40%"/>
                        <column messageId="ajxp_conf.115" attributeName="auth_scheme" sortType="String" defaultWidth="5%"/>
                        <column messageId="ajxp_conf.7" attributeName="isAdmin" sortType="String" defaultWidth="5%"/>
                        <column messageId="ajxp_conf.70" attributeName="ajxp_roles" sortType="String" defaultWidth="15%"/>
                        <column messageId="ajxp_conf.62" attributeName="rights_summary" sortType="String" defaultWidth="15%"/>
            </columns>';
        }
        if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig($columns);
        if(!AuthService::usersEnabled()) return ;
        if(empty($hashValue)) $hashValue = 1;

        $count = AuthService::authCountUsers($baseGroup);
        if (AuthService::authSupportsPagination() && $count >= $USER_PER_PAGE) {
            $offset = ($hashValue - 1) * $USER_PER_PAGE;
            if(!$returnNodes) AJXP_XMLWriter::renderPaginationData($count, $hashValue, ceil($count/$USER_PER_PAGE));
            $users = AuthService::listUsers($baseGroup, "", $offset, $USER_PER_PAGE);
            if ($hashValue == 1) {
                $groups = AuthService::listChildrenGroups($baseGroup);
            } else {
                $groups = array();
            }
        } else {
            $users = AuthService::listUsers($baseGroup);
            $groups = AuthService::listChildrenGroups($baseGroup);
        }
        foreach ($groups as $groupId => $groupLabel) {

            $nodeKey = "/data/".$root."/".ltrim($groupId,"/");
            $meta = array(
                "icon" => "users-folder.png",
                "ajxp_mime" => "group"
            );
            if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            $xml = AJXP_XMLWriter::renderNode($nodeKey,
                $groupLabel, false, $meta, true, false);
            if(!$returnNodes) print($xml);
            else $allNodes[$nodeKey] = $xml;

        }
        $mess = ConfService::getMessages();
        $repos = ConfService::getRepositoriesList("all");
        $loggedUser = AuthService::getLoggedUser();
        $userArray = array();
        foreach ($users as $userIndex => $userObject) {
            $label = $userObject->getId();
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
        ksort($userArray);
        foreach ($userArray as $userObject) {
            $isAdmin = $userObject->isAdmin();
            $userId = $userObject->getId();
            $icon = "user".($userId=="guest"?"_guest":($isAdmin?"_admin":""));
            if ($userObject->hasParent()) {
                $icon = "user_child";
            }
            $rightsString = "";
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
            $nodeLabel = $userId;
            $test = $userObject->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
            if(!empty($test)) $nodeLabel = $test;
            $scheme = AuthService::getAuthScheme($userId);
            $nodeKey = "/data/$root/".$userId;
            $roles = array_filter(array_keys($userObject->getRoles()), array($this, "filterReservedRoles"));
            $meta = array(
                "isAdmin" => $mess[($isAdmin?"ajxp_conf.14":"ajxp_conf.15")],
                "icon" => $icon.".png",
                "auth_scheme" => ($scheme != null? $scheme : ""),
                "rights_summary" => $rightsString,
                "ajxp_roles" => implode(", ", $roles),
                "ajxp_mime" => "user".(($userId!="guest"&&$userId!=$loggedUser->getId())?"_editable":"")
            );
            if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            $xml = AJXP_XMLWriter::renderNode($nodeKey, $nodeLabel, true, $meta, true, false);
            if(!$returnNodes) print($xml);
            else $allNodes[$nodeKey] = $xml;
        }
        return $allNodes;
    }

    public function listRoles($root, $child, $hashValue = null, $returnNodes = false)
    {
        $allNodes = array();
        if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" template_name="ajxp_conf.roles">
            <column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String"/>
            <column messageId="ajxp_conf.114" attributeName="is_default" sortType="String"/>
            <column messageId="ajxp_conf.62" attributeName="rights_summary" sortType="String"/>
            </columns>');
        if(!AuthService::usersEnabled()) return ;
        $roles = AuthService::getRolesList(array(), !$this->listSpecialRoles);
        $mess = ConfService::getMessages();
        $repos = ConfService::getRepositoriesList("all");
        ksort($roles);
        foreach ($roles as $roleId => $roleObject) {
            //if(strpos($roleId, "AJXP_GRP_") === 0 && !$this->listSpecialRoles) continue;
            $r = array();
            if(!AuthService::canAdministrate($roleObject)) continue;
            foreach ($repos as $repoId => $repository) {
                if($repository->getAccessType() == "ajxp_shared") continue;
                if(!$roleObject->canRead($repoId) && !$roleObject->canWrite($repoId)) continue;
                $rs = ($roleObject->canRead($repoId) ? "r" : "");
                $rs .= ($roleObject->canWrite($repoId) ? "w" : "");
                $r[] = $repository->getDisplay()." (".$rs.")";
            }
            $rightsString = implode(", ", $r);
            $nodeKey = "/data/roles/".$roleId;
            $meta = array(
                "icon" => "user-acl.png",
                "rights_summary" => $rightsString,
                "is_default"    => implode(",", $roleObject->listAutoApplies()), //($roleObject->autoAppliesTo("standard") ? $mess[440]:$mess[441]),
                "ajxp_mime" => "role",
                "text"      => $roleObject->getLabel()
            );
            if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            $xml = AJXP_XMLWriter::renderNode($nodeKey, $roleId, true, $meta, true, false);
            if(!$returnNodes) echo $xml;
            else $allNodes[$nodeKey] = $xml;
        }
        return $allNodes;
    }

    public function repositoryExists($name)
    {
        $repos = ConfService::getRepositoriesList();
        foreach ($repos as $obj)
            if ($obj->getDisplay() == $name) return true;

        return false;
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

    public function listRepositories($root, $child, $hashValue = null, $returnNodes = false)
    {
        $repos = ConfService::getRepositoriesList("all");
        if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="list" switchGridMode="filelist" template_name="ajxp_conf.repositories">
            <column messageId="ajxp_conf.8" attributeName="ajxp_label" sortType="String"/>
            <column messageId="ajxp_conf.9" attributeName="accessType" sortType="String"/>
            <column messageId="ajxp_shared.27" attributeName="owner" sortType="String"/>
            <column messageId="ajxp_conf.106" attributeName="repository_id" sortType="String"/>
            </columns>');
        $repoArray = array();
        $childRepos = array();
        $templateRepos = array();
        $flatChildrenRepos = array();
        $allNodes = array();
        //uasort($repos, array($this, "sortReposByLabel"));
        foreach ($repos as $repoIndex => $repoObject) {
            if (!AuthService::canAdministrate($repoObject)) {
                continue;
            }
            if($repoObject->getAccessType() == "ajxp_conf" || $repoObject->getAccessType() == "ajxp_shared") continue;
            if(is_numeric($repoIndex)) $repoIndex = "".$repoIndex;
            $name = AJXP_Utils::xmlEntities(SystemTextEncoding::toUTF8($repoObject->getDisplay()));
            if ($repoObject->hasOwner() || $repoObject->hasParent()) {
                $parentId = $repoObject->getParentId();
                if (isSet($repos[$parentId]) && AuthService::canAdministrate($repos[$parentId])) {
                    if(!isSet($childRepos[$parentId])) $childRepos[$parentId] = array();
                    $childRepos[$parentId][] = array("name" => $name, "index" => $repoIndex);
                    $flatChildrenRepos[] = $repoIndex;
                    continue;
                }
            }
            if ($repoObject->isTemplate) {
                $templateRepos[$name] = $repoIndex;
            } else {
                $repoArray[$name] = $repoIndex;
            }
        }
        // Sort the list now by name
        ksort($templateRepos);
        ksort($repoArray);
        $repoArray = array_merge($templateRepos, $repoArray);
        // Append child repositories
        $sortedArray = array();
        foreach ($repoArray as $name => $repoIndex) {
            $sortedArray[$name] = $repoIndex;
            if (isSet($childRepos[$repoIndex]) && is_array($childRepos[$repoIndex])) {
                foreach ($childRepos[$repoIndex] as $childData) {
                    $sortedArray[$childData["name"]] = $childData["index"];
                }
            }
        }
        foreach ($sortedArray as $name => $repoIndex) {
            $repoObject =& $repos[$repoIndex];
            $icon = (in_array($repoIndex, $flatChildrenRepos)?"repo_child.png":"hdd_external_unmount.png");
            $editable = $repoObject->isWriteable();
            if ($repoObject->isTemplate) {
                $icon = "hdd_external_mount.png";
                if (AuthService::getLoggedUser() != null && AuthService::getLoggedUser()->getGroupPath() != "/") {
                    $editable = false;
                }
            }
            $meta = array(
                "repository_id" => $repoIndex,
                "accessType"	=> ($repoObject->isTemplate?"Template for ":"").$repoObject->getAccessType(),
                "icon"			=> $icon,
                "owner"			=> ($repoObject->hasOwner()?$repoObject->getOwner():""),
                "openicon"		=> $icon,
                "parentname"	=> "/repositories",
                "ajxp_mime" 	=> "repository".($editable?"_editable":"")
            );
            $nodeKey = "/data/repositories/$repoIndex";
            if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
            $xml = AJXP_XMLWriter::renderNode($nodeKey, $name, true, $meta, true, false);
            if($returnNodes) $allNodes[$nodeKey] = $xml;
            else print($xml);
        }
        return $allNodes;
    }

    public function listActions($dir, $root = NULL, $hash = null, $returnNodes = false)
    {
        $allNodes = array();
        $parts = explode("/",$dir);
        $pServ = AJXP_PluginsService::getInstance();
        $activePlugins = $pServ->getActivePlugins();
        $types = $pServ->getDetectedPlugins();
        if (count($parts) == 1) {
            // list all types
            if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" template_name="ajxp_conf.plugins_folder">
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
                $xml = AJXP_XMLWriter::renderNode($nodeKey, ucfirst($t), false, $meta, true, false);
                if($returnNodes) $allNodes[$nodeKey] = $xml;
                else print($xml);
            }

        } else if (count($parts) == 2) {
            // list plugs
            $type = $parts[1];
            if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="detail" template_name="ajxp_conf.plugins_folder">
                <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String"/>
                <column messageId="ajxp_conf.103" attributeName="actions" sortType="String"/>
            </columns>');
            $pObject = new AJXP_Plugin("","");
            foreach ($types[$type] as $pId => $pObject) {
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
                $xml = AJXP_XMLWriter::renderNode($nodeKey, $pObject->getManifestLabel(), false, $meta, true, false);
                if($returnNodes) $allNodes[$nodeKey] = $xml;
                else print($xml);

            }

        } else if (count($parts) == 3) {
            // list actions
            $type = $parts[1];
            $name = $parts[2];
            $mess = ConfService::getMessages();
            if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="full" template_name="ajxp_conf.plugins_folder">
                <column messageId="ajxp_conf.101" attributeName="ajxp_label" sortType="String" defaultWidth="10%"/>
                <column messageId="ajxp_conf.103" attributeName="parameters" sortType="String" fixedWidth="30%"/>
            </columns>');
            $pObject = new AJXP_Plugin("","");
            $pObject = $types[$type][$name];

            $actions = $pObject->getManifestRawContent("//action", "xml", true);
            $allNodesAcc = array();
            if ($actions->length) {
                foreach ($actions as $node) {
                    $xPath = new DOMXPath($node->ownerDocument);
                    $callbacks = $xPath->query("processing/serverCallback", $node);
                    if(!$callbacks->length) continue;
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
                        foreach ($params as $param) {
                            $paramLabel[]= '. ['.$param->getAttribute("type").'] <b>'.$param->getAttribute("name").($param->getAttribute("mandatory") == "true" ? '*':'').'</b> : '.$param->getAttribute("description");
                        }
                    }
                    $parameters = "";
                    $meta = array(
                        "icon" 		=> "preferences_plugin.png",
                        "action_id" => $actName,
                        "parameters"=> '<div class="developerDoc">'.implode("<br/>", $paramLabel).'</div>',
                        "rest_params"=> $restPath
                    );
                    $nodeKey = "/$root/actions/$type/".$pObject->getName()."/$actName";
                    if(in_array($nodeKey, $this->currentBookmarks)) $meta = array_merge($meta, array("ajxp_bookmarked" => "true", "overlay_icon" => "bookmark.png"));
                    $allNodes[$nodeKey] = $allNodesAcc[$actName] = AJXP_XMLWriter::renderNode(
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

    public function listHooks($dir, $root = NULL, $hash = null, $returnNodes = false)
    {
        $jsonContent = json_decode(file_get_contents(AJXP_Utils::getHooksFile()), true);
        $config = '<columns switchDisplayMode="full" template_name="hooks.list">
                <column messageId="ajxp_conf.17" attributeName="ajxp_label" sortType="String" defaultWidth="20%"/>
                <column messageId="ajxp_conf.18" attributeName="description" sortType="String" defaultWidth="20%"/>
                <column messageId="ajxp_conf.19" attributeName="triggers" sortType="String" defaultWidth="25%"/>
                <column messageId="ajxp_conf.20" attributeName="listeners" sortType="String" defaultWidth="25%"/>
                <column messageId="ajxp_conf.21" attributeName="sample" sortType="String" defaultWidth="10%"/>
            </columns>';
        if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig($config);
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
            $xml = AJXP_XMLWriter::renderNode($nodeKey, $hookName, true, $metadata, true, false);
            if($returnNodes) $allNodes[$nodeKey] = $xml;
            else print($xml);
        }
        return $allNodes;
    }

    public function listLogFiles($dir, $root = NULL, $hash = null, $returnNodes = false)
    {
        $dir = "/$dir";
        $allNodes = array();
        $logger = AJXP_Logger::getInstance();
        $parts = explode("/", $dir);
        if (count($parts)>4) {
            $config = '<columns switchDisplayMode="list" switchGridMode="grid" template_name="ajxp_conf.logs">
                <column messageId="ajxp_conf.17" attributeName="date" sortType="MyDate" defaultWidth="10%"/>
                <column messageId="ajxp_conf.18" attributeName="ip" sortType="String" defaultWidth="10%"/>
                <column messageId="ajxp_conf.19" attributeName="level" sortType="String" defaultWidth="10%"/>
                <column messageId="ajxp_conf.20" attributeName="user" sortType="String" defaultWidth="10%"/>
                <column messageId="ajxp_conf.21" attributeName="action" sortType="String" defaultWidth="10%"/>
                <column messageId="ajxp_conf.22" attributeName="params" sortType="String" defaultWidth="50%"/>
            </columns>';
            if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig($config);
            $date = $parts[count($parts)-1];
            $logger->xmlLogs($dir, $date, "tree", "/".$root."/logs");
        } else {
            if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.16" attributeName="ajxp_label" sortType="String"/></columns>');
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

    public function printDiagnostic($dir, $root = NULL, $hash = null, $returnNodes = false)
    {
        $outputArray = array();
        $testedParams = array();
        $allNodes = array();
        $passed = AJXP_Utils::runTests($outputArray, $testedParams);
        AJXP_Utils::testResultsToFile($outputArray, $testedParams);
        if(!$returnNodes) AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="list" switchGridMode="fileList" template_name="ajxp_conf.diagnostic" defaultWidth="20%"><column messageId="ajxp_conf.23" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_conf.24" attributeName="data" sortType="String"/></columns>');
        if (is_file(TESTS_RESULT_FILE)) {
            include_once(TESTS_RESULT_FILE);
            if (isset($diagResults)) {
                foreach ($diagResults as $id => $value) {
                    $value = AJXP_Utils::xmlEntities($value);
                    $xml =  "<tree icon=\"susehelpcenter.png\" is_file=\"1\" filename=\"/$dir/$id\" text=\"$id\" data=\"$value\" ajxp_mime=\"testResult\"/>";
                    if(!$returnNodes) print($xml);
                    else $allNodes["/$dir/$id"] = $xml;
                }
            }
        }
        return $allNodes;
    }

    public function listSharedFiles()
    {
        AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" template_name="ajxp_conf.shared">
                <column messageId="ajxp_shared.4" attributeName="ajxp_label" sortType="String" defaultWidth="30%"/>
                <column messageId="ajxp_shared.27" attributeName="owner" sortType="String" defaultWidth="10%"/>
                <column messageId="ajxp_shared.17" attributeName="download_url" sortType="String" defaultWidth="40%"/>
                <column messageId="ajxp_shared.6" attributeName="password" sortType="String" defaultWidth="4%"/>
                <column messageId="ajxp_shared.7" attributeName="expiration" sortType="String" defaultWidth="4%"/>
                <column messageId="ajxp_shared.20" attributeName="expired" sortType="String" defaultWidth="4%"/>
                <column messageId="ajxp_shared.14" attributeName="integrity" sortType="String" defaultWidth="4%" hidden="true"/>
            </columns>');
        $dlFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        if(!is_dir($dlFolder)) return ;
        $files = glob($dlFolder."/*.php");
        if($files === false) return ;
        $mess = ConfService::getMessages();
        $loggedUser = AuthService::getLoggedUser();
        $userId = $loggedUser->getId();
        $dlURL = ConfService::getCoreConf("PUBLIC_DOWNLOAD_URL");
        if ($dlURL!= "") {
            $downloadBase = rtrim($dlURL, "/");
        } else {
            $fullUrl = AJXP_Utils::detectServerURL() . dirname($_SERVER['REQUEST_URI']);
            $downloadBase = str_replace("\\", "/", $fullUrl.rtrim(str_replace(AJXP_INSTALL_PATH, "", $dlFolder), "/"));
        }

        foreach ($files as $file) {
            $publicletData = $this->loadPublicletData($file);
            if (!is_a($publicletData["REPOSITORY"], "Repository")) {
                continue;
            }
            AJXP_XMLWriter::renderNode(str_replace(".php", "", basename($file)), "".SystemTextEncoding::toUTF8($publicletData["REPOSITORY"]->getDisplay()).":/".SystemTextEncoding::toUTF8($publicletData["FILE_PATH"]), true, array(
                "icon"		=> "html.png",
                "password" => ($publicletData["PASSWORD"]!=""?$publicletData["PASSWORD"]:"-"),
                "expiration" => ($publicletData["EXPIRE_TIME"]!=0?date($mess["date_format"], $publicletData["EXPIRE_TIME"]):"-"),
                "expired" => ($publicletData["EXPIRE_TIME"]!=0?($publicletData["EXPIRE_TIME"]<time()?$mess["ajxp_shared.21"]:$mess["ajxp_shared.22"]):"-"),
                "integrity"  => (!$publicletData["SECURITY_MODIFIED"]?$mess["ajxp_shared.15"]:$mess["ajxp_shared.16"]),
                "download_url" => $downloadBase . "/".basename($file),
                "owner" => (isset($publicletData["OWNER_ID"])?$publicletData["OWNER_ID"]:"-"),
                "ajxp_mime" => "shared_file")
            );
        }
    }

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

    public function clearExpiredFiles()
    {
        $files = glob(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")."/*.php");
        $loggedUser = AuthService::getLoggedUser();
        $userId = $loggedUser->getId();
        $deleted = array();
        foreach ($files as $file) {
            $publicletData = $this->loadPublicletData($file);
            if (isSet($publicletData["EXPIRATION_TIME"]) && is_numeric($publicletData["EXPIRATION_TIME"]) && $publicletData["EXPIRATION_TIME"] > 0 && $publicletData["EXPIRATION_TIME"] < time()) {
                unlink($file);
                $deleted[] = basename($file);
            }
        }
        return $deleted;
    }

    protected function loadPublicletData($file)
    {
        $inputData = null;
        $lines = file($file);
        $id = str_replace(".php", "", basename($file));
        $code = trim($lines[3] . $lines[4] . $lines[5]);
        if(strpos($code, '$cypheredData =') !== 0) return null;
        eval($code);
        $dataModified = !ShareCenter::checkHash($inputData, $id);
        $publicletData = unserialize($inputData);
        if(!is_array($publicletData)) return null;
        $publicletData["SECURITY_MODIFIED"] = $dataModified;
        return $publicletData;
    }

    public function updateUserRole($userId, $roleId, $addOrRemove, $updateSubUsers = false)
    {
        $confStorage = ConfService::getConfStorageImpl();
        $user = $confStorage->createUserObject($userId);
        if(!AuthService::canAdministrate($user)){
            throw new Exception("Cannot update user data for ".$userId);
        }
        if ($addOrRemove == "add") {
            $roleObject = AuthService::getRole($roleId);
            $user->addRole($roleObject);
        } else {
            $user->removeRole($roleId);
        }
        $user->save("superuser");
        $loggedUser = AuthService::getLoggedUser();
        if ($loggedUser->getId() == $user->getId()) {
            AuthService::updateUser($user);
        }
        return $user;

    }


    public function parseParameters(&$repDef, &$options, $userId = null, $globalBinaries = false)
    {
        AJXP_Utils::parseStandardFormParameters($repDef, $options, $userId, "DRIVER_OPTION_", ($globalBinaries?array():null));

    }

    public function flattenKeyValues(&$result, $values, $parent = "")
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $this->flattenKeyValues($result, $value, $parent."/".$key);
            } else {
                if ($key == "group_switch_value" || $key == "instance_name") {
                    $result[$parent] = $value;
                } else {
                    $result[$parent.'/'.$key] = $value;
                }
            }
        }
    }

}
