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

namespace Pydio\Access\Core\Stream;

use GuzzleHttp\Command\Guzzle\GuzzleClient\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Command\Guzzle\GuzzleClient\DescriptionInterface as DescriptionInterface;

/**
 * Client to interact with SabreDAV Client
 *
 *
 * @link http://docs.aws.amazon.com/aws-sdk-php/v2/guide/service-s3.html User guide
 * @link http://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.S3.S3Client.html API docs
 */
interface ClientInterface
{
    /**
     * Register this client on the StreamWrapper
     */
    public function registerStreamWrapper();

    /**
     *
     * Set the authentication parameters
     *
     * @param array $params
     */
    public function setAuth($arr);

    /**
     * Set the default url of the client
     *
     * @param string $url
     */
    public function setDefaultUrl($url);

    /**
     * Redefine a file stat
     *
     * @param array $arr
     *
     * @return array Associative array containing the stats
     */
    public function formatUrlStat($arr);

    /**
     * Get a Directory Iterator based on the given array
     *
     * @param array $arr
     *
     * @return DirIterator
     */
    public function getIterator($arr, $params);
}
