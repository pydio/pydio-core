<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>, Afterster
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
namespace Pydio\Action\Avatar;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\UsersService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Simple implementation of Avatar for Gravatar / Libravatar
 */
class AvatarProvider extends Plugin
{
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws \Pydio\Core\Exception\UserNotFoundException
     */
    public function receiveAction(\Psr\Http\Message\ServerRequestInterface &$requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $ctx = $requestInterface->getAttribute("ctx");
        $provider = $this->getContextualOption($ctx, "AVATAR_PROVIDER");
        $type = $this->getContextualOption($ctx, "GRAVATAR_TYPE");
        $httpVars = $requestInterface->getParsedBody();
        if ($requestInterface->getAttribute("action") == "get_avatar_url") {
            $suffix = "";
            switch ($provider) {
                case "gravatar":
                default:
                    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                        $url = "https://secure.gravatar.com";
                    } else {
                        $url = "http://www.gravatar.com";
                    }
                    $url .= "/avatar/";
                    $suffix .= "?s=80&r=g&d=" . $type;
                    break;
                case "libravatar":
                    // Federated Servers are not supported here without libravatar.org. Should query DNS server first.
                    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                        $url = "https://seccdn.libravatar.org";
                    } else {
                        $url = "http://cdn.libravatar.org";
                    }
                    $url .= "/avatar/";
                    $suffix = "?s=80&d=" . $type;
                    break;
            }

            if (isSet($httpVars["userid"])) {
                $userid = $httpVars["userid"];
                if (UsersService::usersEnabled() && UsersService::userExists($userid)) {
                    $user = UsersService::getUserById($userid, false);
                    $userEmail = $user->getPersonalRole()->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
                    if (!empty($userEmail)) {
                        $url .= md5(strtolower(trim($userEmail)));
                    }
                }
            }
            $url .= $suffix;
            $responseInterface->getBody()->write($url);
        }
    }
}
