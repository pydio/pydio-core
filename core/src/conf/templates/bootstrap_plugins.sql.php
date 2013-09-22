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
 * This is the main configuration file for configuring the basic plugins the application
 * needs to run properly : an Authentication plugin, a Configuration plugin, and a Logger plugin.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');
/********************************************
 * CUSTOM VARIABLES HOOK
 ********************************************/
/**
 * This is a sample "hard" hook, directly included. See directly the PluginSkeleton class
 * for more explanation.
 */
//require_once AJXP_INSTALL_PATH."/plugins/action.skeleton/class.PluginSkeleton.php";
//AJXP_Controller::registerIncludeHook("vars.filter", array("PluginSkeleton", "filterVars"));

/*********************************************************/
/* PLUGINS DEFINITIONS
/* Drivers will define how the application will work. For
/* each type of operation, there are multiple implementation
/* possible. Check the content of the plugins folder.
/* CONF = users and repositories definition,
/* AUTH = users authentification mechanism,
/* LOG = logs of the application.
/*
/* This template shows how to configure the three plugins
/* using SQL database. It is based on the dibiphp.com
/* implementation and thus can be stored in various db types.
*/
/*********************************************************/
$sqlDriver =  array(
    "driver"        => "mysql|sqlite|etc..",
    "host"          => "YOUR_HOST",
    "database"      => "DATABASE_NAME",
    "user"          => "DB_USER",
    "password"      => "DB_PASSWORD",
);

$PLUGINS = array(
    "AUTH_DRIVER" => array(
        "NAME"		=> "sql",
              "OPTIONS"	=> array(
                  "SQL_DRIVER"	=> $sqlDriver,
            )
    ),
    "CONF_DRIVER" => array(
        "NAME"		=> "sql",
        "OPTIONS"	=> array(
            "SQL_DRIVER"	=> $sqlDriver,
            )
    ),
    "LOG_DRIVER"    => array(
        "NAME" => "sql",
        "OPTIONS" => array(
            "SQL_DRIVER"    => $sqlDriver
        )
    )

);
