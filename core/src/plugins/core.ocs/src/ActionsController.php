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

namespace Pydio\OCS;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Cache\Core\CacheStreamLayer;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Vars\InputFilter;

use Pydio\Log\Core\Logger;
use Pydio\OCS\Client\OCSClient;
use Pydio\OCS\Model\SQLStore;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class ActionsController
 * @package Pydio\OCS
 */
class ActionsController
{
    private $configs;

    /**
     * ActionsController constructor.
     * @param $configs
     */
    public function __construct($configs){
        $this->configs = $configs;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return null
     * @throws PydioException
     * @throws \Exception
     */
    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response) {

        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $action = $request->getAttribute("action");
        $httpVars = $request->getParsedBody();
        
        switch($action){

            case "user_list_authorized_users":

                if(isSet($httpVars['trusted_server_id'])){
                    $this->forwardUsersListToRemote($request, $response);
                }

                break;

            case "accept_invitation":

                $remoteShareId = InputFilter::sanitize($httpVars["remote_share_id"], InputFilter::SANITIZE_ALPHANUM);
                $store = new SQLStore();
                $remoteShare = $store->remoteShareById($remoteShareId);
                if($remoteShare === null){
                    throw new PydioException("Cannot find remote share with ID ".$remoteShareId);
                }

                $client = new OCSClient();
                $client->acceptInvitation($remoteShare);
                $remoteShare->setStatus(OCS_INVITATION_STATUS_ACCEPTED);
                $store->storeRemoteShare($remoteShare);

                $urlBase = $ctx->getUrlBase();
                
                CacheStreamLayer::clearDirCache($urlBase);
                CacheStreamLayer::clearStatCache($urlBase . "/" . $remoteShare->getDocumentName());

                $remoteCtx = new Context($remoteShare->getUser(), "ocs_remote_share_" . $remoteShare->getId());
                CacheStreamLayer::clearStatCache($remoteCtx->getUrlBase());

                break;

            case "reject_invitation":

                $remoteShareId = InputFilter::sanitize($httpVars["remote_share_id"], InputFilter::SANITIZE_ALPHANUM);
                $store = new SQLStore();
                $remoteShare = $store->remoteShareById($remoteShareId);
                if($remoteShare === null){
                    throw new PydioException("Cannot find remote share with ID ".$remoteShareId);
                }

                $client = new OCSClient();
                try {
                    $client->declineInvitation($remoteShare);
                } catch (\Exception $e) {
                    // If the reject fails, we still want the share to be removed from the db
                    Logger::error(__CLASS__,"Exception",$e->getMessage());
                }
                $store->deleteRemoteShare($remoteShare);
                ConfService::getInstance()->invalidateLoadedRepositories();

                $urlBase = $ctx->getUrlBase();

                CacheStreamLayer::clearDirCache($urlBase);
                CacheStreamLayer::clearStatCache($urlBase . "/" . $remoteShare->getDocumentName());

                $remoteCtx = new Context($remoteShare->getUser(), "ocs_remote_share_" . $remoteShare->getId());
                CacheStreamLayer::clearStatCache($remoteCtx->getUrlBase());

                break;

            default:

                break;
            
        }

        return null;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $responseInterface
     * @throws PydioException
     */
    protected function forwardUsersListToRemote(ServerRequestInterface &$request, ResponseInterface &$responseInterface){

        $httpVars           = $request->getParsedBody();
        $searchQuery       = InputFilter::sanitize($httpVars['value'], InputFilter::SANITIZE_HTML_STRICT);
        $trustedServerId   = InputFilter::sanitize($httpVars['trusted_server_id'], InputFilter::SANITIZE_ALPHANUM);
        if(!isSet($this->configs["TRUSTED_SERVERS"]) || !isSet($this->configs["TRUSTED_SERVERS"][$trustedServerId])){
            throw new PydioException("Cannot find trusted server with id " . $trustedServerId);
        }
        $serverData = $this->configs["TRUSTED_SERVERS"][$trustedServerId];
        $url = $serverData['url'] . '/api/pydio/user_list_authorized_users/' . $searchQuery;
        $params = [
            'format'=> 'json',
            'users_only' => 'true',
            'existing_only' => 'true'
        ];
        $client = new Client();
        $postResponse = $client->post($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($serverData['user'] . ':' . $serverData['pass'])
            ],
            'body' => $params
        ]);
        $body = $postResponse->getBody()->getContents();
        $jsonContent = json_decode($body);
        foreach($jsonContent as $userEntry){
            $userEntry->trusted_server_id       = $trustedServerId;
            $userEntry->trusted_server_label    = $serverData['label'];
        }

        $httpVars['processed'] = true;
        $request = $request->withParsedBody($httpVars);
        $responseInterface = new JsonResponse($jsonContent);

    }

}