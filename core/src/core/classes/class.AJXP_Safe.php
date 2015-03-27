<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <http://pyd.io/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Credential keeper that can be stored in the session, the credentials are kept crypted.
 * @package Pydio
 * @subpackage Core
 */
class AJXP_Safe
{
    private static $instance;

    private $user;
    private $encodedPassword;
    private $secretKey;
    private $separator = "__SAFE_SEPARATOR__";
    private $forceSessionCredentials = false;
    /**
     * Instance constructor
     */
    public function __construct()
    {
        if (defined('AJXP_SAFE_SECRET_KEY')) {
            $this->secretKey = AJXP_SAFE_SECRET_KEY;
        } else {
            $this->secretKey = "\1CDAFx¨op#";
        }
    }
    /**
     * Store the user/password pair. Password will be encoded
     * @param string $user
     * @param string $password
     * @return void
     */
    public function setCredentials($user, $password)
    {
        $this->user = $user;
        $this->encodedPassword = $this->_encodePassword($password, $user);
    }
    /**
     * Return the user/password pair, or false if cannot find it.
     * @return array|bool
     */
    public function getCredentials()
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
        if (function_exists('mcrypt_encrypt')) {
            // We encode as base64 so if we need to store the result in a database, it can be stored in text column
            $password = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($user.$this->secretKey), $password, MCRYPT_MODE_ECB));
        }
        return $password;
    }
    /**
     * Use mcrypt functions to decode the password
     * @param $encoded
     * @param $user
     * @return string
     */
    private function _decodePassword($encoded, $user)
    {
        if (function_exists('mcrypt_decrypt')) {
             // We have encoded as base64 so if we need to store the result in a database, it can be stored in text column
             $encoded = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($user.$this->secretKey), base64_decode($encoded), MCRYPT_MODE_ECB), "\0");
        }
        return $encoded;
    }
    /**
     * Store the password credentials in the session
     * @return void
     */
    public function store()
    {
        $_SESSION["AJXP_SAFE_CREDENTIALS"] = base64_encode($this->user.$this->separator.$this->encodedPassword);
    }

    /**
     * Load the credentials from session
     * @param string $encodedString
     * @return void
     */
    public function load($encodedString = "")
    {
        if ($encodedString == "" && !empty($_SESSION["AJXP_SAFE_CREDENTIALS"])) {
            $encodedString = $_SESSION["AJXP_SAFE_CREDENTIALS"];
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
    public function clear()
    {
        unset($_SESSION["AJXP_SAFE_CREDENTIALS"]);
        $this->user = null;
        $this->encodedPassword = null;
    }
    /**
     * For the session credentials to override other credentials set via config
     * @return void
     */
    public function forceSessionCredentialsUsage()
    {
        $this->forceSessionCredentials = true;
    }



    /**
     * Creates the singleton instance
     * @return AJXP_Safe
     */
    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new AJXP_Safe();
        }
        return self::$instance;
    }
    /**
     * Store the user/pass key pair
     * @static
     * @param string $user
     * @param string $password
     * @return void
     */
    public static function storeCredentials($user, $password)
    {
        $inst = AJXP_Safe::getInstance();
        $inst->setCredentials($user, $password);
        $inst->store();
    }
    /**
     * Remove the user/pass encoded from the session
     * @static
     * @return void
     */
    public static function clearCredentials()
    {
        $inst = AJXP_Safe::getInstance();
        $inst->clear();
    }
    /**
     * Retrieve the user/pass from the session
     * @static
     * @return array|bool
     */
    public static function loadCredentials()
    {
        $inst = AJXP_Safe::getInstance();
        $inst->load();
        return $inst->getCredentials();
    }

    public static function getEncodedCredentialString()
    {
        return $_SESSION["AJXP_SAFE_CREDENTIALS"];
    }

    public static function getCredentialsFromEncodedString($encoded)
    {
        $tmpInstance = new AJXP_Safe();
        $tmpInstance->load($encoded);
        return $tmpInstance->getCredentials();
    }

    /**
     * Will try to get the credentials for a given repository as follow :
     * + Try to get the credentials from the url parsing
     * + Try to get them from the user "Wallet" (personal data)
     * + Try to get them from the repository configuration
     * + Try to get them from the AJXP_Safe.
     *
     * @param array $parsedUrl
     * @param Repository $repository
     * @return array
     */
    public static function tryLoadingCredentialsFromSources($parsedUrl, $repository)
    {
        $user = $password = "";
        $optionsPrefix = "";
        if ($repository->getAccessType() == "ftp") {
            $optionsPrefix = "FTP_";
        }
        // Get USER/PASS
        // 1. Try from URL
        if (isSet($parsedUrl["user"]) && isset($parsedUrl["pass"])) {
            $user = rawurldecode($parsedUrl["user"]);
            $password = rawurldecode($parsedUrl["pass"]);
        }
        // 2. Try from user wallet
        if ($user=="") {
            $loggedUser = AuthService::getLoggedUser();
            if ($loggedUser != null) {
                $wallet = $loggedUser->getPref("AJXP_WALLET");
                if (is_array($wallet) && isSet($wallet[$repository->getId()][$optionsPrefix."USER"])) {
                    $user = $wallet[$repository->getId()][$optionsPrefix."USER"];
                    $password = AJXP_Utils::decypherStandardFormPassword($loggedUser->getId(), $wallet[$repository->getId()][$optionsPrefix."PASS"]);
                }
            }
        }
        // 2bis. Wallet is now a custom parameter
        if ($user =="") {
            $loggedUser = AuthService::getLoggedUser();
            if ($loggedUser != null) {
                $u = $loggedUser->mergedRole->filterParameterValue("access.".$repository->getAccessType(), $optionsPrefix."USER", $repository->getId(), "");
                $p = $loggedUser->mergedRole->filterParameterValue("access.".$repository->getAccessType(), $optionsPrefix."PASS", $repository->getId(), "");
                if (!empty($u) && !empty($p)) {
                    $user = $u;
                    $password = AJXP_Utils::decypherStandardFormPassword($loggedUser->getId(), $p);
                }
            }
        }
        // 3. Try from repository config
        if ($user=="") {
            $user = $repository->getOption($optionsPrefix."USER");
            $password = $repository->getOption($optionsPrefix."PASS");
        }
        // 4. Test if there are encoded credentials available
        if ($user == "" && $repository->getOption("ENCODED_CREDENTIALS") != "") {
            list($user, $password) = AJXP_Safe::getCredentialsFromEncodedString($repository->getOption("ENCODED_CREDENTIALS"));
        }
        // 5. Try from session
        $storeCreds = false;
        if ($repository->getOption("META_SOURCES")) {
            $options["META_SOURCES"] = $repository->getOption("META_SOURCES");
            foreach ($options["META_SOURCES"] as $metaSource) {
                if (isSet($metaSource["USE_SESSION_CREDENTIALS"]) && $metaSource["USE_SESSION_CREDENTIALS"] === true) {
                    $storeCreds = true;
                    break;
                }
            }
        }
        if ($user=="" && ( $repository->getOption("USE_SESSION_CREDENTIALS") || $storeCreds || self::getInstance()->forceSessionCredentials )) {
            $safeCred = AJXP_Safe::loadCredentials();
            if ($safeCred !== false) {
                $user = $safeCred["user"];
                $password = $safeCred["password"];
            }
        }
        return array("user" => $user, "password" => $password);

    }

}
