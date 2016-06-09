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
namespace Pydio\Access\Driver\StreamProvider\Inbox;

use ArrayIterator;
use Pydio\Access\Core\AJXP_MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\IAjxpWrapper;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Utils;

defined('AJXP_EXEC') or die('Access not allowed');


class inboxAccessWrapper implements IAjxpWrapper
{
    /**
     * @var ArrayIterator
     */
    private $nodesIterator;

    /**
     * @var Resource
     */
    private $fp;
    /**
     * @var AJXP_Node
     */
    private static $linkNode;


    /**
     * Support for opendir().
     *
     * @param string $path    The path to the directory
     * @param string $options Whether or not to enforce safe_mode (0x04). Unused.
     *
     * @return bool true on success
     * @see http://www.php.net/manual/en/function.opendir.php
     */
    public function dir_opendir($path, $options)
    {
        if(trim(parse_url($path, PHP_URL_PATH), "/") == ""){
            $this->nodesIterator = new ArrayIterator(inboxAccessDriver::getNodes(true));
        }else{
            $this->nodesIterator = new ArrayIterator([]);
        }


        return true;
    }

    /**
     * Close the directory listing handles
     *
     * @return bool true on success
     */
    public function dir_closedir()
    {
        $this->nodesIterator = null;

        return true;
    }

    /**
     * This method is called in response to rewinddir()
     *
     * @return boolean true on success
     */
    public function dir_rewinddir()
    {
        $this->nodesIterator->rewind();

        return true;
    }

    /**
     * This method is called in response to readdir()
     *
     * @return string Should return a string representing the next filename, or false if there is no next file.
     *
     * @link http://www.php.net/manual/en/function.readdir.php
     */
    public function dir_readdir()
    {
        // Skip empty result keys
        if (!$this->nodesIterator->valid()) {
            return false;
        }

        $key = $this->nodesIterator->key();
        $current = $this->nodesIterator->current();

        $this->nodesIterator->next();

        return $key;
    }

    public static function translateURL($path){

        $pydioScheme = false;
        self::$linkNode = null;

        $nodes = inboxAccessDriver::getNodes(false);
        $nodePath = basename(parse_url($path, PHP_URL_PATH));
        $node = $nodes[ltrim($nodePath, '/')];

        if (empty($node) || ! isset($node['url'])) {
            return Utils::getAjxpTmpDir();
        }

        $url = $node['url'];
        $label = $node['label'];

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if($scheme == "pydio"){
            self::$linkNode = new AJXP_Node($path);
            $pydioScheme = true;
        }

        if(empty($nodePath)){
            return Utils::getAjxpTmpDir();
        }

        if($pydioScheme){
            $node = new AJXP_Node($url);
            $node->setLabel($label);
            $node->getRepository()->driverInstance = null;
            try{
                $node->getDriver();
            }catch (\Exception $e){

            }
            AJXP_MetaStreamWrapper::detectWrapperForNode($node, true);
        }
        return $url;
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
        $url = self::translateURL($path);
        if(self::$linkNode !== null){
            $isRemote = AJXP_MetaStreamWrapper::wrapperIsRemote($url);
            $realFilePointer = AJXP_MetaStreamWrapper::getRealFSReference($url, true);
            if(!$isRemote){
                $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                $tmpname = tempnam(Utils::getAjxpTmpDir(), "real-file-inbox-pointer").".".$ext;
                copy($realFilePointer, $tmpname);
                $realFilePointer = $tmpname;
            }
            self::$linkNode->getDriver();
            return $realFilePointer;
        }else{
            $tmpname = tempnam(Utils::getAjxpTmpDir(), "real-file-inbox-pointer");
            $source = fopen($url, "r");
            $dest = fopen($tmpname, "w");
            stream_copy_to_stream($source, $dest);
            return $tmpname;
        }

    }

    /**
     * Read a file (by chunks) and copy the data directly inside the given stream.
     *
     * @param string $path
     * @param resource $stream
     */
    public static function copyFileInStream($path, $stream)
    {
        $url = self::translateURL($path);
        AJXP_MetaStreamWrapper::copyFileInStream($url, $stream);
        /*
        $wrapperClass = AJXP_MetaStreamWrapper::actualRepositoryWrapperClass(parse_url($url, PHP_URL_HOST));
        call_user_func(array($wrapperClass, "copyFileInStream"), $url, $stream);
        */
        if(self::$linkNode !== null){
            self::$linkNode->getDriver();
        }
    }

    /**
     * Chmod implementation for this type of access.
     *
     * @param string $path
     * @param number $chmodValue
     */
    public static function changeMode($path, $chmodValue){
        
    }

    /**
     * Describe whether the current wrapper operates on a remote server or not.
     * @static
     * @return boolean
     */
    public static function isRemote()
    {
        return true;
    }

    /**
     * Describe whether the current wrapper can rewind a stream or not.
     * @param String $url Url of the resource
     * @static
     * @return boolean
     */
    public static function isSeekable($url)
    {
        return true;
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
    public function rename($path_from, $path_to)
    {
        return false;
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
        return false;
    }

    /**
     * Enter description here...
     *
     */
    public function stream_close()
    {
        if($this->fp !== null){
            fclose($this->fp);
            if(self::$linkNode !== null){
                self::$linkNode->getDriver();
            }
        }
    }

    /**
     * Enter description here...
     *
     * @return bool
     */
    public function stream_eof()
    {
        if(is_resource($this->fp)){
            return feof($this->fp);
        }
        return false;
    }

    /**
     * Enter description here...
     *
     * @return bool
     */
    public function stream_flush()
    {
        if($this->fp !== null){
            return fflush($this->fp);
        }
        return null;
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
        $this->fp = fopen(self::translateURL($path), $mode, $options);
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
        if($this->fp !== null){
            return fread($this->fp, $count);
        }
        return null;
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
        if($this->fp !== null){
            return fseek($this->fp, $offset, $whence);
        }
        return null;
    }

    /**
     * Enter description here...
     *
     * @return array
     */
    public function stream_stat()
    {
        if($this->fp !== null){
            return fstat($this->fp);
        }
        return false;
    }

    /**
     * Enter description here...
     *
     * @return int
     */
    public function stream_tell()
    {
        if($this->fp !== null){
            return ftell($this->fp);
        }
        return null;
    }

    /**
     * Enter description here...
     *
     * @param string $data
     * @return int
     */
    public function stream_write($data)
    {
        if($this->fp !== null){
            return fwrite($this->fp, $data);
        }
        return 0;
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @return bool
     */
    public function unlink($path)
    {
        // TODO: Implement unlink() method.
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
        $nodeData = inboxAccessDriver::getNodeData($path);
        return $nodeData["stat"];
    }

}