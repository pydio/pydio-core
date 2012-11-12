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
 *
 * Description : Specific inclusion to run publiclet scripts
 */
require_once("base.conf.php");

$pServ = AJXP_PluginsService::getInstance();
ConfService::init();
$confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);
$pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", $confPlugin);
ConfService::start();
$authDriver = ConfService::getAuthDriverImpl();
$confDriver = ConfService::getConfStorageImpl();
require_once($confDriver->getUserClassFileName());
require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/class.ShareCenter.php");

$fakes = '
// Non working exception class
class AJXP_Exception extends Exception 
{
    public function AJXP_Exception($msg) { echo "$msg"; exit(); }
}';

eval($fakes);

?>