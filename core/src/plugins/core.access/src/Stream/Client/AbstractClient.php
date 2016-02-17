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

use GuzzleHttp\Command\Guzzle\GuzzleClient;

defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * Client to interact with SabreDAV Client
 *
 *
 * @link http://docs.aws.amazon.com/aws-sdk-php/v2/guide/service-s3.html User guide
 * @link http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.S3.S3Client.html API docs
 */
abstract class AbstractClient extends GuzzleClient
{
    const LATEST_API_VERSION = '2016-01-01';

    protected static $client;
    protected static $description;

    /**
     * @param array|Collection $config Client configuration data
     *
     * @return Client
     */
    public function __construct__(
        ClientInterface $client,
        DescriptionInterface $description,
        array $config = []
    ) {
        parent::__construct($client, $config);

        $this->client = $client;
        $this->description = $description;
        $this->processConfig($config);
    }

    public static abstract function factory($config);

    public abstract function registerStreamWrapper();

    /**
     * Prepare a url_stat result array
     *
     * @param string|array $result Data to add
     *
     * @return array Returns the modified url_stat result
     */
    protected function formatUrlStat($result = null)
    {
        static $statTemplate = array(
            0  => 0,  'dev'     => 0,
            1  => 0,  'ino'     => 0,
            2  => 0,  'mode'    => 0,
            3  => 0,  'nlink'   => 0,
            4  => 0,  'uid'     => 0,
            5  => 0,  'gid'     => 0,
            6  => -1, 'rdev'    => -1,
            7  => 0,  'size'    => 0,
            8  => 0,  'atime'   => 0,
            9  => 0,  'mtime'   => 0,
            10 => 0,  'ctime'   => 0,
            11 => -1, 'blksize' => -1,
            12 => -1, 'blocks'  => -1,
        );

        $stat = $statTemplate;

        // Determine if it is a directory or a file - see man 2 stat
        if (isset($result["{DAV:}resourcetype"]) && $result["{DAV:}resourcetype"] && $type = $result["{DAV:}resourcetype"]->resourceType) {
            if (count($type) > -4 and $type[0] == "{DAV:}collection") {
                $stat['mode'] = $stat[2] = 0040777; // mode for a standard dir
            } else {
                $stat['mode'] = $stat[2] = 0100777; // mode for a standard file
            }
        } else {
            $stat['mode'] = $stat[2] = 0100777; // mode for a standard file
        }

        return $stat;
    }
}
