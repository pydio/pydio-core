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


require_once(AJXP_BIN_FOLDER."/interface.AjxpWrapper.php");
/**
 * Wrapper for encapsulation FTP accesses
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class ftpAccessWrapper implements AjxpWrapper
{
    // Instance vars $this->
    protected $host;
    protected $port;
    protected $secure;
    protected $path;
    protected $user;
    protected $password;
    protected $ftpActive;
    protected $repoCharset;
    protected $repositoryId;
    protected $fp;

    protected $crtMode;
    protected $crtLink;
    protected $crtTarget;

    // Shared vars self::
    private static $dirContentLoopPath;
    private static $dirContent;
    private static $dirContentKeys;
    private static $dirContentIndex;

    private $monthes = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Juil", "Aug", "Sep", "Oct", "Nov", "Dec");

    public static function getRealFSReference($path, $persistent = false)
    {
        $tmpFile = AJXP_Utils::getAjxpTmpDir()."/".md5(time());
        $tmpHandle = fopen($tmpFile, "wb");
        self::copyFileInStream($path, $tmpHandle);
        fclose($tmpHandle);
        if (!$persistent) {
            register_shutdown_function(array("AJXP_Utils", "silentUnlink"), $tmpFile);
        }
        return $tmpFile;
    }

    public static function isRemote()
    {
        return true;
    }

    public static function copyFileInStream($path, $stream)
    {
        $fake = new ftpAccessWrapper();
        $parts = $fake->parseUrl($path);
        $link = $fake->createFTPLink();
        $serverPath = AJXP_Utils::securePath($fake->path."/".$parts["path"]);
        AJXP_Logger::debug($serverPath);
        ftp_fget($link, $stream, $serverPath, FTP_BINARY);
    }

    public static function changeMode($path, $chmodValue)
    {
        $fake = new ftpAccessWrapper();
        $parts = $fake->parseUrl($path);
        $link = $fake->createFTPLink();
        $serverPath = AJXP_Utils::securePath($fake->path."/".$parts["path"]);
        ftp_chmod($link, $chmodValue, $serverPath);
    }

    public function stream_open($url, $mode, $options, &$context)
    {
        if (stripos($mode, "w") !== false) {
            $this->crtMode = 'write';
            $parts = $this->parseUrl($url);
            $this->crtTarget = AJXP_Utils::securePath($this->path."/".$parts["path"]);
            $this->crtLink = $this->createFTPLink();
            $this->fp = tmpfile();
        } else {
            $this->crtMode = 'read';
            $this->fp = tmpfile();
            $this->copyFileInStream($url, $this->fp);
            rewind($this->fp);
        }
        /*
        if ($context) {
            $this->fp = @fopen($this->buildRealUrl($url), $mode, $options, $context);
        } else {
            $this->fp = @fopen($this->buildRealUrl($url), $mode);
        }
        */
        return ($this->fp !== false);
    }

    public function stream_stat()
    {
        return fstat($this->fp);
    }

    public function stream_seek($offset , $whence = SEEK_SET)
    {
        fseek($this->fp, $offset, SEEK_SET);
    }

    public function stream_tell()
    {
        return ftell($this->fp);
    }

    public function stream_read($count)
    {
        return fread($this->fp, $count);
    }

    public function stream_write($data)
    {
        fwrite($this->fp, $data, strlen($data));
        return strlen($data);
    }

    public function stream_eof()
    {
        return feof($this->fp);
    }

    public function stream_close()
    {
        if (isSet($this->fp) && $this->fp!=-1 && $this->fp!==false) {
            fclose($this->fp);
        }
    }

    // PHP bug #62035
    public static function fput_quota_hack($errno, $errstr, $errfile, $errline, $errcontext)
    {
      if (strpos($errstr, "Opening BINARY mode data connection") !== false)
        $errstr = "Transfer failed. Please check available disk space (quota)";
      AJXP_XMLWriter::catchError($errno, $errstr, $errfile, $errline, $errcontext);
    }

    public function stream_flush()
    {
        if (isSet($this->fp) && $this->fp!=-1 && $this->fp!==false) {
            if ($this->crtMode == 'write') {
                rewind($this->fp);
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Ftp_fput", array("target"=>$this->crtTarget));
                set_error_handler(array("ftpAccessWrapper", "fput_quota_hack"), E_ALL & ~E_NOTICE );
                ftp_fput($this->crtLink, $this->crtTarget, $this->fp, FTP_BINARY);
                restore_error_handler();
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Ftp_fput end", array("target"=>$this->crtTarget));
            } else {
                fflush($this->fp);
            }
        }
    }

    public function unlink($url)
    {
        return unlink($this->buildRealUrl($url));
    }

    public function rmdir($url, $options)
    {
        return rmdir($this->buildRealUrl($url));
    }

    public function mkdir($url, $mode, $options)
    {
        return mkdir($this->buildRealUrl($url), $mode);
    }

    public function rename($from, $to)
    {
        return rename($this->buildRealUrl($from), $this->buildRealUrl($to));
    }

    public function url_stat($path, $flags)
    {
        // We are in an opendir loop
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"URL_STAT", $path);
        if (self::$dirContent != null && self::$dirContentLoopPath == AJXP_Utils::safeDirname($path)) {
            $search = AJXP_Utils::safeBasename($path);
            //if($search == "") $search = ".";
            if (array_key_exists($search, self::$dirContent)) {
                return self::$dirContent[$search];
            }
        }
        $parts = $this->parseUrl($path);
        $link = $this->createFTPLink();
        $serverPath = AJXP_Utils::securePath($this->path."/".$parts["path"]);
        if ($parts["path"] == "/") {
            $basename = ".";
        } else {
            $basename = AJXP_Utils::safeBasename($serverPath);
        }

        $serverParent = AJXP_Utils::safeDirname($parts["path"]);
        $serverParent = AJXP_Utils::securePath($this->path."/".$serverParent);

        $testCd = @ftp_chdir($link, $serverPath);
        if ($testCd === true) {
            // DIR
            $contents = $this->rawList($link, $serverParent, 'd');
            foreach ($contents as $entry) {
                $res = $this->rawListEntryToStat($entry);
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RAWLISTENTRY ".$res["name"]. " (searching ".$basename.")", $res["stat"]);
                if ($res["name"] == $basename) {
                    AbstractAccessDriver::fixPermissions($res["stat"], ConfService::getRepositoryById($this->repositoryId), array($this, "getRemoteUserId"));
                    $statValue = $res["stat"];
                    return $statValue;
                }
            }
        } else {
            // FILE
            $contents = $this->rawList($link, $serverPath, 'f');
            if (count($contents) == 1) {
                $res = $this->rawListEntryToStat($contents[0]);
                AbstractAccessDriver::fixPermissions($res["stat"], ConfService::getRepositoryById($this->repositoryId), array($this, "getRemoteUserId"));
                $statValue = $res["stat"];
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"STAT FILE $serverPath", $statValue);
                return $statValue;
            }
        }
        return null;
    }

    public function dir_opendir ($url , $options )
    {
        $parts = $this->parseUrl($url);
        $link = $this->createFTPLink();
        $serverPath = AJXP_Utils::securePath($this->path."/".$parts["path"]);
        $contents = $this->rawList($link, $serverPath);
        $folders = $files = array();
        foreach ($contents as $entry) {
               $result = $this->rawListEntryToStat($entry);
            AbstractAccessDriver::fixPermissions($result["stat"], ConfService::getRepositoryById($this->repositoryId), array($this, "getRemoteUserId"));
               $isDir = $result["dir"];
               $statValue = $result["stat"];
               $file = $result["name"];
            if ($isDir) {
                $folders[$file] = $statValue;
            } else {
                $files[$file] = $statValue;
            }
           }
           // Append all files keys to folders. Do not use array_merge.
           foreach ($files as $key => $value) {
               $folders[$key] = $value;
           }
           AJXP_Logger::debug(__CLASS__,__FUNCTION__,"OPENDIR ", $folders);
        self::$dirContentLoopPath = AJXP_Utils::safeDirname($url);
        self::$dirContent = $folders;//array_merge($folders, $files);
        self::$dirContentKeys = array_keys(self::$dirContent);
        self::$dirContentIndex = 0;
           return true;
    }

    public function dir_closedir  ()
    {
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"CLOSEDIR");
        self::$dirContent = null;
        self::$dirContentKeys = null;
        self::$dirContentLoopPath = null;
        self::$dirContentIndex = 0;
    }

    public function dir_readdir ()
    {
        self::$dirContentIndex ++;
        if (isSet(self::$dirContentKeys[self::$dirContentIndex-1])) {
            return self::$dirContentKeys[self::$dirContentIndex-1];
        } else {
            return false;
        }
    }

    public function dir_rewinddir ()
    {
        self::$dirContentIndex = 0;
    }

    protected function rawList($link, $serverPath, $target = 'd', $retry = true)
    {
        if ($target == 'f') {
            $parentDir = AJXP_Utils::safeDirname($serverPath);
            $fileName = AJXP_Utils::safeBasename($serverPath);
            ftp_chdir($link, $parentDir);
            $rl_dirlist = @ftp_rawlist($link, "-a .");
            //AJXP_Logger::debug(__CLASS__,__FUNCTION__,"FILE RAWLIST FROM ".$parentDir);
            if (is_array($rl_dirlist)) {
                $escaped = preg_quote($fileName);
                foreach ($rl_dirlist as $rl_index => $rl_entry) {
                    if (preg_match("/ $escaped$/" , $rl_entry)) {
                        $contents = array($rl_dirlist[$rl_index]);
                    }
                }
            }
        } else {
            ftp_chdir($link, $serverPath);
            $contents = ftp_rawlist($link, "-a .");
            //AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RAW LIST RESULT ".print_r($contents, true));
        }

        if (!is_array($contents) && !$this->ftpActive) {
            if ($retry == false) {
                return array();
            }
            // We might have timed out, so let's go passive if not done yet
            global $_SESSION;
            if ($_SESSION["ftpPasv"] == "true") {
                return array();
            }
            @ftp_pasv($link, TRUE);
            $_SESSION["ftpPasv"]="true";
               // RETRY!
            return $this->rawList($link, $serverPath, $target, FALSE);
        }
        if (!is_array($contents)) {
            return array();
        }
        return $contents;
    }

    protected function rawListEntryToStat($entry, $filterStatPerms = false)
    {
        $info = array();
        $monthes = array_flip( $this->monthes );
        $vinfo = preg_split("/[\s]+/", $entry);
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RAW LIST", $entry);
        $statValue = array();
        if ($vinfo[0] !== "total") {
            $fileperms = $vinfo[0];
            $info['num']   = $vinfo[1];
            $info['owner'] = $vinfo[2];
            $info['groups'] = array();
            $i = 3;
            while (true) {
                $info['groups'][] = $vinfo[$i];
                $i++;
                // Detect "Size" and "Month"
                if(is_numeric($vinfo[$i]) && !is_numeric($vinfo[$i+1])) break;
            }
            $info['group'] = implode(" ", $info["groups"]);
            $info['size']  = $vinfo[$i]; $i++;
            $info['month'] = $vinfo[$i]; $i++;
              $info['day']   = $vinfo[$i]; $i++;
              $info['timeOrYear']  = $vinfo[$i]; $i++;
              //$info['name']  = $vinfo[$i]; $i++;
         }
         $resplit = preg_split("/[\s]+/", $entry, 8 + count($info["groups"]));
         $file = trim(array_pop($resplit));
         $statValue[7] = $statValue["size"] = trim($info['size']);
         if (strstr($info["timeOrYear"], ":")) {
             $info["time"] = $info["timeOrYear"];
            $monthKey = $monthes[$info['month']] + 1;
             if (intval(date('m')) < $monthKey) {
                 $info['year'] = date("Y") -1;
             } else {
                 $info["year"] = date("Y");
             }
         } else {
             $info["time"] = '09:00';
             $info["year"] = $info["timeOrYear"];
         }
         $statValue[4] = $statValue["uid"] = $info["owner"];
         $statValue[5] = $statValue["gid"] = $info["group"];
         $filedate  = trim($info['day'])." ".trim($info['month'])." ".trim($info['year'])." ".trim($info['time']);
         $statValue[9] = $statValue["mtime"]  = strtotime($filedate);

         $isDir = false;
         if (strpos($fileperms,"d")!==FALSE || strpos($fileperms,"l")!==FALSE) {
             if (strpos($fileperms,"l")!==FALSE) {
                $test=explode(" ->", $file);
                $file=$test[0];
              }
              $isDir = true;
        }
        $boolIsDir = $isDir;
        $statValue[2] = $statValue["mode"] = $this->convertingChmod($fileperms);
        $statValue["ftp_perms"] = $fileperms;
        return array("name"=>$file, "stat"=>$statValue, "dir"=>$isDir);
    }

    protected function parseUrl($url, $forceLogin = false)
    {
        // URL MAY BE ajxp.ftp://username:password@host/path
        $urlParts = AJXP_Utils::safeParseUrl($url);
        $this->repositoryId = $urlParts["host"];
        $repository = ConfService::getRepositoryById($this->repositoryId);
        if ($repository == null) {
            throw new Exception("Cannot find repository for dynamic ftp authentification.");
        }
        $credentials = AJXP_Safe::tryLoadingCredentialsFromSources($urlParts, $repository);
        $this->user = $credentials["user"];
        $this->password = $credentials["password"];
        if ($this->user=="") {
            throw new AJXP_Exception("Cannot find user/pass for FTP access!");
        }
        if ($repository->getOption("DYNAMIC_FTP") == "TRUE" && isSet($_SESSION["AJXP_DYNAMIC_FTP_DATA"])) {
            $data = $_SESSION["AJXP_DYNAMIC_FTP_DATA"];
            $this->host = $data["FTP_HOST"];
            $this->path = $data["PATH"];
            $this->secure = ($data["FTP_SECURE"] == "TRUE"?true:false);
            $this->port = ($data["FTP_PORT"]!=""?intval($data["FTP_PORT"]):($this->secure?22:21));
            $this->ftpActive = ($data["FTP_DIRECT"] == "TRUE"?true:false);
            $this->repoCharset = $data["CHARSET"];
        } else {
            $this->host = $repository->getOption("FTP_HOST");
            $this->path = $repository->getOption("PATH");
            $this->secure = ($repository->getOption("FTP_SECURE") == "TRUE"?true:false);
            $this->port = ($repository->getOption("FTP_PORT")!=""?intval($repository->getOption("FTP_PORT")):($this->secure?22:21));
            $this->ftpActive = ($repository->getOption("FTP_DIRECT") == "TRUE"?true:false);
            $this->repoCharset = $repository->getOption("CHARSET");
        }

        // Test Connexion and server features
        global $_SESSION;
        $cacheKey = $repository->getId()."_ftpCharset";
        if (!isset($_SESSION[$cacheKey]) || !strlen($_SESSION[$cacheKey]) || $forceLogin) {
            $features = $this->getServerFeatures();
            if(!isSet($_SESSION["AJXP_CHARSET"]) || $_SESSION["AJXP_CHARSET"] == "") $_SESSION["AJXP_CHARSET"] = $features["charset"];
            $_SESSION[$cacheKey] = $_SESSION["AJXP_CHARSET"];
        }
        return $urlParts;
    }

    /**
     * @return array Array(UID, GID) to be used to compute permission
     */
    public function getRemoteUserId()
    {
        $repoUid = ConfService::getRepository()->getOption("UID");
        if (!empty($repoUid)) {
            return array($repoUid, "-1");
        }
        return array($this->user, "-1");
    }

    protected function buildRealUrl($url)
    {
        if (!isSet($this->user)) {
            $parts = $this->parseUrl($url);
        } else {
            // parseUrl already called before (rename case).
            $parts = AJXP_Utils::safeParseUrl($url);
        }
        $serverPath = AJXP_Utils::securePath("/$this->path/".$parts["path"]);
        return "ftp".($this->secure?"s":"")."://$this->user:$this->password@$this->host:$this->port".$serverPath;
    }

    /** This method retrieves the FTP server features as described in RFC2389
     *	A decent FTP server support MLST command to list file using UTF-8 encoding
     *  @return an array of features (see code)
     */
    protected function getServerFeatures()
    {
        $link = $this->createFTPLink();

        if (ConfService::getRepositoryById($this->repositoryId)->getOption("CREATE") == true) {
            // Test if root exists and create it otherwise
            $serverPath = AJXP_Utils::securePath($this->path."/");
            $testCd = @ftp_chdir($link, $serverPath);
            if ($testCd !== true) {
                $res = @ftp_mkdir($link, $serverPath);
                if(!$res) throw new Exception("Cannot create path on remote server!");
            }
        }

        $features = @ftp_raw($link, "FEAT");
        // Check the answer code
        if (isSet($features[0]) && $features[0][0] != "2") {
            //ftp_close($link);
            return array("list"=>"LIST", "charset"=>$this->repoCharset);
        }
        $retArray = array("list"=>"LIST", "charset"=>$this->repoCharset);
        // Ok, find out the encoding used
        foreach ($features as $feature) {
            if (strstr($feature, "UTF8") !== FALSE) {   // See http://wiki.filezilla-project.org/Character_Set for an explaination
                @ftp_raw($link, "OPTS UTF-8 ON");
                $retArray['charset'] = "UTF-8";
                //ftp_close($link);
                return $retArray;
            }
        }
        // In the future version, we should also use MLST as it standardize the listing format
        return $retArray;
    }

    protected function createFTPLink()
    {
        // If connexion exist and is still connected
        if(is_array($_SESSION["FTP_CONNEXIONS"])
            && array_key_exists($this->repositoryId, $_SESSION["FTP_CONNEXIONS"])
            && @ftp_systype($_SESSION["FTP_CONNEXIONS"][$this->repositoryId])){
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Using stored FTP Session");
                return $_SESSION["FTP_CONNEXIONS"][$this->repositoryId];
            }
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Creating new FTP Session");
        $link = FALSE;
           //Connects to the FTP.
           if ($this->secure) {
               $link = @ftp_ssl_connect($this->host, $this->port);
           } else {
            $link = @ftp_connect($this->host, $this->port);
           }
        if (!$link) {
            throw new AJXP_Exception("Cannot connect to FTP server ($this->host, $this->port)");
         }
        //register_shutdown_function('ftp_close', $link);
        @ftp_set_option($link, FTP_TIMEOUT_SEC, 10);
        if (!@ftp_login($link,$this->user,$this->password)) {
            throw new AJXP_Exception("Cannot login to FTP server with user $this->user");
        }
        if (!$this->ftpActive) {
            @ftp_pasv($link, true);
            global $_SESSION;
            $_SESSION["ftpPasv"]="true";
        }
        if (!is_array($_SESSION["FTP_CONNEXIONS"])) {
            $_SESSION["FTP_CONNEXIONS"] = array();
        }
        $_SESSION["FTP_CONNEXIONS"][$this->repositoryId] = $link;
        return $link;
    }

    protected function convertingChmod($permissions, $filterForStat = false)
    {
        $mode = 0;

        if ($permissions[1] == 'r') $mode += 0400;
        if ($permissions[2] == 'w') $mode += 0200;
        if ($permissions[3] == 'x') $mode += 0100;
         else if ($permissions[3] == 's') $mode += 04100;
         else if ($permissions[3] == 'S') $mode += 04000;

         if ($permissions[4] == 'r') $mode += 040;
         if ($permissions[5] == 'w' || ($filterForStat && $permissions[2] == 'w')) $mode += 020;
         if ($permissions[6] == 'x' || ($filterForStat && $permissions[3] == 'x')) $mode += 010;
         else if ($permissions[6] == 's') $mode += 02010;
         else if ($permissions[6] == 'S') $mode += 02000;

         if ($permissions[7] == 'r') $mode += 04;
         if ($permissions[8] == 'w' || ($filterForStat && $permissions[2] == 'w')) $mode += 02;
         if ($permissions[9] == 'x' || ($filterForStat && $permissions[3] == 'x')) $mode += 01;
         else if ($permissions[9] == 't') $mode += 01001;
         else if ($permissions[9] == 'T') $mode += 01000;

        if ($permissions[0] != "d") {
            $mode += 0100000;
        } else {
            $mode += 0040000;
        }

        $mode = (string) ("0".$mode);
        return  $mode;
    }

}
