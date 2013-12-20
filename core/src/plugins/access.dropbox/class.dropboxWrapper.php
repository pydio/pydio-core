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
 *
 */
defined('AJXP_EXEC') or die( 'Access not allowed');


require_once (AJXP_INSTALL_PATH.'/plugins/access.dropbox/dropbox-php/autoload.php');
require_once (AJXP_BIN_FOLDER.'/interface.AjxpWrapper.php');

/**
 * AjxpWrapper encapsulation the PHP Dropbox client
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class dropboxWrapper implements AjxpWrapper
{
    /**
     *
     * @var Dropbox_API
     */
    private static $dropbox;
    private static $oauth;

    private static $crtDirContent = array();
    private static $crtDirIndex = 0;

    private static $crtHandle;
    private static $crtTmpFile;
    private static $crtWritePath;

    public function __construct()
    {
    }

    public function initPath($ajxpPath)
    {
        $repo = ConfService::getRepository();
        if (empty(self::$dropbox)) {
            $consumerKey = $repo->getOption('CONSUMER_KEY');
            $consumerSecret = $repo->getOption('CONSUMER_SECRET');

            self::$oauth = new Dropbox_OAuth_PEAR($consumerKey, $consumerSecret);
            self::$oauth->setToken($_SESSION["OAUTH_DROPBOX_TOKENS"]);
            self::$dropbox = new Dropbox_API(self::$oauth);
        }
        $basePath = $repo->getOption("PATH");
        if(empty($basePath)) $basePath = "";
        $parts = AJXP_Utils::safeParseUrl($ajxpPath);
        $path = $basePath."/".ltrim($parts["path"], "/");

        if($path == "") return "/";
        return $path;
    }

    public static function staticInitPath($ajxpPath)
    {
        $tmpObject = new dropboxWrapper();
        return $tmpObject->initPath($ajxpPath);
    }

    protected function metadataToStat($metaEntry)
    {
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Stating ", $metaEntry);
        $mode = 0666;
        if(intval($metaEntry["is_dir"]) == 1) $mode += 0040000;
        else $mode += 0100000;
        $time = strtotime($metaEntry["modified"]);
        $size = intval($metaEntry["bytes"]);
        $keys = array(
            'dev' => 0,
            'ino' => 0,
            'mode' => $mode,
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => $size,
            'atime' => $time,
            'mtime' => $time,
            'ctime' => $time,
            'blksize' => 0,
            'blocks' => 0
        );
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Stat value", $keys);
        return $keys;
    }

    public static function copyFileInStream($path, $stream)
    {
        $path = self::staticInitPath($path);
        $data = self::$dropbox->getFile($path);
        fwrite($stream, $data, strlen($data));
    }

    public static function isRemote()
    {
        return true;
    }

    public static function getRealFSReference($path)
    {
        $tmpFile = AJXP_Utils::getAjxpTmpDir()."/".rand();
        $path = self::staticInitPath($path);
        file_put_contents($tmpFile, self::$dropbox->getFile($path));
        return $tmpFile;
    }

    public static function changeMode($path, $chmodValue)
    {
    }


    public function rename($path_from, $path_to)
    {
        $path1 = $this->initPath($path_from);
        $path2 = $this->initPath($path_to);
        self::$dropbox->copy($path1, $path2);
        self::$dropbox->delete($path1);
    }

    public function mkdir($path, $mode, $options)
    {
        $path = $this->initPath($path);
        try {
            self::$dropbox->createFolder($path);
        } catch (Dropbox_Exception $e) {
            return false;
        }
        return true;
    }

    public function rmdir($path, $options)
    {
        $path = $this->initPath($path);
        try {
            self::$dropbox->delete($path);
        } catch (Dropbox_Exception $e) {
            return false;
        }
        return true;
    }

    public function unlink($path)
    {
        $path = $this->initPath($path);
        try {
            self::$dropbox->delete($path);
        } catch (Dropbox_Exception $e) {
            return false;
        }
        return true;
    }

    public function url_stat($path, $flags)
    {
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"STATING $path");
        $path = $this->initPath($path);
        $meta = null;
        if (self::$crtDirContent != null) {
            foreach (self::$crtDirContent as $metaEntry) {
                if ($metaEntry["path"] == $path) {
                    $metaEntry = $meta;
                    break;
                }
            }
        }
        if (empty($meta)) {
            try {
                $meta = self::$dropbox->getMetaData($path);
            } catch (Dropbox_Exception_NotFound $nf) {
                return false;
            }
        }
        return $this->metadataToStat($meta);
    }

    public function dir_opendir($path, $options)
    {
        $path = $this->initPath($path);
        $metadata = self::$dropbox->getMetaData($path);
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"CONTENT for $path", $metadata);
        self::$crtDirContent = $metadata["contents"];
        if (!is_array(self::$crtDirContent)) {
            return false;
        }
        return true;
    }

    public function dir_readdir()
    {
        //return false;
        if(self::$crtDirIndex == count(self::$crtDirContent)) return false;
        $meta = self::$crtDirContent[self::$crtDirIndex];
        self::$crtDirIndex ++;
        return basename($meta["path"]);
    }

    public function dir_rewinddir()
    {
        self::$crtDirIndex = 0;
    }

    public function dir_closedir()
    {
        self::$crtDirContent = array();
        self::$crtDirIndex = 0;
    }


    public function stream_flush()
    {
        return fflush(self::$crtHandle);
    }

    public function stream_read($count)
    {
        return fread(self::$crtHandle, $count);
    }

    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return fseek(self::$crtHandle, $offset, $whence);
    }

    public function stream_write($data)
    {
        return fwrite(self::$crtHandle, $data);
    }

    public function stream_close()
    {
        $res = fclose(self::$crtHandle);
        if (self::$crtWritePath != null) {
            $path = $this->initPath(self::$crtWritePath);
            try {
                $postRes = self::$dropbox->putFile($path, self::$crtTmpFile);
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Post to $path succeeded:");
            } catch (Dropbox_Exception $dE) {
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Post to $path failed :".$dE->getMessage());
            }
        }
        unlink(self::$crtTmpFile);
        return $res;
    }

    public function stream_tell()
    {
        return ftell(self::$crtHandle);
    }

    public function stream_eof()
    {
        return feof(self::$crtHandle);
    }

    public function stream_stat()
    {
        return true;
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        if (strstr($mode, "r") !== false) {
            self::$crtTmpFile = self::getRealFSReference($path);
            self::$crtWritePath = null;
        } else {
            self::$crtTmpFile = AJXP_Utils::getAjxpTmpDir()."/".rand();
            self::$crtWritePath = $path;
        }
        self::$crtHandle = fopen(self::$crtTmpFile, $mode);
        return true;
    }
}
