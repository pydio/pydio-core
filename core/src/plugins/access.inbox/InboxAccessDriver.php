<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Access\Driver\StreamProvider\Inbox;

use Pydio\Access\Core\Exception\FileNotFoundException;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Filter\ContentFilter;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\StatHelper;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Driver for inbox repository
 * Class inboxAccessDriver
 * @package Pydio\Access\Driver\StreamProvider\Inbox
 */
class InboxAccessDriver extends FsAccessDriver
{
    private static $output;

    /**
     * @param ContextInterface $contextInterface
     * @throws PydioException
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {
        $this->urlBase = $contextInterface->getUrlBase();
    }

    /**
     * Hook to node.info event
     * @param AJXP_Node $ajxpNode
     * @param bool $parentNode
     * @param bool $details
     */
    public function loadNodeInfo(&$ajxpNode, $parentNode = false, $details = false)
    {
        parent::loadNodeInfo($ajxpNode, $parentNode, $details);
        if(!$ajxpNode->isRoot()){

            // Retrieving stored details
            $originalNode = self::$output[$ajxpNode->getLabel()];
            if(isSet($originalNode["meta"])){
                $meta = $originalNode["meta"];
            }else{
                $meta = [];
            }
            $label = $originalNode["label"];

            if(!$ajxpNode->isLeaf()){
                $meta["icon"] = "mime_empty.png";
            }

            // Overriding display name with repository name
            $ajxpNode->setLabel($label);
            $ajxpNode->mergeMetadata($meta);
        }
    }

    /**
     * Hook for load_repository_info event
     * @param ContextInterface $ctx
     * @param $data
     */
    public function loadRepositoryInfo(ContextInterface $ctx, &$data){
        $allNodes = self::getNodes($ctx, false, false);
        $data['access.inbox'] = [
            'files' => count($allNodes)
        ];
    }

    /**
     * @param string $nodePath Url of a node
     * @return array|mixed
     * @throws FileNotFoundException
     */
    public static function getNodeData($nodePath){
        $nodeObject = new AJXP_Node($nodePath);
        $basename = $nodeObject->getLabel();
        if($nodeObject->isRoot()){
            return ['stat' => stat(ApplicationState::getTemporaryFolder())];
        }
        $allNodes = self::getNodes($nodeObject->getContext(), true );
        if(!isSet($allNodes[$basename])){
            throw new FileNotFoundException($basename);
        }
        $nodeData = $allNodes[$basename];
        if(!isSet($nodeData["stat"])){
            if(in_array(pathinfo($basename, PATHINFO_EXTENSION), ["error", "invitation"])){
                $stat = stat(ApplicationState::getTemporaryFolder());
            }else{
                $url = $nodeData["url"];
                $node = new AJXP_Node($url);
                try{
                    $node->getDriver()->detectStreamWrapper(true);
                    if($node->getRepository()->hasContentFilter()){
                        $node->setLeaf(true);
                    }
                    Controller::applyHook("node.read", [&$node]);
                    $stat = @stat($url);
                }catch (\Exception $e){
                    $stat = stat(ApplicationState::getTemporaryFolder());
                }
                if(is_array($stat) && $nodeObject->getContext()->hasUser()){
                    $acl = $nodeObject->getContext()->getUser()->getMergedRole()->getAcl($nodeData["meta"]["shared_repository_id"]);
                    if($acl == "r"){
                        self::disableWriteInStat($stat);
                    }
                }
                self::$output[$basename]["stat"] = $stat;
            }
            $nodeData["stat"] = $stat;
        }
        return $nodeData;
    }

    /**
     * @param ContextInterface $parentContext
     * @param bool $checkStats
     * @param bool $touch
     * @return array
     */
    public static function getNodes(ContextInterface $parentContext, $checkStats = false, $touch = true){

        if(isSet(self::$output)){
            return self::$output;
        }

        $mess = LocaleService::getMessages();
        $repos = UsersService::getRepositoriesForUser($parentContext->getUser());
        $userId = $parentContext->getUser()->getId();

        $output = [];
        $touchReposIds = [];
        foreach($repos as $repo) {
            if (!$repo->hasOwner() || !$repo->hasContentFilter()) {
                continue;
            }
            $repoId = $repo->getId();

//            DISABLE REMOTE SHARE FOR TESTING
            /*if(strpos($repoId, "ocs_remote_share_") === 0){
                continue;
            }*/

            if(strpos($repoId, "ocs_remote_share_") !== 0){
                $touchReposIds[] = $repoId;
            }

            $url = "pydio://".$userId . "@" . $repoId . "/";
            $meta = [
                "shared_repository_id" => $repoId,
                "ajxp_description" => "File shared by ".$repo->getOwner(). " ". StatHelper::relativeDate($repo->getSafeOption("CREATION_TIME"), $mess),
                "share_meta_type" => 1
            ];

            $cFilter = $repo->getContentFilter();
            $filter = ($cFilter instanceof ContentFilter) ? array_keys($cFilter->filters)[0] : $cFilter;
            if (!is_array($filter)) {
                $label = basename($filter);
            }else{
                $label = $repo->getDisplay();
            }
            if(strpos($repoId, "ocs_remote_share") !== 0){
                // FOR REMOTE SHARES, DO NOT APPEND THE DOCUMENTNAME, WE STAT THE ROOT DIRECTLY
                $url .= $label;
            }

            $status = null;
            $remoteShare = null;
            $name = pathinfo($label, PATHINFO_FILENAME);
            $ext = pathinfo($label, PATHINFO_EXTENSION);

            $node = new AJXP_Node($url);
            $node->setLabel($label);

            if($checkStats){

                try{
                    $node->getDriver()->detectStreamWrapper(true);
                }catch (\Exception $e){
                    $ext = "error";
                    $meta["ajxp_mime"] = "error";
                }

                $stat = @stat($url);
                if($stat === false){
                    $ext = "error";
                    $meta["ajxp_mime"] = "error";
                    $meta["share_meta_type"] = 2;
                    if(strpos($repoId, "ocs_remote_share_") === 0){
                        $meta["remote_share_id"] = str_replace("ocs_remote_share_", "", $repoId);
                    }
                }else if(strpos($repoId, "ocs_remote_share_") === 0){
                    // Check Status
                    $linkId = str_replace("ocs_remote_share_", "", $repoId);
                    $ocsStore = new \Pydio\OCS\Model\SQLStore();
                    $remoteShare = $ocsStore->remoteShareById($linkId);
                    $status = $remoteShare->getStatus();
                    if($status == OCS_INVITATION_STATUS_PENDING){
                        $stat = stat(ApplicationState::getTemporaryFolder());
                        $ext = "invitation";
                        $meta["ajxp_mime"] = "invitation";
                        $meta["share_meta_type"] = 0;
                    } else {
                        $meta["remote_share_accepted"] = "true";
                    }
                    $meta["remote_share_id"] = $remoteShare->getId();
                }
                if($ext == "invitation"){
                    $label .= " (".$mess["inbox_driver.4"].")";
                }else if($ext == "error"){
                    $label .= " (".$mess["inbox_driver.5"].")";
                }
                if(is_array($stat) && $parentContext->hasUser()){
                    $acl = $parentContext->getUser()->getMergedRole()->getAcl($repoId);
                    if($acl == "r"){
                        self::disableWriteInStat($stat);
                    }

                }

            }

            $index = 0;$suffix = "";
            while(isSet($output[$name.$suffix.".".$ext])){
                $index ++;
                $suffix = " ($index)";
            }
            $output[$name.$suffix.".".$ext] = [
                "label" => $label,
                "url" => $url,
                "remote_share" => $remoteShare,
                "meta" => $meta
            ];
            if(isset($stat)){
                $output[$name.$suffix.".".$ext]['stat'] = $stat;
            }
        }
        self::$output = $output;

        if ($touch) {
            if (count($touchReposIds) && $parentContext->hasUser()) {
                $uPref = $parentContext->getUser()->getPref("repository_last_connected");
                if (empty($uPref)) $uPref = [];
                foreach ($touchReposIds as $rId) {
                    $uPref[$rId] = time();
                }
                $parentContext->getUser()->setPref("repository_last_connected", $uPref);
            }
        }
        return $output;
    }

    /**
     * @param array $stat
     */
    protected static function disableWriteInStat(&$stat){
        $octRights = decoct($stat["mode"]);
        $last = (strlen($octRights)) - 1;
        $octRights[$last] = $octRights[$last-1] = $octRights[$last-2] = 5;
        $stat["mode"] = $stat[2] = octdec($octRights);
    }

}
