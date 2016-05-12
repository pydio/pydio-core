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
 * The latest code can be found at <http://pyd.io/>.
 */
namespace Pydio\Core\Http\Message;

use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Http\Response\XMLSerializableResponseChunk;

defined('AJXP_EXEC') or die('Access not allowed');

class BgActionTrigger implements XMLSerializableResponseChunk
{
    private $actionName;
    private $parameters;
    private $messageId;
    private $delay;

    private $javascriptCode;

    /**
     * @param string $actionName
     * @param array $parameters
     * @param string $messageId
     * @param int $delay
     */
    public function __construct($actionName, $parameters, $messageId, $delay = 0)
    {
        $this->actionName = $actionName;
        $this->parameters = $parameters;
        $this->messageId = $messageId;
        $this->delay = $delay;
    }

    public static function createForJsAction($jsCode, $messageId, $delay = 0){
        $newOne = new BgActionTrigger("javascript_action", [], $messageId, $delay);
        $newOne->javascriptCode = $jsCode;
        return $newOne;
    }

    /**
     * @return string
     */
    public function toXML()
    {
        if(isSet($this->javascriptCode)){
            return XMLWriter::triggerBgJSAction($this->javascriptCode, false, $this->delay);
        }else{
            return XMLWriter::triggerBgAction($this->actionName, $this->parameters, $this->messageId, false, $this->delay);
        }
    }
}