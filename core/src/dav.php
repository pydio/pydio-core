<?php
include_once("server/conf/base.conf.php");

require_once("server/classes/class.AJXP_Utils.php");
require_once("server/classes/class.AJXP_VarsFilter.php");
require_once("server/classes/class.SystemTextEncoding.php");
require_once("server/classes/class.Repository.php");
require_once("server/classes/class.AJXP_Exception.php");
require_once("server/classes/class.AJXP_Plugin.php");
require_once("server/classes/class.AJXP_PluginsService.php");
require_once("server/classes/class.AbstractAccessDriver.php");
require_once("server/classes/class.AjxpRole.php");
require_once("server/classes/class.ConfService.php");
require_once("server/classes/class.AuthService.php");
require_once("server/classes/class.UserSelection.php");
require_once("server/classes/class.HTMLWriter.php");
require_once("server/classes/class.AJXP_XMLWriter.php");
require_once("server/classes/class.RecycleBinManager.php");
require_once("server/classes/class.AJXP_Logger.php");
//set_error_handler(array("AJXP_XMLWriter", "catchError"), E_ALL & ~E_NOTICE );
//set_exception_handler(array("AJXP_XMLWriter", "catchException"));
$pServ = AJXP_PluginsService::getInstance();
$pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", AJXP_INSTALL_PATH."/server/conf");
ConfService::init("server/conf/conf.php");

if(!AJXP_WEBDAV_ENABLE){
	die('You are not allowed to access this service');
}

$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());

//session_start();

require_once AJXP_INSTALL_PATH."/".SERVER_RESOURCES_FOLDER."/ezc/Base/base.php";
spl_autoload_register( array( 'ezcBase', 'autoload' ) );

$baseURL = AJXP_WEBDAV_BASEHOST;
$baseURI = AJXP_WEBDAV_BASEURI;

$requestUri = $_SERVER["REQUEST_URI"];
$end = substr($requestUri, strlen($baseURI."/"));
$parts = explode("/", $end);
$repositoryId = $parts[0];

$server = ezcWebdavServer::getInstance();
$pathFactory = new ezcWebdavBasicPathFactory($baseURL.$baseURI."/$repositoryId");
foreach ( $server->configurations as $conf ){
    $conf->pathFactory = $pathFactory;
}
if(AuthService::usersEnabled()){
	$server->options->realm = AJXP_WEBDAV_DIGESTREALM;
	$server->auth = new AJXP_WebdavAuth($repositoryId);
}

$backend = new AJXP_WebdavBackend($repositoryId);

$lockConf = new ezcWebdavLockPluginConfiguration();
$server->pluginRegistry->registerPlugin(
	$lockConf
);

//$backend = new ezcWebdavFileBackend(AJXP_INSTALL_PATH."/files/");
$server->handle( $backend ); 

?>