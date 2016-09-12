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

namespace Pydio\OCS;
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

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class ActionsController
 * @package Pydio\OCS
 */
class ActionsController
{
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
}