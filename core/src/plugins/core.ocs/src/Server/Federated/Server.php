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
namespace Pydio\OCS\Server\Federated;

use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Vars\InputFilter;

use Pydio\OCS\Model\RemoteShare;
use Pydio\OCS\Model\SQLStore;
use Pydio\OCS\Client\OCSClient;
use Pydio\OCS\Server\Dummy;
use Pydio\OCS\Server\InvalidArgumentsException;
use Pydio\OCS\Server\UserNotFoundException;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class Server
 * @package Pydio\OCS\Server\Federated
 */
class Server extends Dummy
{
    /**
     * @param $uriParts
     * @param $parameters
     * @throws InvalidArgumentsException
     * @throws UserNotFoundException
     */
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

    /**
     * @param $parameters
     * @throws InvalidArgumentsException
     * @throws UserNotFoundException
     */
    protected function actionReceive($parameters){

        $targetUser = InputFilter::sanitize($parameters["shareWith"], InputFilter::SANITIZE_EMAILCHARS);
        if(!UsersService::userExists($targetUser)){
            throw new UserNotFoundException();
        }
        $token          = InputFilter::sanitize($parameters["token"], InputFilter::SANITIZE_ALPHANUM);
        $remoteId       = InputFilter::sanitize($parameters["remoteId"], InputFilter::SANITIZE_ALPHANUM);
        $documentName   = InputFilter::sanitize($parameters["name"], InputFilter::SANITIZE_FILENAME);
        $sender         = InputFilter::sanitize($parameters["owner"], InputFilter::SANITIZE_EMAILCHARS);
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

        $share->setHost(rtrim($remote, '/'));
        $share->setOcsServiceUrl(rtrim($remote, '/').$endpoints['share']);
        $share->setOcsDavUrl(rtrim($remote, '/').$endpoints['webdav']);

        $share->pingRemoteDAVPoint();

        $store = new SQLStore();
        $newShare = $store->storeRemoteShare($share);
        $response = $this->buildResponse("ok", 200, "Successfully received share, waiting for user response.", array("id" => $newShare->getId()));
        $this->sendResponse($response, $this->getFormat($parameters));

        $userRole = RolesService::getRole("AJXP_USR_/" . $targetUser);
        if($userRole !== false){
            // Artificially "touch" user role
            // to force repositories reload if he is logged in
            RolesService::updateRole($userRole);
        }

    }

    /**
     * @param $remoteId
     * @param $token
     * @param $parameters
     * @throws InvalidArgumentsException
     */
    protected function actionAccept($remoteId, $token, $parameters){

        $store = new SQLStore();
        $invitation = $store->invitationById($remoteId);
        if(empty($invitation)){
            throw new InvalidArgumentsException();
        }
        if($token !== $invitation->getLinkHash()){
            throw new InvalidArgumentsException();
        }
        $invitation->setStatus(OCS_INVITATION_STATUS_ACCEPTED);
        $store->storeInvitation($invitation);
        $response = $this->buildResponse("ok", 200, "Successfully accepted invitation", array("remoteId" => $remoteId));
        $this->sendResponse($response, $this->getFormat($parameters));


    }

    /**
     * @param $remoteId
     * @param $token
     * @param $parameters
     * @throws InvalidArgumentsException
     */
    protected function actionDecline($remoteId, $token, $parameters){

        $store = new SQLStore();
        $invitation = $store->invitationById($remoteId);
        if(empty($invitation)){
            throw new InvalidArgumentsException();
        }
        if($token !== $invitation->getLinkHash()){
            throw new InvalidArgumentsException();
        }
        $invitation->setStatus(OCS_INVITATION_STATUS_REJECTED);
        $store->storeInvitation($invitation);
        $response = $this->buildResponse("ok", 200, "Successfully rejected invitation", array("remoteId" => $remoteId));
        $this->sendResponse($response, $this->getFormat($parameters));

    }

    /**
     * @param $remoteId
     * @param $token
     * @param $parameters
     * @throws InvalidArgumentsException
     */
    protected function actionUnshare($remoteId, $token, $parameters){

        $token          = InputFilter::sanitize($token, InputFilter::SANITIZE_ALPHANUM);
        $remoteId       = InputFilter::sanitize($remoteId, InputFilter::SANITIZE_ALPHANUM);
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

        $userRole = RolesService::getRole("AJXP_USR_/" . $targetUser);
        if($userRole !== false){
            // Artificially "touch" user role
            // to force repositories reload if he is logged in
            RolesService::updateRole($userRole);
        }

    }

    /**
     * @param $parameters
     * @throws InvalidArgumentsException
     */
    protected function validateReceiveShareParameters($parameters){

        $keys = array("shareWith", "token", "name", "remoteId", "owner", "remote");
        foreach($keys as $k){
            if(!array_key_exists($k, $parameters)){
                throw new InvalidArgumentsException();
            }
        }

    }

}
