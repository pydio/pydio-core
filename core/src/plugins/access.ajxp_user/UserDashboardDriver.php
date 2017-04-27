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
 *
 */
namespace Pydio\Access\Driver\DataProvider;

use DOMXPath;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Http\Message\ReloadMessage;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\UsersService;
use Pydio\Share\ShareCenter;
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

    /**
     * @param ContextInterface $contextInterface
     */
    protected function initRepository(ContextInterface $contextInterface)
    {
        require_once AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/action.share/vendor/autoload.php";
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @throws \Exception
     */
    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        parent::accessPreprocess($requestInterface);
        if(!UsersService::usersEnabled()) return ;
        $action     = $requestInterface->getAttribute("action");
        $httpVars   = $requestInterface->getParsedBody();
        $ctx        = $requestInterface->getAttribute("ctx");

        if ($action == "edit") {
            if (isSet($httpVars["sub_action"])) {
                $action = $httpVars["sub_action"];
            }
        }
        $mess = LocaleService::getMessages();

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
                        $x->addChunk($this->listUsers($ctx));
                    } else if ($strippedDir == "teams") {
                        $x->addChunk($this->listTeams($ctx));
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
                
                $mime       = $httpVars["ajxp_mime"];
                $selection  = UserSelection::fromContext($ctx, $httpVars);
                $files      = $selection->getFiles();
                $minisites  = $this->listMinisites($ctx);
                
                /**
                 * @var ShareCenter $shareCenter
                 */
                $shareCenter = PluginsService::getInstance($ctx)->getPluginById("action.share");
                foreach ($files as $index => $element) {
                    $element = basename($element);
                    $ar = explode("shared_", $mime);
                    $mime = array_pop($ar);
                    if($mime == "repository" && isSet($minisites[$element])){
                        $mime = "minisite";
                        $element = $minisites[$element];
                    }
                    
                    $shareCenter->getShareStore($ctx)->deleteShare($mime, $element);
                    $out = "";
                    if($mime == "repository" || $mime == "minisite") $out = $mess["ajxp_conf.59"];
                    else if($mime == "user") $out = $mess["ajxp_conf.60"];
                    else if($mime == "file") $out = $mess["user_dash.13"];
                    if(!empty($out)){
                        $x->addChunk(new UserMessage($out, LOG_LEVEL_INFO));
                    }
                }
                $x->addChunk(new ReloadMessage());
            break;
            
            default:
            break;
        }

        return;
    }

    /**
     * @param $ctx ContextInterface
     * @return array
     */
    public function listMinisites(ContextInterface $ctx)
    {
        /**
         * @var ShareCenter $shareCenter
         */
        $shareCenter = PluginsService::getInstance($ctx)->getPluginById("action.share");
        $publicLets = $shareCenter->getShareStore($ctx)->listShares($ctx->hasUser() ? $ctx->getUser()->getId() : "shared", "");
        $minisites = array();
        foreach ($publicLets as $hash => $publicletData) {
            if(!isSet($publicletData["AJXP_APPLICATION_BASE"]) && !isSet($publicletData["TRAVEL_PATH_TO_ROOT"])) continue;
            $minisites[$publicletData["REPOSITORY"]] = $hash;
        }
        return $minisites;
    }

    /**
     * @param $metaIcon
     * @return string
     */
    private function metaIcon($metaIcon)
    {
        return "<span class='icon-".$metaIcon." meta-icon'></span> ";
    }

    /**
     * @param ContextInterface $ctx
     * @return NodesList
     */
    public function listTeams(ContextInterface $ctx)
    {
        $conf = ConfService::getConfStorageImpl();
        $nodesList = new NodesList();
        if(!method_exists($conf, "listUserTeams")) {
            return $nodesList;
        }

        $nodesList->initColumnsData('fileList', 'detail');
        $nodesList->appendColumn('ajxp_conf.6', 'ajxp_label');
        $nodesList->appendColumn('user_dash.10', 'repo_accesses');

        $teams = $conf->listUserTeams($ctx->getUser());
        foreach ($teams as $teamId => $team) {
            if(empty($team["LABEL"])) continue;
            $team["USERS_LABELS"] = array();
            foreach(array_values($team["USERS"]) as $userId){
                $team["USERS_LABELS"][] = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $userId, "core.conf", $userId);
            }
            $metaData = [
                "text" => $team["LABEL"],
                "icon" => "users-folder.png",
                "fonticon" => "account-multiple",
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
     * @param ContextInterface $ctx
     * @return NodesList
     */
    public function listUsers(ContextInterface $ctx)
    {
        $nodesList = new NodesList();
        $nodesList->initColumnsData('fileList');
        $nodesList->appendColumn('ajxp_conf.6', 'ajxp_label');
        $nodesList->appendColumn('user_dash.10', 'repo_accesses');

        if(!UsersService::usersEnabled()) {
            return $nodesList;
        }
        $loggedUser = $ctx->getUser();
        $users = ConfService::getConfStorageImpl()->getUserChildren($loggedUser->getId()); // AuthService::listUsers();
        $mess = LocaleService::getMessages();
        $count = 0;
        $repoList = RepositoryService::listRepositoriesWithCriteria(array(
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
            $label = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $userObject);
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
                "fonticon"  => "account-circle",
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
    
}