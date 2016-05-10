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
namespace Pydio\Access\Core\Model;

use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Http\XMLSerializableResponseChunk;

defined('AJXP_EXEC') or die('Access not allowed');

class NodesDiff implements XMLSerializableResponseChunk
{
    /**
     * @var AJXP_Node[] Array of new nodes added, indexes are numeric.
     */
    private $added;

    /**
     * @var AJXP_Node[] Array of nodes updated, INDEXED by their original path.
     */
    private $updated;

    /**
     * @var string[] Array of removed nodes PATHES.
     */
    private $removed;

    public function __construct()
    {
        $this->added = [];
        $this->updated = [];
        $this->removed = [];
    }

    public function isEmpty(){
        return !(count($this->added) || count($this->updated) || count($this->removed));
    }

    /**
     * @param AJXP_Node|AJXP_Node[] $nodes
     */
    public function add($nodes){
        if(!is_array($nodes)) $nodes = [$nodes];
        $this->added = array_merge($this->added, $nodes);
    }

    /**
     * @param AJXP_Node|AJXP_Node[] $nodes
     * @param string|null $originalPath
     */
    public function update($nodes, $originalPath = null){
        if(!is_array($nodes)) $nodes = [$originalPath => $nodes];
        $this->updated = array_merge($this->updated, $nodes);
    }

    /**
     * @param string|string[] $nodePathes
     */
    public function remove($nodePathes){
        if(!is_array($nodePathes)) $nodePathes = [$nodePathes];
        $this->removed = array_merge($this->removed, $nodePathes);
    }

    /**
     * @return string
     */
    public function toXML()
    {
        return XMLWriter::writeNodesDiff(["ADD" => $this->added, "REMOVE" => $this->removed, "UPDATE" => $this->updated]);
    }
}