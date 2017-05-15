<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
namespace Pydio\Access\Core\Filter;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Utils\Vars\PathUtils;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class ContentFilter
 */
class ContentFilter {

    public $filters = array();
    public $virtualPaths = array();

    /**
     * @param AJXP_Node[] $nodes
     */
    function __construct($nodes){
        foreach($nodes as $n){
            $virtualPath = $this->getVirtualPath($n->getPath());
            $this->filters[$n->getPath()] = $virtualPath;
        }
        $this->virtualPaths = array_flip($this->filters);
    }

    /**
     * @param $path
     * @return string
     */
    private function getVirtualPath($path){
        return "/".substr(md5($path), 0, 10)."/".basename($path);
    }

    /**
     * @param UserSelection $userSelection
     */
    function filterUserSelection( &$userSelection ){
        if($userSelection->isEmpty()){
            foreach($this->filters as $path => $virtual){
                $userSelection->addFile($path);
            }
        }else{
            $newFiles = array();
            foreach($userSelection->getFiles() as $f){
                if(isSet($this->virtualPaths[$f])){
                    $newFiles[] = $this->virtualPaths[$f];
                }else{
                    $testB = base64_decode($f);
                    if(isSet($this->virtualPaths[$testB])){
                        $newFiles[] = $this->virtualPaths[$testB];
                    }
                }
            }
            $userSelection->setFiles($newFiles);
        }
    }

    /**
     * @return mixed|string
     */
    function getBaseDir(){
        return PathUtils::forwardSlashDirname(array_keys($this->filters)[0]);
    }

    /**
     * Retrieves the path of the first object
     * @return mixed|string
     */
    function getUniquePath(){
        return PathUtils::forwardSlashBasename(array_keys($this->filters)[0]);
    }

    /**
     * @param AJXP_Node $node
     * @return String
     */
    function externalPath(AJXP_Node $node){
        return $this->getVirtualPath($node->getPath());
    }

    /**
     * @param String $vPath
     * @return String mixed
     */
    function filterExternalPath($vPath){
        if(isSet($this->virtualPaths) && isSet($this->virtualPaths[$vPath])){
            return $this->virtualPaths[$vPath];
        }
        return $vPath;
    }

    /**
     * @param String $oldPath
     * @param String $newPath
     * @return bool Operation result
     */
    public function movePath($oldPath, $newPath){

        if(isSet($this->filters[$oldPath])){
            $this->filters[$newPath] = $this->getVirtualPath($newPath);
            unset($this->filters[$oldPath]);
            $this->virtualPaths = array_flip($this->filters);
            return true;
        }
        return false;

    }

    /**
     * @return array public data as array, pre-utf8 encoded
     */
    public function toArray(){
        $data = array("filters" => array(), "virtualPaths" => array());
        foreach($this->filters as $k => $v){
            $data["filters"][$k] = $v;
        }
        foreach($this->virtualPaths as $k => $v){
            $data["virtualPaths"][$k] = $v;
        }
        return $data;
    }

    /**
     * @param $filters
     */
    public function fromFilterArray($filters){
        $this->filters = $filters;
        $this->virtualPaths = array_flip($this->filters);
    }

} 