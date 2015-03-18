<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * Abstraction of a user selection passed via http parameters.
 * @package Pydio
 * @subpackage Core
 */
class UserSelection
{
    public $files;
    private $nodes;
    public $varPrefix = "file";
    public $dirPrefix = "dir";
    public $isUnique = true;
    public $dir;

    public $inZip = false;
    public $zipFile;
    public $localZipPath;

    /**
     * @var ContentFilter
     */
    private $contentFilter;

    /**
     * Construction selector
     * @param Repository|null $repository
     */
    public function UserSelection($repository = null, $httpVars = null)
    {
        $this->files = array();
        if(isSet($repository) && $repository->hasContentFilter()){
            $this->contentFilter = $repository->getContentFilter();
        }
        if(iSset($httpVars)){
            $this->initFromHttpVars($httpVars);
        }
    }
    /**
     * Init the selection from the query vars
     * @param array $passedArray
     * @return void
     */
    public function initFromHttpVars($passedArray=null)
    {
        if ($passedArray != null) {
            if (isSet($passedArray["selection_nodes"]) && is_array($passedArray["selection_nodes"])) {
                $this->initFromNodes($passedArray["selection_nodes"]);
            } else {
                $this->initFromArray($passedArray);
            }
        } else {
            $this->initFromArray($_GET);
            $this->initFromArray($_POST);
        }
        if(isSet($this->contentFilter)){
            $this->contentFilter->filterUserSelection($this);
        }
    }

    /**
     * @param AJXP_Node[] $nodes
     */
    public function initFromNodes($nodes)
    {
        $this->nodes = $nodes;
        $this->isUnique = (count($nodes) == 1);
        $this->dir = '/';
        $this->files = array();
        $this->inZip = false;
        foreach ($nodes as $n) {
            $this->files[] = $n->getPath();
        }

    }

    /**
     *
     * Init from a simple array
     * @param $array
     */
    public function initFromArray($array)
    {
        if (!is_array($array)) {
            return ;
        }
        if (isSet($array[$this->varPrefix]) && $array[$this->varPrefix] != "") {
            $v = $array[$this->varPrefix];
            if(strpos($v, "base64encoded:") === 0){
                $v = base64_decode(array_pop(explode(':', $v, 2)));
            }
            $this->files[] = AJXP_Utils::decodeSecureMagic($v);
            $this->isUnique = true;
            //return ;
        }
        if (isSet($array[$this->varPrefix."_0"])) {
            $index = 0;
            while (isSet($array[$this->varPrefix."_".$index])) {
                $v = $array[$this->varPrefix."_".$index];
                if(strpos($v, "base64encoded:") === 0){
                    $v = base64_decode(array_pop(explode(':', $v, 2)));
                }
                $this->files[] = AJXP_Utils::decodeSecureMagic($v);
                $index ++;
            }
            $this->isUnique = false;
            if (count($this->files) == 1) {
                $this->isUnique = true;
            }
            //return ;
        }
        if (isSet($array["nodes"]) && is_array($array["nodes"])) {
            $this->files = array();
            foreach($array["nodes"] as $value){
                $this->files[] = AJXP_Utils::decodeSecureMagic($value);
            }
            $this->isUnique = count($this->files) == 1;
        }
        if (isSet($array[$this->dirPrefix])) {
            $this->dir = AJXP_Utils::securePath($array[$this->dirPrefix]);
            if ($test = $this->detectZip($this->dir)) {
                $this->inZip = true;
                $this->zipFile = $test[0];
                $this->localZipPath = $test[1];
            }
        } else if (!$this->isEmpty() && $this->isUnique()) {
            if ($test = $this->detectZip(AJXP_Utils::safeDirname($this->files[0]))) {
                $this->inZip = true;
                $this->zipFile = $test[0];
                $this->localZipPath = $test[1];
            }
        }
    }
    /**
     * Does the selection have one or more items
     * @return bool
     */
    public function isUnique()
    {
        return (count($this->files) == 1);
    }
    /**
     * Are we currently inside a zip?
     * @return bool
     */
    public function inZip()
    {
        return $this->inZip;
    }
    /**
     * Returns UTF8 encoded path
     * @param bool $decode
     * @return String
     */
    public function getZipPath($decode = false)
    {
        if($decode) return AJXP_Utils::decodeSecureMagic($this->zipFile);
        else return $this->zipFile;
    }

    /**
     * Returns UTF8 encoded path
     * @param bool $decode
     * @return String
     */
    public function getZipLocalPath($decode = false)
    {
        if($decode) return AJXP_Utils::decodeSecureMagic($this->localZipPath);
        else return $this->localZipPath;
    }
    /**
     * Number of selected items
     * @return int
     */
    public function getCount()
    {
        return count($this->files);
    }
    /**
     * List of items selected
     * @return string[]
     */
    public function getFiles()
    {
        return $this->files;
    }
    /**
     * First item of the list
     * @return string
     */
    public function getUniqueFile()
    {
        return $this->files[0];
    }

    /**
     * @param AbstractAccessDriver $accessDriver
     * @return AJXP_Node
     * @throws Exception
     */
    public function getUniqueNode(AbstractAccessDriver $accessDriver)
    {
        if (isSet($this->nodes) && is_array($this->nodes)) {
            return $this->nodes[0];
        }
        $repo = $accessDriver->repository;
        $user = AuthService::getLoggedUser();
        if (!AuthService::usersEnabled() && $user!=null && !$user->canWrite($repo->getId())) {
            throw new Exception("You have no right on this action.");
        }

        $currentFile = $this->getUniqueFile();
        $wrapperData = $accessDriver->detectStreamWrapper(false);
        $urlBase = $wrapperData["protocol"]."://".$accessDriver->repository->getId();
        $ajxpNode = new AJXP_Node($urlBase.$currentFile);
        return $ajxpNode;

    }

    /**
     * @param AbstractAccessDriver $accessDriver
     * @return AJXP_Node[]
     * @throws Exception
     */
    public function buildNodes(AbstractAccessDriver $accessDriver)
    {
        if (isSet($this->nodes)) {
            return $this->nodes;
        }
        $wrapperData = $accessDriver->detectStreamWrapper(false);
        $urlBase = $wrapperData["protocol"]."://".$accessDriver->repository->getId();
        $nodes = array();
        foreach ($this->files as $file) {
            $nodes[] = new AJXP_Node($urlBase.$file);
        }
        return $nodes;

    }

    /**
     * Is this selection empty?
     * @return bool
     */
    public function isEmpty()
    {
        if (count($this->files) == 0) {
            return true;
        }
        return false;
    }
    /**
     * Detect if there is .zip somewhere in the path
     * @static
     * @param string $dirPath
     * @return array|bool
     */
    public static function detectZip($dirPath)
    {
        if (preg_match("/\.zip\//i", $dirPath) || preg_match("/\.zip$/i", $dirPath)) {
            $contExt = strpos(strtolower($dirPath), ".zip");
            $zipPath = substr($dirPath, 0, $contExt+4);
            $localPath = substr($dirPath, $contExt+4);
            if($localPath == "") $localPath = "/";
            return array($zipPath, $localPath);
        }
        return false;
    }
    /**
     * Sets the selected items
     * @param array $files
     * @return void
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }

    public function removeFile($file){
        $newFiles = array();
        foreach($this->files as $k => $f){
            if($f != $file) $newFiles[] = $file;
        }
        $this->files = $newFiles;
        if(isSet($this->nodes)){
            $newNodes = array();
            foreach($this->nodes as $l => $n){
                if($n->getPath() != $file) $newNodes[] = $n;
            }
            $this->nodes = $newNodes;
        }
    }

    public function addFile($file){
        $this->files[] = $file;
    }

}
