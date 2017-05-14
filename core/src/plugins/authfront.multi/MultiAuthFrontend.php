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
namespace Pydio\Auth\Frontend;

use DOMXPath;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Auth\Frontend\Core\AbstractAuthFrontend;
use Pydio\Core\Model\ContextInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class MultiAuthFrontend
 * @package Pydio\Auth\Frontend
 */
class MultiAuthFrontend extends AbstractAuthFrontend
{

    /**
     * Try to authenticate the user based on various external parameters
     * Return true if user is now logged.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param bool $isLast Whether this is is the last plugin called.
     * @return bool
     */
    function tryToLogUser(ServerRequestInterface &$request, ResponseInterface &$response, $isLast = false)
    {
        return false;
    }

    /**
     * Dynamically modify some registry contributions nodes. Can be easily derivated to enable/disable
     * some features dynamically during plugin initialization.
     * @param ContextInterface $ctx
     * @param \DOMNode $contribNode
     * @return void
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        $sources = array();

        if (!isSet($this->options) || !isSet($this->options["DRIVERS"]) || !is_array($this->options["DRIVERS"])
            || (isSet($this->options["MODE"]) && $this->options["MODE"] == "MASTER_SLAVE")
        ) {

            $contribXpath = new DOMXPath($contribNode->ownerDocument);

            $action = $contribXpath->query('action[@name="login"]', $contribNode);
            if ($action->length) {
                $action = $action->item(0);
                $contribNode->removeChild($action);
            }

            $clientConfigs = $contribXpath->query('component_config', $contribNode);
            if($clientConfigs->length){
                $contribNode->removeChild($clientConfigs->item(0));
            }

            return;

        }

        if ($contribNode->nodeName != "actions") return;
        $actionXpath = new DOMXPath($contribNode->ownerDocument);
        $loginCallbackNodeList = $actionXpath->query('//clientCallback', $contribNode);
        $callbackNode = $loginCallbackNodeList->item(0);
        $xmlContent = $callbackNode->firstChild->wholeText;


        foreach ($this->options["DRIVERS"] as $driverDef) {
            $dName = $driverDef["NAME"];
            if (isSet($driverDef["LABEL"])) {
                $dLabel = $driverDef["LABEL"];
            } else {
                $dLabel = $driverDef["NAME"];
            }
            $sources[$dName] = $dLabel;
        }

        $xmlContent = str_replace("AJXP_MULTIAUTH_SOURCES", json_encode($sources), $xmlContent);
        $xmlContent = str_replace("AJXP_MULTIAUTH_MASTER", $this->options["MASTER_DRIVER"], $xmlContent);
        $xmlContent = str_replace("AJXP_USER_ID_SEPARATOR", $this->options["USER_ID_SEPARATOR"], $xmlContent);
        if ($callbackNode) {
            $callbackNode->removeChild($callbackNode->firstChild);
            $callbackNode->appendChild($contribNode->ownerDocument->createCDATASection($xmlContent));
        }

    }


}