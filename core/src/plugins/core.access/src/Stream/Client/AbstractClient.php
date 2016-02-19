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
 * The latest code can be found at <http://pyd.io/>.
 */

namespace CoreAccess\Stream\Client;

use GuzzleHttp\Command\Guzzle\GuzzleClient;

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

    public static $STAT_TEMPLATE = [
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
    ];

    /**
     * @param ClientInterface      $client      HTTP client to use.
     * @param DescriptionInterface $description Guzzle service description
     * @param array                $config      Configuration options
     */
    public function __construct__(
        ClientInterface $client,
        DescriptionInterface $description,
        array $config = []
    ) {
        parent::__construct($client, $config);

        $this->processConfig($config);
    }

    /**
     * Register this client on the StreamWrapper
     */
    public abstract function registerStreamWrapper();

    /**
     * Redefine a file stat
     *
     * @param array $arr
     *
     * @return array Associative array containing the stats
     */
    public abstract function formatUrlStat($arr);

    /**
     * Get a Directory Iterator based on the given array
     *
     * @param array $arr
     *
     * @return DirIterator
     */
    public abstract function getIterator($arr);

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
        $repositoryId = $parts["host"];
        $repository = \ConfService::getRepositoryById($repositoryId);
        $credentials = \AJXP_Safe::tryLoadingCredentialsFromSources($parts, $repository);

        $username = $credentials["user"];
        $password = $credentials["password"];

        $this->setConfig('defaults/request_options/auth', [$username, $password]);

        $parts['basepath'] = $this->urlParams['path'];
        $parts['fullpath'] = dirname($this->urlParams['path'] . $parts['path']);
        $parts['itemname'] = basename($parts['path']);

        if (!isset($parts['path'])) {
            $parts['fullpath'] = $parts['basepath'];
            $parts['path'] = '/';
        }

        $parts['path'] = dirname($parts['path']);
        $parts['auth'] = [$username, $password];

        return $parts;
    }
}
