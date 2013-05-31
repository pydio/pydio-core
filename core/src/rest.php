<?php

die("This is experimental! You must set an API KEY & SECRET to enable Basic Http Auth");

define("AJXP_API_LOGIN", "admin");
define("AJXP_API_PASSWORD", "123456");
define("AJXP_API_USER", "admin");

if (!isset($_SERVER['PHP_AUTH_USER'])  || $_SERVER["PHP_AUTH_USER"] != AJXP_API_LOGIN || $_SERVER["PHP_AUTH_PW"] != AJXP_API_PASSWORD ) {
    header('WWW-Authenticate: Basic realm="AjaXplorer API Realm"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'You are not authorized to access this API.';
    exit;
}

include_once("base.conf.php");

set_error_handler(array("AJXP_XMLWriter", "catchError"), E_ALL & ~E_NOTICE & ~E_STRICT );
set_exception_handler(array("AJXP_XMLWriter", "catchException"));

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
AuthService::logUser(AJXP_API_USER, "", true);
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

AJXP_Controller::findRestActionAndApply($action, $path);