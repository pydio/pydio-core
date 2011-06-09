<?php

class imapAccessWrapper implements AjxpWrapper {
	
	var $ih;
	var $host;
	var $port;
	var $username;
	var $password;
	var $path;
	// stuff for dir reading
	var $dir;
	var $pos;
	// stuff for file reading
	var $data;
	var $gotbody;
	var $size;
	var $time;
	
	var $fragment;
	
		
	function stream_open($path, $mode, $options, &$opened_path) {
		// parse URL
		$parts = parse_url($path);
		$this->repositoryId = $parts["host"];
		$repository = ConfService::getRepositoryById($this->repositoryId);		
		$ssl = $repository->getOption("SSL") == "true" ? true: false ;
		$pop3 = $repository->getOption("BOX_TYPE") == "pop3" ? true : false;
		$this->host = $repository->getOption("HOST");
		$this->port = $repository->getOption("PORT");
		$this->username = $repository->getOption("USER");
		$this->password = $repository->getOption("PASS");
		$this->path = substr($parts["path"], 1);
		$this->fragment = $parts["fragment"];
		
		/*
		$url = parse_url ( $path );
		$ssl = false;
		$pop3 = false;
		if ($url ['scheme'] === 'pop3s' || $url['scheme'] === 'imaps') {
			$ssl = true;
		}
		if ($url ['scheme'] === 'pop3' || $url['scheme'] === 'pop3s') {
			$pop3 = true;
		}
		$this->host = $url ['host'];
		if (! empty ( $url ['port'] )) {
			$this->port = $url ['port'];
		} else {
			$this->port = 110;
		}
		$this->username = $url ['user'];
		$this->password = $url ['pass'];
		$this->path = substr ( $url ['path'], 1 );
		if(!empty($url['fragment'])){
			$this->fragment = $url['fragment'];
		}else{
			$this->fragment = "";
		}
		*/
		//print($this->path);
		// do we have a path?
		if (empty ( $this->path ) && $mode !== 'np') {
			return false;
		}
		
		// open IMAP connection
		$mailbox = "{". $this->host . ":" . $this->port . "/".($pop3?"pop3/":"").($ssl?"ssl/novalidate-cert":"")."}INBOX";
		AJXP_Logger::debug($mailbox);
		$this->ih = imap_open ( $mailbox , $this->username, $this->password, NULL, 1);
		if ($this->ih) {
			if (! empty ( $this->path )) {
				list ( $stats, ) = imap_fetch_overview ( $this->ih, $this->path );
				$this->size = $stats->size;
				$this->time = strtotime ( $stats->date );
			}
			return true;
		} else {
			return false;
		}
	}
	
	function stream_close() {
		imap_close ( $this->ih );
	}
	
	/* Smart reader, at first it only downloads the header to memory, but if a read request is made
       beyond the header, we download the rest of the body */
	function stream_read($count) {
		// smart... only download the header WHEN data is requested
		if (empty ( $this->data )) {
			$this->pos = 0;
			$this->gotbody = false;
			$this->data = imap_fetchheader ( $this->ih, $this->path );
		}
		// only download the body once we read past the header
		if ($this->gotbody == false && ($this->pos + $count > strlen ( $this->data )) && $this->fragment != "header") {
			$this->gotbody = true;
			$this->data .= imap_body ( $this->ih, $this->path );
			$this->size = strlen ( $this->data );
		}
		if ($this->pos >= $this->size) {
			return false;
		} else {
			$d = substr ( $this->data, $this->pos, $count );
			if ($this->pos + $count > strlen ( $this->data )) {
				$this->pos = strlen ( $this->data );
			} else {
				$this->pos = $this->pos + $count;
			}
			return $d;
		}
	}
	
	/* Can't write to POP3 */
	function stream_write($data) {
		return false;
	}
	
	function stream_eof() {
		if ($this->pos == $this->size) {
			return true;
		} else {
			return false;
		}
	}
	
	function stream_tell() {
		return $this->pos;
	}
	
    public function stream_seek($offset , $whence = SEEK_SET){
    	switch ($whence) {
			case SEEK_SET :
				$this->pos = $offset;
				break;
			case SEEK_CUR :
				$this->pos = $this->pos + $offset;
				break;
			case SEEK_END :
				$this->pos = $this->size + $offset;
				break;
		}
	}
	
	function stream_stat() {
		$keys = array ('dev' => 0, 'ino' => 0, 'mode' => 33216, 'nlink' => 0, 'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => $this->size, 'atime' => $this->time, 'mtime' => $this->time, 'ctime' => $this->time, 'blksize' => 0, 'blocks' => 0 );
		return $keys;
	}
	
	function dir_opendir($path, $options) {
		// setting mode to 'np' avoids the path check
		$st = '';
		if ($this->stream_open ( $path, 'np', $options, $st )) {
			$this->dir = imap_num_msg ( $this->ih );
			$this->pos = $this->dir;
			$this->stream_close ();
			return true;
		} else {
			return false;
		}
	}
	
	function dir_closedir() {
		// do nothing.
	}
	
	function url_stat($path, $flags) {
		$emptyString = '';
		if ($this->stream_open ( $path, 'np', $flags, $emptyString)) {
			if(!empty($this->path)){
				// Mail
				$stats = array ();
				list ( $stats, ) = imap_fetch_overview ( $this->ih, $this->path );
				$time = strtotime ( $stats->date );
				$keys = array (
					'dev' => 0, 
					'ino' => 0, 
					'mode' => 33216, 
					'nlink' => 0, 
					'uid' => 0, 
					'gid' => 0, 
					'rdev' => 0, 
					'size' => $stats->size, 
					'atime' => $time, 
					'mtime' => $time, 
					'ctime' => $time,
					'blksize' => 0, 
					'blocks' => 0 );
			}else{
				// BOX
				$keys = array (
					'dev' => 0, 
					'ino' => 0, 
					'mode' => 33216, 
					'nlink' => 0, 
					'uid' => 0, 
					'gid' => 0, 
					'rdev' => 0,
					'size' => 0, 
					'atime' => 0, 
					'mtime' => 0, 
					'ctime' => 0, 
					'blksize' => 0, 
					'blocks' => 0 
				);
			}
			$this->stream_close ();
			return $keys;
		} else {
			return false;
		}
	}
	
	function dir_readdir() {
		if ($this->pos == 1) {
			return false;
		} else {
			$x = $this->pos;
			$this->pos --;
		}
		return $x;
	}
	
	function dir_rewinddir() {
		$this->pos = 1;
	}
	
	/* Delete an email from the mailbox */
	function unlink($path) {
		$st='';
		if ($this->stream_open ( $path, '', '', $st )) {
			imap_delete ( $this->ih, $this->path );
			$this->stream_close ();
			return true;
		} else {
			return false;
		}
	}

	
	/**
	 * Get a "usable" reference to a file : the real file or a tmp copy.
	 *
	 * @param unknown_type $path
	 */
    public static function getRealFSReference($path){
    	return $path;
    }
    
    /**
     * Read a file (by chunks) and copy the data directly inside the given stream.
     *
     * @param unknown_type $path
     * @param unknown_type $stream
     */
    public static function copyFileInStream($path, $stream){
    	//return $path;
    }
    
    /**
     * Chmod implementation for this type of access.
     *
     * @param unknown_type $path
     * @param unknown_type $chmodValue
     */
    public static function changeMode($path, $chmodValue){
    	
    }
	
    /**
     * Enter description here...
     *
     * @param string $path
     * @param int $mode
     * @param int $options
     * @return bool
     */
    public function mkdir($path , $mode , $options){
    	
    }

    /**
     * Enter description here...
     *
     * @param string $path_from
     * @param string $path_to
     * @return bool
     */
    public function rename($path_from , $path_to){
    	
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function rmdir($path , $options){
    	
    }


    /**
     * Enter description here...
     *
     * @return bool
     */
    public function stream_flush(){
    	
    }
	
	
}

//stream_register_wrapper("pop3", "imapAccessWrapper");
//stream_register_wrapper("pop3s", "imapAccessWrapper");
//stream_register_wrapper("imap", "imapAccessWrapper");
//stream_register_wrapper("imaps", "imapAccessWrapper");

?>