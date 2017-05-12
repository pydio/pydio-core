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
namespace Pydio\OCS\Client;

defined('AJXP_EXEC') or die('Access not allowed');
defined('AJXP_BIN_FOLDER') or die('Bin folder not available');

use Pydio\Core\Services\ApplicationState;

use GuzzleHttp\Exception\RequestException;
use Pydio\OCS\Model\RemoteShare;
use Pydio\OCS\Model\ShareInvitation;
use GuzzleHttp\Client as GuzzleClient;


/**
 * Class OCSClient
 * @package Pydio\OCS\Client
 */
class OCSClient implements IFederated, IServiceDiscovery
{
    /**
     *
     * Sends an invitation to a remote user on a remote server
     *
     * @param ShareInvitation $invitation
     * @return boolean success
     * @throws \Exception
     */
    public function sendInvitation(ShareInvitation $invitation)
    {
        $url = $invitation->getTargetHost();
        $client = self::getClient($url);

        $endpoints = self::findEndpointsForClient($client);

        $body = [
            'shareWith' => $invitation->getTargetUser(),
            'token' => $invitation->getLinkHash(),
            'name' => $invitation->getDocumentName(),
            'remoteId' => $invitation->getId(),
            'owner' => $invitation->getOwner(),
            'remote' => ApplicationState::detectServerURL(true)
        ];

        $response = $client->post(ltrim($endpoints['share'], '/'), [
            'body' => $body
        ]);

        if ($response->getStatusCode() != 200) {
            throw new \Exception($response->getReasonPhrase());
        }

        return true;
    }

    /**
     *
     * Cancels a sent invitation
     *
     * @param ShareInvitation $invitation
     * @return bool success
     * @throws \Exception
     */
    public function cancelInvitation(ShareInvitation $invitation)
    {
        $url = $invitation->getTargetHost();
        $client = self::getClient($url);

        $endpoints = self::findEndpointsForClient($client);

        $response = $client->post(ltrim($endpoints['share'] . '/' . $invitation->getId() . '/unshare', '/'), [
            'body' => [
                'token' => $invitation->getLinkHash()
            ]
        ]);

        if ($response->getStatusCode() != 200) {
            throw new \Exception($response->getReasonPhrase());
        }

        return true;
    }

    /**
     *
     * Accepts the invitation sent by the original owner on a remote server
     *
     * @param RemoteShare $remoteShare
     * @return boolean success
     * @throws \Exception
     */
    public function acceptInvitation(RemoteShare $remoteShare)
    {
        $url = $remoteShare->getOcsServiceUrl();
        $client = self::getClient($url);

        $response = $client->post($remoteShare->getOcsRemoteId() . '/accept', [
            'body' => [
                'token' => $remoteShare->getOcsToken(),
            ]
        ]);

        if ($response->getStatusCode() != 200) {
            throw new \Exception($response->getReasonPhrase());
        }

        return true;
    }

    /**
     *
     * Declines the invitation sent by the original owner on a remote server
     *
     * @param RemoteShare $remoteShare
     * @return boolean success
     * @throws \Exception
     */
    public function declineInvitation(RemoteShare $remoteShare)
    {
        $url = $remoteShare->getOcsServiceUrl();
        $client = self::getClient($url);

        $response = $client->post($remoteShare->getOcsRemoteId() . '/decline', [
            'body' => [
                'token' => $remoteShare->getOcsToken(),
            ]
        ]);

        if ($response->getStatusCode() != 200) {
            throw new \Exception($response->getReasonPhrase());
        }

        return true;
    }

    /**
     *
     * Retrieves the OCS Provider endpoints for the URL
     * @param string $url
     *
     * @return array
     */
    public static function findEndpointsForURL($url) {
        $client = self::getClient($url);
        return self::findEndpointsForClient($client);
    }

    /**
     *
     * Retrieves the OCS Provider endpoints for the Guzzle Client via a GET request
     *
     * @param GuzzleClient $client
     * @return array endpoints location
     * @throws \Exception
     */
    public static function findEndpointsForClient($client)
    {
        try {
            // WARNING - This needs to be relative... :/
            $response = $client->get('ocs-provider/');
        } catch (RequestException $e) {
            throw new \Exception('Failed to communicate with ocs provider : '. $e->getMessage());
        }

        if ($response->getStatusCode() != 200) {
            throw new \Exception('Could not get OCS Provider endpoints');
        }

        $contentType = array_shift(explode(";", $response->getHeader('Content-Type')));
        if (array_search($contentType, ['text/json', 'application/json']) !== false) {
            $response = $response->json();
        } else if (array_search($contentType, ['text/xml', 'application/xml']) !== false) {
            $response = $response->xml();
        }

        // Flattening response coz Owncloud are not respecting the API
        $response = array_merge((array) $response, (array) $response['services']);

        if (!isset($response['FEDERATED_SHARING']['endpoints'])) {
            throw new \Exception('Provider endpoints response not valid');
        }

        return $response['services']['FEDERATED_SHARING']['endpoints'];
    }

    /**
     * @param $url
     * @return GuzzleClient
     */
    private static function getClient($url) {
        $url = rtrim($url, "/");

        return new GuzzleClient([
            'base_url' => $url."/"
        ]);
    }

}
