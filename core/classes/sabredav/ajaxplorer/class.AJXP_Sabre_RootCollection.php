<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.core
 */
class AJXP_Sabre_RootCollection extends Sabre_DAV_SimpleCollection
{

    function getChildren(){

        $this->children = array();
        $u = AuthService::getLoggedUser();
        if($u != null){
            $repos = ConfService::getAccessibleRepositories($u);
            // Refilter to make sure the driver is an AjxpWebdavProvider
            foreach($repos as $repository){
                $accessType = $repository->getAccessType();
                $driver = AJXP_PluginsService::getInstance()->getPluginByTypeName("access", $accessType);
                if(is_a($driver, "AjxpWebdavProvider")){
                    $this->children[$repository->getSlug()] = new Sabre_DAV_SimpleCollection($repository->getSlug());
                }
            }
        }
        return $this->children;
    }

    function childExists($name){
        $c = $this->getChildren();
        return array_key_exists($name, $c);
    }

    function getChild($name){
        $c = $this->getChildren();
        return $c[$name];
    }

}
