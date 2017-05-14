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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Core\Http\Message;

use Pydio\Core\Http\Response\JSONSerializableResponseChunk;
use Pydio\Core\Http\Response\XMLSerializableResponseChunk;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class UserMessage
 * User message displayed on the UI
 * @package Pydio\Core\Http\Message
 */
class UserMessage implements XMLSerializableResponseChunk, JSONSerializableResponseChunk
{

    private $level;
    private $message;

    /**
     * UserMessage constructor.
     * @param $message
     * @param string $level
     */
    public function __construct($message, $level=LOG_LEVEL_INFO)
    {
        $this->message = $message;
        $this->level = $level;
    }

    /**
     * @return string
     */
    public function toXML()
    {
        if ($this->level === LOG_LEVEL_INFO) {
            $messageType = "SUCCESS";
        } else {
            $messageType = strtoupper($this->level);
        }
        $message = StringHelper::xmlContentEntities($this->message);
        return "<message type=\"$messageType\">".$message."</message>";
    }

    /**
     * @return array
     */
    public function jsonSerializableData()
    {
        return ['level'=>$this->level, 'message' => $this->message];
    }

    /**
     * @return string
     */
    public function jsonSerializableKey()
    {
        return 'message';
    }
}