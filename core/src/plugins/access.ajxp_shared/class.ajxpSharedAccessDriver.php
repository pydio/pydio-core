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
class ajxpSharedAccessDriver extends AbstractAccessDriver
{

    public function initRepository()
    {
        require_once AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/class.ShareCenter.php";
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
                    "files" => array("LABEL" => $mess["ajxp_shared.3"], "ICON" => "html.png", "DESCRIPTION" => $mess["ajxp_shared.28"]),
                    "repositories" => array("LABEL" => $mess["ajxp_shared.2"], "ICON" => "document_open_remote.png", "DESCRIPTION" => $mess["ajxp_shared.29"]),
                    "users" => array("LABEL" => $mess["ajxp_shared.1"], "ICON" => "user_shared.png", "DESCRIPTION" => $mess["ajxp_shared.30"])
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
                    } else if ($strippedDir == "repositories") {
                        $this->listRepositories();
                    } else if ($strippedDir == "files") {
                        $this->listSharedFiles();
                    }
                    AJXP_XMLWriter::close();
                } else {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_shared.8" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_shared.31" attributeName="description" sortType="String"/></columns>');
                    foreach ($rootNodes as $key => $data) {
                        print '<tree text="'.$data["LABEL"].'" icon="'.$data["ICON"].'" filename="/'.$key.'" parentname="/" description="'.$data["DESCRIPTION"].'" />';
                    }
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
                foreach ($files as $index => $element) {
                    $element = basename($element);
                    $ar = explode("shared_", $mime);
                    $mime = array_pop($ar);
                    ShareCenter::deleteSharedElement($mime, $element, $loggedUser);
                    if($mime == "repository") $out = $mess["ajxp_conf.59"];
                    else if($mime == "user") $out = $mess["ajxp_conf.60"];
                    else if($mime == "file") $out = $mess["ajxp_shared.13"];
                }
                AJXP_XMLWriter::sendMessage($out, null);
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::close();
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

    public function listSharedFiles()
    {
        AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist">
                <column messageId="ajxp_shared.4" attributeName="ajxp_label" sortType="String" width="20%"/>
                <column messageId="ajxp_shared.17" attributeName="download_url" sortType="String" width="20%"/>
                <column messageId="ajxp_shared.20" attributeName="download_count" sortType="String" width="2%"/>
                <column messageId="share_center.22" attributeName="download_limit" sortType="String" width="2%"/>
                <column messageId="ajxp_shared.6" attributeName="password" sortType="String" width="5%"/>
                <column messageId="ajxp_shared.7" attributeName="expiration" sortType="String" width="5%"/>
            </columns>');
        $dlFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        if(!is_dir($dlFolder)) return ;
        $files = glob($dlFolder."/*.php");
        if(!is_array($files))return;
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
            $ar = explode(".", basename($file));
            $id = array_shift($ar);
            if($ar[0] != "php") continue;
            //if(strlen($id) != 32) continue;
            $publicletData = ShareCenter::loadPublicletData($id);
            if (isset($publicletData["OWNER_ID"]) && $publicletData["OWNER_ID"] != $userId) {
                continue;
            }
            $expired = ($publicletData["EXPIRE_TIME"]!=0?($publicletData["EXPIRE_TIME"]<time()?true:false):false);
            if(!is_a($publicletData["REPOSITORY"], "Repository")) continue;
            AJXP_XMLWriter::renderNode(str_replace(".php", "", basename($file)), "".SystemTextEncoding::toUTF8($publicletData["REPOSITORY"]->getDisplay()).":/".SystemTextEncoding::toUTF8($publicletData["FILE_PATH"]), true, array(
                "icon"		=> "html.png",
                "password" => ($publicletData["PASSWORD"]!=""?$publicletData["PASSWORD"]:"-"),
                "expiration" => ($publicletData["EXPIRE_TIME"]!=0?($expired?"[!]":"").date($mess["date_format"], $publicletData["EXPIRE_TIME"]):"-"),
                "download_count" => $publicletData["DOWNLOAD_COUNT"],
                "download_limit" => ($publicletData["DOWNLOAD_LIMIT"] == 0 ? "-" : $publicletData["DOWNLOAD_LIMIT"] ),
                "integrity"  => (!$publicletData["SECURITY_MODIFIED"]?$mess["ajxp_shared.15"]:$mess["ajxp_shared.16"]),
                "download_url" => $downloadBase . "/".basename($file),
                "ajxp_mime" => "shared_file")
            );
        }
    }

    public function clearExpiredFiles()
    {
        $files = glob(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")."/*.php");
        $loggedUser = AuthService::getLoggedUser();
        $userId = $loggedUser->getId();
        $deleted = array();
        foreach ($files as $file) {
            $ar = explode(".", basename($file));
            $id = array_shift($ar);
            if(strlen($id) != 32) continue;
            $publicletData = ShareCenter::loadPublicletData($id);
            if (!isSet($publicletData["OWNER_ID"]) || $publicletData["OWNER_ID"] != $userId) {
                continue;
            }
            if( (isSet($publicletData["EXPIRE_TIME"]) && is_numeric($publicletData["EXPIRE_TIME"]) && $publicletData["EXPIRE_TIME"] > 0 && $publicletData["EXPIRE_TIME"] < time()) ||
                            (isSet($publicletData["DOWNLOAD_LIMIT"]) && $publicletData["DOWNLOAD_LIMIT"] > 0 && $publicletData["DOWNLOAD_LIMIT"] <= $publicletData["DOWNLOAD_COUNT"]) ) {
                unlink($file);
                $deleted[] = basename($file);
                PublicletCounter::delete(str_replace(".php", "", basename($file)));
            }
        }
        return $deleted;
    }

    public function listUsers()
    {
        AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.6" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_shared.10" attributeName="repo_accesses" sortType="String"/></columns>');
        if(!AuthService::usersEnabled()) return ;
        $users = AuthService::listUsers();
        $mess = ConfService::getMessages();
        $loggedUser = AuthService::getLoggedUser();
        $repoList = ConfService::getRepositoriesList("all");
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
            $isAdmin = $userObject->isAdmin();
            $userId = AJXP_Utils::xmlEntities($userObject->getId());
            $repoAccesses = array();
            foreach ($repoList as $repoObject) {
                if ($repoObject->hasOwner() && $repoObject->getOwner() == $loggedUser->getId()) {
                    $acl = $userObject->mergedRole->getAcl($repoObject->getId());
                    if(!empty($acl)) $repoAccesses[] = $repoObject->getDisplay()." ($acl)";
                }
            }
            print '<tree
                text="'.$userId.'"
                isAdmin="'.$mess[($isAdmin?"ajxp_conf.14":"ajxp_conf.15")].'"
                icon="user_shared.png"
                openicon="user_shared.png"
                filename="/users/'.$userId.'"
                repo_accesses="'.implode(", ", $repoAccesses).'"
                parentname="/users"
                is_file="1"
                ajxp_mime="shared_user"
                />';
        }
    }

    public function listRepositories()
    {
        $repos = ConfService::getRepositoriesList("all");
        AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="ajxp_conf.8" attributeName="ajxp_label" sortType="String"/><column messageId="ajxp_conf.9" attributeName="accessType" sortType="String"/><column messageId="ajxp_shared.9" attributeName="repo_accesses" sortType="String"/></columns>');
        $repoArray = array();
        $childRepos = array();
        $loggedUser = AuthService::getLoggedUser();
        $users = AuthService::listUsers();
        foreach ($repos as $repoIndex => $repoObject) {
            if($repoObject->getAccessType() == "ajxp_conf") continue;
            if (!$repoObject->hasOwner() || $repoObject->getOwner() != $loggedUser->getId()) {
                continue;
            }
            if(is_numeric($repoIndex)) $repoIndex = "".$repoIndex;
            $name = AJXP_Utils::xmlEntities(SystemTextEncoding::toUTF8($repoObject->getDisplay()));
            $repoArray[$name] = $repoIndex;
        }
        // Sort the list now by name
        ksort($repoArray);
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
            $repoAccesses = array();
            foreach ($users as $userId => $userObject) {
                //if(!$userObject->hasParent()) continue;
                if($userObject->getId() == $loggedUser->getId()) continue;
                $label = $userObject->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, $userId);
                $acl = $userObject->mergedRole->getAcl($repoObject->getId());
                if(!empty($acl)) $repoAccesses[] = $label. " (".$acl.")";
            }

            $metaData = array(
                "repository_id" => $repoIndex,
                "accessType"	=> $repoObject->getAccessType(),
                "icon"			=> "document_open_remote.png",
                "openicon"		=> "document_open_remote.png",
                "parentname"	=> "/repositories",
                "repo_accesses" => implode(", ", $repoAccesses),
                "ajxp_mime" 	=> "shared_repository"
            );
            AJXP_XMLWriter::renderNode("/repositories/$repoIndex", $name, true, $metaData);
        }
    }

}
