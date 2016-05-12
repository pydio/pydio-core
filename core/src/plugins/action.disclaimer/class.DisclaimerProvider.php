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

use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\PluginFramework\Plugin;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Simple implementation for forcing using to accept a disclaimer
 * @package AjaXplorer_Plugins
 * @subpackage Disclaimer
 */
class DisclaimerProvider extends Plugin
{

    public function toggleDisclaimer(ServerRequestInterface &$request, ResponseInterface &$response){

        $httpVars = $request->getParsedBody();
        $u = AuthService::getLoggedUser();
        $u->personalRole->setParameterValue(
            "action.disclaimer",
            "DISCLAIMER_ACCEPTED",
            $httpVars["validate"] == "true"  ? "yes" : "no",
            AJXP_REPO_SCOPE_ALL
        );

        if($httpVars["validate"] == "true"){

            $u->removeLock();
            $u->save("superuser");
            AuthService::updateUser($u);
            ConfService::switchUserToActiveRepository($u);
            $force = $u->mergedRole->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
            $passId = -1;
            if ($force != "" && $u->canSwitchTo($force) && !isSet($httpVars["tmp_repository_id"]) && !isSet($_SESSION["PENDING_REPOSITORY_ID"])) {
                $passId = $force;
            }
            $res = ConfService::switchUserToActiveRepository($u, $passId);
            if (!$res) {
                AuthService::disconnect();
                throw new \Pydio\Core\Exception\AuthRequiredException();
            }
            ConfService::getInstance()->invalidateLoadedRepositories();

        }else{

            $u->setLock("validate_disclaimer");
            $u->save("superuser");

            AuthService::disconnect();
            throw new \Pydio\Core\Exception\AuthRequiredException();

        }
    }

    public function loadDisclaimer(ServerRequestInterface &$request, ResponseInterface &$response){

        $response = $response->withHeader("Content-Type", "text/plain");
        $content = $this->getFilteredOption("DISCLAIMER_CONTENT", AJXP_REPO_SCOPE_ALL);
        $state = $this->getFilteredOption("DISCLAIMER_ACCEPTED", AJXP_REPO_SCOPE_ALL);
        if($state == "true") $state = "yes";
        $response->getBody()->write($state .":" . nl2br($content));

    }

}
