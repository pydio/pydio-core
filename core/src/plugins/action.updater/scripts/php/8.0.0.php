<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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
defined('AJXP_EXEC') or die( 'Access not allowed');

if(!function_exists('forceRenameConfFile')){

    function forceRenameConfFile($prefix){

        // FORCE bootstrap_repositories copy
        if (is_file(AJXP_INSTALL_PATH."/conf/$prefix.php".".new-".date("Ymd"))) {
            rename(AJXP_INSTALL_PATH."/conf/$prefix.php", AJXP_INSTALL_PATH."/conf/$prefix.php.pre-update");
            rename(AJXP_INSTALL_PATH."/conf/$prefix.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/$prefix.php");
        }


    }

}

if(!function_exists('updatePluginConfig')){

    function updatePluginConfig($pluginId, $parameterName, $parameterValue){

        $p = \Pydio\Core\PluginFramework\PluginsService::getInstance()->getPluginById($pluginId);
        $options = $p->getConfigs();
        $confDriver = \Pydio\Core\Services\ConfService::getConfStorageImpl();
        $options[$parameterName] = $parameterValue;
        $confDriver->savePluginConfig($pluginId, $options);

    }

}

forceRenameConfFile('bootstrap_repositories');
updatePluginConfig('gui.ajax', 'GUI_THEME', 'orbit');