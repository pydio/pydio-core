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

namespace Pydio\Access\Core\Stream;

use AJXP_Utils;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Pydio\Access\Core\Stream\Exception\NotFoundException;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Pydio\Access\Core\Stream\ClientInterface;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Stream\CachingStream;

/**
 * Standard stream wrapper to use files with PHP streams, supporting "r", "w", "a", "x".
 *
 * # Supported stream related PHP functions:
 * - fopen, fclose, fread, fwrite, fseek, ftell, feof, fflush
 * - opendir, closedir, readdir, rewinddir
 * - copy, rename, unlink
 * - mkdir, rmdir, rmdir (recursive)
 * - file_get_contents, file_put_contents
 * - file_exists, filesize, is_file, is_dir
 *
 */
class StreamWrapper
{
    /**
     * @var AbstractClient Client used to send requests
     */
    protected static $client;

    /**
     * @var string Mode the stream was opened with
     */
    protected $mode;

    /**
     * @var EntityBody Underlying stream resource
     */
    protected $body;

    /**
     * @var array Current parameters to use with the flush operation
     */
    protected $params;

    /**
     * @var DirIterator Iterator used with opendir() and subsequent readdir() calls
     */
    protected $objectIterator;

    /**
     * @var string the current protocol
     */
    private $protocol;

    /**
     * @var array The next key to retrieve when using a directory iterator. Helps for fast directory traversal.
     */
    protected static $nextStat = array();

    /**
     * @var array The list of files not found received as response
     * If we receive a 404 once, we should be able to tell if we created the file after that
     */
    protected static $filesNotFound = array();

    /**
     * Register the stream wrapper
     *
     * @param Client $client to use with the stream wrapper
     */
    public static function register (
        ClientInterface $client,
        $protocol
    ) {
        if (in_array($protocol, stream_get_wrappers())) {
            stream_wrapper_unregister($protocol);
        }

        // Set the client passed in as the default stream context client
        stream_wrapper_register($protocol, get_called_class(), STREAM_IS_URL);

        $default = stream_context_get_options(stream_context_get_default());
        $default[$protocol]['client'] = $client;
        stream_context_set_default($default);
    }

    public static function applyInitPathHook($url) {
        // Do nothing
    }

    /**
     * Close the stream
     */
    public function stream_close()
    {
        $this->body = null;
    }

    /**
     * @param string $path
     * @param string $mode
     * @param int    $options
     * @param string $opened_path
     *
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->mode = $mode = rtrim($mode, 'bt');
        $this->params = $params = $this->getParams($path);
        $errors = array();

        if (!in_array($mode, array('r', 'w', 'a', 'x', '+'))) {
            $errors[] = "Mode not supported: {$mode}. Use one 'r', 'w', 'a', or 'x'.";
        }

        if (!$errors) {
            if ($mode == 'r') {
                return $this->openReadStream($params, $errors);
            } elseif ($mode == 'a') {
                return $this->openAppendStream($params, $errors);
            } else {
                return $this->openWriteStream($params, $errors);
            }
        }

        return $this->triggerError($errors);
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return $this->body->eof();
    }

    /**
     * @return bool
     */
    public function stream_flush()
    {
        if ($this->mode == 'r') {
            return false;
        }

        $this->body->seek(0);

        $params = $this->params;
        $params['body'] = $this->body;

        try {
            $this->getClient()->put($params);
        } catch (\Exception $e) {
            $this->triggerError("Unable to write content : " . $e->getMessage());
            return false;
        }

        $this->clearStatInfo($params['path/key']);

        return true;
    }

    /**
     * Read data from the underlying stream
     *
     * @param int $count Amount of bytes to read
     *
     * @return string
     */
    public function stream_read($count)
    {
        return $this->body->read($count);
    }

    /**
     * Seek to a specific byte in the stream
     *
     * @param int $offset Seek offset
     * @param int $whence Whence (SEEK_SET, SEEK_CUR, SEEK_END)
     *
     * @return bool
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return $this->body->seek($offset, $whence);
    }

    /**
     * Get the current position of the stream
     *
     * @return int Returns the current position in the stream
     */
    public function stream_tell()
    {
        return $this->body->ftell();
    }

    /**
     * Write data the to the stream
     *
     * @param string $data
     *
     * @return int Returns the number of bytes written to the stream
     */
    public function stream_write($data)
    {
        return $this->body->write($data);
    }

    /**
     * Delete a specific object
     *
     * @param string $path
     * @return bool
     */
    public function unlink($path)
    {
        $params = $this->getParams($path);

        try {
            $this->clearStatInfo($path);
            $this->getClient()->delete($params);
        } catch (\Exception $e) {
            $this->triggerError("Unable to delete item : " . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public function stream_stat()
    {
        $stat = @fstat($this->body);

        // Add the size of the underlying stream if it is known
        if ($this->mode == 'r' && $this->body->getSize()) {
            $stat[7] = $stat['size'] = $this->body->getSize();
        }

        return $stat;
    }

    /*
     * Wrapper around stat
     */
    public function file_exists($path) {
        $params = $this->getParams($path);

        $key = $params['path/key'];

        if (isset(static::$filesNotFound[$key])) {
            return false;
        }

        return file_exists($path);
    }

    /**
     * Provides information for is_dir, is_file, filesize, etc. Works on buckets, keys, and prefixes
     *
     * @param string $path
     * @param int    $flags
     *
     * @return array Returns an array of stat data
     * @link http://www.php.net/manual/en/streamwrapper.url-stat.php
     */
    public function url_stat($path, $flags)
    {
        $params = $this->getParams($path);

        $key = $params['path/key'];

        if (isset(static::$nextStat[$key])) {
            return static::$nextStat[$key];
        }

        try {
            $result = $this->getClient()->stat($params);

            if ($result) {
                $result = $this->getClient()->formatUrlStat($result);

                static::$nextStat[$key] = $result;
            }
        } catch (ClientException $e) {
            if ($e->getCode() == 404) {
                static::$nextStat[$key] = false;
                return false;
            }
        } catch (\Exception $e) {
            static::$nextStat[$key] = false;
            return $this->triggerError('Cannot access file ' . $e->getMessage(), $flags);
        }

        return $result;
    }

    /**
     * Support for mkdir().
     *
     * @param string $path    Directory which should be created.
     * @param int    $mode    Permissions. 700-range permissions map to ACL_PUBLIC. 600-range permissions map to
     *                        ACL_AUTH_READ. All other permissions map to ACL_PRIVATE. Expects octal form.
     * @param int    $options A bitwise mask of values, such as STREAM_MKDIR_RECURSIVE.
     *
     * @return bool
     * @link http://www.php.net/manual/en/streamwrapper.mkdir.php
     */
    public function mkdir($path, $mode, $options)
    {
        $params = $this->getParams($path);

        $key = $params['path/key'];

        $this->clearStatInfo($key);

        try {
            $result = $this->getClient()->mkdir($params);
        } catch (\Exception $e) {
            $this->triggerError("Unable to create directory : " . $e->getMessage());
            return false;
        }

        return $result;
    }

    /**
     * Support for rmdir().
     *
     * @param string $path the directory path
     * @param int    $options A bitwise mask of values
     *
     * @return bool true if directory was successfully removed
     * @link http://www.php.net/manual/en/streamwrapper.rmdir.php
     */
    public function rmdir($path, $options)
    {
        $params = $this->getParams($path);

        $key = $params['path/key'];

        $this->clearStatInfo($key);

        try {
            $result = $this->getClient()->rmdir($params);
        } catch (\Exception $e) {
            $this->triggerError("Unable to remove directory : " . $e->getMessage());
            return false;
        }

        return $result;
    }

    /**
     * Support for opendir().
     *
     * @param string $path    The path to the directory (e.g. "s3://dir[</prefix>]")
     * @param string $options Whether or not to enforce safe_mode (0x04). Unused.
     *
     * @return bool true on success
     * @see http://www.php.net/manual/en/function.opendir.php
     */
    public function dir_opendir($path, $options)
    {
        $params = $this->getParams($path);

        try {
            $result = $this->getClient()->ls($params);
        } catch (\Exception $e) {
            $this->triggerError("Unable to list directory : " . $e->getMessage());
        }

        $this->objectIterator = $this->getClient()->getIterator($result, $params);

        return true;
    }

    /**
     * Close the directory listing handles
     *
     * @return bool true on success
     */
    public function dir_closedir()
    {
        $this->objectIterator = null;

        return true;
    }

    /**
     * This method is called in response to rewinddir()
     *
     * @return boolean true on success
     */
    public function dir_rewinddir()
    {
        $this->objectIterator->rewind();

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
        if (!$this->objectIterator->valid()) {
            return false;
        }

        $current = $this->objectIterator->current();

        $this->objectIterator->next();

        static::$nextStat[$current[1]] = $current[2];

        return $current[0];
    }

    /**
     * Called in response to rename() to rename a file or directory. Currently only supports renaming objects.
     *
     * @param string $path_from the path to the file to rename
     * @param string $path_to   the new path to the file
     *
     * @return bool true if file was successfully renamed
     * @link http://www.php.net/manual/en/function.rename.php
     */
    public function rename($path_from, $path_to)
    {
        $paramsTo = $this->getParams($path_to, "to");
        $paramsFrom = $this->getParams($path_from, "from");

        $params = $paramsTo + $paramsFrom;

        $this->clearStatInfo($path_from);
        $this->clearStatInfo($path_to);

        try {
            $result = $this->getClient()->rename($params);
        } catch (\Exception $e) {
            $this->triggerError("Unable to rename item : " . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Get the params from the passed path
     *
     * @param string $path Path passed to the stream wrapper
     *
     * @return array Hash of custom params
     */
    protected function getParams($path, $mainPrefix = "")
    {
        $parts = explode('://', $path, 2);
        $this->protocol = $parts[0];

        $default = stream_context_get_options(stream_context_get_default());
        $default['core']['currentProtocol'] = $this->protocol;
        stream_context_set_default($default);

        $parts = AJXP_Utils::safeParseUrl($path);

        $params = [];

        $default = stream_context_get_options(stream_context_get_default());

        $it = new RecursiveArrayIterator($default[$this->protocol]);

        foreach ($it as $k => $v) {
            $prefix = $mainPrefix . $k . "/";

            $itChild = new RecursiveArrayIterator($default[$this->protocol][$k]);

            foreach ($itChild as $kChild => $vChild) {
                $params[$prefix . $kChild] = $vChild;
            }
        }

        // TODO - add call to parent client
        $params["parentReference"] = [
            "path" => "/drive/root:" . dirname($parts['path'])
        ];

        $params[$mainPrefix . 'path/itemname'] = basename($parts['path']);
        $params[$mainPrefix . 'path/path']     = dirname($parts['path']);
        $params[$mainPrefix . 'path/fullpath'] = rtrim(dirname($parts['path']), '/') . '/' . basename($parts['path']);
        $params[$mainPrefix . 'path/fulluri'] = $params[$mainPrefix . 'path/basepath'] . '/' . $params[$mainPrefix . 'path/fullpath'];

        return $params;
    }

    /**
     * Initialize the stream wrapper for a read only stream
     *
     * @param array $params Operation parameters
     * @param array $errors Any encountered errors to append to
     *
     * @return bool
     */
    protected function openReadStream(array $params, array &$errors)
    {
        // Create the command and serialize the request
        try {
            $response = $this->getClient()->open($params);
        } catch (\Exception $e) {
            $this->triggerError("Unable to open stream : " . $e->getMessage());
            return false;
        }

        $this->body = $response->getBody();

        return true;
    }

    /**
     * Initialize the stream wrapper for a write only stream
     *
     * @param array $params Operation parameters
     * @param array $errors Any encountered errors to append to
     *
     * @return bool
     */
    protected function openWriteStream(array $params, array &$errors)
    {
        $this->body = Stream::factory(fopen('php://temp', 'r+'));

        return true;
    }

    /**
     * Initialize the stream wrapper for an append stream
     *
     * @param array $params Operation parameters
     * @param array $errors Any encountered errors to append to
     *
     * @return bool
     */
    protected function openAppendStream(array $params, array &$errors)
    {
        try {
            $this->body->seek(0, SEEK_END);
        } catch (\Exception $e) {
            // The object does not exist, so use a simple write stream
            $this->openWriteStream($params, $errors);
        }

        return true;
    }

    /**
     * Trigger one or more errors
     *
     * @param string|array $errors Errors to trigger
     * @param mixed        $flags  If set to STREAM_URL_STAT_QUIET, then no error or exception occurs
     *
     * @return bool Returns false
     * @throws RuntimeException if throw_errors is true
     */
    protected function triggerError($errors, $flags = null)
    {
        if ($flags & STREAM_URL_STAT_QUIET) {
          // This is triggered with things like file_exists()

          if ($flags & STREAM_URL_STAT_LINK) {
            // This is triggered for things like is_link()
            return $this->getClient()->formatUrlStat([]);
          }
          return false;
        }

        // This is triggered when doing things like lstat() or stat()
        trigger_error(implode("\n", (array) $errors), E_USER_WARNING);

        return false;
    }

    /**
     * Clear the next stat result from the cache
     *
     * @param string $path If a path is specific, clearstatcache() will be called
     */
    protected function clearStatInfo($path = null)
    {
        if ($path) {
            unset(static::$nextStat[$path]);
            clearstatcache(true, $path);
        } else {
            static::$nextStat = array();
            clearstatcache(true);
        }
    }

    /**
     * Determine the most appropriate ACL based on a file mode.
     *
     * @param int $mode File mode
     *
     * @return string
     */
    private function determineAcl($mode)
    {
        $mode = decoct($mode);

        if ($mode >= 700 && $mode <= 799) {
            return 'public-read';
        }

        if ($mode >= 600 && $mode <= 699) {
            return 'authenticated-read';
        }

        return 'private';
    }

    public static function getRealFSReference($path, $persistent = false)
    {
        $tmpFile = AJXP_Utils::getAjxpTmpDir()."/".md5(time()).".".pathinfo($path, PATHINFO_EXTENSION);
        $tmpHandle = fopen($tmpFile, "wb");

        self::copyFileInStream($path, $tmpHandle);

        fclose($tmpHandle);

        if (!$persistent) {
            register_shutdown_function(array("AJXP_Utils", "silentUnlink"), $tmpFile);
        }
        return $tmpFile;
    }

    public static function copyFileInStream($path, $stream)
    {
        $fp = fopen($path, "r");
        if(!is_resource($fp)) return;
        while (!feof($fp)) {
            $data = fread($fp, 4096);
            fwrite($stream, $data, strlen($data));
        }
        fclose($fp);
    }

    public static function changeMode($path, $chmodValue)
    {
        @chmod($path, $chmodValue);
    }

    public static function isSeekable($url) {
        return true;
    }

    public static function isRemote() {
        return true;
    }

    /**
     * Gets the client from the stream context
     *
     * @return Client
     * @throws Exception if no client has been configured
     */
    private function getClient()
    {
        $default = stream_context_get_options(stream_context_get_default());

        if (!$client = $default[$this->protocol]['client']) {
            throw new \Exception('No client defined for '. $this->protocol);
        }

        return $client;
    }
}
