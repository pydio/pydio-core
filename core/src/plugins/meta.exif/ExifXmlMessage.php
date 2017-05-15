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
namespace Pydio\Access\Meta\Exif;

use Pydio\Core\Utils\Vars\StringHelper;


defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class ExifXmlMessage
 * @package Pydio\Access\Meta\Exif
 */
class ExifXmlMessage implements \Pydio\Core\Http\Response\XMLSerializableResponseChunk, \Pydio\Core\Http\Response\JSONSerializableResponseChunk
{
    protected $data;

    /**
     * ExifXmlMessage constructor.
     * @param $filteredData
     */
    public function __construct($filteredData)
    {
        $this->data = $filteredData;
    }

    /**
     * @return string
     */
    public function toXML()
    {
        $buffer = "";
        foreach ($this->data as $section => $data) {
            $buffer .= "<exifSection name='$section'>";
            foreach ($data as $key => $value) {
                $buffer .= "<exifTag name=\"$key\">". StringHelper::xmlEntities($value) ."</exifTag>";
            }
            $buffer .= "</exifSection>";
        }
        return $buffer;

    }

    /**
     * @return string
     */
    public function jsonSerializableKey()
    {
        return "exif";
    }

    /**
     * @return mixed
     */
    public function jsonSerializableData()
    {
        return $this->data;
    }

}