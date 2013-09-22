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

defined('AJXP_EXEC') or die('Access not allowed');
/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
class CoreConfLoader extends AJXP_Plugin
{
    /**
     * @var AbstractConfDriver
     */
    protected static $confImpl;

    /**
     * @return AbstractConfDriver
     */
    public function getConfImpl()
    {
        if (!isSet(self::$confImpl) || (isset($this->pluginConf["UNIQUE_INSTANCE_CONFIG"]["instance_name"]) && self::$confImpl->getId() != $this->pluginConf["UNIQUE_INSTANCE_CONFIG"]["instance_name"])) {
            if (isset($this->pluginConf["UNIQUE_INSTANCE_CONFIG"])) {
                self::$confImpl = ConfService::instanciatePluginFromGlobalParams($this->pluginConf["UNIQUE_INSTANCE_CONFIG"], "AbstractConfDriver");
                AJXP_PluginsService::getInstance()->setPluginUniqueActiveForType("conf", self::$confImpl->getName());
            }
        }
        return self::$confImpl;

    }


}
