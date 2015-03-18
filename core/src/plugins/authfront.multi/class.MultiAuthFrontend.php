<?php
/*
 * Copyright 2007-2015 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
defined('AJXP_EXEC') or die( 'Access not allowed');


class MultiAuthFrontend extends AbstractAuthFrontend {

    function tryToLogUser(&$httpVars, $isLast = false){
        return false;
    }

    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if($contribNode->nodeName != "actions") return ;

        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $loginCallbackNodeList = $actionXpath->query('//clientCallback', $contribNode);
        $callbackNode = $loginCallbackNodeList->item(0);
        $xmlContent = $callbackNode->firstChild->wholeText;

        $sources = array();
        if(!isSet($this->options) || !isSet($this->options["DRIVERS"]) || !is_array($this->options["DRIVERS"])) return;
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
        if($callbackNode) {
            $callbackNode->removeChild($callbackNode->firstChild);
            $callbackNode->appendChild($contribNode->ownerDocument->createCDATASection($xmlContent));
        }

    }


}