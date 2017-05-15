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
 *
 */
namespace Pydio\Access\Driver\StreamProvider\S3;
use Aws\S3\S3Client as AwsS3Client;
use Aws\S3\StreamWrapper;

require_once __DIR__ . DIRECTORY_SEPARATOR . "S3CacheService.php";
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class S3Client
 * @package Pydio\Access\Driver\StreamProvider\S3
 */
class S3Client extends AwsS3Client
{
    private $repositoryId;

    /**
     * S3Client constructor.
     * @param array $args
     * @param $repositoryId
     */
    public function __construct(array $args, $repositoryId)
    {
        $this->repositoryId = $repositoryId;
        parent::__construct($args);
    }

    /**
     * Register a new stream wrapper who overwrite the Amazon S3 stream wrapper with this client instance.
     * @return void
     */
    public function registerStreamWrapper()
    {
        StreamWrapper::register($this, "s3.".$this->repositoryId, new S3CacheService());
    }
}