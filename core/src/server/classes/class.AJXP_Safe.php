<?php
class AJXP_Safe{
	
	private static $instance;
	
	private $user;
	private $encodedPassword;
	private $secretKey;	
	private $separator = "__SAFE_SEPARATOR__";
	
	public function __construct(){
		if(defined('AJXP_SAFE_SECRET_KEY')){
			$this->secretKey = AJXP_SAFE_SECRET_KEY;
		}else{
			$this->secretKey = "\1CDAFx¨op#";
		}
	} 
	
	public function setCredentials($user, $password){
		$this->user = $user;
		$this->encodedPassword = $this->_encodePassword($password, $user);
	}
	
	public function getCredentials(){
		if(isSet($this->user) && isSet($this->encodedPassword)){
			$decoded = $this->_decodePassword($this->encodedPassword, $this->user);
			return array(
				"user" 		=> $this->user,
				"password"	=> $decoded,
				0			=> $this->user,
				1			=> $decoded
			);
		}else{
			return false;
		}
	}
	
	private function _encodePassword($password, $user){
		if (function_exists('mcrypt_encrypt'))
        {
	        // The initialisation vector is only required to avoid a warning, as ECB ignore IV
	        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
	        // We encode as base64 so if we need to store the result in a database, it can be stored in text column
	        $password = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($user.$this->secretKey), $password, MCRYPT_MODE_ECB, $iv));
        }
		return $password;
	}
	
	private function _decodePassword($encoded, $user){
        if (function_exists('mcrypt_decrypt'))
        {
             // The initialisation vector is only required to avoid a warning, as ECB ignore IV
             $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
             // We have encoded as base64 so if we need to store the result in a database, it can be stored in text column
             $encoded = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($user.$this->secretKey), base64_decode($encoded), MCRYPT_MODE_ECB, $iv));
        }
		return $encoded;
	}
	
	public function store(){
		$_SESSION["AJXP_SAFE_CREDENTIALS"] = base64_encode($this->user.$this->separator.$this->encodedPassword);
	}
	
	public function load(){
		if(empty($_SESSION["AJXP_SAFE_CREDENTIALS"])) return;
		$sessData = base64_decode($_SESSION["AJXP_SAFE_CREDENTIALS"]);
		$parts = explode($this->separator, $sessData);
		$this->user = $parts[0];
		$this->encodedPassword = $parts[1];
	}
	
	public function clear(){
		unset($_SESSION["AJXP_SAFE_CREDENTIALS"]);
		$this->user = null;
		$this->encodedPassword = null;
	}
		
	
	/**
	 * Creates the singleton instance
	 * @return AJXP_Safe
	 */
	public static function getInstance(){
		if(empty(self::$instance)){
			self::$instance = new AJXP_Safe();
		}
		return self::$instance;
	}
	
	public static function storeCredentials($user, $password){
		$inst = AJXP_Safe::getInstance();
		$inst->setCredentials($user, $password);
		$inst->store();
	}
	
	public static function clearCredentials(){
		$inst = AJXP_Safe::getInstance();
		$inst->clear();
	}
	
	public static function loadCredentials(){
		$inst = AJXP_Safe::getInstance();
		$inst->load();
		return $inst->getCredentials();
	}
	
	/**
	 * 
	 * @param array $parsedUrl
	 * @param Repository $repository
	 * @param string $optionsPrefix
	 */
	public static function tryLoadingCredentialsFromSources($parsedUrl, $repository, $optionsPrefix = ""){
		$user = $password = "";
		// Get USER/PASS
		// 1. Try from URL
		if(isSet($parsedUrl["user"]) && isset($parsedUrl["pass"])){
			$user = $parsedUrl["user"];
			$password = $parsedUrl["pass"];			
		}
		// 2. Try from user wallet
		if($user==""){
			$loggedUser = AuthService::getLoggedUser();
			if($loggedUser != null){
				$wallet = $loggedUser->getPref("AJXP_WALLET");
				if(is_array($wallet) && isSet($wallet[$repository->getId()][$optionsPrefix."USER"])){
					$user = $wallet[$repository->getId()][$optionsPrefix."USER"];
					$password = $loggedUser->decodeUserPassword($wallet[$repository->getId()][$optionsPrefix."PASS"]);
				}
			}
		}
		// 3. Try from repository config
		if($user==""){
			$user = $repository->getOption($optionsPrefix."USER");
			$password = $repository->getOption($optionsPrefix."PASS");
		}
		// 4. Try from session		
		
		if($user=="" && $repository->getOption("USE_SESSION_CREDENTIALS")){
			$safeCred = AJXP_Safe::loadCredentials();
			if($safeCred !== false){			
				$user = $safeCred["user"];
				$password = $safeCred["password"];
			}
		}
		
		return array("user" => $user, "password" => $password);
		
	}
	
}

?>