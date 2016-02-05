<?php
/**
 * Copyright 2007-2016 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <http://pydio.com/>.
 *
 * ------
 *
 * This file is adapted from Amazon S3 Client package.
 * Adapted to force the PathStyle to use path instead of VirtualHost for AWS S3 StreamWrapper
 *
 * Original Copyright 2010-2013 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 */
namespace Aws\S3;

use Guzzle\Common\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listener used to change the way in which buckets are referenced (path/virtual style) based on context
 */
class ForcePathStyleStyleListener implements EventSubscriberInterface
{
    private static $exclusions = array('GetBucketLocation' => true);

    public static function getSubscribedEvents()
    {
        return array('command.after_prepare' => array('onCommandAfterPrepare', -255));
    }

    /**
     * Changes how buckets are referenced in the HTTP request
     *
     * @param Event $event Event emitted
     */
    public function onCommandAfterPrepare(Event $event)
    {
        $command = $event['command'];
        $bucket = $command['Bucket'];
        $request = $command->getRequest();

        // Skip operations that do not need the bucket moved to the host.
        if (isset(self::$exclusions[$command->getName()])) {
            return;
        }

        if ($key = $command['Key']) {
            // Modify the command Key to account for the {/Key*} explosion into an array
            if (is_array($key)) {
                $command['Key'] = $key = implode('/', $key);
            }
        }

        // Set the key and bucket on the request
        $request->getParams()->set('bucket', $bucket)->set('key', $key);

        // Switch to virtual if PathStyle is disabled, or not a DNS compatible bucket name, or the scheme is
        // http, or the scheme is https and there are no dots in the host header (avoids SSL issues)
        // This is the initial code, automatically switching to VirtualHost
        /*
        if (!$command['PathStyle'] && $command->getClient()->isValidBucketName($bucket)
            && !($command->getRequest()->getScheme() == 'https' && strpos($bucket, '.'))
        ) {
            // Switch to virtual hosted bucket
            $request->setHost($bucket . '.' . $request->getHost());
            $request->setPath(preg_replace("#^/{$bucket}#", '', $request->getPath()));
        } else {
            $pathStyle = true;
        }
        */
        $pathStyle = true;

        if (!$bucket) {
            $request->getParams()->set('s3.resource', '/');
        } elseif ($pathStyle) {
            // Path style does not need a trailing slash
            $path = $request->getPath();
            $request->setHost(str_replace($bucket.".", "", $request->getHost()));
            $request->setPath('/'.rawurlencode($bucket).($path == "/" ? "":$path));
            $request->getParams()->set(
                's3.resource',
                '/' . rawurlencode($bucket) . ($key ? ('/' . S3Client::encodeKey($key)) : '')
            );
        } else {
            // Bucket style needs a trailing slash
            $request->getParams()->set(
                's3.resource',
                '/' . rawurlencode($bucket) . ($key ? ('/' . S3Client::encodeKey($key)) : '/')
            );
        }
    }
}
