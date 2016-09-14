<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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
 */
namespace Pydio\Core\Http\Message;

use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Http\Response\JSONSerializableResponseChunk;
use Pydio\Core\Http\Response\XMLSerializableResponseChunk;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class Message
 * Generic user message serialized either as XML or JSON.
 * @package Pydio\Core\Http\Message
 */
class Message implements XMLSerializableResponseChunk, JSONSerializableResponseChunk
{
    /**
     * @var string
     */
    private $message;

    /**
     * Message constructor.
     * @param string $message
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function toXML()
    {
        return XMLWriter::sendMessage($this->message, $this->message);
    }

    /**
     * @return string
     */
    public function jsonSerializableData()
    {
        return $this->message;
    }

    /**
     * @return string
     */
    public function jsonSerializableKey()
    {
        return 'message';
    }
}