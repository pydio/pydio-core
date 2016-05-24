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
use PublicletCounter;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Message\XMLMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\PluginFramework\PluginsService;
use ShareCenter;
use Zend\Diactoros\Response\EmptyResponse;

defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 * @class ajxpSharedAccessDriver
 * Plugin to access the shared elements of the current user
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

    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        parent::accessPreprocess($requestInterface);
        if(!AuthService::usersEnabled()) return ;
        $action     = $requestInterface->getAttribute("action");
        $httpVars   = $requestInterface->getParsedBody();

        if ($action == "edit") {
            if (isSet($httpVars["sub_action"])) {
                $action = $httpVars["sub_action"];
            }
        }
        $mess = ConfService::getMessages();

        $x = new SerializableResponseStream();
        $responseInterface = $responseInterface->withBody($x);

        switch ($action) {
            //------------------------------------
            //	BASIC LISTING
            //------------------------------------
            case "ls":
                $rootNodes = array(
                    "settings" => array(
                        "LABEL" => $mess["user_dash.36"],
                        "ICON" => "user_shared.png",
                        "ICON-CLASS" => "icon-cog",
                        "DESCRIPTION" => $mess["user_dash.37"]
                    ),
                    "users" => array(
                        "LABEL" => $mess["user_dash.1"],
                        "ICON" => "user_shared.png",
                        "ICON-CLASS" => "icon-book",
                        "DESCRIPTION" => $mess["user_dash.30"]
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
                if (!empty($strippedDir) && array_key_exists($strippedDir, $rootNodes)) {
                    if ($strippedDir == "users") {
                        $x->addChunk($this->listUsers());
                    } else if ($strippedDir == "teams") {
                        $x->addChunk($this->listTeams());
                    }
                } else {
                    $responseInterface  = new EmptyResponse();
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
                $minisites = $this->listMinisites();
                /**
                 * @var ShareCenter $shareCenter
                 */
                $shareCenter = PluginsService::findPluginById("action.share");
                foreach ($files as $index => $element) {
                    $element = basename($element);
                    $ar = explode("shared_", $mime);
                    $mime = array_pop($ar);
                    if($mime == "repository" && isSet($minisites[$element])){
                        $mime = "minisite";
                        $element = $minisites[$element];
                    }
                    $shareCenter->getShareStore()->deleteShare($mime, $element);
                    $out = "";
                    if($mime == "repository" || $mime == "minisite") $out = $mess["ajxp_conf.59"];
                    else if($mime == "user") $out = $mess["ajxp_conf.60"];
                    else if($mime == "file") $out = $mess["user_dash.13"];
                    if(!empty($out)){
                        $x->addChunk(new UserMessage($out, LOG_LEVEL_INFO));
                    }
                }
                $x->addChunk(new XMLMessage(XMLWriter::reloadDataNode("", "", false)));
            break;

            case "clear_expired" :

                /**
                 * @var ShareCenter $shareCenter
                 */
                $shareCenter = PluginsService::getInstance()->findPluginById("action.share");
                $deleted = $shareCenter->getShareStore()->clearExpiredFiles(true);
                if (count($deleted)) {
                    $x->addChunk(new UserMessage(sprintf($mess["user_dash.23"], count($deleted)."")));
                    $x->addChunk(new XMLMessage(XMLWriter::reloadDataNode("", "", false)));
                } else {
                    $x->addChunk(new UserMessage($mess["user_dash.24"]));
                }

            break;

            case "reset_download_counter" :

                $selection = new UserSelection();
                $selection->initFromHttpVars($httpVars);
                $elements = $selection->getFiles();
                foreach ($elements as $element) {
                    PublicletCounter::reset(str_replace(".php", "", basename($element)));
                }
                $x->addChunk(new XMLMessage(XMLWriter::reloadDataNode("", "", false)));
                break;

            default:
            break;
        }

        return;
    }

    /**
     * @return array
     */
    public function listMinisites()
    {
        /**
         * @var ShareCenter $shareCenter
         */
        $shareCenter = PluginsService::getInstance()->findPluginById("action.share");
        $publicLets = $shareCenter->listShares(true, null);
        $minisites = array();
        foreach ($publicLets as $hash => $publicletData) {
            if(!isSet($publicletData["AJXP_APPLICATION_BASE"]) && !isSet($publicletData["TRAVEL_PATH_TO_ROOT"])) continue;
            $minisites[$publicletData["REPOSITORY"]] = $hash;
        }
        return $minisites;
    }

    private function metaIcon($metaIcon)
    {
        return "<span class='icon-".$metaIcon." meta-icon'></span> ";
    }

    /**
     * @return NodesList
     */
    public function listTeams()
    {
        $conf = ConfService::getConfStorageImpl();
        $nodesList = new NodesList();
        if(!method_exists($conf, "listUserTeams")) {
            return $nodesList;
        }

        $nodesList->initColumnsData('fileList', 'detail');
        $nodesList->appendColumn('ajxp_conf.6', 'ajxp_label');
        $nodesList->appendColumn('user_dash.10', 'repo_accesses');

        $teams = $conf->listUserTeams();
        foreach ($teams as $teamId => $team) {
            if(empty($team["LABEL"])) continue;
            $team["USERS_LABELS"] = array();
            foreach(array_values($team["USERS"]) as $userId){
                $team["USERS_LABELS"][] = ConfService::getUserPersonalParameter("USER_DISPLAY_NAME", $userId, "core.conf", $userId);
            }
            $metaData = [
                "text" => $team["LABEL"],
                "icon" => "users-folder.png",
                "ajxp_mime" => "ajxp_team",
                "users"     => "<span class='icon-groups'></span> ".implode(",", array_values($team["USERS"])),
                "users_labels"     => "<span class='icon-groups'></span> ".implode(", ", $team["USERS_LABELS"])
            ];
            $n = new AJXP_Node("/teams/".$teamId, $metaData);
            $nodesList->addBranch($n);
        }
        return $nodesList;
    }

    /**
     * @return NodesList
     */
    public function listUsers()
    {
        $nodesList = new NodesList();
        $nodesList->initColumnsData('fileList');
        $nodesList->appendColumn('ajxp_conf.6', 'ajxp_label');
        $nodesList->appendColumn('user_dash.10', 'repo_accesses');

        if(!AuthService::usersEnabled()) {
            return $nodesList;
        }
        $loggedUser = AuthService::getLoggedUser();
        $users = ConfService::getConfStorageImpl()->getUserChildren($loggedUser->getId()); // AuthService::listUsers();
        $mess = ConfService::getMessages();
        $count = 0;
        $repoList = ConfService::listRepositoriesWithCriteria(array(
            "owner_user_id" => $loggedUser->getId()
        ), $count);
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
            $label = ConfService::getUserPersonalParameter("USER_DISPLAY_NAME", $userObject);
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
            $metaData = [
                "text"      => $label,
                "isAdmin"   => $mess[($isAdmin?"ajxp_conf.14":"ajxp_conf.15")],
                "icon"      => "user_shared.png",
                "openicon"  => "user_shared.png",
                "repo_accesses" => count($repoAccesses) ? $this->metaIcon("share-sign"). implode(", ", $repoAccesses):"",
                "parentname"    => "/users",
                "is_file"       => "1",
                "ajxp_mime"     => "shared_user"
            ];
            $node = new AJXP_Node("/users/".$userId, $metaData);
            $nodesList->addBranch($node);
        }
        return $nodesList;
    }

    /*
    public function listRepositories()
    {
        $nodesList = new NodesList();
        $nodesList->initColumnsData('fileList');
        $nodesList->appendColumn('ajxp_conf.8', 'ajxp_label');
        $nodesList->appendColumn('user_dash.9', 'parent_label');
        $nodesList->appendColumn('user_dash.9', 'repo_accesses');

        $repoArray = array();
        $loggedUser = AuthService::getLoggedUser();
        $count = 0;
        $repos = ConfService::listRepositoriesWithCriteria(array(
            "owner_user_id" => $loggedUser->getId()
        ), $count);

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
            $name = (isSet($minisites[$repoIndex]) ? "[Minisite] ":""). Utils::xmlEntities(TextEncoder::toUTF8($repoObject->getDisplay()));
            $repoArray[$name] = $repoIndex;
        }
        // Sort the list now by name
        ksort($repoArray);
        foreach ($repoArray as $name => $repoIndex) {
            $repoObject =& $repos[$repoIndex];
            $repoAccesses = array();
            foreach ($users as $userId => $userObject) {
                if($userObject->getId() == $loggedUser->getId()) continue;
                $label = ConfService::getUserPersonalParameter("USER_DISPLAY_NAME", $userObject, "core.conf", $userId);
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
                "text"          => $name,
                "repository_id" => $repoIndex,
                "icon"			=> "document_open_remote.png",
                "openicon"		=> "document_open_remote.png",
                "parentname"	=> "/repositories",
                "parent_label"  => $parentLabel,
                "repo_accesses" => count($repoAccesses) ?  $this->metaIcon("share-sign").implode(", ", $repoAccesses) : "",
                "ajxp_mime" 	=> "shared_repository"
            );
            $nodesList->addBranch(new AJXP_Node("/repositories/$repoIndex", $metaData));
            //XMLWriter::renderNode("/repositories/$repoIndex", $name, true, $metaData);
        }
        return $nodesList;
    }
    */

}
