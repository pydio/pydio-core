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
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Test user agent
 * @package AjaXplorer_Plugins
 * @subpackage Editor
 */
class MobileGuiPlugin extends AJXP_Plugin
{
    public function performChecks()
    {
        if(!AJXP_Utils::userAgentIsMobile()) throw new Exception("no");
    }

    public function parseSpecificContributions(&$contribNode){

        if($contribNode->nodeName == "client_configs" && !$this->orbitExtensionActive()){
            // remove template_part for orbit_content
            $xPath=new DOMXPath($contribNode->ownerDocument);
            $tplNodeList = $xPath->query('template_part[@ajxpId="orbit_content"]', $contribNode);
            if(!$tplNodeList->length) return ;
            $contribNode->removeChild($tplNodeList->item(0));
        }

    }

    private function orbitExtensionActive(){
        $confs = ConfService::getConfStorageImpl()->loadPluginConfig("gui", "ajax");
        if(!isset($confs) || !isSet($confs["GUI_THEME"])) $confs["GUI_THEME"] = "orbit";
        if($confs["GUI_THEME"] == "orbit"){
            $pServ = AJXP_PluginsService::getInstance();
            $activePlugs    = $pServ->getActivePlugins();
            $streamWrappers = $pServ->getStreamWrapperPlugins();
            $streamActive   = false;
            foreach($streamWrappers as $sW){
                if((array_key_exists($sW, $activePlugs) && $activePlugs[$sW] === true)){
                    $streamActive = true;
                    break;
                }
            }
            return $streamActive;
        }
        return false;
    }

}
