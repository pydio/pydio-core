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

namespace Pydio\Action\Disclaimer;

use Pydio\Core\Http\Middleware\SessionMiddleware;
use Pydio\Core\Http\Middleware\SessionRepositoryMiddleware;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\PluginFramework\Plugin;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Implementation for forcing using to accept a disclaimer
 */
class DisclaimerProvider extends Plugin
{

    /**
     * Set disclaimer validation on/off
     *
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

            $u->removeLock("validate_disclaimer");
            $u->save("superuser");
            AuthService::updateSessionUser($u);
            $repo = SessionRepositoryMiddleware::switchUserToRepository($u, $request);
            if (!$repo) {
                AuthService::disconnect();
                throw new \Pydio\Core\Exception\AuthRequiredException();
            }
            $ctx->setRepositoryObject($repo);
            SessionMiddleware::updateContext($ctx);
            Logger::updateContext($ctx);
            ConfService::getInstance()->invalidateLoadedRepositories();

        } else {

            $u->setLock("validate_disclaimer");
            $u->save("superuser");

            AuthService::disconnect();
            throw new \Pydio\Core\Exception\AuthRequiredException();

        }
    }

    /**
     * Serve the content of the Dicsclaimer.
     *
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

    /**
     * Hooked to user.after_create
     * Is disabled on shared links, this will automatically validate the disclaimer
     * for any new shared user created .
     *
     * @param ContextInterface $ctx
     * @param UserInterface $userObject
     */
    public function updateSharedUser(ContextInterface $ctx, UserInterface $userObject){
        if($userObject->isHidden() && !$this->getContextualOption($ctx, "DISCLAIMER_ENABLE_SHARED")){
            $userObject->removeLock("validate_disclaimer");
            $userObject->getPersonalRole()->setParameterValue("action.disclaimer", "DISCLAIMER_ACCEPTED", "yes", AJXP_REPO_SCOPE_SHARED);
            $userObject->save("superuser");
        }
    }

    /**
     * Hooked to user.after_login
     * If enabled on shared links, this will always reset the validation to false just after login.
     * Validation should be ok during the session, then disabled next time the link is loaded.
     *
     * @param ContextInterface $ctx
     * @param UserInterface $userObject
     */
    public function updateSharedUserLogin(ContextInterface $ctx, UserInterface $userObject){
        if(!$userObject->isHidden()){
            $param = $userObject->getPersonalRole()->filterParameterValue("action.disclaimer", "DISCLAIMER_ACCEPTED", AJXP_REPO_SCOPE_ALL, "no");
            if($param === "no"){
                $userObject->setLock("validate_disclaimer");
                $userObject->save("superuser");
            }
        }
        if($userObject->isHidden() && $this->getContextualOption($ctx, "DISCLAIMER_ENABLE_SHARED")){
            $userObject->setLock("validate_disclaimer");
            $userObject->getPersonalRole()->setParameterValue("action.disclaimer", "DISCLAIMER_ACCEPTED", "no", AJXP_REPO_SCOPE_SHARED);
            $userObject->save("superuser");
        }
    }    
    
}

