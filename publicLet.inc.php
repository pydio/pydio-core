<?php
require_once("conf/base.conf.php");

$pServ = AJXP_PluginsService::getInstance();
ConfService::init(AJXP_INSTALL_PATH."/conf/conf.php");
$confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);
$pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", $confPlugin);
ConfService::start();



$fakes = '
// Non working exception class
class AJXP_Exception extends Exception 
{
    public function AJXP_Exception($msg) { echo "$msg"; exit(); }
}';

eval($fakes);

?>