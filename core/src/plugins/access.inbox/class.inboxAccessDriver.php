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

use Pydio\Access\Core\Stream\Iterator\DirIterator;

class inboxAccessDriver extends fsAccessDriver
{
    private static $output;

    public function initRepository()
    {
        $this->detectStreamWrapper(true);
        $this->urlBase = "pydio://".$this->repository->getId();
    }

    /**
     *
     * Main callback for all share- actions.
     * @param string $action
     * @param array $httpVars
     * @param array $fileVars
     * @return null
     * @throws Exception
     */
    public function switchAction($action, $httpVars, $fileVars) {

        switch ($action) {
            //------------------------------------
            // SHARING FILE OR FOLDER
            //------------------------------------
            case "load_shares":

                $nodes = static::getNodes();

                header("Content-type:application/json");

                $data = [];
                $data["shares"] = [];

                foreach ($nodes as $key => $node) {

                    $n = new AJXP_Node($node['url']);

                    $remoteShare = $node['remote_share'];

                    $repositoryId = $n->getRepositoryId();
                    $n->getRepository()->driverInstance = null;

                    try {
                        ConfService::loadDriverForRepository($n->getRepository());
                    } catch (\Exception $e) {
                        continue;
                    }

                    $repository = $n->getRepository();

                    $currentData = [
                        "repositoryId"  => $repositoryId
                    ];

                    if (!empty($remoteShare)) {
                        $currentData += [
                            "label" => $remoteShare->getDocumentName(),
                            "owner" => $remoteShare->getSender() . ' (remote)',
                            "cr_date" => $remoteShare->getReceptionDate(),
                            "status" => $remoteShare->getStatus()
                        ];

                        if ($remoteShare->getStatus() == 1) {
                            // Adding the actions button
                            $currentData += [
                                "actions" => [[
                                    "id" => "accept",
                                    "message" => "Accepter",
                                    "options" => [
                                        "get_action" => "accept_invitation",
                                        "remote_share_id" => $remoteShare->getId(),
                                        "statusOnSuccess" => OCS_INVITATION_STATUS_ACCEPTED
                                    ]
                                ],
                                [
                                    "id" => "decline",
                                    "message" => "Refuser",
                                    "options" => [
                                        "get_action" => "reject_invitation",
                                        "remote_share_id" => $remoteShare->getId(),
                                        "statusOnSuccess" => OCS_INVITATION_STATUS_REJECTED
                                    ]
                                ]]
                            ];
                        }
                    } else {
                        $currentData += [
                            "label" => $n->getLabel(),
                            "owner" => $repository->getOwner(),
                            "cr_date" => $repository->getOption('CREATION_TIME'),
                        ];
                    }

                    // Ensuring that mandatory options are set
                    $currentData += [
                        "status" => 0,
                        "actions" => [[
                            "id" => "view",
                            "message" => "View",
                            "options" => [
                                "get_action" => "switch_repository",
                                "repository_id" => $repositoryId
                            ]
                        ]]
                    ];

                    $data["shares"][] = $currentData;
                }

                echo json_encode($data);

                break;

            default:
                parent::switchAction($action, $httpVars, $fileVars);

                break;
        }
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
                $label = $r->getDisplay();
            }else{
                $owner = "http://".parse_url($targetUrl, PHP_URL_HOST);
                $creationTime = time();
                $label = "";
            }
            $leaf = $ajxpNode->isLeaf();
            $meta = array(
                "shared_repository_id" => $repoId,
                "ajxp_description" => ($leaf?"File":"Folder")." shared by ".$owner. " ". AJXP_Utils::relativeDate($creationTime, $mess)
                );
            if(!$leaf){
                $meta["ajxp_mime"] = "shared_folder";
            }

            // Retrieving stored details
            $originalNode = self::$output[$repoId];
            $remoteShare = $originalNode['remote_share'];

            if (!empty($remoteShare)) {
                $meta["remote_share_id"] = $remoteShare->getId();
                if ($remoteShare->getStatus() == 1) {
                    $label .= " (pending)";
                    $meta["ajxp_mime"] = "invitation";
                } else {
                    $label .= " (accepted)";
                    $meta["remote_share_accepted"] = "true";
                }
            }

            // Overriding display name with repository name
            $ajxpNode->setLabel($label);
            $ajxpNode->mergeMetadata($meta);
        }
    }

    public static function getNodes(){
        if(isSet(self::$output)){
            return self::$output;
        }

        $repos = ConfService::getAccessibleRepositories();

        self::$output = array();
        foreach($repos as $repo) {
            if (!$repo->hasOwner()) {
                continue;
            }

            $cFilter = $filter = $label = null;

            $repoId = $repo->getId();
            $url = "pydio://" . $repoId . "/";

            if ($repo->hasContentFilter()) {
                $cFilter = $repo->getContentFilter();
                $filter = ($cFilter instanceof ContentFilter) ? array_keys($cFilter->filters)[0] : $cFilter;

                if (!is_array($filter)) {
                    $label = basename($filter);
                }
            }

            if (empty($label)) {
                $label = $repo->getDisplay();
            }

            $url .= $label;
            if(strpos($repoId, "ocs_remote_share_") === 0){
                // Check Status
                $linkId = str_replace("ocs_remote_share_", "", $repoId);

                $ocsStore = new \Pydio\OCS\Model\SQLStore();
                $remoteShare = $ocsStore->remoteShareById($linkId);
            }

            self::$output[$repoId] = [
                "label" => $label,
                "url" => $url,
                "remote_share" => $remoteShare
            ];
        }

        return self::$output;
    }
}