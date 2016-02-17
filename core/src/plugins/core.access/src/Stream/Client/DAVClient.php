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

namespace CoreAccess\Stream\Client;

use CoreAccess\Stream\StreamWrapper;
use CoreAccess\Stream\Listener\PathListener;
use CoreAccess\Stream\Iterator\DirIterator;
use Guzzle\Service\Loader\JsonLoader;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Command\Guzzle\Description as GuzzleDescription;
use Symfony\Component\Config\FileLocator;
use Sabre\DAV\Client as SabreDAVClient;

/**
 * Client to interact with a WebDAV FS
 *
 */
class DAVClient extends AbstractClient
{
    const PROTOCOL = "dav";
    const RESOURCES_PATH = "../Resources";
    const RESOURCES_FILE = "dav.json";

    protected static $urlParams;
    protected static $client;
    protected static $httpClient;
    protected static $sabreDAVClient;

    public static function factory($config = array())
    {
        static::$urlParams = parse_url($config['baseUri']);

        // Creating Guzzle instances
        $httpClient = new GuzzleClient([
            'base_url' => $config['baseUri'],
            'defaults' => [
                'auth'    => [$config['username'], $config['password']],
            ]
        ]);

        $configDirectories = array(__DIR__ . "/" . self::RESOURCES_PATH);
        $locator = new FileLocator($configDirectories);
        $jsonLoader = new JsonLoader($locator);

        $description = $jsonLoader->load($locator->locate(self::RESOURCES_FILE));
        $description = new GuzzleDescription($description);
        $client = new self($httpClient, $description);

        $client->getEmitter()->attach(new PathListener());

        static::$httpClient = $httpClient;

        // Creating DAV instance
        $sabreDAVClient = new SabreDAVClient([
            'baseUri' => $config['baseUri'] . '/',
            'userName' => $config['username'],
            'password' => $config['password']
        ]);

        static::$sabreDAVClient = $sabreDAVClient;

        return $client;
    }

    /**
     * Register the Sabre DAV stream wrapper and associates it with this client object
     *
     * @return $this
     */
    public function registerStreamWrapper()
    {
        StreamWrapper::register($this);

        return $this;
    }

    /**
     * Get the params from the passed path
     *
     * @param string $path Path passed to the stream wrapper
     *
     * @return array Hash of custom params
     */
    public function getParams($path, $defaults = array())
    {
        $parts = parse_url($path);

        $parts['fullpath'] = static::$urlParams['path'] . $parts['path'];

        return $parts;
    }

    // We intercept all calls to the stat command to redirect to the DAV Client
    public function stat($params) {
        try {
            $result = static::$sabreDAVClient->propFind(
                ltrim($params['path'], '/'),
                array(
                    '{DAV:}getlastmodified',
                    '{DAV:}getcontentlength',
                    '{DAV:}resourcetype'

                )
            );
        } catch (Exception $e) {
            return false;
        }


        return $this->formatUrlStat($result);
    }

    public function ls($params) {
        try {
            $response = static::$sabreDAVClient->propFind(
                ltrim($params['path'], '/'),
                array(
                    '{DAV:}getlastmodified',
                    '{DAV:}getcontentlength',
                    '{DAV:}resourcetype'
                ),
                1
            );

            $this->files = array();

            $keys = array_keys($response);
            foreach ($keys as $key) {

                $formattedStat = $this->formatUrlStat($response[$key]);
                $this->files[] = [
                    basename($key),
                    $formattedStat
                ];
            }

            return new DirIterator($this->files);

        } catch (Exception $e) {
            return false;
        }
    }
}
