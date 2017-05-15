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

namespace Pydio\Access\Core\Stream;
use ArrayIterator;
use GuzzleHttp\Stream\StreamInterface;
use Pydio\Access\Core\IAjxpWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Exception\PydioPromptException;
use Pydio\Access\Core\Stream\Exception\OAuthException;

use Pydio\Core\Utils\FileHelper;
use React\Promise\Deferred;


/**
 * Standard stream wrapper to use files with Pydio streams, supporting "r", "w", "a", "x".
 */
class StreamWrapper implements IAjxpWrapper
{
    /** @var resource */
    public $context;

    /** @var StreamInterface */
    private $stream;

    /** @var string r, r+, or w */
    private $mode;

    /** @var Deferred */
    private $deferred;

    /** @var ArrayIterator */
    private $iterator;

    /**
     * Registers the stream wrapper if needed
     * @param $protocol
     */
    public static function register($protocol) {
        if (!in_array($protocol, stream_get_wrappers())) {
            stream_wrapper_register($protocol, __CLASS__);
        }
    }

    /**
     * @param string $path
     * @param string $mode
     * @param int $options
     * @param string $opened_path
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path) {
        $this->stream = self::createStream($path, $mode);

        return true;
    }

    /**
     * @return mixed
     */
    public function stream_stat() {
        return $this->stream->stat();
    }

    /**
     * @param string $data
     * @return int
     */
    public function stream_write($data) {
        return (int) $this->stream->write($data);
    }

    /**
     * @param int $count
     * @return string
     */
    public function stream_read($count) {
        return $this->stream->read($count);
    }

    /**
     * @return bool|int
     */
    public function stream_tell() {
        return $this->stream->tell();
    }

    /**
     * @return bool
     */
    public function stream_eof() {
        return $this->stream->eof();
    }

    /**
     * @param int $offset
     * @param int $whence
     * @return bool
     */
    public function stream_seek($offset, $whence=SEEK_SET) {
        return $this->stream->seek($offset, $whence);
    }

    public function stream_close() {
        $this->stream->close();
    }

    /**
     * @param $path
     * @param $options
     * @return bool
     */
    public function dir_opendir($path, $options) {
        $this->stream = self::createStream($path);

        // TODO - do that asynchronously
        $contents = $this->stream->getContents();
        $this->iterator = new ArrayIterator($contents);

        return true;
    }

    /**
     * @return bool
     */
    public function dir_readdir() {
        if (!$this->iterator->valid()) {
            return false;
        }

        $current =  $this->iterator->current();

        $this->iterator->next();

        return $current["name"];
    }
    /**
     * @return bool
     */
    public function dir_closedir() {
        $this->iterator = null;
        $this->stream->close();
        return true;
    }

    /**
     * @return bool
     */
    public function dir_rewinddir() {
        if (isset($this->iterator)) {
            $this->iterator->rewind();
        }

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
    public function mkdir($path, $mode, $options) {
        $stream = self::createStream($path);

        return $stream->mkdir();
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function rmdir($path, $options) {
        $stream = self::createStream($path);

        return $stream->rmdir();
    }

    /**
     * @param string $oldName
     * @param string $newName
     * @return bool
     */
    public function rename($oldName, $newName) {

        $stream = self::createStream($oldName);

        return $stream->rename(new AJXP_Node($newName));
    }

    /**
     * @param string $path
     * @param int $flags
     * @return array
     */
    public function url_stat($path, $flags) {
        $stream = self::createStream($path);
        $resource = PydioStreamWrapper::getResource($stream);
        $stat = fstat($resource);
        fclose($resource);

        return $stat;
    }

    /**
     * @param String $url
     * @return bool
     */
    public static function isSeekable($url) {
        return true;
    }

    /**
     * @param $url
     * @return bool
     */
    public static function isRemote($url) {
        return true;
    }

    /**
     * @param string $path
     * @param bool $persistent
     * @return string
     */
    public static function getRealFSReference($path, $persistent = false) {
        $nodeStream = self::createStream($path);
        $nodeStream->getContents();

        $tmpFile = ApplicationState::getTemporaryFolder() ."/".md5(time()).".".pathinfo($path, PATHINFO_EXTENSION);
        $tmpHandle = fopen($tmpFile, "wb");

        self::copyStreamInStream(PydioStreamWrapper::getResource($nodeStream), $tmpHandle);

        fclose($tmpHandle);

        if (!$persistent) {
            register_shutdown_function(function() use($tmpFile){
                FileHelper::silentUnlink($tmpFile);
            }, $tmpFile);
        }
        return $tmpFile;
    }

    /**
     * @param string $path
     * @param resource $stream
     */
    public static function copyFileInStream($path, $stream) {
        $nodeStream = self::createStream($path);
        $nodeStream->getContents();

        self::copyStreamInStream(PydioStreamWrapper::getResource($nodeStream), $stream);
    }

    /**
     * @param $from
     * @param $to
     */
    public static function copyStreamInStream($from, $to) {
        while (!feof($from)) {
            $data = fread($from, 4096);
            fwrite($to, $data, strlen($data));
        }
    }

    /**
     * @param string $path
     * @param number $chmodValue
     */
    public static function changeMode($path, $chmodValue) {
    }

    /**
     * @param $path
     * @param string $mode
     * @return \GuzzleHttp\Stream\Stream|AuthStream|MetadataCachingStream|OAuthStream|Stream|WriteBufferStream
     * @throws \Exception
     */
    public static function createStream($path, $mode = "r+") {
        $node = new AJXP_Node($path);
        $repository = $node->getRepository();
        $ctx = $node->getContext();

        $useOAuthStream = $repository->getContextOption($ctx, "USE_OAUTH_STREAM", false);
        $useAuthStream = $repository->getContextOption($ctx, "USE_AUTH_STREAM", !$useOAuthStream);

        $nodeStream = Stream::factory($node, $mode);
        if ($useAuthStream) $nodeStream = new AuthStream($nodeStream, $node);

        try {
            if ($useOAuthStream) $nodeStream = new OAuthStream($nodeStream, $node);
        } catch (OAuthException $e) {
            throw PydioPromptException::promptForAuthRedirection("test", $e->getURL());
        }

        if (strpos($mode, 'w') !== false) {
            $nodeStream = new WriteBufferStream($nodeStream, $node);
        }
        $nodeStream = new MetadataCachingStream($nodeStream, $node);

        PydioStreamWrapper::getResource($nodeStream);

        return $nodeStream;
    }

    /**
     * @param AJXP_Node $node
     * @return array
     */
    public static function getResolvedOptionsForNode($node)
    {
        // TODO: Implement getResolvedOptionsForNode() method.
        // Create a generic HTTP Type inc. Authentication data
        return ["TYPE" => "php"];
    }

    /**
     * Enter description here...
     *
     * @return bool
     */
    public function stream_flush()
    {
        //$this->stream->flush();
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @return bool
     */
    public function unlink($path)
    {
        $stream = self::createStream($path);
        return $stream->delete();
    }
}
