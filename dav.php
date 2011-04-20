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
$pServ->loadPluginsRegistry(INSTALL_PATH."/plugins", INSTALL_PATH."/server/conf");
ConfService::init("server/conf/conf.php");

$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());

session_start();

require_once "ezc/Base/base.php"; // dependent on installation method, see below
spl_autoload_register( array( 'ezcBase', 'autoload' ) );

$baseURL = "http://localhost";
$baseURI = "/ajaxplorer/shares";

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
	$server->options->realm = "ajxp_webdav_realm";
	$server->auth = new AJXP_WebdavAuth($repositoryId);
}

$backend = new AJXP_WebdavBackend($repositoryId);
//$backend = new ezcWebdavFileBackend(AJXP_INSTALL_PATH."/files/");
$server->handle( $backend ); 

?>