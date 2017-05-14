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
namespace Pydio\Auth\Frontend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ApiKeysService;
use Pydio\Core\Services\AuthService;
use Pydio\Auth\Frontend\Core\AbstractAuthFrontend;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Utils\Http\UserAgent;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Controller\HTMLWriter;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class Pydio\Auth\Frontend\KeystoreAuthFrontend
 */
class KeystoreAuthFrontend extends AbstractAuthFrontend
{
    
    /**
     * @param $httpVars
     * @param $varName
     * @return string
     */
    function detectVar(&$httpVars, $varName)
    {
        if (isSet($httpVars[$varName])) return $httpVars[$varName];
        if (isSet($_SERVER["HTTP_PYDIO_" . strtoupper($varName)])) return $_SERVER["HTTP_" . strtoupper($varName)];
        return "";
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param bool $isLast
     * @return bool
     */
    function tryToLogUser(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response, $isLast = false)
    {

        $httpVars = $request->getParsedBody();
        $token = $this->detectVar($httpVars, "auth_token");
        if (empty($token)) {
            //$this->logDebug(__FUNCTION__, "Empty token", $_POST);
            return false;
        }

        $data = ApiKeysService::loadDataForPair($token);
        if($data === false){
            $this->logDebug(__FUNCTION__, "Cannot find token in keystore");
            return false;
        }
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
        $replay = hash_hmac("sha256", $server_uri . ":" . $nonce . ":" . $private, $token);
        //$this->logDebug(__FUNCTION__, "Replay is ".$replay);

        if ($replay == $hash) {
            try {
                $loggedUser = AuthService::logUser($userId, "", true);
                $request = $request->withAttribute("ctx", Context::contextWithObjects($loggedUser, null));
                return true;
            } catch (\Pydio\Core\Exception\LoginException $l) {
            }
        }
        return false;

    }

    /**
     * @param ContextInterface $ctx
     * @param $userId
     * @return bool|null
     */
    public function revokeUserTokens(ContextInterface $ctx, $userId)
    {
        try{
            $count = ApiKeysService::revokeTokens($userId);
            $this->logInfo(__FUNCTION__, "Revoking " . $count . " keys for user '" . $userId . "' on user modification action.");
        }catch (PydioException $e){}
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return String
     */
    function authTokenActions(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        /** @var ContextInterface $ctx */
        $ctx            = $requestInterface->getAttribute("ctx");
        $action         = $requestInterface->getAttribute("action");
        $httpVars       = $requestInterface->getParsedBody();
        
        if (!$ctx->hasUser()) {
            return null;
        }

        $u = $ctx->getUser();
        $user = $u->getId();
        if ($u->isAdmin() && isSet($httpVars["user_id"])) {
            $user = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
        }
        switch ($action) {

            case "keystore_generate_auth_token":

                if (ConfService::getContextConf($ctx, "SESSION_SET_CREDENTIALS", "auth") && UserAgent::osFromUserAgent($requestInterface->getServerParams()['HTTP_USER_AGENT']) !== 'Pydio Booster') {
                    $this->logDebug("Keystore Generate Tokens", "Session Credentials set: returning empty tokens to force basic authentication");
                    HTMLWriter::charsetHeader("text/plain");
                    echo "";
                    break;
                }
                $device = (isSet($httpVars["device"]) ? InputFilter::sanitize($httpVars["device"], InputFilter::SANITIZE_ALPHANUM) : "");
                $tokenPair = ApiKeysService::generatePairForAuthfront($user, $device, $_SERVER["HTTP_USER_AGENT"], $_SERVER["REMOTE_ADDR"]);
                $responseInterface = new JsonResponse($tokenPair);

                break;

            case "keystore_revoke_tokens":

                // Invalidate previous tokens
                $mess = LocaleService::getMessages();
                $passedKeyId = "";
                if (isSet($httpVars["key_id"])) $passedKeyId = $httpVars["key_id"];
                $r = ApiKeysService::revokeTokens($user, $passedKeyId);
                $responseInterface = new JsonResponse([
                    "result" => "SUCCESS",
                    "message" => $mess["keystore.8"]
                ]);
                break;

            case "keystore_list_tokens":
                
                if (!isSet($user)) break;

                $keys = ApiKeysService::listPairsForUser($user);
                $responseInterface = new JsonResponse($keys);

                break;

            default:
                break;
        }

        return null;
    }

} 