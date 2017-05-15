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

use Pydio\Core\Http\Response\XMLSerializableResponseChunk;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class ReloadRepoListMessage Encapsulate XML message for reloading repositories list
 * @package Pydio\Core\Http\Message
 */
class ReloadRepoListMessage extends Message implements XMLSerializableResponseChunk
{
    /**
     * ReloadRepoListMessage constructor.
     */
    public function __construct()
    {
        parent::__construct("reload_repositories");
    }

    /**
     * Serialize as XML
     * @return string
     */
    public function toXML()
    {
        return "<reload_instruction object=\"repository_list\"/>";
    }

    public static function XML(){
        $m = new ReloadRepoListMessage();
        return $m->toXML();
    }

}