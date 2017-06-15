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
namespace Pydio\Access\Driver\StreamProvider\Inodes;

use Pydio\Access\Core\IAjxpWrapper;
use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Utils\Vars\UrlUtils;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class InodesAccessWrapper
 * @package Pydio\Access\Driver\StreamProvider\Inodes
 */
class InodesAccessWrapper implements IAjxpWrapper
{

    /**
     * @param $url
     * @return array
     */
    private static function parseUrlData($url){
        $parts = UrlUtils::mbParseUrl($url);
        if(!empty($parts["path"])){
            self::updateUrlWithInodeRoot($url);
            $parts = UrlUtils::mbParseUrl($url);
        }
        $repoId = $parts["host"];
        $repository = RepositoryService::getRepositoryById($repoId);
        $ctx = AJXP_Node::contextFromUrl($url);
        return [
            "URL_PARTS"                 => $parts,
            "ROOT_INODES"               => explode(",", $repository->getContextOption($ctx, "ROOT_INODES")),
            "MIRRORING_REPOSITORY_ID"   => $repository->getContextOption($ctx, "MIRROR_REPOSITORY_ID")
        ];
    }

    /**
     * @param string $url
     */
    private static function updateUrlWithInodeRoot(&$url){
        $parts = UrlUtils::mbParseUrl($url);
        $repoId = $parts["host"];
        $path = $parts["path"];
        $repository = RepositoryService::getRepositoryById($repoId);
        $inodes = explode(",", $repository->getContextOption(AJXP_Node::contextFromUrl($url), "ROOT_INODES"));
        $res = \dibi::query("SELECT * FROM [ajxp_index] WHERE [node_id] IN (%s)", $inodes);
        foreach($res as $row){
            $inodePath = $row->node_path;
            if(strpos($path, "/".basename($inodePath)) === 0){
                // This url is a descendant of this inode.
                $path = rtrim(dirname($inodePath), "/") . $path;
                $url = $parts["scheme"] ."://" . $parts["user"] . "@" . $parts["host"] . $path;
            }
        }
    }

    /**
     * @param $url
     * @return array
     */
    private function getChildren($url){
        $data = $this->parseUrlData($url);
        $path = rtrim($data["URL_PARTS"]["path"], "/");
        $childrens = [];
        if(empty($path)){
            // This is root, load root inodes
            $res = \dibi::query("SELECT * FROM [ajxp_index] WHERE [node_id] IN (%s)", $data["ROOT_INODES"]);
        }else{
            $res = \dibi::query("SELECT * FROM [ajxp_index] WHERE [node_path] LIKE %s AND [node_path] NOT LIKE %s", $path."/%", $path."/%/%");
        }
        foreach($res as $row){
            $childPath = $row->node_path;
            $childrens[basename($childPath)] = $childPath;
        }
        return $childrens;
    }


    /**
     * @param $url
     * @return array
     */
    private function getStat($url){

        $data = $this->parseUrlData($url);
        $path = rtrim($data["URL_PARTS"]["path"], "/");
        if(empty($path)){
            return stat(ApplicationState::getTemporaryFolder());
        }
        $res = \dibi::query("SELECT * FROM [ajxp_index] WHERE [node_path] = %s", $path);
        $row = $res->fetchAll()[0];
        if($row === null){
            return false;
        }
        $mode = 0000000;
        if($row->md5 === 'directory'){
            $mode |= 0040000;
        }
        else{
            $mode |= 0100000;
        }
        // Add readonly flag
        //$mode |= 0x01;
        $mode |= 0666;
        $stat = [
            "ino" => $row->node_id,
            "mtime" => $row->mtime,
            "size"  => $row->bytesize,
            "mode"  => $mode
        ];
        return $stat;
    }


    /**
     * @param $url string Something like pydio://user@workspaceId/path/to/file
     * @return mixed
     */
    private static function translateUrl($url){

        $data = self::parseUrlData($url);
        $parts = $data["URL_PARTS"];
        return "pydio://" . $parts["user"] . "@" . $data["MIRRORING_REPOSITORY_ID"] . $parts["path"];

    }

    /**
     * Get a "usable" reference to a file : the real file or a tmp copy.
     *
     * @param string $path
     * @param bool $persistent
     * @return string
     */
    public static function getRealFSReference($path, $persistent = false)
    {
        $newUrl = self::translateUrl($path);
        return MetaStreamWrapper::getRealFSReference($newUrl, $persistent);
    }

    /**
     * @param AJXP_Node $node
     * @return array
     */
    public static function getResolvedOptionsForNode($node)
    {
        return MetaStreamWrapper::getResolvedOptionsForNode(new AJXP_Node(self::translateUrl($node->getUrl())));
    }

    /**
     * Read a file (by chunks) and copy the data directly inside the given stream.
     *
     * @param string $path
     * @param resource $stream
     */
    public static function copyFileInStream($path, $stream)
    {
        MetaStreamWrapper::copyFileInStream(self::translateUrl($path), $stream);
    }

    /**
     * Chmod implementation for this type of access.
     *
     * @param string $path
     * @param number $chmodValue
     */
    public static function changeMode($path, $chmodValue)
    {
        return;
    }

    /**
     * Describe whether the current wrapper operates on a remote server or not.
     * @static
     * @param $url
     * @return bool
     */
    public static function isRemote($url)
    {
        return false;
    }

    /**
     * Describe whether the current wrapper can rewind a stream or not.
     * @param String $url Url of the resource
     * @static
     * @return boolean
     */
    public static function isSeekable($url)
    {
        return false;
    }

    /**
     * @var \ArrayIterator
     */
    private $nodesIterator;

    /**
     * @var resource
     */
    private $handle;

    /**
     *
     *
     * @return bool
     */
    public function dir_closedir()
    {
        if($this->nodesIterator !== null) $this->nodesIterator = null;
        else if($this->handle !== null) closedir($this->handle);
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function dir_opendir($path, $options)
    {

        $children = $this->getChildren($path);
        $this->nodesIterator = new \ArrayIterator($children);
        return true;
    }

    /**
     * Enter description here...
     *
     * @return string
     */
    public function dir_readdir()
    {
        if($this->nodesIterator !== null) {
            $key = $this->nodesIterator->key();
            $this->nodesIterator->next();
            if($key === null) return false;
            return ltrim($key, "/");
        }else{
            return false;
        }
    }

    /**
     * Enter description here...
     *
     * @return bool
     */
    public function dir_rewinddir()
    {
        if($this->nodesIterator !== null) {
            $this->nodesIterator->rewind();
        }
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @param int $mode
     * @param int $options
     * @return bool
     */
    public function mkdir($path, $mode, $options)
    {
        return false;
    }

    /**
     * Enter description here...
     *
     * @param string $path_from
     * @param string $path_to
     * @return bool
     */
    public function rename($path_from, $path_to){
        return false;
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function rmdir($path, $options){
        return false;
    }

    /**
     * Enter description here...
     *
     */
    public function stream_close()
    {
        return fclose($this->handle);
    }

    /**
     * Enter description here...
     *
     * @return bool
     */
    public function stream_eof()
    {
        return feof($this->handle);
    }

    /**
     * Enter description here...
     *
     * @return bool
     */
    public function stream_flush()
    {
        return fflush($this->handle);
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @param string $mode
     * @param int $options
     * @param string &$opened_path
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->handle = fopen(self::translateUrl($path), $mode);
        return true;
    }

    /**
     * Enter description here...
     *
     * @param int $count
     * @return string
     */
    public function stream_read($count)
    {
        return fread($this->handle, $count);
    }

    /**
     * Enter description here...
     *
     * @param int $offset
     * @param int $whence = SEEK_SET
     * @return bool
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return fseek($this->handle, $offset, $whence);
    }

    /**
     * Enter description here...
     *
     * @return array
     */
    public function stream_stat()
    {
        return fstat($this->handle);
    }

    /**
     * Enter description here...
     *
     * @return int
     */
    public function stream_tell()
    {
        return ftell($this->handle);
    }

    /**
     * Enter description here...
     *
     * @param string $data
     * @return int
     */
    public function stream_write($data)
    {
        return fwrite($this->handle, $data);
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @return bool
     */
    public function unlink($path)
    {
        return false;
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @param int $flags
     * @return array
     */
    public function url_stat($path, $flags)
    {
        return $this->getStat($path);
    }


}