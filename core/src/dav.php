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
 * Description : Command line access of the framework.
 * DAV controller, loads the ezComponent webDav Server
 */
include_once("base.conf.php");

//set_error_handler(array("AJXP_XMLWriter", "catchError"), E_ALL & ~E_NOTICE );
//set_exception_handler(array("AJXP_XMLWriter", "catchException"));
$pServ = AJXP_PluginsService::getInstance();
ConfService::init();
$confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);
$pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", $confPlugin);
ConfService::start();

if(!ConfService::getCoreConf("WEBDAV_ENABLE")){
	die('You are not allowed to access this service');
}

$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());

//session_start();

require_once AJXP_BIN_FOLDER."/ezc/Base/base.php";
spl_autoload_register( array( 'ezcBase', 'autoload' ) );

if(ConfService::getCoreConf("WEBDAV_BASEHOST") != ""){
	$baseURL = ConfService::getCoreConf("WEBDAV_BASEHOST");
}else{
	$baseURL = AJXP_Utils::detectServerURL();
}				
$baseURI = ConfService::getCoreConf("WEBDAV_BASEURI");

$requestUri = $_SERVER["REQUEST_URI"];
$end = substr($requestUri, strlen($baseURI."/"));
$parts = explode("/", $end);
$repositoryId = $parts[0];

$repository = ConfService::getRepositoryById($repositoryId);
if($repository == null){
	AJXP_Logger::debug("Searching by alias $repositoryId");
	$repository = ConfService::getRepositoryByAlias($repositoryId);
}
if($repository == null){
	AJXP_Logger::debug("not found, dying $repositoryId");
	die('You are not allowed to access this service');
}

$server = ezcWebdavServer::getInstance();
$pathFactory = new ezcWebdavBasicPathFactory($baseURL.$baseURI."/$repositoryId");
foreach ( $server->configurations as $conf ){
    $conf->pathFactory = $pathFactory;
}
if(AuthService::usersEnabled()){
	$server->options->realm = ConfService::getCoreConf("WEBDAV_DIGESTREALM");
	$server->auth = new AJXP_WebdavAuth($repository->getId());
}

$backend = new AJXP_WebdavBackend($repository);

$lockConf = new ezcWebdavLockPluginConfiguration();
$server->pluginRegistry->registerPlugin(
	$lockConf
);

//$backend = new ezcWebdavFileBackend(AJXP_INSTALL_PATH."/files/");
//ob_start();
$server->handle( $backend ); 
//$c = ob_get_clean();
//AJXP_Logger::logAction("OUTPUT : ".$c);
//print($c);
?>