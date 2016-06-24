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
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Vars\InputFilter;

use Pydio\OCS\Client\OCSClient;
use Pydio\OCS\Model\SQLStore;

defined('AJXP_EXEC') or die('Access not allowed');


class ActionsController
{
    public function switchActions($actionName, $httpVars, $fileVars){

        switch($actionName){

            case "accept_invitation":

                $remoteShareId = InputFilter::sanitize($httpVars["remote_share_id"], InputFilter::SANITIZE_ALPHANUM);
                $store = new SQLStore();
                $remoteShare = $store->remoteShareById($remoteShareId);
                if($remoteShare !== null){
                    $client = new OCSClient();
                    $client->acceptInvitation($remoteShare);
                    $remoteShare->setStatus(OCS_INVITATION_STATUS_ACCEPTED);
                    $store->storeRemoteShare($remoteShare);
                }

                break;

            case "reject_invitation":

                $remoteShareId = InputFilter::sanitize($httpVars["remote_share_id"], InputFilter::SANITIZE_ALPHANUM);
                $store = new SQLStore();
                $remoteShare = $store->remoteShareById($remoteShareId);
                if($remoteShare !== null){
                    $client = new OCSClient();
                    $client->declineInvitation($remoteShare);
                    $store->deleteRemoteShare($remoteShare);
                    ConfService::getInstance()->invalidateLoadedRepositories();
                }

                break;
            default:
                break;
        }

        return null;

    }


}