<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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

defined('AJXP_EXEC') or die('Access not allowed');


class CompositeShare
{
    /**
     * @var AJXP_Node
     */
    protected $node;

    /**
     * @var string
     */
    protected $repositoryId;

    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var ShareLink[]
     */
    protected $shareLinks = array();

    public function __construct($node, $repositoryId)
    {
        $this->node = $node;
        $this->repositoryId = $repositoryId;
    }

    /**
     * @return Repository
     */
    public function getRepository(){
        if(!isSet($this->repository)){
            $this->repository = ConfService::getRepositoryById($this->repositoryId);
        }
        return $this->repository;
    }

    /**
     * @param ShareLink $link
     */
    public function addLink($link){
        $this->shareLinks[] = $link;
    }

    /**
     * @return string
     */
    public function getRepositoryId(){
        return $this->repositoryId;
    }

    /**
     * @return string
     */
    public function getOwner(){
        $repo = $this->getRepository();
        return $repo->getOwner();
    }

    /**
     * @return string
     */
    public function getVisibilityScope(){
        $repo = $this->getRepository();
        if($repo !== null && isSet($repo->options["SHARE_ACCESS"]) && $repo->options["SHARE_ACCESS"] == "public"){
            return "public";
        }else{
            return "private";
        }
    }

    /**
     * @return bool
     */
    public function isInvalid(){
        return $this->getRepository() == null;
    }

    /**
     * @param MetaWatchRegister|false $watcher
     * @param ShareRightsManager $rightsManager
     * @param PublicAccessManager $publicAccessManager
     * @param array $messages
     * @return array|false
     */
    public function toJson($watcher, $rightsManager, $publicAccessManager, $messages){

        $repoRootNode = new AJXP_Node("pydio://".$this->getRepositoryId()."/");
        $elementWatch = false;
        if ($watcher != false) {
            $elementWatch = $watcher->hasWatchOnNode(
                $repoRootNode,
                AuthService::getLoggedUser()->getId(),
                MetaWatchRegister::$META_WATCH_NAMESPACE
            );
        }
        $sharedEntries = $rightsManager->computeSharedRepositoryAccessRights($this->getRepositoryId(), true, $repoRootNode);
        if(empty($sharedEntries)){
            return false;
        }
        $cFilter = $this->getRepository()->getContentFilter();
        if(!empty($cFilter)){
            $cFilter = $cFilter->toArray();
        }
        $jsonData = array(
            "repositoryId"  => $this->getRepositoryId(),
            "users_number"  => AuthService::countUsersForRepository($this->getRepositoryId()),
            "label"         => $this->getRepository()->getDisplay(),
            "description"   => $this->getRepository()->getDescription(),
            "entries"       => $sharedEntries,
            "element_watch" => $elementWatch,
            "repository_url"=> AJXP_Utils::getWorkspaceShortcutURL($this->getRepository())."/",
            "content_filter"=> $cFilter,
            "share_owner"   => $this->getOwner(),
            "share_scope"    => $this->getVisibilityScope()
        );
        $jsonData["links"]  = array();
        foreach ($this->shareLinks as $shareLink) {
            $uniqueUser = $shareLink->getUniqueUser();
            $found = false;
            foreach($sharedEntries as $entry){
                if($entry["ID"] == $uniqueUser) $found = true;
            }
            if(!$found){
                // STRANGE, THE ASSOCIATED USER IS MISSING
                error_log("Found shareLink orphan with uniqueUser ".$uniqueUser);
                continue;
            }
            $jsonData["links"][$shareLink->getHash()] = $shareLink->getJsonData($publicAccessManager, $messages);
        }
        return $jsonData;

    }

}