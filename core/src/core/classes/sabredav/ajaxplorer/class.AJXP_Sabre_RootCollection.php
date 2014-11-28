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
 * @package Pydio
 * @subpackage SabreDav
 */
class AJXP_Sabre_RootCollection extends Sabre\DAV\SimpleCollection
{

    public function getChildren()
    {
        $this->children = array();
        $u = AuthService::getLoggedUser();
        if ($u != null) {
            $repos = ConfService::getAccessibleRepositories($u);
            // Refilter to make sure the driver is an AjxpWebdavProvider
            foreach ($repos as $repository) {
                $accessType = $repository->getAccessType();
                $driver = AJXP_PluginsService::getInstance()->getPluginByTypeName("access", $accessType);
                if (is_a($driver, "AjxpWrapperProvider") && $repository->getOption("AJXP_WEBDAV_DISABLED") !== true) {
                    $this->children[$repository->getSlug()] = new Sabre\DAV\SimpleCollection($repository->getSlug());
                }
            }
        }
        return $this->children;
    }

    public function childExists($name)
    {
        $c = $this->getChildren();
        return array_key_exists($name, $c);
    }

    public function getChild($name)
    {
        $c = $this->getChildren();
        return $c[$name];
    }

}
