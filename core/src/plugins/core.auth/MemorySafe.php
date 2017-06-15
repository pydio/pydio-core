<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Auth\Core;

use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Utils\Crypto;
use Pydio\Core\Utils\Vars\OptionsHelper;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Credential keeper that can be stored in the session, the credentials are kept crypted.
 * @package Pydio
 * @subpackage Core
 */
class MemorySafe
{

    const SAFE_CREDENTIALS_KEY = "PYDIO_SAFE_CREDENTIALS";

    private static $instances;
    private static $cache;

    private $instanceId = "";
    private $user;
    private $encodedPassword;
    private $secretKey;
    private $separator = "__SAFE_SEPARATOR__";
    private $forceSessionCredentials = false;

    /**
     * Instance constructor
     * @param string $instanceId
     */
    public function __construct($instanceId = "")
    {
        $this->secretKey = Crypto::getApplicationSecret();
        $this->instanceId = $instanceId;
    }

    /**
     * @return null|string
     */
    public function getEncodedCredentials(){
        return SessionService::fetch(self::SAFE_CREDENTIALS_KEY.$this->instanceId);
    }

    /**********************/
    /* STATIC ENV METHODS */
    /**********************/
    /**
     * @param ContextInterface $ctx
     * @return bool|string FALSE if no need, or String (warning, it can be an empty string) if instance needed.
     */
    public static function contextUsesInstance($ctx){
        if ($ctx->hasRepository() && $ctx->getRepository()->getContextOption($ctx, "USE_SESSION_CREDENTIALS")) {
            $instanceId = self::getInstanceId($ctx);
            if (empty($instanceId)) $instanceId = "";
            return $instanceId;
        }
        return false;
    }

    /**
     * @param ContextInterface $ctx
     * @return string return instanceId (e.g "authfront.cas")
     */
    public static function getInstanceId(ContextInterface $ctx){
        $instanceId = $ctx->getRepository()->getContextOption($ctx, "SESSION_CREDENTIALS_AUTHFRONT", null);
        if(!empty($instanceId)) return $instanceId;
        $loggedUser = $ctx->getUser();
        if ($loggedUser != null) {
            $repository = $ctx->getRepository();
            $instanceId = $loggedUser->getMergedRole()->filterParameterValue("access.".$repository->getAccessType(), "SESSION_CREDENTIALS_AUTHFRONT", $repository->getId(), "");
        }
        if (empty($instanceId)) $instanceId = "";
        return $instanceId;
    }

    /**
     * @param ContextInterface $ctx
     * @return bool
     */
    public static function setEnvForContext($ctx){
        $instanceId = self::contextUsesInstance($ctx);
        if($instanceId !== false){
            return self::setEnv($instanceId);
        }
        return false;
    }
    /**
     * Set the encrypted string in the environment for running a CLI.
     * @param string
     * @return bool
     */
    public static function setEnv($instanceId = ""){
        // Pass Default Instance Credentials
        $encodedCreds = self::getInstance($instanceId)->getEncodedCredentials();
        if (!empty($encodedCreds)) {
            putenv(self::SAFE_CREDENTIALS_KEY. "=" . $encodedCreds);
            return true;
        }
        return false;
    }
    /**
     * Clear the environment variable
     */
    public static function clearEnv(){
        putenv(self::SAFE_CREDENTIALS_KEY);
    }
    /**
     * Try to load encrypted string, decode, and get password if the user is corresponding.
     * @param string $userId
     * @return bool|mixed
     */
    public static function loadPasswordStringFromEnvironment($userId){
        $env = getenv(self::SAFE_CREDENTIALS_KEY);
        if(!empty($env)){
            $array = self::getCredentialsFromEncodedString($env);
            if(isSet($array["user"]) && $array["user"] == $userId){
                return $array["password"];
            }
        }
        return false;
    }

    /**
     * For the session credentials to override other credentials set via config
     * @return void
     */
    public function forceSessionCredentialsUsage()
    {
        $this->forceSessionCredentials = true;
    }

    /****************************/
    /* STATIC INSTANCE METHODS  */
    /****************************/
    /**
     * Creates the singleton instance
     * @param string $instanceId
     * @return MemorySafe
     */
    public static function getInstance($instanceId = '')
    {
        $instanceKey = $instanceId;
        if(empty($instanceKey)) {
            $instanceKey = '__DEFAULT__';
        }
        if (empty(self::$instances)) {
            self::$instances = [];
        }
        if(!isSet(self::$instances[$instanceKey])){
            self::$instances[$instanceKey] = new MemorySafe($instanceId);
        }
        return self::$instances[$instanceKey];
    }
    /**
     * Store the user/pass key pair
     * @static
     * @param string $user
     * @param string $password
     * @param string $instanceId
     * @return void
     */
    public static function storeCredentials($user, $password, $instanceId = '')
    {
        $inst = MemorySafe::getInstance($instanceId);
        $inst->setCredentials($user, $password);
        $inst->store();
    }
    /**
     * Remove the user/pass encoded from the session
     * @param string $instanceId
     * @static
     * @return void
     */
    public static function clearCredentials($instanceId = '')
    {
        $inst = MemorySafe::getInstance($instanceId);
        $inst->clear();
    }
    /**
     * Retrieve the user/pass from the session
     * @param string $instanceId
     * @static
     * @return array|bool
     */
    public static function loadCredentials($instanceId = '')
    {
        $inst = MemorySafe::getInstance($instanceId);
        $inst->load();
        return $inst->getCredentials();
    }


    /**
     * @param $encoded
     * @return array|bool
     */
    public static function getCredentialsFromEncodedString($encoded)
    {
        $tmpInstance = new MemorySafe();
        $tmpInstance->load($encoded);
        return $tmpInstance->getCredentials();
    }
    /**
     * Will try to get the credentials for a given repository as follow :
     * + Try to get the credentials from the url parsing
     * + Try to get them from the user merged role
     * + Try to get them from the repository configuration
     * + Try to get them from the MemorySafe.
     *
     * @param ContextInterface $ctx
     * @return array
     */
    public static function tryLoadingCredentialsFromSources($ctx)
    {
        $user = $password = "";
        $optionsPrefix = "";
        $repository = $ctx->getRepository();
        
        $instanceId = self::getInstanceId($ctx);
        $instanceId = empty($instanceId) ? "" : $instanceId;
        $instanceKey = $instanceId;
        if(empty($instanceKey)) {
            $instanceKey = '__DEFAULT__';
        }

    	$cache = self::$cache;
        if (isset($cache[$instanceKey])) {
            return $cache[$instanceKey];
        }

        if ($repository->getAccessType() == "ftp") {
            $optionsPrefix = "FTP_";
        }
        // 1. Look in Role parameters
        if ($user =="") {
            $loggedUser = $ctx->getUser();
            if ($loggedUser != null) {
                $u = $loggedUser->getMergedRole()->filterParameterValue("access.".$repository->getAccessType(), $optionsPrefix."USER", $repository->getId(), "");
                $p = $loggedUser->getMergedRole()->filterParameterValue("access.".$repository->getAccessType(), $optionsPrefix."PASS", $repository->getId(), "");
                if (!empty($u) && !empty($p)) {
                    $user = $u;
                    $password = OptionsHelper::decypherStandardFormPassword($loggedUser->getId(), $p);
                }
            }
        }
        // 2. Try from repository config
        if ($user=="") {
            $user       = $repository->getContextOption($ctx, $optionsPrefix."USER");
            $password   = $repository->getContextOption($ctx, $optionsPrefix."PASS");
        }
        // 3. Test if there are encoded credentials available
        if ($user == "" && $repository->getContextOption($ctx, "ENCODED_CREDENTIALS") != "") {
            list($user, $password) = MemorySafe::getCredentialsFromEncodedString($repository->getContextOption($ctx, "ENCODED_CREDENTIALS"));
        }
        // 4. Try from session
        $storeCreds = false;
        if ($repository->getContextOption($ctx, "META_SOURCES")) {
            $options["META_SOURCES"] = $repository->getContextOption($ctx, "META_SOURCES");
            foreach ($options["META_SOURCES"] as $metaSource) {
                if (isSet($metaSource["USE_SESSION_CREDENTIALS"]) && $metaSource["USE_SESSION_CREDENTIALS"] === true) {
                    $storeCreds = true;
                    break;
                }
            }
        }
        if ($user=="" && ( $repository->getContextOption($ctx, "USE_SESSION_CREDENTIALS") || $storeCreds || self::getInstance()->forceSessionCredentials )) {
            $safeCred = MemorySafe::loadCredentials($instanceId);
            if ($safeCred !== false) {
                $user = $safeCred["user"];
                $password = $safeCred["password"];
            }
        }

        $res = ["user" => $user, "password" => $password];

        // Storing to local cache when we have a result
        if ($user != "") {
            self::$cache[$instanceKey] = $res;
        }

        return $res;
    }

    /*******************/
    /* PRIVATE METHODS */
    /*******************/

    /**
     * Store the user/password pair. Password will be encoded
     * @param string $user
     * @param string $password
     * @return void
     */
    private function setCredentials($user, $password)
    {
        $this->user = $user;
        $this->encodedPassword = $this->_encodePassword($password, $user);
    }
    /**
     * Return the user/password pair, or false if cannot find it.
     * @return array|bool
     */
    private function getCredentials()
    {
        if (isSet($this->user) && isSet($this->encodedPassword)) {
            $decoded = $this->_decodePassword($this->encodedPassword, $this->user);
            return array(
                "user" 		=> $this->user,
                "password"	=> $decoded,
                0			=> $this->user,
                1			=> $decoded
            );
        } else {
            return false;
        }
    }
    /**
     * Use mcrypt function to encode the password
     * @param $password
     * @param $user
     * @return string
     */
    private function _encodePassword($password, $user)
    {
        return Crypto::encrypt($password, Crypto::buildKey($user, $this->secretKey));
    }
    /**
     * Use mcrypt functions to decode the password
     * @param $encoded
     * @param $user
     * @return string
     */
    private function _decodePassword($encoded, $user)
    {
        return  Crypto::decrypt($encoded, Crypto::buildKey($user, $this->secretKey, $encoded));
    }
    /**
     * Store the password credentials in the session
     * @return void
     */
    private function store() {
        SessionService::save(self::SAFE_CREDENTIALS_KEY.$this->instanceId, base64_encode($this->user.$this->separator.$this->encodedPassword));
    }
    /**
     * Load the credentials from session
     * @param string $encodedString
     * @return void
     */
    private function load($encodedString = "")
    {
        if ($encodedString == "" && SessionService::has(self::SAFE_CREDENTIALS_KEY.$this->instanceId)) {
            $encodedString = SessionService::fetch(self::SAFE_CREDENTIALS_KEY.$this->instanceId);
        }
        if(empty($encodedString)) return;
        $sessData = base64_decode($encodedString);
        $parts = explode($this->separator, $sessData);
        $this->user = $parts[0];
        $this->encodedPassword = $parts[1];
    }
    /**
     * Remove the credentials from session
     * @return void
     */
    private function clear()
    {
        SessionService::delete(self::SAFE_CREDENTIALS_KEY.$this->instanceId);
        $this->user = null;
        $this->encodedPassword = null;
    }


}
