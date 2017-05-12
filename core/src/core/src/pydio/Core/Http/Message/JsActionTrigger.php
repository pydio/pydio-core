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

use Pydio\Core\Http\Response\XMLSerializableResponseChunk;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class BgActionTrigger
 * An XML response triggering a background action in Pydio UI.
 * @package Pydio\Core\Http\Message
 */
class JsActionTrigger implements XMLSerializableResponseChunk
{
    private $delay;
    private $javascriptCode;

    /**
     * @param string $jsCode
     * @param int $delay
     */
    public function __construct($jsCode, $delay = 0)
    {
        $this->javascriptCode = $jsCode;
        $this->delay = $delay;
    }

    /**
     * @return string
     */
    public function toXML()
    {
        $data = "<trigger_bg_action name=\"javascript_instruction\" delay=\"".$this->delay."\">";
        $data .= "<clientCallback><![CDATA[".$this->javascriptCode."]]></clientCallback>";
        $data .= "</trigger_bg_action>";
        return $data;
    }
}