<?php
/**
 * Copyright 2007-2011 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * DAV controller, loads the ezComponent webDav Server
 */

include_once("conf/base.conf.php");

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
	$http_mode = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
	$baseURL = $http_mode . $_SERVER['HTTP_HOST'];
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
$server->handle( $backend ); 

?>