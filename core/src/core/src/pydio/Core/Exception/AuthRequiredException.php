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
namespace Pydio\Core\Exception;

use Pydio\Core\Http\Message\LoggingResult;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Response\JSONSerializableResponseChunk;
use Pydio\Core\Http\Response\XMLSerializableResponseChunk;

use Pydio\Core\Services\LocaleService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class AuthRequiredException
 * @package Pydio\Core\Exception
 */
class AuthRequiredException extends PydioException implements XMLSerializableResponseChunk, JSONSerializableResponseChunk
{
    private $loginResult;

    /**
     * AuthRequiredException constructor.
     * @param string $messageId
     * @param string $messageString
     * @param null $loginResult
     */
    public function __construct($messageId = "", $messageString = "", $loginResult = null)
    {
        if(!empty($messageId)){
            $mess = LocaleService::getMessages();
            if(isSet($mess[$messageId])) $messageString = $mess[$messageId];
        }
        if(!empty($loginResult)){
            $this->loginResult = $loginResult;
        }
        parent::__construct($messageString, $messageId);
    }

    /**
     * @return mixed
     */
    public function jsonSerializableData()
    {
        return ["message" => $this->getMessage()];
    }

    /**
     * @return string
     */
    public function jsonSerializableKey()
    {
        return "authRequired";
    }

    /**
     * @return string
     */
    public function toXML()
    {
        if(!empty($this->loginResult)){
            $res = new LoggingResult($this->loginResult, "", "", "");
            $xml = $res->toXML();
        }else{
            $xml = "<require_auth/>";
            if($this->getMessage()){
                $error = new UserMessage($this->getMessage(), LOG_LEVEL_ERROR);
                $xml.= $error->toXML();
            }
        }
        return $xml;
    }
}