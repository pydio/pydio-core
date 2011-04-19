<?php

class AJXP_WebdavAuth implements ezcWebdavBasicAuthenticator, ezcWebdavAuthorizer
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

    public function authorize( $user, $path, $access = ezcWebdavAuthorizer::ACCESS_READ )
    {
        if ( $access === ezcWebdavAuthorizer::ACCESS_READ )
        {
            return ( $this->currentUser->canRead($this->repositoryId) );
        }
        else if( $access === ezcWebdavAuthorizer::ACCESS_WRITE )
        {
            return ( $this->currentUser->canWrite($this->repositoryId) );       	
        }
        return false;
    }
}

?>