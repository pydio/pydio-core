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

$mess = array(
    "1" => "Upgrade",
    "2" => "Automatic Software Upgrade",
    "3" => "Click on the button to start upgrading. Please make sure that the software folder is installed before starting. If you have a 'Linux Package' warning, it means that update cannot be performed in-app but that you must use your server yum/apt-get command.",
    "4" => "Start update",
    "5" => "From 3.2.4",
    "6" => "Import configuration data from 3.2.4",
    "7" => "Simulate the data import",
    "8" => "Enter full path (from server root) to the previous location, then run the simulation",
    "9" => "Run real import now",
    "10"=> "This is a 'dry-run'. Please review the logs of all actions that will be performed, and if it's ok for you press 'Run real import now'",
    "11"=> "Migrate meta.serial",
    "12"=> "Old meta.serial plugin was removed and split into metastore.serial and meta.user",
    "13"=> "Simulate migration",
    "14"=> "Run migration now",
);
