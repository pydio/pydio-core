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
 * The latest code can be found at <http://pyd.io/>.
 */
namespace Pydio\Core\Http\Wopi;

use JWT;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ApiKeysService;
use Pydio\Auth\Frontend\Core\AbstractAuthFrontend;
use Pydio\Conf\Sql\SqlConfDriver;
use Pydio\Log\Core\Logger;
use Zend\Diactoros\UploadedFile;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class Pydio\Core\Http\Wopi\WopiJWTAuthFrontend;
 */
class AuthFrontend extends AbstractAuthFrontend
{
    const NOT_FOUND = "";

    /**
     * @var SqlConfDriver $storage
     */
    var $storage;

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

    function retrieveParams(ServerRequestInterface &$request, ResponseInterface &$response) {

        /** @var ContextInterface $context */
        $action = $request->getAttribute("action");

        $httpVars = $request->getParsedBody();

        $jwt = $this->detectVar($httpVars, "access_token");
        if (empty($jwt)) {
            return false;
        }

        // We have an access token - decode
        $payload = JWT::decode($jwt);

        $httpVars["auth_token"] = $payload->token;
        $httpVars["auth_hash"] = $payload->hash;

        $uri = $request->getUri();
        $query = $uri->getQuery();
        $uri = $uri->withPath($payload->uri);
        $path = $uri->getPath();

        $_SERVER["REQUEST_URI"] = $path . '?' . $query;

        // Handle upload case
        if ($action == "upload") {
            $stream = $request->getBody();

            $size = (int)$request->getHeader("Content-Length")[0];

            $uploadedFile = new UploadedFile(
                $stream,
                $size,
                0,
                basename($payload->uri)
            );

            $request = $request->withUploadedFiles(["userfile_0" => $uploadedFile]);
        }

        $request = $request
            ->withUri($uri)
            ->withParsedBody($httpVars);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param bool $isLast
     * @return bool
     */
    function tryToLogUser(ServerRequestInterface &$request, ResponseInterface &$response, $isLast = false) {

        // This plugin is depending on other authfront having found the current user
        /** @var ContextInterface $context */
        $context = $request->getAttribute("ctx");
        if (!$context->hasUser()) {
            return false;
        }

        $currentUser = $context->getUser();

        $httpVars = $request->getParsedBody();
        $jwt = $this->detectVar($httpVars, "access_token");
        if (empty($jwt)) {
            return false;
        }

        // We have an access token - decode
        $payload = JWT::decode($jwt);

        if (!isset($payload->token) || !isset($payload->task)) {
            return false;
        }

        // We have a token - retrieve private signature
        $token = $payload->token;
        $task = $payload->task;

        // store  encrypted user's credential in cache.
        $sessionId = $payload->session_id;
        $encryptedString = CacheService::fetch(AJXP_CACHE_SERVICE_NS_SHARED, $sessionId);
        $credential = MemorySafe::getCredentialsFromEncodedString($encryptedString);
        MemorySafe::storeCredentials($credential["user"], $credential["password"]);

        $key = ApiKeysService::findPairForAdminTask($task, $currentUser->getId());

        if ($key["t"] !== $token) {
            return false;
        }

        $signature = $key["p"];

        if ($signature == self::NOT_FOUND) {
            return false;
        }

        // We have a signature - verify the payload
        try {
            JWT::decode($jwt, $signature, ['HS256']);
        } catch (\Exception $e) {
            return false;
        }

        // We're through
        return true;
    }
}
