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
 *
 * This is the main configuration file for configuring the core of the application.
 * In a standard usage, you should not have to change any variables.
 */
define("AJXP_PACKAGING", "zip");
define("AJXP_INSTALL_PATH", realpath(dirname(__FILE__)));
define("AJXP_CONF_PATH", AJXP_INSTALL_PATH."/conf");
require_once(AJXP_CONF_PATH."/bootstrap_context.php");
