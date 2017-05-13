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
 *
 * Description : Specific inclusion to run publiclet scripts
 */
use Pydio\Core\Services\ConfService;
use Pydio\Core\PluginFramework\PluginsService;

require_once("base.conf.php");

$pServ = PluginsService::getInstance();
ConfService::init();
ConfService::start();
$authDriver = ConfService::getAuthDriverImpl();
require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/vendor/autoload.php");
class_alias("Pydio\\Share\\ShareCenter", "ShareCenter");

$fakes = '
// Non working exception class
class PydioException extends Exception
{
    public function __construct($msg) { echo "$msg"; exit(); }
}';

eval($fakes);
