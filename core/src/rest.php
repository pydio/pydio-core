<?php

die("This is experimental and should not be used in production yet!");

include_once("base.conf.php");

$pServ = AJXP_PluginsService::getInstance();
ConfService::init();
$confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);
$pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", $confPlugin);
ConfService::start();
$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());
session_name("AjaXplorer");
session_start();
AuthService::$useSession = false;
AuthService::logUser("admin", "", true);
$authDriver = ConfService::getAuthDriverImpl();


$uri = $_SERVER["REQUEST_URI"];
$scriptUri = dirname($_SERVER["SCRIPT_NAME"])."/api/";
$uri = substr($uri, strlen($scriptUri));
$uri = explode("/", $uri);
// GET REPO ID
$repoID = array_shift($uri);
// GET ACTION NAME
$action = array_shift($uri);
$path = "/".implode("/", $uri);
$repo = &ConfService::findRepositoryByIdOrAlias($repoID);
if($repo == null){
    die("Cannot find repository with ID ".$repoID);
}
ConfService::switchRootDir($repo->getId());
// DRIVERS BELOW NEED IDENTIFICATION CHECK
if(!AuthService::usersEnabled() || ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") || AuthService::getLoggedUser()!=null){
    $confDriver = ConfService::getConfStorageImpl();
    $Driver = ConfService::loadDriverForRepository($repo);
}
AJXP_PluginsService::getInstance()->initActivePlugins();

try{
    AJXP_Controller::findRestActionAndApply($action, $path);
}catch(Exception $e){
}