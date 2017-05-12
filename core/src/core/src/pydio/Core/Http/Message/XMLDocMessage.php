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

defined('AJXP_EXEC') or die('Access not allowed');

use Pydio\Core\Http\Response\XMLDocSerializableResponseChunk;

/**
 * Class XMLDocMessage
 * XML Message, represented as a whole XML Document
 * @package Pydio\Core\Http\Message
 */
class XMLDocMessage extends \DOMDocument implements XMLDocSerializableResponseChunk
{
    /**
     * XMLDocMessage constructor.
     * @param string $xmlString
     */
    public function __construct($xmlString = null)
    {
        parent::__construct("1.0", "UTF-8");
        if(!empty($xmlString)){
            $this->loadXML($xmlString);
        }
    }

    /**
     * @return string
     */
    public function getCharset()
    {
        return  'UTF-8';
    }

    /**
     * @return string
     */
    public function toXML()
    {
        return $this->saveXML();
    }
}