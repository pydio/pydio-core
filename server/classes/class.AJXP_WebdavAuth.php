<?php

class AJXP_WebdavAuth implements ezcWebdavBasicAuthenticator, ezcWebdavDigestAuthenticator, ezcWebdavAuthorizer
{
	
    protected $repositoryId;
    protected $currentUser;
    
    public function __construct($repositoryId){
    	$this->repositoryId = $repositoryId;
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
    	if(!ALLOW_GUEST_BROWSING){
	        return false;
    	}
    	AuthService::logUser(null, null);
    	return $this->updateCurrentUserRights(AuthService::getLoggedUser());
    }

    public function authenticateBasic( ezcWebdavBasicAuth $data )
    {
    	$res = AuthService::logUser($data->username, $data->password, false, false, -1);
    	if($res != 1) {
    		return false;
    	}
    	else {
    		return $this->updateCurrentUserRights(AuthService::getLoggedUser());
    	}
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
    	if(!AuthService::userExists($data->username)){
    		return false;
    	}
    	$passCheck = $this->checkDigest($data, "admin");
    	if($passCheck){
    		AuthService::logUser($data->username, null, true);
    		return $this->updateCurrentUserRights(AuthService::getLoggedUser());
    	}else{
    		return false;
    	}
    }
    

    public function authorize( $user, $path, $access = ezcWebdavAuthorizer::ACCESS_READ )
    {
        if ( $access === ezcWebdavAuthorizer::ACCESS_READ )
        {
        	$res = $this->currentUser->canRead($this->repositoryId);
        	AJXP_Logger::debug("Authorize read ? $res ".$user.$path);
            return ( $this->currentUser->canRead($this->repositoryId) );
        }
        else if( $access === ezcWebdavAuthorizer::ACCESS_WRITE )
        {
        	$res = $this->currentUser->canWrite($this->repositoryId);
        	AJXP_Logger::debug("Authorize write ? $res ".$user.$path);
        	return ( $this->currentUser->canWrite($this->repositoryId) );       	
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
    
}

?>