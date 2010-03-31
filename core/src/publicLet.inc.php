<?php
require_once("server/classes/class.AJXP_Logger.php"); 
require_once("server/classes/class.AJXP_Plugin.php"); 
require_once("server/classes/class.AJXP_PluginsService.php"); 
require_once("server/conf/base.conf.php"); 
require_once("server/classes/class.Repository.php"); 
require_once("server/classes/class.AJXP_Utils.php"); 
require_once("server/classes/class.SystemTextEncoding.php"); 
require_once("server/classes/class.UserSelection.php"); 
require_once("server/classes/class.AbstractAccessDriver.php"); 
require_once("server/classes/class.HTMLWriter.php"); 
require_once("server/classes/class.RecycleBinManager.php"); 

$pServ = AJXP_PluginsService::getInstance();
$pServ->loadPluginsRegistry(INSTALL_PATH."/plugins", INSTALL_PATH."/server/conf");


$fakes = '
// Non working conf service 
class ConfService
{
	private static $repository;
	public function getMessages() { return array(); }
	public function getConf($str) { if ($str == "USE_HTTPS") return (!empty($_SERVER["HTTPS"])) ? 1 : 0; return NULL; }
	public function getRepositoryById($id) {return self::$repository;}
	public function setRepository($repo) {self::$repository = $repo;}
};

/**
 * 
 * Non working auth service / Fake. 
 * Get the currently logged user object
 *
 * @return AbstractAjxpUser
 */
class AuthService
{
    public function usersEnabled() { return FALSE; }
    public function getLoggedUser() { return NULL; }
}

// Non working exception class
class AJXP_Exception extends Exception 
{
    public function AJXP_Exception($msg) { echo "$msg"; exit(); }
}';

eval($fakes);

?>