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


class CypheredAuthFrontend extends AbstractAuthFrontend {

    function detectVar(&$httpVars, $varName){
        if(isSet($httpVars[$varName])) return $httpVars[$varName];
        if(isSet($_SERVER["HTTP_PYDIO_".strtoupper($varName)])) return $_SERVER["HTTP_".strtoupper($varName)];
        return "";
    }

    function getLastKeys(){
        $file = $this->getPluginWorkDir(false)."/last_inc";
        if(!is_file($file)) return array();
        $content = file_get_contents($file);
        if(empty($content)) return array();
        $data = unserialize($content);
        if(is_array($data)) return $data;
        return array();
    }

    function storeLastKeys($data){
        $file = $this->getPluginWorkDir(true)."/last_inc";
        file_put_contents($file, serialize($data));
    }

    /**
     * decrypt AES 256
     *
     * @param string $password
     * @param data $edata
     * @return dencrypted data
     */
    public function decrypt($password, $edata) {
        $data = base64_decode($edata);
        $salt = substr($data, 8, 8);
        $ct = substr($data, 16);
        /**
         * From https://github.com/mdp/gibberish-aes
         *
         * Number of rounds depends on the size of the AES in use
         * 3 rounds for 256
         *        2 rounds for the key, 1 for the IV
         * 2 rounds for 128
         *        1 round for the key, 1 round for the IV
         * 3 rounds for 192 since it's not evenly divided by 128 bits
         */
        $rounds = 3;
        $data00 = $password.$salt;
        $md5_hash = array();
        $md5_hash[0] = md5($data00, true);
        $result = $md5_hash[0];
        for ($i = 1; $i < $rounds; $i++) {
            $md5_hash[$i] = md5($md5_hash[$i - 1].$data00, true);
            $result .= $md5_hash[$i];
        }
        $key = substr($result, 0, 32);
        $iv  = substr($result, 32,16);

        return openssl_decrypt($ct, 'aes-256-cbc', $key, true, $iv);
    }

    /**
     * crypt AES 256
     *
     * @param string $password
     * @param string $data
     * @return string encrypted data
     */
    public function crypt($password, $data) {
        // Set a random salt
        $salt = openssl_random_pseudo_bytes(8);

        $salted = '';
        $dx = '';
        // Salt the key(32) and iv(16) = 48
        while (strlen($salted) < 48) {
            $dx = md5($dx.$password.$salt, true);
            $salted .= $dx;
        }

        $key = substr($salted, 0, 32);
        $iv  = substr($salted, 32,16);

        $encrypted_data = openssl_encrypt($data, 'aes-256-cbc', $key, true, $iv);
        return base64_encode('Salted__' . $salt . $encrypted_data);
    }



    function tryToLogUser(&$httpVars, $isLast = false){

        $checkNonce = $this->pluginConf["CHECK_NONCE"] === true;
        $token = $this->detectVar($httpVars, "cyphered_token");
        $tokenInc = $this->detectVar($httpVars, "cyphered_token_inc");
        if(empty($token) || ($checkNonce && empty($tokenInc))){
            return false;
        }

        if(!$checkNonce){
            $decoded = $this->decrypt($this->pluginConf["PRIVATE_KEY"], $token);
        }else{
            $decoded = $this->decrypt($this->pluginConf["PRIVATE_KEY"].":".$tokenInc, $token);
        }
        if($decoded == null){
            return false;
        }
        $data = unserialize($decoded);
        if(empty($data) || !is_array($data) || !isset($data["user_id"]) || !isset($data["user_pwd"])){
            $this->logDebug(__FUNCTION__, "Cyphered Token found but wrong deserizalized data");
            return false;
        }
        if(AuthService::getLoggedUser() != null){
            $currentUser = AuthService::getLoggedUser()->getId();
            if($currentUser != $data["user_id"]){
                AuthService::disconnect();
            }
        }
        $this->logDebug(__FUNCTION__, "Trying to log user ".$data["user_id"]." from cyphered token");
        $userId = $data["user_id"];
        if($checkNonce){
            $keys = $this->getLastKeys();
            $lastInc = 0;
            if(isSet($keys[$userId])){
                $lastInc = $keys[$userId];
            }
            if($tokenInc <= $lastInc){
                $this->logDebug(__FUNCTION__, "Key was already used for this user id");
                return false;
            }
        }
        $res = AuthService::logUser($data["user_id"], $data["user_pwd"], false, false, -1);
        if($res > 0) {
            $this->logDebug(__FUNCTION__, "Success");
            if($checkNonce){
                $keys[$userId] = $tokenInc;
                $this->storeLastKeys($keys);
            }
            $loggedUser = AuthService::getLoggedUser();
            $force = $loggedUser->mergedRole->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
            $passId = -1;
            if (isSet($httpVars["tmp_repository_id"])) {
                $passId = $httpVars["tmp_repository_id"];
            } else if ($force != "" && $loggedUser->canSwitchTo($force) && !isSet($httpVars["tmp_repository_id"]) && !isSet($_SESSION["PENDING_REPOSITORY_ID"])) {
                $passId = $force;
            }
            ConfService::switchUserToActiveRepository($loggedUser, $passId);
            return true;
        }

        $this->logDebug(__FUNCTION__, "Wrong result ".$res);
        return false;

    }

} 