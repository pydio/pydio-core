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
 *
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * AJXP_Plugin to access a dropbox account
 */
class dropboxAccessDriver extends fsAccessDriver
{
	/**
	* @var Repository
	*/
	public $repository;
	public $driverConf;
	protected $wrapperClassName;
	protected $urlBase;
		
	function initRepository(){
		if(is_array($this->pluginConf)){
			$this->driverConf = $this->pluginConf;
		}else{
			$this->driverConf = array();
		}
				
		$wrapperData = $this->detectStreamWrapper(true);
		AJXP_Logger::debug("Detected wrapper data", $wrapperData);
		$this->wrapperClassName = $wrapperData["classname"];
		$this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();

        $consumerKey = $this->repository->getOption("CONSUMER_KEY");
        $consumerSecret = $this->repository->getOption("CONSUMER_SECRET");
        $oauth = new Dropbox_OAuth_PEAR($consumerKey, $consumerSecret);

        // TOKENS IN SESSION?
        if(!empty($_SESSION["OAUTH_DROPBOX_TOKENS"])) return;

        // TOKENS IN FILE ?
        $tokens = $this->getTokens($this->repository->getId());
        if(!empty($tokens)){
            $_SESSION["OAUTH_DROPBOX_TOKENS"] = $tokens;
            return;
        }

        // OAUTH NEGOCIATION
        if (isset($_SESSION['DROPBOX_NEGOCIATION_STATE'])) {
            $state = $_SESSION['DROPBOX_NEGOCIATION_STATE'];
        } else {
            $state = 1;
        }
        switch($state) {

            case 1 :
                $tokens = $oauth->getRequestToken();
                //print_r($tokens);

                // Note that if you want the user to automatically redirect back, you can
                // add the 'callback' argument to getAuthorizeUrl.
                //echo "Step 2: You must now redirect the user to:\n";
                $_SESSION['DROPBOX_NEGOCIATION_STATE'] = 2;
                $_SESSION['oauth_tokens'] = $tokens;
                throw new Exception("Please go to <a style=\"text-decoration:underline;\" target=\"_blank\" href=\"".$oauth->getAuthorizeUrl()."\">".$oauth->getAuthorizeUrl()."</a> to authorize the access to your dropbox. Then try again to switch to this repository.");

            case 2 :
                $oauth->setToken($_SESSION['oauth_tokens']);
                $tokens = $oauth->getAccessToken();
                $_SESSION['DROPBOX_NEGOCIATION_STATE'] = 3;
                $_SESSION['OAUTH_DROPBOX_TOKENS'] = $tokens;
                $this->setTokens($this->repository->getId(), $tokens);
                return;
        }

        throw new Exception("Impossible to find the tokens for accessing the dropbox repository");

	}
	
	function performChecks(){
		if(!AJXP_Utils::searchIncludePath('HTTP/OAuth/Consumer.php')){
			throw new Exception("The PEAR HTTP_OAuth package must be installed!");
		}
	}
	
	function isWriteable($dir, $type = "dir"){
		return true;
	}

    function getTokens($repositoryId){
        return AJXP_Utils::loadSerialFile(AJXP_DATA_PATH."/plugins/access.dropbox/".$repositoryId."_tokens");
    }
    function setTokens($repositoryId, $oauth_tokens){
        return AJXP_Utils::saveSerialFile(AJXP_DATA_PATH."/plugins/access.dropbox/".$repositoryId."_tokens", $oauth_tokens, true);
    }

}

?>