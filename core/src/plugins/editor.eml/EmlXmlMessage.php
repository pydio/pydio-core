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
namespace Pydio\Editor\EML;

use Pydio\Core\Http\Response\XMLDocSerializableResponseChunk;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class EmlXmlMessage
 * @package Pydio\Editor\EML
 */
class EmlXmlMessage implements XMLDocSerializableResponseChunk
{

    protected $charset;
    protected $html;
    protected $text;

    /**
     * EmlXmlMessage constructor.
     * @param string $charset
     * @param bool $html
     * @param bool $text
     */
    public function __construct($charset = "UTF-8", $html = false, $text = false)
    {
        $this->charset = $charset;
        $this->html = $html;
        $this->text = $text;
    }

    /**
     * @return string
     */
    public function toXML()
    {
        $buffer = "";
        if (isSet($this->charset)) {
            header('Content-Type: text/xml; charset='.$this->charset);
        } 
        $buffer .= '<email_body>';
        if ($this->html!==false) {
            $buffer .= '<mimepart type="html"><![CDATA[';
            $buffer .= $this->html->body;
            $buffer .= "]]></mimepart>";
        }
        if ($this->text!==false) {
            $buffer .= '<mimepart type="plain"><![CDATA[';
            $buffer .= $this->text->body;
            $buffer .= "]]></mimepart>";
        }
        $buffer .= "</email_body>";
        return $buffer;
        
    }

    /**
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

}