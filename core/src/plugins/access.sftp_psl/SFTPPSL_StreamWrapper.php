<?php

/**
 * PHP 5.4.0
 *
 * This class implements a read/write SFTP stream wrapper based on "phpseclib"
 *
 * Requirement:	phpseclib - PHP Secure Communications Library
 *
 * Filename:	SFTPPSL_StreamWrapper.php
 * Classname:	SFTPPSL_StreamWrapper
 *
 * #######################################################################
 * # Protocol									sftp://
 * #######################################################################
 * # Context Options							No
 * # Restricted by allow_url_fopen				Yes
 * # Allows Reading								Yes
 * # Allows Writing								Yes
 * # Allows Appending							Yes
 * # Allows Simultaneous Reading and Writing	No
 * # Supports stat()							Yes
 * # Supports unlink()							Yes
 * # Supports rename()							Yes
 * # Supports mkdir()							Yes
 * # Supports rmdir()							Yes
 * #######################################################################
 * # Possible Modes For fopen()					r, r+, w, w+, a, a+, c, c+
 * #######################################################################
 *
 * @category	Net
 * @package		Net_SFTP_StreamWrapper
 * @author		Nikita ROUSSEAU <warhawk3407@gmail.com>
 * @author		Jim WIGGINTON <terrafrost@php.net>
 * @copyright	© 2013
 * @license		http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version		Release: @1.1.0@
 * @date		March 2013
 */

/**
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Here's a short example of how to use this library:
 * <code>
 * <?php
 *		include('Net/SFTPPSL_StreamWrapper.php');
 *
 *		$host = 'www.domain.tld';
 *		$port = '22';
 *		$user = 'user';
 *		$pass = 'secret';
 *		$path = '/home/user/file';
 *
 *		$url = "sftp://".$user.':'.$pass.'@'.$host.':'.$port.$path;
 *
 *		print_r(stat($url));
 *
 *		echo "\r\n<hr>\r\n";
 *
 *		$handle = fopen($url, "r");
 *		$contents = '';
 *		while (!feof($handle)) {
 *			$contents .= fread($handle, 8192);
 *		}
 *		fclose($handle);
 *		echo $contents;
 * ?>
 * </code>
 */



/**
 * Include Net_SFTP
 */
if (!class_exists('Net_SFTP')) {
    require_once('phpseclib/SFTP.php');
}

/**
 * Check PHP_VERSION
 */
//if (version_compare(PHP_VERSION, '5.4.0') == -1) {
//	exit('PHP 5.4.0 is required!');
//}

/**
 * Pure-PHP implementations of Net_SFTP as a stream wrapper class
 *
 * @author	Nikita ROUSSEAU <warhawk3407@gmail.com>
 * @version	1.1.0
 * @access	public
 * @package	Net_SFTP_StreamWrapper
 * @link	http://www.php.net/manual/en/class.streamwrapper.php
 */
class SFTPPSL_StreamWrapper
{
	/**
	 * SFTP Object
	 *
	 * @var Net_SFTP
	 * @access private
	 */
	private $sftp;

	/**
	 * SFTP Path
	 *
	 * @var String
	 * @access private
	 */
	private $path;

	/**
	 * Pointer Offset
	 *
	 * @var Integer
	 * @access private
	 */
	private $position;

	/**
	 * Context resource
	 *
	 * @var Resource
	 * @access public
	 */
	public $context;

	/**
	 * Mode
	 *
	 * SUPPORTED: 		r, r+, w, w+, a, a+, c, c+
	 * NOT SUPPORTED:	x, x+
	 *
	 * @var String
	 * @access private
	 */
	private $mode;

	/**
	 * SFTP Connection Instances
	 *
	 * Rather than re-create the connection we re-use instances if possible
	 *
	 * @var array
	 * @access private
	 */
	private static $instances;

	/**
	 * Directory Listing
	 *
	 * @var array
	 * @access private
	 */
	private $dir_entries;

	/**
	 * This method is called in response to closedir()
	 *
	 * Closes a directory handle
	 *
	 * Alias of stream_close()
	 *
	 * @return bool
	 * @access public
	 */
	public function dir_closedir()
	{
		$this->stream_close();

		$this->dir_entries = FALSE;

		return TRUE;
	}

	/**
	 * This method is called in response to opendir()
	 *
	 * Opens up a directory handle to be used in subsequent closedir(), readdir(), and rewinddir() calls
	 *
	 * NOTES:
	 * It loads the entire directory contents into memory.
	 * The only $options is "whether or not to enforce safe_mode (0x04)". Since safe mode was deprecated in 5.3 and removed in 5.4 we are going
	 * to ignore it
	 *
	 * @param String $path
	 * @param Integer $options
	 * @return bool
	 * @access public
	 */
	public function dir_opendir($path, $options)
	{
		if ( $this->stream_open($path, NULL, NULL, $opened_path) ) {
			$this->dir_entries = $this->sftp->nlist($this->path);
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * This method is called in response to readdir()
	 *
	 * Reads entry from directory
	 *
	 * NOTE: In this method, Pointer Offset is an index of the array returned by Net_SFTP::nlist()
	 *
	 * @return string
	 * @access public
	 */
	public function dir_readdir()
	{
		if ($this->dir_entries === false) {
			return FALSE;
		}

		if ( isset($this->dir_entries[$this->position]) ) {
			$filename = $this->dir_entries[$this->position];

			$this->position += 1;

			return $filename;
		} else {
			return FALSE;
		}
	}

	/**
	 * This method is called in response to rewinddir()
	 *
	 * Resets the directory pointer to the beginning of the directory
	 *
	 * @return bool
	 * @access public
	 */
	public function dir_rewinddir()
	{
		$this->position = 0;

		return TRUE;
	}

	/**
	 * Attempts to create the directory specified by the path
	 *
	 * Makes a directory
	 *
	 * NOTE: Only valid option is STREAM_MKDIR_RECURSIVE ( http://www.php.net/manual/en/function.mkdir.php )
	 *
	 * @param String $path
	 * @param Integer $mode
	 * @param Integer $options
	 * @return bool
	 * @access public
	 */
	public function mkdir($path, $mode, $options)
	{
		$connection = $this->stream_open($path, NULL, NULL, $opened_path);
		if ($connection === false) {
			return FALSE;
		}

		if ( $options === STREAM_MKDIR_RECURSIVE ) {
			$mkdir = $this->sftp->mkdir($this->path, $mode, true);
		} else {
			$mkdir = $this->sftp->mkdir($this->path, $mode, false);
		}

		$this->stream_close();

		return $mkdir;
	}

	/**
	 * Attempts to rename path_from to path_to
	 *
	 * Attempts to rename oldname to newname, moving it between directories if necessary.
	 * If newname exists, it will be overwritten.
	 *
	 * @param String $path_from
	 * @param String $path_to
	 * @return bool
	 * @access public
	 */
	public function rename($path_from, $path_to)
	{
		$path1 = parse_url($path_from);
		$path2 = parse_url($path_to);
		unset($path1['path'], $path2['path']);
		if ($path1 != $path2) {
			return FALSE;
		}
		unset($path1, $path2);

		$connection = $this->stream_open($path_from, NULL, NULL, $opened_path);
		if ($connection === false) {
			return FALSE;
		}

		$path_to = parse_url($path_to, PHP_URL_PATH);

		// "It is an error if there already exists a file with the name specified by newpath."
		//  -- http://tools.ietf.org/html/draft-ietf-secsh-filexfer-02#section-6.5
		if (!$this->sftp->rename($this->path, $path_to)) {
			if ($this->sftp->stat($path_to)) {
				$del = $this->sftp->delete($path_to, true);
				$rename = $this->sftp->rename($this->path, $path_to);

				$this->stream_close();
				return $del && $rename;
			}
		}

		$this->stream_close();
		return TRUE;
	}

	/**
	 * Attempts to remove the directory named by the path
	 *
	 * Removes a directory
	 *
	 * NOTE: rmdir() does not have a $recursive parameter as mkdir() does ( http://www.php.net/manual/en/streamwrapper.rmdir.php )
	 *
	 * @param String $path
	 * @param Integer $options
	 * @return bool
	 * @access public
	 */
	public function rmdir($path, $options)
	{
		$connection = $this->stream_open($path, NULL, NULL, $opened_path);
		if ($connection === false) {
			return FALSE;
		}

		$rmdir = $this->sftp->rmdir($this->path);

		$this->stream_close();

		return $rmdir;
	}

	/**
	 * This method is called in response to stream_select()
	 *
	 * Retrieves the underlaying resource
	 *
	 * @param Integer $cast_as
	 * @return resource
	 * @access public
	 */
	public function stream_cast($cast_as)
	{
		return $this->sftp->fsock;
	}

	/**
	 * This method is called in response to fclose()
	 *
	 * Closes SFTP connection
	 *
	 * @return void
	 * @access public
	 */
	public function stream_close()
	{
		// We do not really close connections because
		// connections are assigned to a class static variable, so the Net_SFTP object will persist
		// even after the stream object has been destroyed. But even without that, it's probably
		// unnecessary as it'd be garbage collected out anyway.
		// http://www.frostjedi.com/phpbb3/viewtopic.php?f=46&t=167493&sid=3161a478bd0bb359f6cefc956d6ac488&start=15#p391181

		//$this->sftp->disconnect();

		$this->position = 0;
	}

	/**
	 * This method is called in response to feof()
	 *
	 * Tests for end-of-file on a file pointer
	 *
	 * @return bool
	 * @access public
	 */
	public function stream_eof()
	{
		$filesize = $this->sftp->size($this->path);

		if ($this->position >= $filesize) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * This method is called in response to fflush()
	 *
	 * NOTE: Always returns true because Net_SFTP doesn't cache stuff before writing
	 *
	 * @return bool
	 * @access public
	 */
	public function stream_flush()
	{
		return TRUE;
	}

	/**
	 * Advisory file locking
	 *
	 * Not Implemented
	 *
	 * @param Integer $operation
	 * @return Boolean
	 * @access public
	*/
	public function stream_lock($operation)
	{
		return FALSE;
	}

	/**
	 * This method is called to set metadata on the stream. It is called when one of the following functions is called on a stream URL:
	 * - touch()
	 * - chmod()
	 * - chown()
	 * - chgrp()
	 *
	 * Changes stream options
	 *
	 * @param String $path
	 * @param Integer $option
	 * @param mixed $var
	 * @return bool
	 * @access public
	 */
	public function stream_metadata($path, $option, $var)
	{
		$connection = $this->stream_open($path, NULL, NULL, $opened_path);
		if ($connection === false) {
			return FALSE;
		}

		switch ($option) {
			case 1: // PHP_STREAM_META_TOUCH
				$touch = $this->sftp->touch($this->path, $var[1], $var[0]);

				$this->stream_close();
				return $touch;

			case 2: // PHP_STREAM_META_OWNER_NAME
				$this->stream_close();
				return FALSE;

			case 3: // PHP_STREAM_META_OWNER
				$chown = $this->sftp->chown($this->path, $var);

				$this->stream_close();
				return $chown;

			case 4: // PHP_STREAM_META_GROUP_NAME
				$this->stream_close();
				return FALSE;

			case 5: // PHP_STREAM_META_GROUP
				$chgrp = $this->sftp->chgrp($this->path, $var);

				$this->stream_close();
				return $chgrp;

			case 6: // PHP_STREAM_META_ACCESS
				$chmod = $this->sftp->chmod($var, $this->path);

				$this->stream_close();
				return $chmod;

			default:
				$this->stream_close();
				return FALSE;
		}
	}

	/**
	 * This method is called immediately after the wrapper is initialized
	 *
	 * Connects to an SFTP server
	 *
	 * NOTE: This method is not get called by default for the following functions:
	 * dir_opendir(), mkdir(), rename(), rmdir(), stream_metadata(), unlink() and url_stat()
	 * So I implemented a call to stream_open() at the beginning of the functions and stream_close() at the end
	 *
	 * The wrapper will also reuse open connections
	 *
	 * @param String $path
	 * @param String $mode
	 * @param Integer $options
	 * @param String &$opened_path
	 * @return bool
	 * @access public
	 */
	public function stream_open($path, $mode, $options, &$opened_path)
	{
		$url = parse_url($path);

		$host = $url["host"];
		$port = $url["port"];
		$user = $url["user"];
		$pass = $url["pass"];

		$this->path = $url["path"];

		$connection_uuid = md5( $host.$port.$user ); // Generate a unique ID for the current connection

		if ( isset(self::$instances[$connection_uuid]) ) {
			// Get previously established connection
			$this->sftp = self::$instances[$connection_uuid];
		} else {
			//$context = stream_context_get_options($this->context);

			if (!isset($user) || !isset($pass)) {
				return FALSE;
			}

			// Connection
			$sftp = new Net_SFTP($host, isset($port) ? $port : 22);
			if (!$sftp->login($user, $pass)) {
				return FALSE;
			}

			// Store connection instance
			self::$instances[$connection_uuid] = $sftp;

			// Get current connection
			$this->sftp = $sftp;
		}

		$filesize = $this->sftp->size($this->path);

		if (isset($mode)) {
			$this->mode = preg_replace('#[bt]$#', '', $mode);
		} else {
			$this->mode = 'r';
		}

		switch ($this->mode[0]) {
			case 'r':
				$this->position = 0;
				break;
			case 'w':
				$this->position = 0;
				if ($filesize === FALSE) {
					$this->sftp->touch( $this->path );
				} else {
					$this->sftp->truncate( $this->path, 0 );
				}
				break;
			case 'a':
				if ($filesize === FALSE) {
					$this->position = 0;
					$this->sftp->touch( $this->path );
				} else {
					$this->position = $filesize;
				}
				break;
			case 'c':
				$this->position = 0;
				if ($filesize === FALSE) {
					$this->sftp->touch( $this->path );
				}
				break;

			default:
				return FALSE;
		}

		if ($options == STREAM_USE_PATH) {
			$opened_path = $this->sftp->pwd();
		}

		return TRUE;
	}

	/**
	 * This method is called in response to fread() and fgets()
	 *
	 * Reads from stream
	 *
	 * @param Integer $count
	 * @return mixed
	 * @access public
	 */
	public function stream_read($count)
	{
		switch ($this->mode) {
			case 'w':
			case 'a':
			case 'x':
			case 'x+':
			case 'c':
				return FALSE;
		}

		$chunk = $this->sftp->get( $this->path, FALSE, $this->position, $count );

		$this->position += strlen($chunk);

		return $chunk;
	}

	/**
	 * This method is called in response to fseek()
	 *
	 * Seeks to specific location in a stream
	 *
	 * @param Integer $offset
	 * @param Integer $whence = SEEK_SET
	 * @return bool
	 * @access public
	 */
	public function stream_seek($offset, $whence)
	{
		$filesize = $this->sftp->size($this->path);

		switch ($whence) {
			case SEEK_SET:
                if ($offset >= $filesize || $offset < 0) {
                    return FALSE;
                }
                break;

			case SEEK_CUR:
				$offset += $this->position;
				break;

			case SEEK_END:
				$offset += $filesize;
				break;

			default:
				return FALSE;
		}

		$this->position = $offset;
		return TRUE;
	}

	/**
	 * This method is called to set options on the stream
	 *
	 * STREAM_OPTION_WRITE_BUFFER isn't supported for the same reason stream_flush() isn't.
	 * The other two aren't supported because of limitations in Net_SFTP.
	 *
	 * @param Integer $option
	 * @param Integer $arg1
	 * @param Integer $arg2
	 * @return Boolean
	 * @access public
	 */
	public function stream_set_option($option, $arg1, $arg2)
	{
		return FALSE;
	}

	/**
	 * This method is called in response to fstat()
	 *
	 * Retrieves information about a file resource
	 *
	 * @return mixed
	 * @access public
	 */
	public function stream_stat()
	{
		$stat = $this->sftp->stat($this->path);

		if ( !empty($stat) ) {
			// mode fix
			$stat['mode'] = $stat['permissions'];
			unset($stat['permissions']);

			return $stat;
		} else {
			return FALSE;
		}
	}

	/**
	 * This method is called in response to fseek() to determine the current position
	 *
	 * Retrieves the current position of a stream
	 *
	 * @return Integer
	 * @access public
	 */
	public function stream_tell()
	{
		return $this->position;
	}

	/**
	 * Will respond to truncation, e.g., through ftruncate()
	 *
	 * Truncates a stream
	 *
	 * NOTE:
	 * If $new_size is larger than the file then the file is extended with null bytes.
	 * If $new_size is smaller than the file then the file is truncated to that size.
	 *
	 * ( http://www.php.net/manual/en/function.ftruncate.php )
	 *
	 * @param Integer $new_size
	 * @return bool
	 * @access public
	 */
	public function stream_truncate($new_size)
	{
		return $this->sftp->truncate( $this->path, $new_size );
	}

	/**
	 * This method is called in response to fwrite()
	 *
	 * Writes to stream
	 *
	 * @param String $data
	 * @return mixed
	 * @access public
	 */
	public function stream_write($data)
	{
		switch ($this->mode) {
			case 'r':
			case 'x':
			case 'x+':
				return FALSE;
		}

		$this->sftp->put($this->path, $data, NET_SFTP_STRING, $this->position);

		$this->position += strlen($data);

		return strlen($data);
	}

	/**
	 * Deletes filename specified by the path
	 *
	 * Deletes a file
	 *
	 * @param String $path
	 * @return bool
	 * @access public
	 */
	public function unlink($path)
	{
		$connection = $this->stream_open($path, NULL, NULL, $opened_path);
		if ($connection === false) {
			return FALSE;
		}

		$del = $this->sftp->delete($this->path);

		$this->stream_close();

		return $del;
	}

	/**
	 * This method is called in response to all stat() related functions
	 *
	 * Retrieves information about a file
	 *
	 * @see SFTP_StreamWrapper::stream_stat()
	 * @param String $path
	 * @param Integer $flags
	 * @return mixed
	 * @access public
	 */
	public function url_stat($path, $flags)
	{
		$connection = $this->stream_open($path, NULL, NULL, $opened_path);
		if ($connection === false) {
			return FALSE;
		}

		if ( $flags === STREAM_URL_STAT_LINK ) {
			$stat = $this->sftp->lstat($this->path);
		} else {
			$stat = $this->sftp->stat($this->path);
		}

		$this->stream_close();

		if ( !empty($stat) ) {
			// mode fix
			$stat['mode'] = $stat['permissions'];
			unset($stat['permissions']);

			return $stat;
		} else {
			return FALSE;
		}
	}

}

/**
 * Register "sftp://" protocol
 */
stream_wrapper_register('sftp', 'SFTPPSL_StreamWrapper')
    or die ('Failed to register protocol');
