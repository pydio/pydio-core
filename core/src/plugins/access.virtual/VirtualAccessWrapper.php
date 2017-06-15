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
namespace Pydio\Access\Driver\StreamProvider\Virtual;

use Pydio\Access\Core\IAjxpWrapper;
use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Services\ApplicationState;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class VirtualAccessWrapper
 * @package Pydio\Access\Driver\StreamProvider\Virtual
 */
class VirtualAccessWrapper implements IAjxpWrapper
{

    private static $roots = [
        // My Files
        "/My Files" => "pydio://admin@1",
        // Common Files
        "/Common" => "pydio://admin@0",
        // Common Files
        "/recycle_bin" => "pydio://admin@0/recycle_bin",
        // S3
        "/S3" => "pydio://admin@0fec340a396ea7deff4210337e364767"
    ];

    private static $files = [
        "/Desert.jpg" => "pydio://admin@1/Desert.jpg",
    ];

    /**
     * @param $url string Something like pydio://user@workspaceId/path/to/file
     * @return mixed
     */
    private static function translateUrl($url){
        $node = new AJXP_Node($url);
        if(!$node->isRoot()){
            $path = $node->getPath();
            if(array_key_exists($path, self::$files)){
                return self::$files[$path];
            }
            foreach(self::$roots as $virtual => $route){
                if(strpos($path, $virtual) === 0){
                    $newRoute = $route;
                    if(strlen($path) > strlen($virtual)){
                        $newRoute .= substr($path, strlen($virtual));
                    }
                    return $newRoute;
                }
            }
        }
        return $url;
    }

    /**
     * @param $url
     * @return bool
     */
    private static function contextIsRoot($url){
        $node = new AJXP_Node($url);
        if($node->getParent() === null) return true;
        $firstLevel = $node->getParent()->getPath() === '/' || $node->getParent()->getPath() === "";
        if(!$firstLevel){
            return false;
        }
        if(array_key_exists($node->getPath(), self::$files)){
            return false;
        }else{
            return true;
        }
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
        if(self::contextIsRoot($path)) return ApplicationState::getTemporaryFolder();
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
        MetaStreamWrapper::changeMode(self::translateUrl($path), $chmodValue);
    }

    /**
     * Describe whether the current wrapper operates on a remote server or not.
     * @static
     * @param $url
     * @return bool
     */
    public static function isRemote($url)
    {
        if(self::contextIsRoot($url)) return false;
        else return MetaStreamWrapper::wrapperIsRemote(self::translateUrl($url));
    }

    /**
     * Describe whether the current wrapper can rewind a stream or not.
     * @param String $url Url of the resource
     * @static
     * @return boolean
     */
    public static function isSeekable($url)
    {
        if(self::contextIsRoot($url)) return false;
        else return MetaStreamWrapper::wrapperIsSeekable(self::translateUrl($url));
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
        $node = new AJXP_Node($path);
        if($node->isRoot()) {
            $this->nodesIterator = new \ArrayIterator(self::$roots + self::$files);
        }else{
            $newUrl = self::translateUrl($path);
            $this->handle = opendir($newUrl);
            if($this->handle === false){
                return false;
            }
        }
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
        }else if($this->handle !== null){
            return readdir($this->handle);
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
        else{
            rewind($this->handle);
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
        if(self::contextIsRoot($path)){
            //throw new FileNotWriteableException($node->getParent());
            return false;
        }else{
            return mkdir(self::translateUrl($path), $mode, $options);
        }
    }

    /**
     * Enter description here...
     *
     * @param string $path_from
     * @param string $path_to
     * @return bool
     */
    public function rename($path_from, $path_to)
    {
        if(self::contextIsRoot($path_from) || self::contextIsRoot($path_to)){
            return false;
        }else{
            $from = new AJXP_Node(self::translateUrl($path_from));
            $to   = new AJXP_Node(self::translateUrl($path_to));
            if($from->getRepositoryId() === $to->getRepositoryId()){
                return rename($from->getUrl(), $to->getUrl());
            }else{
                return false;
            }
        }
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function rmdir($path, $options)
    {
        if(self::contextIsRoot($path)){
            return false;
        }
        return rmdir(self::translateUrl($path), $options);
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
        if(self::contextIsRoot($path)){
            return false;
        }
        return unlink(self::translateUrl($path));
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
        if(self::contextIsRoot($path)){
            $s = stat(ApplicationState::getTemporaryFolder());
            $n = new AJXP_Node($path);
            if($n->isRoot()){
                $this->disableWriteInStat($s);
            }
            return $s;
        }else{
            return stat(self::translateUrl($path));
        }
    }

    /**
     * @param array $stat
     */
    protected function disableWriteInStat(&$stat){
        $octRights = decoct($stat["mode"]);
        $last = (strlen($octRights)) - 1;
        $octRights[$last] = $octRights[$last-1] = $octRights[$last-2] = 5;
        $stat["mode"] = $stat[2] = octdec($octRights);
    }

}