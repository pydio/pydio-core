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
 * @class ajxpSharedAccessDriver
 * AJXP_Plugin to access the shared elements of the current user
 */
class UserDashboardDriver extends AbstractAccessDriver
{

    public function initRepository()
    {
        require_once AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/class.ShareCenter.php";
    }

    public function parseSpecificContributions(&$contribNode){
        $disableAddressBook = $this->getFilteredOption("DASH_DISABLE_ADDRESS_BOOK") === true;
        if($contribNode->nodeName == "client_configs" && $disableAddressBook){
            // remove template_part for orbit_content
            $xPath=new DOMXPath($contribNode->ownerDocument);
            $tplNodeList = $xPath->query('component_config[@className="AjxpTabulator::userdashboard_main_tab"]', $contribNode);
            if(!$tplNodeList->length) return ;
            $contribNode->removeChild($tplNodeList->item(0));
        }
        parent::parseSpecificContributions($contribNode);
    }

    public function switchAction($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return;
        parent::accessPreprocess($action, $httpVars, $fileVars);
        $loggedUser = AuthService::getLoggedUser();
        if(!AuthService::usersEnabled()) return ;

        if ($action == "edit") {
            if (isSet($httpVars["sub_action"])) {
                $action = $httpVars["sub_action"];
            }
        }
        $mess = ConfService::getMessages();

        switch ($action) {
            //------------------------------------
            //	BASIC LISTING
            //------------------------------------
            case "ls":
                $rootNodes = array(
                    "users" => array(
                        "LABEL" => $mess["user_dash.1"],
                        "ICON" => "user_shared.png",
                        "ICON-CLASS" => "icon-book",
                        "DESCRIPTION" => $mess["user_dash.30"]
                    ),
                    "files" => array(
                        "LABEL" => $mess["user_dash.34"],
                        "ICON" => "user_shared.png",
                        "ICON-CLASS" => "icon-share",
                        "DESCRIPTION" => $mess["user_dash.35"]
                    ),
                    "settings" => array(
                        "LABEL" => $mess["user_dash.36"],
                        "ICON" => "user_shared.png",
                        "ICON-CLASS" => "icon-cog",
                        "DESCRIPTION" => $mess["user_dash.37"]
                    ),
                    "repositories" => array(
                        "LABEL" => $mess["user_dash.36"],
                        "ICON" => "user_shared.png",
                        "ICON-CLASS" => "icon-cog",
                        "DESCRIPTION" => $mess["user_dash.37"]
                    ),
                    "teams" => array(
                        "LABEL" => "Teams",
                        "ICON" => "user_shared.png",
                        "ICON-CLASS" => "icon-group",
                        "DESCRIPTION" => "My Teams"
                    )
                );
                $dir = (isset($httpVars["dir"])?$httpVars["dir"]:"");
                $splits = explode("/", $dir);
                if (count($splits)) {
                    if($splits[0] == "") array_shift($splits);
                    if(count($splits)) $strippedDir = strtolower(urldecode($splits[0]));
                    else $strippedDir = "";
                }
                if (array_key_exists($strippedDir, $rootNodes)) {
                    AJXP_XMLWriter::header();
                    if ($strippedDir == "users") {
                        $this->listUsers();
                    } else if ($strippedDir == "teams") {
                        $this->listTeams();
                    } else if ($strippedDir == "repositories") {
                        $this->listRepositories();
                    } else if ($strippedDir == "files") {
                        $this->listSharedFiles("files");
                    }
                    AJXP_XMLWriter::close();
                } else {
                    AJXP_XMLWriter::header();
                    /*
                    AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="user_dash.8" attributeName="ajxp_label" sortType="String"/><column messageId="user_dash.31" attributeName="description" sortType="String"/></columns>');
                    foreach ($rootNodes as $key => $data) {
                        $l = $data["LABEL"];
                        print '<tree text="'.$l.'" icon="'.$data["ICON"].'" filename="/'.$key.'" parentname="/" description="'.$data["DESCRIPTION"].'" />';
                    }
                    */
                    AJXP_XMLWriter::close();
                }
            break;

            case "stat" :

                header("Content-type:application/json");
                print '{"mode":true}';

            break;

            case "delete" :
                $mime = $httpVars["ajxp_mime"];
                $selection = new UserSelection();
                $selection->initFromHttpVars($httpVars);
                $files = $selection->getFiles();
                AJXP_XMLWriter::header();
                $minisites = $this->listSharedFiles("minisites");
                $shareCenter = AJXP_PluginsService::findPluginById("action.share");
                foreach ($files as $index => $element) {
                    $element = basename($element);
                    $ar = explode("shared_", $mime);
                    $mime = array_pop($ar);
                    if($mime == "repository" && isSet($minisites[$element])){
                        $mime = "minisite";
                        $element = $minisites[$element];
                    }
                    $shareCenter->deleteSharedElement($mime, $element, $loggedUser);
                    if($mime == "repository" || $mime == "minisite") $out = $mess["ajxp_conf.59"];
                    else if($mime == "user") $out = $mess["ajxp_conf.60"];
                    else if($mime == "file") $out = $mess["user_dash.13"];
                }
                AJXP_XMLWriter::sendMessage($out, null);
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::close();
            break;

            case "clear_expired" :

                $shareCenter = AJXP_PluginsService::getInstance()->findPluginById("action.share");
                $deleted = $shareCenter->clearExpiredFiles(true);
                AJXP_XMLWriter::header();
                if (count($deleted)) {
                    AJXP_XMLWriter::sendMessage(sprintf($mess["user_dash.23"], count($deleted).""), null);
                    AJXP_XMLWriter::reloadDataNode();
                } else {
                    AJXP_XMLWriter::sendMessage($mess["user_dash.24"], null);
                }
                AJXP_XMLWriter::close();

            break;

            case "reset_download_counter" :

                $selection = new UserSelection();
                $selection->initFromHttpVars($httpVars);
                $elements = $selection->getFiles();
                foreach ($elements as $element) {
                    PublicletCounter::reset(str_replace(".php", "", basename($element)));
                }
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::close();

            break;

            default:
            break;
        }

        return;
    }

    /**
     * @param string $mode
     * @return array|void
     */
    public function listSharedFiles($mode = "files")
    {
        if($mode == "files"){
            AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="list" switchGridMode="filelist">
                <column messageId="user_dash.4" attributeName="ajxp_label" sortType="String" width="20%"/>
                <column messageId="user_dash.17" attributeName="download_url" sortType="String" width="20%"/>
                <column messageId="user_dash.20" attributeName="download_count" sortType="String" width="2%"/>
                <column messageId="share_center.22" attributeName="download_limit" sortType="String" width="2%"/>
                <column messageId="user_dash.6" attributeName="password" sortType="String" width="5%"/>
                <column messageId="user_dash.7" attributeName="expiration" sortType="String" width="5%"/>
            </columns>');
        }

        $mess = ConfService::getMessages();
        $loggedUser = AuthService::getLoggedUser();
        $userId = $loggedUser->getId();

        $shareCenter = AJXP_PluginsService::getInstance()->findPluginById("action.share");
        $downloadBase = $shareCenter->buildPublicDlURL();
        $publicLets = $shareCenter->listShares(true, null);

        $minisites = array();
        foreach ($publicLets as $hash => $publicletData) {
            if($mode == "files"){
                if(isSet($publicletData["AJXP_APPLICATION_BASE"]) || isSet($publicletData["TRAVEL_PATH_TO_ROOT"])
                    ||  (isset($publicletData["OWNER_ID"]) && $publicletData["OWNER_ID"] != $userId)
                    || empty($publicletData["FILE_PATH"])
                    ){
                    continue;
                }
                $expired = ($publicletData["EXPIRE_TIME"]!=0?($publicletData["EXPIRE_TIME"]<time()?true:false):false);
                if(!is_a($publicletData["REPOSITORY"], "Repository")) continue;
                AJXP_XMLWriter::renderNode($hash, "".SystemTextEncoding::toUTF8($publicletData["REPOSITORY"]->getDisplay()).":/".SystemTextEncoding::toUTF8($publicletData["FILE_PATH"]), true, array(
                        "icon"		=> "html.png",
                        "password" => ($publicletData["PASSWORD"]!=""?$this->metaIcon("key").$publicletData["PASSWORD"]:""),
                        "expiration" => ($publicletData["EXPIRE_TIME"]!=0?($expired?$this->metaIcon("time"):"").date($mess["date_format"], $publicletData["EXPIRE_TIME"]):""),
                        "download_count" => !empty($publicletData["DOWNLOAD_COUNT"])?$this->metaIcon("download-alt").$publicletData["DOWNLOAD_COUNT"]:"",
                        "download_limit" => ($publicletData["DOWNLOAD_LIMIT"] == 0 ? "" : $this->metaIcon("cloud-download").$publicletData["DOWNLOAD_LIMIT"] ),
                        "download_url" => $this->metaIcon("link").$downloadBase . "/".$hash,
                        "ajxp_mime" => "shared_file")
                );
            }else if($mode == "minisites"){
                if(!isSet($publicletData["AJXP_APPLICATION_BASE"]) && !isSet($publicletData["TRAVEL_PATH_TO_ROOT"])) continue;
                $minisites[$publicletData["REPOSITORY"]] = $hash;
            }
        }
        if($mode == "minisites"){
            return $minisites;
        }
    }

    private function metaIcon($metaIcon)
    {
        return "<span class='icon-".$metaIcon." meta-icon'></span> ";
    }

    public function listTeams()
    {
        $conf = ConfService::getConfStorageImpl();
        if(!method_exists($conf, "listUserTeams")) return;
        AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="detail" switchGridMode="filelist">
            <column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String"/>
            <column messageId="user_dash.10" attributeName="users" sortType="String"/>
        </columns>');
        $teams = $conf->listUserTeams();
        foreach ($teams as $teamId => $team) {
            if(empty($team["LABEL"])) continue;
            AJXP_XMLWriter::renderNode("/teams/".$teamId, $team["LABEL"], true, array(
                    "icon"      => "users-folder.png",
                    "ajxp_mime" => "ajxp_team",
                    "users"     => "<span class='icon-groups'></span> ".implode(", ", array_values($team["USERS"]))
                ), true, true);
        }
    }

    public function listUsers()
    {
        AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String"/><column messageId="user_dash.10" attributeName="repo_accesses" sortType="String"/></columns>');
        if(!AuthService::usersEnabled()) return ;
        $loggedUser = AuthService::getLoggedUser();
        $users = ConfService::getConfStorageImpl()->getUserChildren($loggedUser->getId()); // AuthService::listUsers();
        $mess = ConfService::getMessages();
        $repoList = ConfService::getConfStorageImpl()->listRepositoriesWithCriteria(array(
            "owner_user_id" => $loggedUser->getId()
        ));
        $userArray = array();
        foreach ($users as $userIndex => $userObject) {
            $label = $userObject->getId();
            if(!$userObject->hasParent() || $userObject->getParent() != $loggedUser->getId()) continue;
            if ($userObject->hasParent()) {
                $label = $userObject->getParent()."000".$label;
            }
            $userArray[$label] = $userObject;
        }
        ksort($userArray);
        foreach ($userArray as $userObject) {
            //$userObject = new AJXP_SerialUser();
            $label = $userObject->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
            $isAdmin = $userObject->isAdmin();
            $userId = $userObject->getId();
            $repoAccesses = array();
            foreach ($repoList as $repoObject) {
                if ($repoObject->hasOwner() && $repoObject->getOwner() == $loggedUser->getId()) {
                    $acl = $userObject->mergedRole->getAcl($repoObject->getId());
                    if(!empty($acl)) $repoAccesses[] = $repoObject->getDisplay()." ($acl)";
                }
            }
            if(!empty($label)) $label .= " ($userId)";
            else $label = $userId;
            print '<tree
                text="'.AJXP_Utils::xmlEntities($label).'"
                isAdmin="'.AJXP_Utils::xmlEntities($mess[($isAdmin?"ajxp_conf.14":"ajxp_conf.15")]).'"
                icon="user_shared.png"
                openicon="user_shared.png"
                filename="/users/'.AJXP_Utils::xmlEntities($userId).'"
                repo_accesses="'.(count($repoAccesses) ? AJXP_Utils::xmlEntities($this->metaIcon("share-sign"). implode(", ", $repoAccesses)):"").'"
                parentname="/users"
                is_file="1"
                ajxp_mime="shared_user"
                />';
        }
    }

    public function listRepositories()
    {
        AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.8" attributeName="ajxp_label" sortType="String"/><column messageId="user_dash.9" attributeName="parent_label" sortType="String"/><column messageId="user_dash.9" attributeName="repo_accesses" sortType="String"/></columns>');
        $repoArray = array();
        $loggedUser = AuthService::getLoggedUser();
        $repos = ConfService::getConfStorageImpl()->listRepositoriesWithCriteria(array(
            "owner_user_id" => $loggedUser->getId()
        ));

        $searchAll = ConfService::getCoreConf("CROSSUSERS_ALLGROUPS", "conf");
        $displayAll = ConfService::getCoreConf("CROSSUSERS_ALLGROUPS_DISPLAY", "conf");
        if($searchAll || $displayAll){
            $baseGroup = "/";
        }else{
            $baseGroup = AuthService::filterBaseGroup("/");
        }
        AuthService::setGroupFiltering(false);
        $users = AuthService::listUsers($baseGroup);

        $minisites = $this->listSharedFiles("minisites");

        foreach ($repos as $repoIndex => $repoObject) {
            if($repoObject->getAccessType() == "ajxp_conf") continue;
            if (!$repoObject->hasOwner() || $repoObject->getOwner() != $loggedUser->getId()) {
                continue;
            }
            if(is_numeric($repoIndex)) $repoIndex = "".$repoIndex;
            $name = (isSet($minisites[$repoIndex]) ? "[Minisite] ":""). AJXP_Utils::xmlEntities(SystemTextEncoding::toUTF8($repoObject->getDisplay()));
            $repoArray[$name] = $repoIndex;
        }
        // Sort the list now by name
        ksort($repoArray);
        foreach ($repoArray as $name => $repoIndex) {
            $repoObject =& $repos[$repoIndex];
            $repoAccesses = array();
            foreach ($users as $userId => $userObject) {
                if($userObject->getId() == $loggedUser->getId()) continue;
                $label = $userObject->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, $userId);
                if(empty($label)) $label = $userId;
                $acl = $userObject->mergedRole->getAcl($repoObject->getId());
                if(!empty($acl)) $repoAccesses[] = $label. " (".$acl.")";
            }
            $parent = $repoObject->getParentId();
            $parentRepo =& $repos[$parent];
            $parentLabel = $this->metaIcon("folder-open").$parentRepo->getDisplay();
            $repoPath = $repoObject->getOption("PATH");
            $parentPath = $parentRepo->getOption("PATH");
            $parentLabel .= " (".str_replace($parentPath, "", $repoPath).")";

            $metaData = array(
                "repository_id" => $repoIndex,
                "icon"			=> "document_open_remote.png",
                "openicon"		=> "document_open_remote.png",
                "parentname"	=> "/repositories",
                "parent_label"   => $parentLabel,
                "repo_accesses" => count($repoAccesses) ?  $this->metaIcon("share-sign").implode(", ", $repoAccesses) : "",
                "ajxp_mime" 	=> "shared_repository"
            );
            AJXP_XMLWriter::renderNode("/repositories/$repoIndex", $name, true, $metaData);
        }
    }

}
