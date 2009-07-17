<?php
require_once("server/classes/class.AJXP_Logger.php"); 
require_once("server/conf/conf.php"); 
require_once("server/classes/class.AbstractDriver.php"); 
require_once("server/classes/class.Repository.php"); 
require_once("server/classes/class.Utils.php"); 
require_once("server/classes/class.SystemTextEncoding.php"); 
require_once("server/classes/class.UserSelection.php"); 
require_once("server/classes/class.AbstractAccessDriver.php"); 
require_once("server/classes/class.HTMLWriter.php"); 

// Non working conf service 
class ConfService
{
   public function getMessages() { return array(); }
   public function getConf($str) { if ($str == "USE_HTTPS") return (!empty($_SERVER['HTTPS'])) ? 1 : 0; return NULL; }
};

// Non working auth service
class AuthService
{
    public function usersEnabled() { return FALSE; }
    public function getLoggedUser() { return NULL; }
}

// Non working exception class
class AJXP_Exception
{
    public function AJXP_Exception($msg) { echo "$msg"; exit(); }
}

?>
