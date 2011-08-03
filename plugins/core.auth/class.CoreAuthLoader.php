<?php
class CoreAuthLoader extends AJXP_Plugin{
	
	public function getConfigs(){
		$configs = parent::getConfigs();		
		$configs["ALLOW_GUEST_BROWSING"] = !isSet($_SERVER["HTTP_AJXP_FORCE_LOGIN"]) && $configs["ALLOW_GUEST_BROWSING"];
		return $configs;
	}
		
}
?>