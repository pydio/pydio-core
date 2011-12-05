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
 * This is the main configuration file for configuring the core of the application.
 * In a standard usage, you should not have to change any variables.
 */
if(function_exists("date_default_timezone_set") and function_exists("date_default_timezone_get")){
	@date_default_timezone_set(@date_default_timezone_get());
}
if(function_exists("xdebug_disable")){
	xdebug_disable();
}
@error_reporting(E_ALL & ~E_NOTICE);
//Windows users may have to uncomment this
setlocale(LC_ALL, '');


list($vNmber,$vDate) = explode("__",file_get_contents(AJXP_CONF_PATH."/VERSION"));
define("AJXP_VERSION", $vNmber);
define("AJXP_VERSION_DATE", $vDate);

define("AJXP_EXEC", true);

// APPLICATION PATHES CONFIGURATION
define("AJXP_DATA_PATH", AJXP_INSTALL_PATH."/data");
define("AJXP_CACHE_DIR", AJXP_DATA_PATH."/cache");
define("AJXP_PLUGINS_CACHE_FILE", AJXP_CACHE_DIR."/plugins_cache.ser");
define("AJXP_PLUGINS_REQUIRES_FILE", AJXP_CACHE_DIR."/plugins_requires.ser");
define("AJXP_SERVER_ACCESS", "index.php");
define("AJXP_PLUGINS_FOLDER", "plugins");
define("AJXP_BIN_FOLDER_REL", "core/classes");
define("AJXP_BIN_FOLDER", AJXP_INSTALL_PATH."/core/classes");
define("AJXP_DOCS_FOLDER", "core/doc");
define("AJXP_COREI18N_FOLDER", AJXP_INSTALL_PATH."/plugins/core.ajaxplorer/i18n");
define("TESTS_RESULT_FILE", AJXP_CACHE_DIR."/diag_result.php");
define("AJXP_TESTS_FOLDER", AJXP_INSTALL_PATH."/core/tests");
define("INITIAL_ADMIN_PASSWORD", "admin");
define("SOFTWARE_UPDATE_SITE", "http://www.ajaxplorer.info/update/");
// Startup admin password (used at first creation). Once
// The admin password is created and his password is changed,
// this config has no more impact.
define("ADMIN_PASSWORD", "admin");
// For a specific distribution, you can specify where the
// log files will be stored. This should be detected by log.* plugins
// and used if defined. See bootstrap_plugins.php default configs for
// example in log.serial. Do not forget the trailing slash
// define("AJXP_FORCE_LOGPATH", "/var/log/ajaxplorer/");


// DEBUG OPTIONS
define("AJXP_CLIENT_DEBUG"  ,	false);
define("AJXP_SERVER_DEBUG"  ,	false);
define("AJXP_SKIP_CACHE"    ,   false);

require(AJXP_BIN_FOLDER."/compat.php");

function AjaXplorer_autoload($className){
	$fileName = AJXP_BIN_FOLDER."/"."class.".$className.".php";
	if(file_exists($fileName)){
		require_once($fileName);
        return;
	}
	$fileName = AJXP_BIN_FOLDER."/"."interface.".$className.".php";
	if(file_exists($fileName)){
		require_once($fileName);
        return;
	}
    $corePlugClass = glob(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/core.*/class.".$className.".php", GLOB_NOSORT);
    if($corePlugClass !== false && count($corePlugClass)){
        require_once($corePlugClass[0]);
    }
}
spl_autoload_register('AjaXplorer_autoload');

AJXP_Utils::safeIniSet("session.cookie_httponly", 1);
//AJXP_Utils::safeIniSet("session.cookie_path", "/ajaxplorer");



?>