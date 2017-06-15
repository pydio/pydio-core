<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Http\Message;

use Zend\Diactoros\UploadedFile;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class ExternalUploadedFile
 * @package Pydio\Core\Http\Message
 */
class ExternalUploadedFile extends UploadedFile
{
    const STATUS_REQUEST_OPTIONS    = "request-options";
    const STATUS_UPLOAD_FINISHED    = "upload-finished";
    const STATUS_UPLOAD_ERROR       = "upload-error";

    private $status;

    /**
     * ExternalUploadedFile constructor.
     * @param string $status self::STATUS_REQUEST_OPTIONS, UPLOAD_FINISHED, UPLOAD_ERROR
     * @param int $size
     * @param int $clientFilename
     */
    public function __construct($status, $size, $clientFilename)
    {
        parent::__construct(
            "fake-tmp-file",
            $size,
            $status === self::STATUS_UPLOAD_ERROR ? UPLOAD_ERR_NO_FILE : UPLOAD_ERR_OK,
            $clientFilename,
            null
        );
        $this->status = $status;
    }

    /**
     * @return string One of the ExternalUploadedFile::STATUS_XXX constant value
     */
    public function getStatus(){
        return $this->status;
    }

    /**
     * Check if status has a valid value
     * @param $status
     * @return bool
     */
    public static function isValidStatus($status){
        return in_array($status, [self::STATUS_REQUEST_OPTIONS, self::STATUS_UPLOAD_ERROR, self::STATUS_UPLOAD_FINISHED]);
    }

}