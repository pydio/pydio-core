<?php
include_once("conf/base.conf.php");

require_once(AJXP_BIN_FOLDER."/class.AJXP_Utils.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_VarsFilter.php");
require_once(AJXP_BIN_FOLDER."/class.SystemTextEncoding.php");
require_once(AJXP_BIN_FOLDER."/class.Repository.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_Exception.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_Plugin.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_PluginsService.php");
require_once(AJXP_BIN_FOLDER."/class.AbstractAccessDriver.php");
require_once(AJXP_BIN_FOLDER."/class.AjxpRole.php");
require_once(AJXP_BIN_FOLDER."/class.ConfService.php");
require_once(AJXP_BIN_FOLDER."/class.AuthService.php");
require_once(AJXP_BIN_FOLDER."/class.UserSelection.php");
require_once(AJXP_BIN_FOLDER."/class.HTMLWriter.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_XMLWriter.php");
require_once(AJXP_BIN_FOLDER."/class.RecycleBinManager.php");
require_once(AJXP_BIN_FOLDER."/class.AJXP_Logger.php");
//set_error_handler(array("AJXP_XMLWriter", "catchError"), E_ALL & ~E_NOTICE );
//set_exception_handler(array("AJXP_XMLWriter", "catchException"));
$pServ = AJXP_PluginsService::getInstance();
$pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", AJXP_INSTALL_PATH."/conf");
ConfService::init("conf/conf.php");

if(!AJXP_WEBDAV_ENABLE){
	die('You are not allowed to access this service');
}

$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());

//session_start();

require_once AJXP_BIN_FOLDER."/ezc/Base/base.php";
spl_autoload_register( array( 'ezcBase', 'autoload' ) );

if(defined("AJXP_WEBDAV_BASEHOST")){
	$baseURL = AJXP_WEBDAV_BASEHOST;
}else{
	$http_mode = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
	$baseURL = $http_mode . $_SERVER['HTTP_HOST'];
}				
$baseURI = AJXP_WEBDAV_BASEURI;

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
	$server->options->realm = AJXP_WEBDAV_DIGESTREALM;
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