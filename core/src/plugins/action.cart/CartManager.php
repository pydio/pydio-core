<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

namespace Pydio\Action\Cart;

use Pydio\Core\Controller\Controller;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;

/**
 * Class CartManager
 * @package Pydio\Action\Cart
 */
class CartManager extends Plugin
{
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws \Pydio\Core\Exception\ActionNotFoundException
     * @throws \Pydio\Core\Exception\AuthRequiredException
     */
    public function switchAction(\Psr\Http\Message\ServerRequestInterface &$requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        if ($requestInterface->getAttribute("action") != "search-cart-download") {
            return;
        }

        // Pipe SEARCH + DOWNLOAD actions.
        $ctx = $requestInterface->getAttribute("ctx");
        $indexer = PluginsService::getInstance($ctx)->getUniqueActivePluginForType("index");
        if ($indexer == false) return;
        $httpVars = $requestInterface->getParsedBody();
        unset($httpVars["get_action"]);
        $requestInterface = $requestInterface->withAttribute("action", "search")->withParsedBody($httpVars);
        $response = Controller::run($requestInterface);
        $body = $response->getBody();
        if ($body instanceof \Pydio\Core\Http\Response\SerializableResponseStream) {
            $chunks = $body->getChunks();
            foreach ($chunks as $chunk) {
                if ($chunk instanceof \Pydio\Access\Core\Model\NodesList) {
                    $res = $chunk->getChildren();
                }
            }
        }


        if (isSet($res) && is_array($res)) {
            $newHttpVars = array(
                "selection_nodes" => $res,
                "dir" => "__AJXP_ZIP_FLAT__/",
                "archive_name" => $httpVars["archive_name"]
            );
            $requestInterface = $requestInterface->withAttribute("action", "download")->withParsedBody($newHttpVars);
            $responseInterface = Controller::run($requestInterface);
        }


    }

}
