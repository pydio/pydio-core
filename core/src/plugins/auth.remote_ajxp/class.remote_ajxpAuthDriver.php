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
use Pydio\Core\Services\AuthService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Authenticates against a remote server implementing ajxp API
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class remote_ajxpAuthDriver extends serialAuthDriver
{
    public function usersEditable()
    {
        return false;
    }
    public function passwordsEditable()
    {
        return false;
    }

    /**
     * Legacy preLogUser method, not used anymore
     * Should be replaced by an authfront plugin.
     * @param String $sessionId
     */
    public function preLogUser($sessionId)
    {
        require_once(AJXP_BIN_FOLDER."/lib/HttpClient.php");
        $client = new HttpClient($this->getOption("REMOTE_SERVER"), $this->getOption("REMOTE_PORT"));
        $client->setDebug(false);
        if ($this->getOption("REMOTE_USER") != "") {
            $client->setAuthorization($this->getOption("REMOTE_USER"), $this->getOption("REMOTE_PASSWORD"));
        }
        $client->setCookies(array(($this->getOption("REMOTE_SESSION_NAME") ? $this->getOption("REMOTE_SESSION_NAME") : "PHPSESSID") => $sessionId));
        $result = $client->get($this->getOption("REMOTE_URL"), array("session_id"=>$sessionId));
        if (!$result) {
            return;
        }
        try{
            $user = $client->getContent();
            if ($this->autoCreateUser()) {
                $logged = AuthService::logUser($user, "", true);
            } else {
                // If not auto-create but the user exists, log him.
                if ($this->userExists($user)) {
                    $logged = AuthService::logUser($user, "", true);
                }
            }
            if(!empty($logged)){
                // TODO : UPDATE CONTEXT ?
            }
        }catch (\Pydio\Core\Exception\LoginException $l){

        }

    }

}
