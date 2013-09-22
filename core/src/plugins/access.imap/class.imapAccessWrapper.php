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

function rejectEmpty($element){return !empty($element);}

/**
 * AJXP_Plugin to browse a mailbox content (IMAP OR POP)
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class imapAccessWrapper implements AjxpWrapper
{
    public $ih;
    public $host;
    public $port;
    public $username;
    public $password;
    public $path;
    public $pop3;
    // stuff for dir reading
    public $dir;
    public $pos;
    // stuff for file reading
    public $data;
    public $gotbody;
    public $size;
    public $time;

    public $fragment;
    public $mailbox;

    public $mailboxes;
    public $currentAttachmentData;


    private static $currentStream;
    private static $currentRef;
    private static $currentCount;
    private static $delimiter;
    private static $attachmentsMetadata;

    public static function closeStreamFunc()
    {
        if (self::$currentStream) {
            AJXP_Logger::debug("Closing stream now!");
            imap_close(self::$currentStream);
        }
    }

    public static function getCurrentDirCount()
    {
        return self::$currentCount;
    }

    public static function getCurrentAttachmentsMetadata()
    {
        return self::$attachmentsMetadata;
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        // parse URL
        $parts = parse_url($path);
        $this->repositoryId = $parts["host"];
        $mainCacheDir = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR);
        if (!isset(self::$delimiter) && file_exists($mainCacheDir."/access.imap/mailbox_delim_".$this->repositoryId)) {
            self::$delimiter = file_get_contents($mainCacheDir."/access.imap/mailbox_delim_".$this->repositoryId);
        }

        $this->path = substr($parts["path"], 1);
        //$this->mailbox = "INBOX";
        $pathParts = explode("/", $this->path);
        $pathParts = array_filter($pathParts, "rejectEmpty");
        if (count($pathParts) > 1) {
            $this->path = array_pop($pathParts);
            $this->mailbox = implode("/", $pathParts);
        } else if (count($pathParts) == 1) {
            $this->mailbox = implode("/", $pathParts);
            $this->path = "";
        } else {
            $this->mailbox = "";
            $this->path = "";
        }
        $this->fragment = $parts["fragment"];
        if (empty ( $this->path ) && $mode !== 'np') {
            return false;
        }
        if (!empty($this->mailbox)) {
            $this->mailbox = mb_convert_encoding($this->mailbox, "UTF7-IMAP", SystemTextEncoding::getEncoding());
            $this->mailbox = str_replace("__delim__", (isSet(self::$delimiter)?self::$delimiter:"/"), $this->mailbox);
        }
        if (!empty($this->fragment) && strpos($this->fragment, "attachments") === 0 && strpos($this->fragment, "/")!== false) {
            // remove fragment
            $ar = explode("#", $path);
            $mailPath = array_shift($ar);
            $ar = explode("/", $this->fragment);
            $attachmentId = array_pop($ar);
            $this->currentAttachmentData = array("realPath" => $mailPath, "attachmentId" => $attachmentId);
            // EXTRACT ATTACHMENT AND RETURN
            require_once AJXP_INSTALL_PATH."/plugins/editor.eml/class.EmlParser.php";
            $emlParser = new EmlParser("", "");
            $attachMeta = array();
            $this->data = $emlParser->getAttachmentBody(
                $this->currentAttachmentData["realPath"],
                $this->currentAttachmentData["attachmentId"],
                true,
                $attachMeta
            );
            if (self::$attachmentsMetadata == null) {
                self::$attachmentsMetadata = array($attachMeta);
            }

            $this->currentAttachmentData["size"] = strlen($this->data);
            $this->pos = 0;
            $this->size = strlen($this->data);
            return true;
        }

        // open IMAP connection
        if (self::$currentStream != null) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Using currently opened stream! ".print_r(self::$currentStream, true));
            $this->ih = self::$currentStream;
            // Rewind everything
            $this->dir_rewinddir();
            $this->stream_seek(0);
        } else {
            $repository = ConfService::getRepositoryById($this->repositoryId);
            $ssl = $repository->getOption("SSL") == "true" ? true: false ;
            $this->pop3 = $repository->getOption("BOX_TYPE") == "pop3" ? true : false;
            $this->host = $repository->getOption("HOST");
            $this->port = $repository->getOption("PORT");
            $this->username = $repository->getOption("USER");
            $this->password = $repository->getOption("PASS");
            $server = "{". $this->host . ":" . $this->port . "/".($this->pop3?"pop3/":"").($ssl?"ssl/novalidate-cert":"novalidate-cert")."}";
            self::$currentRef = $server;
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Opening a new stream ".$server." with mailbox '".$this->mailbox."'");
            try {
                $this->ih = imap_open ( $server.$this->mailbox , $this->username, $this->password, (!$this->pop3 && empty($this->mailbox)?OP_HALFOPEN:NULL), 1);
            } catch (Exception $e) {
                throw new Exception($e->getMessage()." - imap errors  : ".print_r(imap_errors(), true), $e->getCode());
            }
            self::$currentStream = $this->ih;
            register_shutdown_function(array("imapAccessWrapper", "closeStreamFunc"));
        }
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

    public function stream_close()
    {
        if (empty($this->mailbox)) {
            //self::$currentStream = null;
            //imap_close ( $this->ih );
        }
        if (!empty($this->currentAttachmentData)) {
            $this->currentAttachmentBody = null;
        }
    }

    /* Smart reader, at first it only downloads the header to memory, but if a read request is made
       beyond the header, we download the rest of the body */
    public function stream_read($count)
    {
        //AJXP_Logger::debug(__CLASS__,__FUNCTION__,"READING $count FROM $this->path", $this->currentAttachmentData);
        if (!empty($this->currentAttachmentData)) {
            if (empty($this->data)) {
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Attachement", $this->currentAttachmentData);
                // EXTRACT ATTACHMENT AND RETURN
                require_once AJXP_INSTALL_PATH."/plugins/editor.eml/class.EmlParser.php";
                $emlParser = new EmlParser("", "");
                $attachMeta = array();
                $this->data = $emlParser->getAttachmentBody(
                    $this->currentAttachmentData["realPath"],
                    $this->currentAttachmentData["attachmentId"],
                    true,
                    $attachMeta
                );
                $this->pos = 0;
                $this->size = strlen($this->data);
            }
        } else {
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
    public function stream_write($data)
    {
        return false;
    }

    public function stream_eof()
    {
        if ($this->pos >= $this->size) {
            return true;
        } else {
            return false;
        }
    }

    public function stream_tell()
    {
        return $this->pos;
    }

    public function stream_seek($offset , $whence = SEEK_SET)
    {
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

    public function stream_stat()
    {
        $keys = array('dev' => 0, 'ino' => 0, 'mode' => 33216, 'nlink' => 0, 'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => $this->size, 'atime' => $this->time, 'mtime' => $this->time, 'ctime' => $this->time, 'blksize' => 0, 'blocks' => 0 );
        return $keys;
    }

    public function dir_opendir($path, $options)
    {
        // Reset
        self::$attachmentsMetadata = null;

        $st = '';
        $stream = $this->stream_open ( $path, 'np', $options, $st );
        if (!$stream) {
            return false;
        }
        if (empty($this->mailbox)) {
            // We are browsing root, we want the list of mailboxes
            $this->mailboxes = imap_getmailboxes($this->ih, self::$currentRef, "*");
            $this->dir = count($this->mailboxes);
            self::$currentCount = count($this->mailboxes);
            $this->pos = $this->dir - 1;
        } else if ($this->fragment == "attachments") {
            require_once AJXP_INSTALL_PATH.'/plugins/editor.eml/class.EmlParser.php';
            $parser = new EmlParser("", "");
            $ar = explode("#", $path);
            $path = array_shift($ar);// remove fragment
            self::$attachmentsMetadata = array();
            $parser->listAttachments($path, true, self::$attachmentsMetadata);
            $this->dir = count(self::$attachmentsMetadata);
            $this->pos = $this->dir - 1;
            self::$currentCount = $this->dir;

        } else {
            // We are in a mailbox, we want the messages number
            $this->dir = imap_num_msg ( $this->ih );
            self::$currentCount = $this->dir;
            $this->pos = $this->dir;
        }
        $this->stream_close ();
        return true;
    }

    public function dir_closedir()
    {
        // do nothing.
        // $this->stream_close();
        $this->mailboxes = null;
    }

    public function dir_readdir()
    {
        if ($this->mailboxes) {
            if($this->pos < 0) return false;
            else{
                $obj = $this->mailboxes[$this->pos];
                $this->pos --;
                $x = $obj->name;
                $x = mb_convert_encoding( $x, "UTF-8", "UTF7-IMAP" );
                $x = str_replace(self::$currentRef, "", $x);
                $mainCacheDir = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR);
                if (!isSet(self::$delimiter) && !file_exists($mainCacheDir."/access.imap/mailbox_delim_".$this->repositoryId)) {
                    if(!is_dir($mainCacheDir ."/access.imap")) mkdir($mainCacheDir."/access.imap");
                    file_put_contents($mainCacheDir."/access.imap/mailbox_delim_".$this->repositoryId, $obj->delimiter);
                    self::$delimiter = $obj->delimiter;
                }
                $x = str_replace($obj->delimiter, "__delim__", $x);
            }
        } else if (self::$attachmentsMetadata != null) {
            if($this->pos < 0) return false;
            $x = self::$attachmentsMetadata[$this->pos]["x-attachment-id"];
            $this->pos--;
        } else {
            if ($this->pos < 1) {
                return false;
            } else {
                $x = $this->pos;
                $this->pos --;
                //$x .= "#header";
            }
        }
        return $x;
    }

    public function dir_rewinddir()
    {
        if (empty($this->mailbox)) {
            $this->pos = $this->dir;
        } else {
            $this->pos = count($this->mailboxes) - 1;
        }
    }

    public function url_stat($path, $flags)
    {
        $emptyString = '';
        if ($this->stream_open ( $path, 'np', $flags, $emptyString)) {
            if (!empty($this->path) && empty($this->currentAttachmentData)) {
                // Mail
                //$stats = array();
                if (empty($this->size) && empty($this->time)) {
                    list ( $stats, ) = imap_fetch_overview ( $this->ih, $this->path );
                    $this->size = $stats->size;
                    $this->time = strtotime ( $stats->date );
                }
                $keys = array(
                    'dev' => 0,
                    'ino' => 0,
                    'mode' => 33216,
                    'nlink' => 0,
                    'uid' => 0,
                    'gid' => 0,
                    'rdev' => 0,
                    'size' => $this->size,
                    'atime' => $this->time,
                    'mtime' => $this->time,
                    'ctime' => $this->time,
                    'blksize' => 0,
                    'blocks' => 0 );
            } else {
                // BOX
                if (empty($this->currentAttachmentData) && !empty($this->mailbox) && !$this->pop3) {
                    // GET LAST MESSAGE
                    imap_reopen($this->ih, self::$currentRef.$this->mailbox);
                    $last = imap_num_msg($this->ih);
                    //AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Should get mailbox data ".self::$currentRef.$this->mailbox . $last);
                    list ( $stats, ) = imap_fetch_overview ( $this->ih, $last );
                    $this->size = $stats->size;
                    $this->time = strtotime ( $stats->date );
                }
                $keys = array(
                    'dev' => 0,
                    'ino' => 0,
                    'mode' => (empty($this->currentAttachmentData)?(33216 | 0040000):33216),
                    'nlink' => 0,
                    'uid' => 0,
                    'gid' => 0,
                    'rdev' => 0,
                    'size' => (!empty($this->currentAttachmentData)?$this->currentAttachmentData["size"]:0),
                    'atime' => (!empty($this->time)?$this->time:0),
                    'mtime' => (!empty($this->time)?$this->time:0),
                    'ctime' => (!empty($this->time)?$this->time:0),
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

    /* Delete an email from the mailbox */
    public function unlink($path)
    {
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
    public static function getRealFSReference($path)
    {
        return $path;
    }

    /**
     * Read a file (by chunks) and copy the data directly inside the given stream.
     *
     * @param unknown_type $path
     * @param unknown_type $stream
     */
    public static function copyFileInStream($path, $stream)
    {
        //return $path;
        $fp = fopen($path, 'r');
        $bufferSize = 4096 * 8;
        if ($fp) {
            $i = 0;
            while (!feof($fp)) {
                $data = fread($fp, $bufferSize);
                fwrite($stream, $data, strlen($data));
                //if($i > 10) break;
                //$i++;
            }
            fclose($fp);
        }
    }

    public static function isRemote()
    {
        return true;
    }

    /**
     * Chmod implementation for this type of access.
     *
     * @param unknown_type $path
     * @param unknown_type $chmodValue
     */
    public static function changeMode($path, $chmodValue)
    {
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @param int $mode
     * @param int $options
     * @return bool
     */
    public function mkdir($path , $mode , $options)
    {
    }

    /**
     * Enter description here...
     *
     * @param string $path_from
     * @param string $path_to
     * @return bool
     */
    public function rename($path_from , $path_to)
    {
    }

    /**
     * Enter description here...
     *
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function rmdir($path , $options)
    {
    }


    /**
     * Enter description here...
     *
     * @return bool
     */
    public function stream_flush()
    {
    }



}
