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


class inboxAccessDriver extends fsAccessDriver
{
    public function initRepository()
    {
        $this->detectStreamWrapper(true);
        $this->urlBase = "pydio://".$this->repository->getId();
    }

    public function loadNodeInfo(&$ajxpNode, $parentNode = false, $details = false)
    {
        $mess = ConfService::getMessages();
        parent::loadNodeInfo($ajxpNode, $parentNode, $details);
        if(!$ajxpNode->isRoot()){
            $targetUrl = inboxAccessWrapper::translateURL($ajxpNode->getUrl());
            $repoId = parse_url($targetUrl, PHP_URL_HOST);
            $r = ConfService::getRepositoryById($repoId);
            if(!is_null($r)){
                $owner = $r->getOwner();
                $creationTime = $r->getOption("CREATION_TIME");
            }else{
                $owner = "http://".parse_url($targetUrl, PHP_URL_HOST);
                $creationTime = time();
            }
            $leaf = $ajxpNode->isLeaf();
            $meta = array(
                "shared_repository_id" => $repoId,
                "ajxp_description" => ($leaf?"File":"Folder")." shared by ".$owner. " ". AJXP_Utils::relativeDate($creationTime, $mess)
                );
            if(!$leaf){
                $meta["ajxp_mime"] = "shared_folder";
            }
            $ajxpNode->mergeMetadata($meta);
        }
    }

}