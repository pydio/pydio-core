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

defined('AJXP_EXEC') or die('Access not allowed');


use Pydio\Core\Http\Response\XMLSerializableResponseChunk;

/**
 * XML result sent after successful or failed login.
 * @package Pydio\Core\Http\Message
 */
class LoggingResult implements XMLSerializableResponseChunk
{
    /**
     * @var int
     */
    private $result;
    /**
     * @var string
     */
    private $rememberLogin;
    /**
     * @var string
     */
    private $rememberPass;
    /**
     * @var string
     */
    private $secureToken;

    /**
     * LoggingResult constructor.
     * @param $result
     * @param string $rememberLogin
     * @param string $rememberPass
     * @param string $secureToken
     */
    public function __construct($result, $rememberLogin="", $rememberPass = "", $secureToken="")
    {
        $this->result = $result;
        $this->rememberLogin = $rememberLogin;
        $this->rememberPass = $rememberPass;
        $this->secureToken = $secureToken;
    }

    /**
     * @return int
     */
    public function getResult(){
        return $this->result;
    }

    /**
     * @return string
     */
    public function toXML()
    {
        $remString = "";
        if ($this->rememberPass != "" && $this->rememberLogin!= "") {
            $remString = " remember_login=\"$this->rememberLogin\" remember_pass=\"$this->rememberPass\"";
        }
        if ($this->secureToken != "") {
            $remString .= " secure_token=\"$this->secureToken\"";
        }
        return "<logging_result value=\"$this->result\"$remString/>";

    }
}