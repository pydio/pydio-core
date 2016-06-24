<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Core\Http\Middleware;


use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Server;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class SecureTokenMiddleware
 * CSRF Prevention
 * @package Pydio\Core\Http\Middleware
 */
class SecureTokenMiddleware
{

    /**
     *
     * @param ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @return \Psr\Http\Message\ResponseInterface
     * @param callable|null $next
     * @throws PydioException
     */
    public static function handleRequest(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface $responseInterface, callable $next = null){

        $pluginsUnSecureActions = PluginsService::searchManifestsWithCache("//action[@skipSecureToken]", function($nodes){
            $res = array();
            /** @var \DOMElement $node */
            foreach ($nodes as $node) {
                $res[] = $node->getAttribute("name");
            }
            return $res;
        });

        $pluginsUnSecureActions[] = "get_secure_token";
        if (!in_array($requestInterface->getAttribute("action"), $pluginsUnSecureActions) && self::getSecureToken()) {
            $params = $requestInterface->getParsedBody();
            if(array_key_exists("secure_token", $params)){
                $token = $params["secure_token"];
            }
            if ( !isSet($token) || !self::checkSecureToken($token)) {
                throw new PydioException("You are not allowed to access this resource.");
            }
        }
        return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

    }

    /**
     * Put a secure token in the session
     * @static
     * @return string
     */
    public static function generateSecureToken()
    {
        if(!isSet($_SESSION["SECURE_TOKENS"])){
            $_SESSION["SECURE_TOKENS"] = array();
        }
        if(isSet($_SESSION["FORCE_SECURE_TOKEN"])){
            $_SESSION["SECURE_TOKENS"][] = $_SESSION["FORCE_SECURE_TOKEN"];
            return $_SESSION["FORCE_SECURE_TOKEN"];
        }
        $newToken = StringHelper::generateRandomString(32);
        $_SESSION["SECURE_TOKENS"][] = $newToken;
        return $newToken;
    }
    /**
     * Get the secure token from the session
     * @static
     * @return string|bool
     */
    public static function getSecureToken()
    {
        if(isSet($_SESSION["SECURE_TOKENS"]) && count($_SESSION["SECURE_TOKENS"])){
            return true;
        }
        return false;
    }
    /**
     * Verify a secure token value from the session
     * @static
     * @param string $token
     * @return bool
     */
    public static function checkSecureToken($token)
    {
        if (isSet($_SESSION["SECURE_TOKENS"]) && in_array($token, $_SESSION["SECURE_TOKENS"])) {
            return true;
        }
        return false;
    }

}