<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>, Afterster
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

namespace Pydio\Action\Disclaimer;

use Pydio\Core\Http\Middleware\SessionMiddleware;
use Pydio\Core\Http\Middleware\SessionRepositoryMiddleware;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\PluginFramework\Plugin;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\UsersService;
use Pydio\Log\Core\AJXP_Logger;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Simple implementation for forcing using to accept a disclaimer
 */
class DisclaimerProvider extends Plugin
{

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws \Pydio\Core\Exception\AuthRequiredException
     */
    public function toggleDisclaimer(ServerRequestInterface &$request, ResponseInterface &$response)
    {

        $httpVars = $request->getParsedBody();
        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $u = $ctx->getUser();
        $u->getPersonalRole()->setParameterValue(
            "action.disclaimer",
            "DISCLAIMER_ACCEPTED",
            $httpVars["validate"] == "true" ? "yes" : "no",
            AJXP_REPO_SCOPE_ALL
        );

        if ($httpVars["validate"] == "true") {

            $u->removeLock();
            $u->save("superuser");
            AuthService::updateUser($u);
            $repo = SessionRepositoryMiddleware::switchUserToRepository($u, $request);
            if (!$repo) {
                AuthService::disconnect();
                throw new \Pydio\Core\Exception\AuthRequiredException();
            }
            $ctx->setRepositoryObject($repo);
            SessionMiddleware::updateContext($ctx);
            AJXP_Logger::updateContext($ctx);
            ConfService::getInstance()->invalidateLoadedRepositories();

        } else {

            $u->setLock("validate_disclaimer");
            $u->save("superuser");

            AuthService::disconnect();
            throw new \Pydio\Core\Exception\AuthRequiredException();

        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    public function loadDisclaimer(ServerRequestInterface &$request, ResponseInterface &$response)
    {

        $response = $response->withHeader("Content-Type", "text/plain");
        $content = $this->getContextualOption($request->getAttribute("ctx"), "DISCLAIMER_CONTENT");
        $state = $this->getContextualOption($request->getAttribute("ctx"), "DISCLAIMER_ACCEPTED");
        if ($state == "true") $state = "yes";
        $response->getBody()->write($state . ":" . nl2br($content));

    }

}
