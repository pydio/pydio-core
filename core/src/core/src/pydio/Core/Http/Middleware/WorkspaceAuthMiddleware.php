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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Core\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Exception\PydioException;

use Pydio\Core\Exception\PydioPromptException;
use Pydio\Core\Exception\WorkspaceAuthRequired;
use Pydio\Core\Http\Server;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class WorkspaceAuthMiddleware
 * PSR7 Middleware that handle Workspace "prompted" authentication
 * @package Pydio\Core\Http\Middleware
 */
class WorkspaceAuthMiddleware
{

    const RESUBMIT_AUTH_VARS    = "PYDIO_WORKSPACE_AUTH_VARS";
    const RESUBMIT_AUTH_KEY     = "PYDIO_WORKSPACE_AUTH_RESUBMIT";
    const RESUBMIT_AUTH_COUNT   = "PYDIO_WORKSPACE_AUTH_RESUBMIT_COUNT";

    const FORM_RESUBMIT_KEY     = "workspace-auth-submission-id";
    const FORM_RESUBMIT_PASS    = "workspace-auth-password";

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface
     * @param callable|null $next
     * @throws PydioException
     */
    public static function handleRequest(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface, callable $next = null){

        $vars = $requestInterface->getParsedBody();
        if(isSet($vars[self::FORM_RESUBMIT_KEY]) && SessionService::has(self::RESUBMIT_AUTH_VARS."-".$vars[self::FORM_RESUBMIT_KEY]) && !empty($vars[self::FORM_RESUBMIT_PASS])){
            $submittedId = $vars[self::FORM_RESUBMIT_KEY];
            // Count a max number of submission?

            /** @var ContextInterface $ctx */
            $ctx = $requestInterface->getAttribute("ctx");
            if($ctx->hasUser()){
                $password = $vars[self::FORM_RESUBMIT_PASS];
                MemorySafe::storeCredentials($ctx->getUser()->getId(), $password);
            }

            $newVars = SessionService::fetch(self::RESUBMIT_AUTH_VARS."-".$submittedId);
            SessionService::delete(self::RESUBMIT_AUTH_VARS."-".$submittedId);

            $vars = $newVars;
            $requestInterface = $requestInterface->withParsedBody($newVars);
            if(isSet($vars["get_action"])){
                $requestInterface = $requestInterface->withAttribute("action", $vars["get_action"]);
            }
        }

        try{

            return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

        } catch (WorkspaceAuthRequired $ex){

            // Generate a random ID.
            $submissionId = StringHelper::generateRandomString(24);
            SessionService::save(self::RESUBMIT_AUTH_VARS."-".$submissionId, $vars);
            $parameters = [self::FORM_RESUBMIT_KEY => $submissionId];
            $postSubmitCallback = "";
            if($requestInterface->getAttribute("action") === "switch_repository"){
                $postSubmitCallback = "ajaxplorer.loadXmlRegistry();";
            }
            // Will throw a prompt exception with all current values
            return PydioPromptException::promptForWorkspaceCredentials($parameters, self::FORM_RESUBMIT_PASS, $postSubmitCallback);

        }


    }

}