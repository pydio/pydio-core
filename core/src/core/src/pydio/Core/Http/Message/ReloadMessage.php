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
 * Class ReloadMessage
 * Sends a Reload instruction to the UI
 * @package Pydio\Core\Http\Message
 */
class ReloadMessage implements XMLSerializableResponseChunk, JSONSerializableResponseChunk
{
    private $dataNode = '';
    private $pendingSelection = '';

    /**
     * ReloadMessage constructor.
     * @param string $dataNode
     * @param string $pendingSelection
     */
    public function __construct($dataNode = "", $pendingSelection = ""){
        $this->dataNode = $dataNode;
        $this->pendingSelection = $pendingSelection;
    }


    /**
     * @return array
     */
    public function jsonSerializableData()
    {
        return ['node'=> $this->dataNode, 'pendingSelection' => $this->pendingSelection];
    }

    /**
     * @return string
     */
    public function jsonSerializableKey()
    {
        return 'reload';
    }

    /**
     * @return string
     */
    public function toXML()
    {
        $nodePath = StringHelper::xmlEntities($this->dataNode, true);
        $pendingSelection = StringHelper::xmlEntities($this->pendingSelection, true);
        return "<reload_instruction object=\"data\" node=\"$nodePath\" file=\"$pendingSelection\"/>";
    }
}