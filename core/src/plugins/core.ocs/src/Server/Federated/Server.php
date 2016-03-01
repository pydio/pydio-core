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
namespace Pydio\OCS\Server\Federated;

use Pydio\OCS\Model\RemoteShare;
use Pydio\OCS\Model\SQLStore;
use Pydio\OCS\Client\OCSClient;
use Pydio\OCS\Server\Dummy;
use Pydio\OCS\Server\InvalidArgumentsException;
use Pydio\OCS\Server\UserNotFoundException;

defined('AJXP_EXEC') or die('Access not allowed');

class Server extends Dummy
{
    public function run($uriParts, $parameters){

        if(!count($uriParts)){

            $this->validateReceiveShareParameters($parameters);
            $this->actionReceive($parameters);

        }else if(count($uriParts) == 2){

            $remoteId = $uriParts[0];
            $action = $uriParts[1];
            if(!array_key_exists("token", $parameters) || !in_array($action, array("accept", "decline", "unshare"))){
                throw new InvalidArgumentsException();
            }
            call_user_func(array($this, "action".ucfirst($action)), $remoteId, $parameters["token"], $parameters);

        }else{

            throw new InvalidArgumentsException();

        }


    }

    protected function actionReceive($parameters){

        $targetUser = \AJXP_Utils::sanitize($parameters["shareWith"], AJXP_SANITIZE_EMAILCHARS);
        if(!\AuthService::userExists($targetUser)){
            throw new UserNotFoundException();
        }
        $token          = \AJXP_Utils::sanitize($parameters["token"], AJXP_SANITIZE_ALPHANUM);
        $remoteId       = \AJXP_Utils::sanitize($parameters["remoteId"], AJXP_SANITIZE_ALPHANUM);
        $documentName   = \AJXP_Utils::sanitize($parameters["name"], AJXP_SANITIZE_FILENAME);
        $sender         = \AJXP_Utils::sanitize($parameters["owner"], AJXP_SANITIZE_EMAILCHARS);
        $remote         = $parameters["remote"];
        $testParts = parse_url($remote);
        if(!is_array($testParts) || empty($testParts["scheme"]) || empty($testParts["host"])){
            throw new InvalidArgumentsException();
        }

        $endpoints = OCSClient::findEndpointsForURL($remote);

        $share = new RemoteShare();
        $share->setUser($targetUser);
        $share->setOcsRemoteId($remoteId);
        $share->setOcsToken($token);
        $share->setDocumentName($documentName);
        $share->setSender($sender);
        $share->setReceptionDate(time());
        $share->setStatus(OCS_INVITATION_STATUS_PENDING);

        $share->setOcsServiceUrl(rtrim($remote, '/').$endpoints['share']);
        $share->setOcsDavUrl(rtrim($remote, '/').$endpoints['webdav']);

        $share->pingRemoteDAVPoint();

        $store = new SQLStore();
        $newShare = $store->storeRemoteShare($share);
        $response = $this->buildResponse("ok", 200, "Successfully received share, waiting for user response.", array("id" => $newShare->getId()));
        $this->sendResponse($response, $this->getFormat($parameters));

        $userRole = \AuthService::getRole("AJXP_USR_/".$targetUser);
        if($userRole !== false){
            // Artificially "touch" user role
            // to force repositories reload if he is logged in
            \AuthService::updateRole($userRole);
        }

    }

    protected function actionAccept($remoteId, $token, $parameters){

    }

    protected function actionDecline($remoteId, $token, $parameters){

    }

    protected function actionUnshare($remoteId, $token, $parameters){

        $token          = \AJXP_Utils::sanitize($token, AJXP_SANITIZE_ALPHANUM);
        $remoteId       = \AJXP_Utils::sanitize($remoteId, AJXP_SANITIZE_ALPHANUM);
        $store = new SQLStore();
        $remoteShare = $store->remoteShareForOcsRemoteId($remoteId);
        if(empty($remoteShare)){
            throw new InvalidArgumentsException();
        }
        if($token !== $remoteShare->getOcsToken()){
            throw new InvalidArgumentsException();
        }
        $targetUser = $remoteShare->getUser();
        $store->deleteRemoteShare($remoteShare);
        $response = $this->buildResponse("ok", 200, "Successfully removed share.");
        $this->sendResponse($response, $this->getFormat($parameters));

        $userRole = \AuthService::getRole("AJXP_USR_/".$targetUser);
        if($userRole !== false){
            // Artificially "touch" user role
            // to force repositories reload if he is logged in
            \AuthService::updateRole($userRole);
        }

    }

    protected function validateReceiveShareParameters($parameters){

        $keys = array("shareWith", "token", "name", "remoteId", "owner", "remote");
        foreach($keys as $k){
            if(!array_key_exists($k, $parameters)){
                throw new InvalidArgumentsException();
            }
        }

    }

}
