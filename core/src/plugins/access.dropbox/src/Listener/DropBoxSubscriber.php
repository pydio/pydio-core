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

namespace Pydio\Access\DropBox\Listener;

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;

/**
 * Listener used to change the way the paths are given (no urlencode for the slash)
 */
class DropBoxSubscriber implements SubscriberInterface
{

    /**
     * Get the list of events this subscriber is being triggered on
     *
     * @return array Events
     */
    public function getEvents()
    {
        return [
            'before'   => ['onBefore'],
            'complete' => ['onComplete'],
            'error'    => ['onError', RequestEvents::EARLY],
        ];
    }

    /**
     * @param BeforeEvent $event
     */
    public function onBefore(BeforeEvent $event)
    {
        $request = $event->getRequest();
        $host = $request->getHost();
        $target = "content.dropboxapi.com";

        $count = $event->getRetryCount();
        $path = $request->getPath();
        $found = strpos($path, "download");
        $found |= strpos($path, "upload");

        if ( $host != $target && $found  && $count == 0) {
            $request->setHost("content.dropboxapi.com");
            $newResponse = $event->getClient()->send($request);
            $event->intercept($newResponse);
        }
    }

    /**
     * @param CompleteEvent $event
     */
    public function onComplete(CompleteEvent $event, $name)
    {
        $response = $event->getResponse();

        $contentType = $response->getHeader('Content-Type');

        if (!isset($contentType) || $contentType != "application/json") {
            return;
        }

        $json = $response->json();

        if (isset($json["entries"])) {
            $json = $json["entries"];

            $message = json_encode($json);
            $stream = Stream::factory(fopen("php://memory", 'w'));
            $stream->write($message);

            $event->intercept(new Response(200, $response->getHeaders(), $stream));
        }
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
        } elseif ($response && 409 == $response->getStatusCode()) {
            $body = $response->getBody();

            $result = json_decode($body->getContents());

            $summary = $result->error_summary;

            if (isset($summary) && strpos($summary, "path/not_found") == 0) {
                $response = new Response("404",[
                    "Content-Length" => 0
                ], null);

                $event->intercept($response);
            }
        }
    }
}
