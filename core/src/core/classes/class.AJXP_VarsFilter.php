<?php
class AJXP_VarsFilter {
	
	public static function filter($value){
		if(is_string($value) && strpos($value, "AJXP_USER")!==false){
			if(AuthService::usersEnabled()){
				$loggedUser = AuthService::getLoggedUser();
				if($loggedUser != null){
					$loggedUser = $loggedUser->getId();
					$value = str_replace("AJXP_USER", $loggedUser, $value);
				}else{
					return "";
				}
			}else{
				$value = str_replace("AJXP_USER", "shared", $value);
			}
		}
		if(is_string($value) && strpos($value, "AJXP_INSTALL_PATH") !== false){
			$value = str_replace("AJXP_INSTALL_PATH", AJXP_INSTALL_PATH, $value);
		}
		AJXP_Controller::applyIncludeHook("vars.filter", array(&$value));		 
		return $value;
	}
}
?>