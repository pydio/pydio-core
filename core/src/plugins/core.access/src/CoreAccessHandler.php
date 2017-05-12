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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Access\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\UsersService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class CoreAccessHandler
 * Manage global actions
 * @package Pydio\Access\Core
 */
class CoreAccessHandler extends Plugin
{
    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        MetaStreamWrapper::register();
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function multipleSearch(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface){
        /** @var ContextInterface $parentContext */
        $parentContext = $requestInterface->getAttribute("ctx");
        $user = $parentContext->getUser();
        $repositories = UsersService::getRepositoriesForUser($user);
        $results = new NodesList();
        $results->setParentNode(new AJXP_Node($parentContext->getUrlBase()));
        $searchParams = $requestInterface->getParsedBody();
        $searchParams["limit"] = 5;
        $searchParams["skip_unindexed"] = "true";
        $searchParams["query"] = $searchParams["query"]."*";
        foreach($repositories as $repository){
            try{
                $childContext = $parentContext->withRepositoryId($repository->getId());
                $indexer = PluginsService::getInstance($childContext)->getUniqueActivePluginForType("index");
                if(empty($indexer)){
                    continue;
                }
                $searchRequest = Controller::executableRequest($childContext, "search", $searchParams);
                $localResponse = Controller::run($searchRequest);
                $serial = $localResponse->getBody();
                if($serial instanceof SerializableResponseStream){
                    /** @var NodesList $nodesList */
                    $nodesList = $serial->getChunks()[0];
                    if($nodesList instanceof NodesList){
                        $children = $nodesList->getChildren();
                        foreach($children as $childNode){
                            $childNode->mergeMetadata([
                                "repository_id" => $repository->getId(),
                                "repository_display" => $repository->getDisplay()
                            ]);
                            $results->addBranch($childNode);
                        }
                    }
                }
            }catch (\Exception $r){
                $message = $r->getMessage();
            }
        }
        $responseInterface = $responseInterface->withBody(new SerializableResponseStream($results));
    }
    
}