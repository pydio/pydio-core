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

/**
 * @param string $className
 * @return void
 */
function AJXP_Sabre_autoload($className) {
    if(strpos($className,'AJXP_Sabre_')===0) {
        include AJXP_BIN_FOLDER. '/sabredav/ajaxplorer/class.' . $className . '.php';
    }
}
spl_autoload_register('AJXP_Sabre_autoload');



include 'core/classes/sabredav/Sabre/autoload.php';

if(ConfService::getCoreConf("WEBDAV_BASEHOST") != ""){
    $baseURL = ConfService::getCoreConf("WEBDAV_BASEHOST");
}else{
    $baseURL = AJXP_Utils::detectServerURL();
}
$baseURI = ConfService::getCoreConf("WEBDAV_BASEURI");

$requestUri = $_SERVER["REQUEST_URI"];
$end = trim(substr($requestUri, strlen($baseURI."/")));
if(!empty($end) && $end[0] != "?"){

    $parts = explode("/", $end);
    $pathBase = $parts[0];
    $repositoryId = $pathBase;

    $repository = ConfService::getRepositoryById($repositoryId);
    if($repository == null){
        $repository = ConfService::getRepositoryByAlias($repositoryId);
        if($repository != null){
            $repositoryId = ($repository->isWriteable()?$repository->getUniqueId():$repository->getId());
        }
    }
    if($repository == null){
        AJXP_Logger::debug("not found, dying $repositoryId");
        die('You are not allowed to access this service');
    }

    $rootDir =  new AJXP_Sabre_Collection("/", $repository, null);
    $server = new Sabre_DAV_Server($rootDir);
    $server->setBaseUri($baseURI."/".$pathBase);


}else{

    $rootDir = new AJXP_Sabre_RootCollection("root");
    $server = new Sabre_DAV_Server($rootDir);
    $server->setBaseUri($baseURI);

}


$authBackend = new AJXP_Sabre_AuthBackend(0);
$authPlugin = new Sabre_DAV_Auth_Plugin($authBackend, ConfService::getCoreConf("WEBDAV_DIGESTREALM"));
$server->addPlugin($authPlugin);

$lockBackend = new Sabre_DAV_Locks_Backend_File("data/plugins/server.sabredav/locks");
$lockPlugin = new Sabre_DAV_Locks_Plugin($lockBackend);
$server->addPlugin($lockPlugin);

if(ConfService::getCoreConf("WEBDAV_BROWSER_LISTING")){
    $browerPlugin = new AJXP_Sabre_BrowserPlugin((isSet($repository)?$repository->getDisplay():null));
    $extPlugin = new Sabre_DAV_Browser_GuessContentType();
    $server->addPlugin($browerPlugin);
    $server->addPlugin($extPlugin);
}

$server->exec();