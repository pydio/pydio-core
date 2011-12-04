<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.core
 */
/**
 * AjaXplorer implementation of the EZC WebDAV Authenticator
 */
class AJXP_WebdavAuth implements ezcWebdavBasicAuthenticator, ezcWebdavDigestAuthenticator, ezcWebdavAuthorizer, ezcWebdavLockAuthorizer
{
	
    protected $repositoryId;
    protected $currentUser;
    protected $currentRead;
    protected $currentWrite;
    private $secretKey;
    
    public function __construct($repositoryId){
    	$this->repositoryId = $repositoryId;
		if(defined('AJXP_SAFE_SECRET_KEY')){
			$this->secretKey = AJXP_SAFE_SECRET_KEY;
		}else{
			$this->secretKey = "\1CDAFx¨op#";
		}    	
    }
    
    protected function updateCurrentUserRights($user){
    	if(!$user->canSwitchTo($this->repositoryId)){
    		return false;
    	}
    	$this->currentUser = $user;
    	return true;
    }

    public function authenticateAnonymous( ezcWebdavAnonymousAuth $data )
    {
    	if(!ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")){
	        return false;
    	}
    	AuthService::logUser(null, null);
    	return $this->updateCurrentUserRights(AuthService::getLoggedUser());
    }

    public function authenticateBasic( ezcWebdavBasicAuth $data )
    {
    	return $this->_performAuthentication($data, "BASIC");
    }
        
    /**
     * Checks authentication for the given $data.
     *
     * This method performs authentication as defined by the HTTP Digest
     * authentication mechanism. The received struct contains all information
     * necessary.
     *
     * If authentication succeeded true is returned, otherwise false.
     *
     * You can use {@link checkDigest()} to perform the actual digest
     * calculation and compare it to the response field.
     * 
     * @param ezcWebdavDigestAuth $data 
     * @return bool
     */
    public function authenticateDigest( ezcWebdavDigestAuth $data ){
    	
    	return $this->_performAuthentication($data, "DIGEST");
    }
    
    protected function _performAuthentication($data, $method = "BASIC"){
    	if(!AuthService::userExists($data->username)){
    		AJXP_Logger::debug("not exists! ".$data->username);
    		return false;
    	}
    	$confDriver = ConfService::getConfStorageImpl();
    	$user = $confDriver->createUserObject($data->username);
    	$webdavData = $user->getPref("AJXP_WEBDAV_DATA");
    	if(empty($webdavData) || !isset($webdavData["ACTIVE"]) || $webdavData["ACTIVE"] !== true || !isSet($webdavData["PASS"])){
    		return false;
    	}
    	//$webdavData = array("PASS" => $this->_encodePassword("admin", "admin"));
    	
    	$passCheck = false;
    	if($method == "BASIC"){
			if ($this->_decodePassword($webdavData["PASS"], $data->username) == $data->password){
				$passCheck = true;
			}
    	}else if($method == "DIGEST"){
    		$passCheck = $this->checkDigest($data, $this->_decodePassword($webdavData["PASS"], $data->username));
    	}
    	
    	if($passCheck){
    		AuthService::logUser($data->username, null, true);
    		$res = $this->updateCurrentUserRights(AuthService::getLoggedUser());
    		if($res === false){
    			return false;
    		}
    		if(ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")){
    			AJXP_Safe::storeCredentials($data->username, $this->_decodePassword($webdavData["PASS"], $data->username));
    		}
    		return true;
    	}else{
    		return false;
    	}
    	
    }

    public function authorize( $user, $path, $access = ezcWebdavAuthorizer::ACCESS_READ )
    {    	
        if ( $access === ezcWebdavAuthorizer::ACCESS_READ )
        {        	
        	if(!isSet($this->currentRead)){
	        	$this->currentRead = $this->currentUser->canRead($this->repositoryId);
        	}
            return ( $this->currentRead );
        }
        else if( $access === ezcWebdavAuthorizer::ACCESS_WRITE )
        {
        	if(!isSet($this->currentWrite)){
	        	$this->currentWrite = $this->currentUser->canWrite($this->repositoryId);
        	}        	
        	return ( $this->currentWrite );       	
        }
        return false;
    }
    
    
    /**
     * Calculates the digest according to $data and $password and checks it.
     *
     * This method receives digest data in $data and a plain text $password for
     * the digest user. It automatically calculates the digest and veryfies it
     * against the $response property of $data.
     *
     * The method returns true, if the digest matched the response, otherwise
     * false.
     *
     * Use this helper method to avoid manually calculating the digest
     * yourself. The submitted $data should be received by {@link
     * authenticateDigest()} and the $password should be read from your
     * authentication back end.
     *
     * For security reasons it is recommended to calculate and verify the
     * digest somewhere else (e.g. in a stored procedure in your database),
     * without loading it as plain text into PHP memory.
     * 
     * @param ezcWebdavDigestAuth $data 
     * @param string $password 
     * @return bool
     */
    protected function checkDigest( ezcWebdavDigestAuth $data, $password )
    {
        $ha1 = md5( "{$data->username}:{$data->realm}:{$password}" );
        $ha2 = md5( "{$data->requestMethod}:{$data->uri}" );

        $digest = null;
        if ( !empty( $data->nonceCount ) && !empty( $data->clientNonce ) && !empty( $data->qualityOfProtection ) )
        {
            // New digest (RFC 2617)
            $digest = md5(
                "{$ha1}:{$data->nonce}:{$data->nonceCount}:{$data->clientNonce}:{$data->qualityOfProtection}:{$ha2}"
            );
        }
        else
        {
            // Old digest (RFC 2069)
            $digest = md5( "{$ha1}:{$data->nonce}:{$ha2}" );
        }

        return $digest === $data->response;
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
	
    /**
     * Assign a $lockToken to a given $user.
     *
     * The authorization backend needs to save an arbitrary number of lock
     * tokens per user. A lock token is a of maximum length 255
     * containing:
     *
     * <ul>
     *  <li>characters</li>
     *  <li>numbers</li>
     *  <li>dashes (-)</li>
     * </ul>
     * 
     * @param string $user 
     * @param string $lockToken 
     * @return void
     */
    public function assignLock( $user, $lockToken ){}

    /**
     * Returns if the given $lockToken is owned by the given $user.
     *
     * Returns true, if the $lockToken is owned by $user, false otherwise.
     * 
     * @param string $user 
     * @param string $lockToken 
     * @return bool
     */
    public function ownsLock( $user, $lockToken ){return true;}
    
    /**
     * Removes the assignement of $lockToken from $user.
     *
     * After a $lockToken has been released from the $user, the {@link
     * ownsLock()} method must return false for the given combination. It might
     * happen, that a lock is to be released, which already has been removed.
     * This case must be ignored by the method.
     * 
     * @param string $user 
     * @param string $lockToken 
     */
    public function releaseLock( $user, $lockToken ){}	
    
}

?>