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
 * The latest code can be found at <https://pydio.com>.
 */

namespace Pydio\Access\WebDAV\Listener;

use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use SimpleXMLElement;

/**
 * Listener used to change the way the paths are given (no urlencode for the slash)
 */
class WebDAVSubscriber implements SubscriberInterface
{

    /**
     * Get the list of events this subscriber is being triggered on
     *
     * @return array Events
     */
    public function getEvents()
    {
        return [
            'prepared' => ['onBefore'],
            'complete' => ['onComplete'],
            'error'    => ['onError', RequestEvents::EARLY],
        ];
    }

    /**
     * @param PreparedEvent $event
     */
    public function onBefore(PreparedEvent $event) {

        /** @var GuzzleClient $client */
        $client = $event->getClient();
        $command = $event->getCommand();

        $description = $client->getDescription();
        $operation = $description->getOperation($command->getName());

        $request = $event->getRequest();
        $headers = $operation->getData("headers");
        $body    = $operation->getData("body");

        if (isset($headers)) {
            $request->addHeaders($headers);
        }

        if (isset($body)) {
            $request->setBody(Stream::factory(join($body, "")));
        }
    }

    /**
     * @param CompleteEvent $event
     */
    public function onComplete(CompleteEvent $event)
    {
        $request  = $event->getRequest();
        $basePath = $request->getPath();

        $response = $event->getResponse();
        $contentType = $response->getHeader('Content-Type');

        // Checking we have xml as response
        if (!isset($contentType) || (strpos($contentType, "application/xml") === FALSE && strpos($contentType, "text/xml") === FALSE)) {
            return;
        }

        $body = $response->getBody();

        $contents = $body->getContents();

        $contents = preg_replace("/xmlns(:[A-Za-z0-9_]*)?=(\"|\')DAV:(\\2)/","xmlns\\1=\\2urn:DAV\\2",$contents);
        $contents = preg_replace("/\n/","",$contents);

        $previous = libxml_disable_entity_loader(true);

        /** @var SimpleXMLElement $responseXML */
        $responseXML = simplexml_load_string($contents, null, LIBXML_NOBLANKS | LIBXML_NOCDATA);
        libxml_disable_entity_loader($previous);

        if ($responseXML===false) {
            throw new \InvalidArgumentException('The passed data is not valid XML');
        }

        $responseXML->registerXPathNamespace('d', 'urn:DAV');

        $result = [];
        foreach($responseXML->xpath('d:response') as $xml) {
            $xml->registerXPathNamespace('d', 'urn:DAV');
            $href = $xml->xpath('d:href');
            $href = (string)$href[0];

            foreach($xml->xpath('d:propstat') as $propStat) {

                $propStat->registerXPathNamespace('d', 'urn:DAV');
                foreach(dom_import_simplexml($propStat)->childNodes as $propNode) {

                    foreach($propNode->childNodes as $propNodeData) {

                        /* If there are no elements in here, we actually get 1 text node, this special case is dedicated to netdrive */
                        if ($propNodeData->nodeType != XML_ELEMENT_NODE) continue;

                        $propertyName = $propNodeData->localName;

                        if ($propNodeData->nodeValue == "" && isset($propNodeData->firstChild)) {
                            $propResult[$propertyName] = $propNodeData->firstChild->localName;
                        } else {
                            $propResult[$propertyName] = $propNodeData->nodeValue;
                        }
                    }
                }
            }

            $baseDecoded = urldecode($basePath);
            $hrefDecoded = urldecode($href);

            $propResult["name"] = trim(str_replace($baseDecoded, "", $hrefDecoded), "/");

            $isFile = $propResult["getcontenttype"] != "httpd/unix-directory";
            $isFile &= $propResult["resourcetype"] != "collection";

            $propResult["resourcetype"] = $isFile ? "file" : "folder";

            $result[] = $propResult;
        }

        $len = count($result);
        $depth = $request->getHeader("Depth");
        $minDepth = max(0, $request->getHeader("Min-Depth"));

        $result = array_slice($result, $minDepth, $len - $minDepth);

        if ($depth == 0) {
            $message = json_encode($result[0]);
        } else {
            $message = json_encode($result);
        }

        $stream = Stream::factory($message);
        $response->setHeader("Content-Type", "application/json");

        $event->intercept(new Response(200, $response->getHeaders(), $stream));
    }

    /**
     * Handle the before trigger
     *
     * @param ErrorEvent $event
     * @internal param ErrorEvent $e
     */
    public function onError(ErrorEvent $event)
    {
        $response = $event->getResponse();

        if ($response && 400 == $response->getStatusCode()) {
            $body = $response->getBody();

            $reason = $body->getContents();

            if (strpos($reason, "The root folder is unsupported.")) {
                $msg = '{".tag": "folder", "size": 1}';

                $stream = Stream::factory(fopen("php://memory", 'w'));
                $stream->write($msg);

                $response = new Response("200",[
                    "Content-Length" => 10
                ], $stream);

                $event->intercept($response);
            }
        }
    }
}
