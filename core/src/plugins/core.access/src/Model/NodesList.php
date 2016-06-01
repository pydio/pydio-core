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

defined('AJXP_EXEC') or die('Access not allowed');

use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Http\Response\JSONSerializableResponseChunk;
use Pydio\Core\Http\Response\XMLDocSerializableResponseChunk;

class NodesList implements XMLDocSerializableResponseChunk, JSONSerializableResponseChunk
{

    /**
     * @var AJXP_Node
     */
    private $parentNode;
    /**
     * @var (AJXP_Node|NodesList)[]
     */
    private $children = array();

    private $isRoot = true;

    private $paginationData;

    private $columnsDescription;

    public function __construct(){
        $this->parentNode = new AJXP_Node("/");
    }

    public function setParentNode(AJXP_Node $parentNode){
        $this->parentNode = $parentNode;
    }
    /**
     * @param AJXP_Node|NodesList $nodeOrList
     */
    public function addBranch($nodeOrList){
        $this->children[] = $nodeOrList;
        if($nodeOrList instanceof NodesList){
            $nodeOrList->setRoot(false);
        }
    }

    /**
     * @return array
     */
    public function getChildren(){
        return $this->children;
    }

    public function setPaginationData($count, $currentPage, $totalPages, $dirsCount = -1, $remoteSortAttributes = null){
        $this->paginationData = [
            'count' => $count,
            'current' => $currentPage,
            'total'   => $totalPages,
            'dirs'   => $dirsCount,
            'remoteSort' => $remoteSortAttributes
        ];
    }

    public function setRoot($bool){
        $this->isRoot = $bool;
    }

    /**
     * @return string
     */
    public function toXML()
    {
        $buffer  = "";
        $buffer .= XMLWriter::renderAjxpNode($this->parentNode, false, false);
        if(isSet($this->paginationData)){
            $buffer .= XMLWriter::renderPaginationData(
                $this->paginationData["count"],
                $this->paginationData["current"],
                $this->paginationData["total"],
                $this->paginationData["dirs"],
                $this->paginationData["remoteSort"],
                false);
        }
        foreach ($this->children as $child){
            if($child instanceof NodesList){
                $buffer .= $child->toXML();
            }else{
                $buffer .= XMLWriter::renderAjxpNode($child, true, false);
            }
        }
        $buffer .= XMLWriter::close("tree", false);
        return $buffer;
    }

    /**
     * @param string $switchGridMode
     * @param string $switchDisplayMode
     * @param string $templateName
     */
    public function initColumnsData($switchGridMode='', $switchDisplayMode='', $templateName=''){
        $this->columnsDescription = [
            'description' => ['switchGridMode' => $switchGridMode, 'switchDisplayMode' => $switchDisplayMode, 'template_name' => $templateName],
            'columns'     => []
        ];
    }

    /**
     * @param string $messageId
     * @param string $attributeName
     * @param string $sortType
     * @param string $width
     */
    public function appendColumn($messageId, $attributeName, $sortType='String', $width=''){
        $this->columnsDescription['columns'][] = [
            'messageId'     => $messageId,
            'attributeName' => $attributeName,
            'sortType'      => $sortType,
            'width'         => $width
        ];
    }

    /**
     * @return mixed
     */
    public function jsonSerializableData()
    {
        $children = [];
        foreach ($this->children as $child){
            if($child instanceof NodesList){
                $children[$child->jsonSerializableKey()] = $child->jsonSerializableData();
            }else{
                $children[$child->getPath()] = $child;
            }
        }
        if(isSet($this->paginationData)){
            return [
                "pagination" => $this->paginationData,
                "data"      => ["node" => $this->parentNode, "children" => $children]
            ];
        }else{
            return [ "node" => $this->parentNode, "children" => $children];
        }
    }

    /**
     * @return string
     */
    public function jsonSerializableKey()
    {
        return $this->parentNode->getPath();
    }

    public function getCharset()
    {
        return "UTF-8";
    }
}