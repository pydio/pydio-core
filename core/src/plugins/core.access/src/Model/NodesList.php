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
use Pydio\Core\Http\Response\CLISerializableResponseChunk;
use Pydio\Core\Http\Response\JSONSerializableResponseChunk;
use Pydio\Core\Http\Response\XMLDocSerializableResponseChunk;
use Pydio\Core\Services\LocaleService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class NodesList
 * @package Pydio\Access\Core\Model
 */
class NodesList implements XMLDocSerializableResponseChunk, JSONSerializableResponseChunk, CLISerializableResponseChunk
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

    /**
     * NodesList constructor.
     * @param string $rootPath
     */
    public function __construct($rootPath = "/"){
        // Create a fake parent node by default, without label
        $this->parentNode = new AJXP_Node($rootPath, ["text" => "", "is_file" => false]);
    }

    /**
     * @param AJXP_Node $parentNode
     */
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
     * @return AJXP_Node[]|NodesList[]
     */
    public function getChildren(){
        return $this->children;
    }

    /**
     * @param $path
     * @return AJXP_Node
     */
    public function findChildByPath( $path ){
        return array_shift(array_filter($this->children, function($child) use ($path){
            return ($child instanceof AJXP_Node && $child->getPath() == $path);
        }));
    }

    /**
     * @param $count
     * @param $currentPage
     * @param $totalPages
     * @param int $dirsCount
     * @param null $remoteSortAttributes
     */
    public function setPaginationData($count, $currentPage, $totalPages, $dirsCount = -1, $remoteSortAttributes = null){
        $this->paginationData = [
            'count' => $count,
            'current' => $currentPage,
            'total'   => $totalPages,
            'dirs'   => $dirsCount,
            'remoteSort' => $remoteSortAttributes
        ];
    }

    /**
     * @param $bool
     */
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
        if(isSet($this->columnsDescription)){
            $xmlChildren = [];
            foreach($this->columnsDescription['columns'] as $column){
                $xmlChildren[] = XMLWriter::toXmlElement("column", $column);
            }
            $xmlConfig = XMLWriter::toXmlElement("columns", $this->columnsDescription['description'], implode("", $xmlChildren));
            $xmlConfig = XMLWriter::toXmlElement("component_config", ["className" => "FilesList"], $xmlConfig);
            $buffer .= XMLWriter::toXmlElement("client_configs", [], $xmlConfig);
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
     * @return $this
     */
    public function initColumnsData($switchGridMode='', $switchDisplayMode='', $templateName=''){
        $this->columnsDescription = [
            'description' => ['switchGridMode' => $switchGridMode, 'switchDisplayMode' => $switchDisplayMode, 'template_name' => $templateName],
            'columns'     => []
        ];
        return $this;
    }

    /**
     * @param string $messageId
     * @param string $attributeName
     * @param string $sortType
     * @param string $width
     * @return $this
     */
    public function appendColumn($messageId, $attributeName, $sortType='String', $width=''){
        $this->columnsDescription['columns'][] = [
            'messageId'     => $messageId,
            'attributeName' => $attributeName,
            'sortType'      => $sortType,
            'width'         => $width
        ];
        return $this;
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

    /**
     * @return string
     */
    public function getCharset()
    {
        return "UTF-8";
    }

    /**
     * @param OutputInterface $output
     * @return mixed
     */
    public function render($output)
    {
        // If recursive, back to JSON for the moment
        $recursive = false;
        foreach($this->children as $child){
            if($child instanceof NodesList){
                $recursive = true;
            }
        }
        if($recursive){
            $data = $this->jsonSerializableData();
            $output->writeln(json_encode($data, JSON_PRETTY_PRINT));
            return;
        }

        $table      = new Table($output);
        $headers    = [];
        $rows       = [];

        // Prepare Headers
        if(isSet($this->columnsDescription["columns"])){
            $messages = LocaleService::getMessages();
            foreach($this->columnsDescription["columns"] as $column){
                $colTitle = $messages[$column["messageId"]];
                $collAttr = $column["attributeName"];
                $headers[$collAttr] = $colTitle;
            }
        }else{
            /** @var AJXP_Node $firstNode */
            $firstNode = $this->children[0];
            $headers["text"] = "Label"; //$firstNode->getLabel();
            $meta = $firstNode->getNodeInfoMeta();
            foreach($meta as $attName => $value){
                if(in_array($attName, ["text", "ajxp_description"])) continue;
                if($attName === "filename") {
                    $headers[$attName] = "Path";
                }else{
                    $headers[$attName] = ucfirst($attName);
                }
            }
        }
        $table->setHeaders(array_values($headers));

        // Prepare Rows
        foreach($this->children as $child){
            $row = [];
            foreach($headers as $attName => $label){
                if($attName === "text" || $attName === "ajxp_label") $row[] = $child->getLabel();
                else if($attName === "is_file") $row[] = $child->isLeaf() ? "True" : "False";
                else $row[] = $child->$attName;
            }
            $rows[] = $row;
        }
        $table->setRows($rows);

        // Render
        $table->render();
    }
}