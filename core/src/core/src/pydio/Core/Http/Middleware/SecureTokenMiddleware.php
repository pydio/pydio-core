<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Core\Http\Middleware;


use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Server;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class SecureTokenMiddleware
 * CSRF Prevention
 * @package Pydio\Core\Http\Middleware
 */
class SecureTokenMiddleware
{

    const SECURE_TOKENS_KEY = "PYDIO_SECURE_TOKENS";

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
        if (!in_array($requestInterface->getAttribute("action"), $pluginsUnSecureActions) && self::hasSecureToken()) {
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
        $arr = SessionService::fetch(self::SECURE_TOKENS_KEY) OR [];
        $newToken = StringHelper::generateRandomString(32);
        $arr[] = $newToken;
        SessionService::save(self::SECURE_TOKENS_KEY, $arr);
        return $newToken;
    }
    /**
     * Get the secure token from the session
     * @static
     * @return string|bool
     */
    protected static function hasSecureToken()
    {
        $arr = SessionService::fetch(self::SECURE_TOKENS_KEY);
        return ($arr !== null && is_array($arr) && count($arr));
    }
    /**
     * Verify a secure token value from the session
     * @static
     * @param string $token
     * @return bool
     */
    public static function checkSecureToken($token)
    {
        $arr = SessionService::fetch(self::SECURE_TOKENS_KEY);
        return ($arr !== null && is_array($arr) && in_array($token, $arr));
    }

}