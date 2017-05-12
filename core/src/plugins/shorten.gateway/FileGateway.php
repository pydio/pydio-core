<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

namespace Pydio\LinkShortener;

use GuzzleHttp\Exception\RequestException;
use Pydio\Core\Model\ContextInterface;
use GuzzleHttp\Client;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\ApplicationState;
use Pydio\Share\Model\ShareLink;
use Pydio\Share\View\PublicAccessManager;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * FileGateway implementation
 * @package Pydio\LinkShortener
 */
class FileGateway extends Plugin
{
    private $servers = [
        "eu.filesend"   => "https://eu.filesend.cc/links/",
        "us.filesend"   => "https://us.filesend.cc/links/",
        "eu.yourshare"  => "https://eu.yoursha.re/links/",
        "us.yourshare"  => "https://us.yoursha.re/links/",
    ];

    /**
     * @param ContextInterface $ctx
     * @param ShareLink $shareObject
     * @param PublicAccessManager $publicAccessManager
     */
    public function processShortenHook(ContextInterface $ctx, &$shareObject, $publicAccessManager){

        $server = $this->getContextualOption($ctx, "GATEWAY_SERVER");
        $apiKey = $this->getContextualOption($ctx, "API_KEY");
        $apiSecret = $this->getContextualOption($ctx, "API_SECRET");
        if(empty($server) || empty($apiKey) || empty($apiSecret)){
            return;
        }
        if(!isSet($this->servers[$server])){
            $this->logError("FileGateway", "Cannot find valid server for key ".$server);
            return;
        }

        $data = [
            "hash"          => $shareObject->getHash(),
            "base"          => ApplicationState::detectServerURL(true),
            "main_endpoint" => "proxy.php?hash={hash}",
            "dl_endpoint"   => "proxy.php?hash={hash}&dl=true&file={path}",
            "shorten_type"  => "full",
        ];
        $headers['Authorization'] = 'Basic '.base64_encode($apiKey.":".$apiSecret);
        $client = new Client([]);
        $request = $client->createRequest("POST", $this->servers[$server], [
            "timeout" => 5,
            "headers" => $headers,
            "json"    => $data
        ]);

        try{
            $response = $client->send($request);
        }catch (RequestException $r){
            $this->logError("FileGateway", "There was an error while trying to submit a request to the server: ".$r->getMessage());
            return;
        }
        $body = $response->getBody();
        $json = json_decode($body, true);
        if(!empty($json)){
            $newUrl = $json["public_url"];
            $shareObject->setShortFormUrl($newUrl);
            $shareObject->save();
        }else{
            $this->logError("FileGateway", "There was an error while trying to decode the server response: ".$body);
        }


    }

}
