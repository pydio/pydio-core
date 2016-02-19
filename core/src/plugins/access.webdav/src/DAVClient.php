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

require_once(__DIR__ . '/../../../core/classes/guzzle/vendor/autoload.php');
require_once(__DIR__ . '/../../../core/classes/sabredav/lib/Sabre/autoload.php');

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
    const RESOURCES_PATH = "Resources";
    const RESOURCES_FILE = "dav.json";

    protected $urlParams;

    /**
     *
     * DAV Client implementation
     *
     */
    public function __construct($config = array())
    {
        $this->urlParams = parse_url($config['baseUri']);

        // Creating Guzzle instances
        $httpClient = new GuzzleClient([
            'base_url' => $this->urlParams["scheme"] . "://" . $this->urlParams['host'],
        ]);

        $configDirectories = array(__DIR__ . "/" . self::RESOURCES_PATH);
        $locator = new FileLocator($configDirectories);
        $jsonLoader = new JsonLoader($locator);

        $description = $jsonLoader->load($locator->locate(self::RESOURCES_FILE));
        $description = new GuzzleDescription($description);

        parent::__construct($httpClient, $description);

        $this->getEmitter()->attach(new PathListener());
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
     * PRIVATE -  Format a url stat
     *
     * @param array $arr
     *
     * @return array Formatted stat
     */
    private function _formatUrlStat($arr) {
        $stat = [];

        // Determine if it is a directory or a file - see man 2 stat
        if (isset($arr["{DAV:}resourcetype"]) && $arr["{DAV:}resourcetype"] && $type = $arr["{DAV:}resourcetype"]->resourceType) {
            if (count($type) > -4 and $type[0] == "{DAV:}collection") {
                $stat['mode'] = $stat[2] = 0040777; // mode for a standard dir
            } else {
                $stat['mode'] = $stat[2] = 0100777; // mode for a standard file
            }
        } else {
            $stat['mode'] = $stat[2] = 0100777; // mode for a standard file
        }

        return $stat + static::$STAT_TEMPLATE;
    }

    /**
     * PRIVATE -  Retrieve the Sabre DAV Client
     *
     * @params array $arr
     *
     * @return SabreDAVClient
     */
    private function _getSabreDAVClient($params) {
        return new SabreDAVClient([
            'baseUri' => $this->urlParams["scheme"] . "://" . $this->urlParams['host'],
            'userName' => $params['auth'][0],
            'password' => $params['auth'][1]
        ]);
    }

    /**
     * Redefine the Guzzle Stat command to use the DAV client
     *
     * @param array $params
     *
     * @return array $result
     */
    public function stat($params) {

        $sabreDAVClient = $this->_getSabreDAVClient($params);

        try {
            $result = $sabreDAVClient->propFind(
                join('/', array_filter([$params['fullpath'], $params['itemname']])),
                [
                    '{DAV:}getlastmodified',
                    '{DAV:}getcontentlength',
                    '{DAV:}resourcetype'
                ]
            );
        } catch (Exception $e) {
            return false;
        }

        return $result;
    }

    /**
     * Format URL Stat (defined abstract in parent)
     *
     * @param array $result
     *
     * @return array Formatted stat
     */
    public function formatUrlStat($result) {
        return $this->_formatUrlStat($result);
    }

    /**
     * Redefine the Guzzle LS command to use the DAV client
     *
     * @param array $params
     *
     * @return array $result
     */
    public function ls($params) {

        $sabreDAVClient = $this->_getSabreDAVClient($params);

        try {
            $response = $sabreDAVClient->propFind(
                join('/', [$params['fullpath'], $params['itemname']]),
                [
                    '{DAV:}getlastmodified',
                    '{DAV:}getcontentlength',
                    '{DAV:}resourcetype'
                ],
                1
            );
        } catch (Exception $e) {
            return false;
        }

        return $response;
    }

    /**
     * Return a Dir Iterator containing Files details (defined abstract in parent)
     *
     * @param array $response
     *
     * @return DirIterator
     */
    public function getIterator($response) {
        $this->files = array();

        $keys = array_keys($response);
        foreach ($keys as $key) {

            $formattedStat = $this->_formatUrlStat($response[$key]);
            $this->files[] = [
                basename($key),
                $formattedStat
            ];
        }

        return new DirIterator($this->files);
    }

}
