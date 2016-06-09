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
use Pydio\Core\Model\Context;
use Pydio\Core\Services\AuthService;
use Pydio\Authfront\Core\AbstractAuthFrontend;
use Pydio\Core\Services\ConfService;
use Pydio\Conf\Sql\sqlConfDriver;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\HTMLWriter;

defined('AJXP_EXEC') or die( 'Access not allowed');


class KeystoreAuthFrontend extends AbstractAuthFrontend {

    /**
     * @var sqlConfDriver $storage
     */
    var $storage;

    function detectVar(&$httpVars, $varName){
        if(isSet($httpVars[$varName])) return $httpVars[$varName];
        if(isSet($_SERVER["HTTP_PYDIO_".strtoupper($varName)])) return $_SERVER["HTTP_".strtoupper($varName)];
        return "";
    }

    function tryToLogUser(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response, $isLast = false){

        $httpVars = $request->getParsedBody();
        $token = $this->detectVar($httpVars, "auth_token");
        if(empty($token)){
            //$this->logDebug(__FUNCTION__, "Empty token", $_POST);
            return false;
        }
        $this->storage = ConfService::getConfStorageImpl();
        if(!($this->storage instanceof \Pydio\Conf\Sql\sqlConfDriver)) return false;

        $data = null;
        $this->storage->simpleStoreGet("keystore", $token, "serial", $data);
        if(empty($data)){
            //$this->logDebug(__FUNCTION__, "Cannot find token in keystore");
            return false;
        }
        //$this->logDebug(__FUNCTION__, "Found token in keystore");
        $userId = $data["USER_ID"];
        $private = $data["PRIVATE"];
        $explode = explode("?", $_SERVER["REQUEST_URI"]);
        $server_uri = rtrim(array_shift($explode), "/");
        $decoded = array_map("urldecode", explode("/", $server_uri));
        $decoded = array_map(array("Pydio\Core\Utils\TextEncoder", "toUTF8"), $decoded);
        $decoded = array_map("rawurlencode", $decoded);
        $server_uri = implode("/", $decoded);
        $server_uri = str_replace("~", "%7E", $server_uri);
        //$this->logDebug(__FUNCTION__, "Decoded URI is ".$server_uri);
        list($nonce, $hash) = explode(":", $this->detectVar($httpVars, "auth_hash"));
        //$this->logDebug(__FUNCTION__, "Nonce / hash is ".$nonce.":".$hash);
        $replay = hash_hmac("sha256", $server_uri.":".$nonce.":".$private, $token);
        //$this->logDebug(__FUNCTION__, "Replay is ".$replay);

        if($replay == $hash){
            try{
                $loggedUser = AuthService::logUser($userId, "", true);
                $request = $request->withAttribute("ctx", Context::contextWithObjects($loggedUser, null));
                return true;
            }catch(\Pydio\Core\Exception\LoginException $l){}
        }
        return false;

    }

    public function revokeUserTokens($userId){

        $this->storage = ConfService::getConfStorageImpl();
        if(!($this->storage instanceof \Pydio\Conf\Sql\sqlConfDriver)) return false;

        $keys = $this->storage->simpleStoreList("keystore", null, "", "serial", '%"USER_ID";s:'.strlen($userId).':"'.$userId.'"%');
        foreach($keys as $keyId => $keyData){
            $this->storage->simpleStoreClear("keystore", $keyId);
        }
        if(count($keys)){
            $this->logInfo(__FUNCTION__, "Revoking ".count($keys)." keys for user '".$userId."' on password change action.");
        }
        return null;
    }

    /**
     * @param String $action
     * @param array $httpVars
     * @param array $fileVars
     * @return String
     */
    function authTokenActions($action, $httpVars, $fileVars, \Pydio\Core\Model\ContextInterface $ctx){

        if(!$ctx->hasUser()) {
            return null;
        }
        $this->storage = ConfService::getConfStorageImpl();
        if(!($this->storage instanceof \Pydio\Conf\Sql\sqlConfDriver)) return false;

        $u = $ctx->getUser();
        $user = $u->getId();
        if($u->isAdmin() && isSet($httpVars["user_id"])){
            $user = Utils::sanitize($httpVars["user_id"], AJXP_SANITIZE_EMAILCHARS);
        }
        switch($action){
            
            case "keystore_generate_auth_token":

                if(ConfService::getCoreConf("SESSION_SET_CREDENTIALS", "auth")){
                    $this->logDebug("Keystore Generate Tokens", "Session Credentials set: returning empty tokens to force basic authentication");
                    HTMLWriter::charsetHeader("text/plain");
                    echo "";
                    break;
                }

                $token = Utils::generateRandomString();
                $private = Utils::generateRandomString();
                $data = array("USER_ID" => $user, "PRIVATE" => $private);
                if(!empty($httpVars["device"])){
                    // Revoke previous tokens for this device
                    $device = $httpVars["device"];
                    $keys = $this->storage->simpleStoreList("keystore", null, "", "serial", '%"DEVICE_ID";s:'.strlen($device).':"'.$device.'"%');
                    foreach($keys as $keyId => $keyData){
                        if($keyData["USER_ID"] != $user) continue;
                        $this->storage->simpleStoreClear("keystore", $keyId);
                    }
                    $data["DEVICE_ID"] = $device;
                }
                $data["DEVICE_UA"] = $_SERVER['HTTP_USER_AGENT'];
                $data["DEVICE_IP"] = $_SERVER['REMOTE_ADDR'];
                $this->storage->simpleStoreSet("keystore", $token, $data, "serial");
                HTMLWriter::charsetHeader("application/json");
                echo(json_encode(array(
                    "t" => $token,
                    "p" => $private)
                ));

                break;

            case "keystore_revoke_tokens":

                // Invalidate previous tokens
                $mess = LocaleService::getMessages();
                $passedKeyId = "";
                if(isSet($httpVars["key_id"])) $passedKeyId = $httpVars["key_id"];
                $keys = $this->storage->simpleStoreList("keystore", null, $passedKeyId, "serial", '%"USER_ID";s:'.strlen($user).':"'.$user.'"%');
                foreach($keys as $keyId => $keyData){
                    $this->storage->simpleStoreClear("keystore", $keyId);
                }
                $message = array(
                    "result" => "SUCCESS",
                    "message" => $mess["keystore.8"]
                );
                HTMLWriter::charsetHeader("application/json");
                echo json_encode($message);
                break;

            case "keystore_list_tokens":
                if(!isSet($user)) break;
                $keys = $this->storage->simpleStoreList("keystore", null, "", "serial", '%"USER_ID";s:'.strlen($user).':"'.$user.'"%');
                foreach($keys as $keyId => &$keyData){
                    unset($keyData["PRIVATE"]);
                    unset($keyData["USER_ID"]);
                    $deviceDesc = "Web Browser";
                    $deviceOS = "Unkown";
                    if(isSet($keyData["DEVICE_UA"])){
                        $agent = $keyData["DEVICE_UA"];
                        if(strpos($agent, "python-requests") !== false) {
                            $deviceDesc = "PydioSync";
                            if(strpos($agent, "Darwin") !== false) $deviceOS = "Mac OS X";
                            else if(strpos($agent, "Windows/7") !== false) $deviceOS = "Windows 7";
                            else if(strpos($agent, "Windows/8") !== false) $deviceOS = "Windows 8";
                            else if(strpos($agent, "Linux") !== false) $deviceOS = "Linux";
                        }else{
                            $deviceOS = Utils::osFromUserAgent($agent);
                        }
                    }
                    $keyData["DEVICE_DESC"] = $deviceDesc;
                    $keyData["DEVICE_OS"]   = $deviceOS;
                }
                header("Content-type: application/json;");
                echo json_encode($keys);

                break;

            default:
                break;
        }

        return null;
    }

} 