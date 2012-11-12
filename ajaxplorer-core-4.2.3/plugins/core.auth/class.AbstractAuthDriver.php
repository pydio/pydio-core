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
 * @package info.ajaxplorer.auth
 * @class AbstractAuthDriver
 * Abstract representation of an authentication driver. Must be implemented by the auth plugin
 */
class AbstractAuthDriver extends AJXP_Plugin {
	
	var $options;
	var $driverName = "abstract";
	var $driverType = "auth";
					
	public function switchAction($action, $httpVars, $fileVars)	{
		if(!isSet($this->actions[$action])) return;
		$mess = ConfService::getMessages();
		
		switch ($action){

            case "login" :

                if(!AuthService::usersEnabled()) return;
                $rememberLogin = "";
                $rememberPass = "";
                $secureToken = "";
                $loggedUser = null;
                include_once(AJXP_BIN_FOLDER."/class.CaptchaProvider.php");
        		if(AuthService::suspectBruteForceLogin() && (!isSet($httpVars["captcha_code"]) || !CaptchaProvider::checkCaptchaResult($httpVars["captcha_code"]))){
        			$loggingResult = -4;
        		}else{
        			$userId = (isSet($httpVars["userid"])?$httpVars["userid"]:null);
        			$userPass = (isSet($httpVars["password"])?$httpVars["password"]:null);
        			$rememberMe = ((isSet($httpVars["remember_me"]) && $httpVars["remember_me"] == "true")?true:false);
        			$cookieLogin = (isSet($httpVars["cookie_login"])?true:false);
        			$loggingResult = AuthService::logUser($userId, $userPass, false, $cookieLogin, $httpVars["login_seed"]);
        			if($rememberMe && $loggingResult == 1){
        				$rememberLogin = "notify";
        				$rememberPass = "notify";
        				$loggedUser = AuthService::getLoggedUser();
        			}
        			if($loggingResult == 1){
        				session_regenerate_id(true);
        				$secureToken = AuthService::generateSecureToken();
        			}
        			if($loggingResult < 1 && AuthService::suspectBruteForceLogin()){
        				$loggingResult = -4; // Force captcha reload
        			}
        		}
                $loggedUser = AuthService::getLoggedUser();
                if($loggedUser != null)
               	{
                       $force = $loggedUser->getPref("force_default_repository");
                       $passId = -1;
                       if(isSet($httpVars["tmp_repository_id"])){
                           $passId = $httpVars["tmp_repository_id"];
                       }else if($force != "" && $loggedUser->canSwitchTo($force) && !isSet($httpVars["tmp_repository_id"])){
                           $passId = $force;
                       }
                       $res = ConfService::switchUserToActiveRepository($loggedUser, $passId);
                       if(!$res){
                           AuthService::disconnect();
                           $loggingResult = -3;
                       }
               	}

                if($loggedUser != null && (AuthService::hasRememberCookie() || (isSet($rememberMe) && $rememberMe ==true))){
                    AuthService::refreshRememberCookie($loggedUser);
                }
        		AJXP_XMLWriter::header();
        		AJXP_XMLWriter::loggingResult($loggingResult, $rememberLogin, $rememberPass, $secureToken);
        		AJXP_XMLWriter::close();


            break;

			//------------------------------------
			//	CHANGE USER PASSWORD
			//------------------------------------	
			case "pass_change":
							
				$userObject = AuthService::getLoggedUser();
				if($userObject == null || $userObject->getId() == "guest"){
					header("Content-Type:text/plain");
					print "SUCCESS";
                    break;
				}
				$oldPass = $httpVars["old_pass"];
				$newPass = $httpVars["new_pass"];
				$passSeed = $httpVars["pass_seed"];
				if(strlen($newPass) < ConfService::getCoreConf("PASSWORD_MINLENGTH", "auth")){
					header("Content-Type:text/plain");
					print "PASS_ERROR";
                    break;
				}
				if(AuthService::checkPassword($userObject->getId(), $oldPass, false, $passSeed)){
					AuthService::updatePassword($userObject->getId(), $newPass);
				}else{
					header("Content-Type:text/plain");
					print "PASS_ERROR";
                    break;
				}
				header("Content-Type:text/plain");
				print "SUCCESS";
				
			break;					

            case "logout" :

                AuthService::disconnect();
                $loggingResult = 2;
                session_destroy();
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::loggingResult($loggingResult, null, null, null);
                AJXP_XMLWriter::close();


            break;

            case "get_seed" :
                $seed = AuthService::generateSeed();
                if(AuthService::suspectBruteForceLogin()){
                    HTMLWriter::charsetHeader('application/json');
                    print json_encode(array("seed" => $seed, "captcha" => true));
                }else{
                    HTMLWriter::charsetHeader("text/plain");
                    print $seed;
                }
                //exit(0);
            break;

            case "get_secure_token" :
                HTMLWriter::charsetHeader("text/plain");
                print AuthService::generateSecureToken();
                //exit(0);
            break;

            case "get_captcha":
                include_once(AJXP_BIN_FOLDER."/class.CaptchaProvider.php");
                CaptchaProvider::sendCaptcha();
                //exit(0) ;
            break;

            case "back":
                AJXP_XMLWriter::header("url");
                  echo AuthService::getLogoutAddress(false);
                  AJXP_XMLWriter::close("url");
                //exit(1);

            break;

			default;
			break;
		}				
		return "";
	}
	
	
	public function getRegistryContributions( $extendedVersion = true ){
        if(!$extendedVersion) return $this->registryContributions;
        
		$logged = AuthService::getLoggedUser();
        if(AuthService::usersEnabled()) {
            if($logged == null){
                return $this->registryContributions;
            }else{
                $xmlString = AJXP_XMLWriter::getUserXml($logged, false);
            }
        }else{
            $xmlString = AJXP_XMLWriter::getUserXml(null, false);
        }
		$dom = new DOMDocument();
		$dom->loadXML($xmlString);
		$this->registryContributions[]=$dom->documentElement;				
		return $this->registryContributions;
	}
	
	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if($contribNode->nodeName != "actions") return ;
		if(AuthService::usersEnabled() && $this->passwordsEditable()) return ;
		// Disable password change action
		$actionXpath=new DOMXPath($contribNode->ownerDocument);
		$passChangeNodeList = $actionXpath->query('action[@name="pass_change"]', $contribNode);
		if(!$passChangeNodeList->length) return ;
		unset($this->actions["pass_change"]);
		$passChangeNode = $passChangeNodeList->item(0);
		$contribNode->removeChild($passChangeNode);
	}
	
	function preLogUser($sessionId){}	

    function supportsUsersPagination(){
        return false;
    }
    function listUsersPaginated($regexp, $offset, $limit){
        return $this->listUsers();
    }
    function getUsersCount(){
        return -1;
    }

    /**
     * @return Array
     */
	function listUsers(){}
    /**
     * @param $login
     * @return boolean
     */
	function userExists($login){}	
	function checkPassword($login, $pass, $seed){}
	function createCookieString($login){}


	function usersEditable(){}
	function passwordsEditable(){}
	
	function createUser($login, $passwd){}	
	function changePassword($login, $newPass){}	
	function deleteUser($login){}

    function supportsAuthSchemes(){
        return false;
    }
    /**
     * @param $login
     * @return String
     */
    function getAuthScheme($login){
        return null;
    }

	function getLoginRedirect(){
		if(isSet($this->options["LOGIN_REDIRECT"])){
			return $this->options["LOGIN_REDIRECT"];
		}else{
			return false;
		}
	}

	function getLogoutRedirect(){
        return false;
    }
	
	function getOption($optionName){	
		return (isSet($this->options[$optionName])?$this->options[$optionName]:"");	
	}
	
	function isAjxpAdmin($login){
		return ($this->getOption("AJXP_ADMIN_LOGIN") === $login);
	}
	
	function autoCreateUser(){
		$opt = $this->getOption("AUTOCREATE_AJXPUSER");
		if($opt === true) return true;
		return false;
	}

	function getSeed($new=true){
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true) return -1;
		if($new){
			$seed = md5(time());
			$_SESSION["AJXP_CURRENT_SEED"] = $seed;	
			return $seed;		
		}else{
			return (isSet($_SESSION["AJXP_CURRENT_SEED"])?$_SESSION["AJXP_CURRENT_SEED"]:0);
		}
	}	
	
	function filterCredentials($userId, $pwd){
		return array($userId, $pwd);
	}
		
}
?>