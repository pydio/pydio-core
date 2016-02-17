<?php
/**
 * Copyright 2010-2013 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

namespace CoreAccess\Stream\Listener;

use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Event\RequestEvents;

/**
 * Listener used to change the way the paths are given (no urlencode for the slash)
 */
class PathListener implements SubscriberInterface
{
    public function getEvents()
    {
        return [
            'prepared'   => ['onBefore']
        ];
    }

    public function onBefore(PreparedEvent $e)
    {
        /** @var Request $request */
        $request = $e->getRequest();
        if (empty($request)) {
            return;
        }

        $path = $request->getPath();
        if (empty($path)) {
            return;
        }

        $path = rawurldecode($path);
        $path = str_replace('//', '/', $path);

        $request->setPath($path);
    }
}
