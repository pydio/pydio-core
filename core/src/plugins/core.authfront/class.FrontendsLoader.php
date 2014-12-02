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

class FrontendsLoader extends AJXP_Plugin {

    public function init($options){

        parent::init($options);

        // Load all enabled frontend plugins
        $fronts = AJXP_PluginsService::getInstance()->getPluginsByType("authfront");
        usort($fronts, array($this, "frontendsSort"));
        foreach($fronts as $front){
            if($front->isEnabled()){
                $configs = $front->getConfigs();
                $protocol = $configs["PROTOCOL_TYPE"];
                if($protocol == "session_only" && !AuthService::$useSession) continue;
                if($protocol == "no_session" && AuthService::$useSession) continue;
                AJXP_PluginsService::setPluginActive($front->getType(), $front->getName(), true);
            }
        }

    }

    /**
     * @param AJXP_Plugin $a
     * @param AJXP_Plugin $b
     * @return int
     */
    public function frontendsSort($a, $b){
        $aConf = $a->getConfigs();
        $bConf = $b->getConfigs();
        $orderA = intval($aConf["ORDER"]);
        $orderB = intval($bConf["ORDER"]);
        if($orderA == $orderB) return 0;
        return $orderA > $orderB ? 1 : -1;
    }

} 