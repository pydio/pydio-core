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
 * The latest code can be found at <https://pydio.com>.
 *
 */
namespace Pydio\Access\Driver\StreamProvider\FTP;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\IAjxpWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Exception\WorkspaceAuthRequired;
use Pydio\Core\Model\ContextInterface;


use Pydio\Core\Exception\PydioException;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\FileHelper;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\PathUtils;
use Pydio\Core\Utils\Vars\UrlUtils;

use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die( 'Access not allowed');


/**
 * Wrapper for encapsulation FTP accesses
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class FtpAccessWrapper implements IAjxpWrapper
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
    private static $dirContentLoopPath = array();
    private static $dirContent = array();
    private static $dirContentKeys = array();
    private static $dirContentIndex = array();

    private $monthes = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Juil", "Aug", "Sep", "Oct", "Nov", "Dec");

    /**
     * @param string $path
     * @param bool $persistent
     * @return string
     */
    public static function getRealFSReference($path, $persistent = false)
    {
        $tmpFile = ApplicationState::getTemporaryFolder() ."/".md5(time());
        $tmpHandle = fopen($tmpFile, "wb");
        self::copyFileInStream($path, $tmpHandle);
        fclose($tmpHandle);
        if (!$persistent) {
            register_shutdown_function(function() use($tmpFile){
                FileHelper::silentUnlink($tmpFile);
            });
        }
        return $tmpFile;
    }

    /**
     * @param $url
     * @return bool
     */
    public static function isRemote($url)
    {
        return true;
    }

    /**
     * @param String $url
     * @return bool
     */
    public static function isSeekable($url)
    {
        return true;
    }

    /**
     * @param string $path
     * @param resource $stream
     * @throws PydioException
     * @throws \Exception
     */
    public static function copyFileInStream($path, $stream)
    {
        $fake = new FtpAccessWrapper();
        $parts = $fake->parseUrl($path);
        $link = $fake->createFTPLink();
        $serverPath = InputFilter::securePath($fake->path . "/" . $parts["path"]);
        Logger::debug($serverPath);
        ftp_fget($link, $stream, $serverPath, FTP_BINARY);
    }

    /**
     * @param string $path
     * @param number $chmodValue
     * @throws PydioException
     * @throws \Exception
     */
    public static function changeMode($path, $chmodValue)
    {
        $fake = new FtpAccessWrapper();
        $parts = $fake->parseUrl($path);
        $link = $fake->createFTPLink();
        $serverPath = InputFilter::securePath($fake->path . "/" . $parts["path"]);
        ftp_chmod($link, $chmodValue, $serverPath);
    }

    /**
     * @param string $url
     * @param string $mode
     * @param int $options
     * @param string $context
     * @return bool
     * @throws PydioException
     * @throws \Exception
     */
    public function stream_open($url, $mode, $options, &$context)
    {
        if (stripos($mode, "w") !== false) {
            $this->crtMode = 'write';
            $parts = $this->parseUrl($url);
            $this->crtTarget = InputFilter::securePath($this->path . "/" . $parts["path"]);
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

    /**
     * @return array
     */
    public function stream_stat()
    {
        return fstat($this->fp);
    }

    /**
     * @param int $offset
     * @param int $whence
     */
    public function stream_seek($offset , $whence = SEEK_SET)
    {
        fseek($this->fp, $offset, SEEK_SET);
    }

    /**
     * @return int
     */
    public function stream_tell()
    {
        return ftell($this->fp);
    }

    /**
     * @param int $count
     * @return string
     */
    public function stream_read($count)
    {
        return fread($this->fp, $count);
    }

    /**
     * @param string $data
     * @return int
     */
    public function stream_write($data)
    {
        fwrite($this->fp, $data, strlen($data));
        return strlen($data);
    }

    /**
     * @return bool
     */
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
    /**
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @param $errcontext
     * @throws PydioException
     */
    public static function fput_quota_hack($errno, $errstr, $errfile, $errline, $errcontext)
    {
      if (strpos($errstr, "Opening BINARY mode data connection") !== false)
        $errstr = "Transfer failed. Please check available disk space (quota)";
        throw new PydioException("$errno - $errstr");
    }

    /**
     *
     */
    public function stream_flush()
    {
        if (isSet($this->fp) && $this->fp!=-1 && $this->fp!==false) {
            if ($this->crtMode == 'write') {
                rewind($this->fp);
                Logger::debug(__CLASS__,__FUNCTION__,"Ftp_fput", array("target"=>$this->crtTarget));
                set_error_handler(array("\\Pydio\\Access\\Driver\\StreamProvider\\FTP\\FtpAccessWrapper", "fput_quota_hack"), E_ALL & ~E_NOTICE );
                ftp_fput($this->crtLink, $this->crtTarget, $this->fp, FTP_BINARY);
                restore_error_handler();
                Logger::debug(__CLASS__,__FUNCTION__,"Ftp_fput end", array("target"=>$this->crtTarget));
            } else {
                fflush($this->fp);
            }
        }
    }

    /**
     * @param string $url
     * @return bool
     */
    public function unlink($url)
    {
        return unlink($this->buildRealUrl($url));
    }

    /**
     * @param string $url
     * @param int $options
     * @return bool
     */
    public function rmdir($url, $options)
    {
        return rmdir($this->buildRealUrl($url));
    }

    /**
     * @param string $url
     * @param int $mode
     * @param int $options
     * @return bool
     */
    public function mkdir($url, $mode, $options)
    {
        return mkdir($this->buildRealUrl($url), $mode);
    }

    /**
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function rename($from, $to)
    {
        return rename($this->buildRealUrl($from), $this->buildRealUrl($to));
    }

    /**
     * @param string $path
     * @param int $flags
     * @return array|mixed|null
     * @throws PydioException
     * @throws \Exception
     */
    public function url_stat($path, $flags)
    {
        // We are in an opendir loop
        Logger::debug(__CLASS__,__FUNCTION__,"URL_STAT", $path);
        $node = new AJXP_Node($path);
        $testLoopPath = PathUtils::forwardSlashDirname($path);
        if (is_array(self::$dirContent[$testLoopPath])) {
            $search = PathUtils::forwardSlashBasename($path);
            //if($search == "") $search = ".";
            if (array_key_exists($search, self::$dirContent[$testLoopPath])) {
                return self::$dirContent[$testLoopPath][$search];
            }
        }
        $parts = $this->parseUrl($path);
        $link = $this->createFTPLink();
        $serverPath = InputFilter::securePath($this->path . "/" . $parts["path"]);
        if(empty($parts["path"])) $parts["path"] = "/";
        if ($parts["path"] == "/") {
            $basename = ".";
        } else {
            $basename = PathUtils::forwardSlashBasename($serverPath);
        }

        $serverParent = PathUtils::forwardSlashDirname($parts["path"]);
        $serverParent = InputFilter::securePath($this->path . "/" . $serverParent);

        $testCd = @ftp_chdir($link, $serverPath);
        if ($testCd === true) {
            // DIR
            $contents = $this->rawList($link, $serverParent, 'd');
            foreach ($contents as $entry) {
                $res = $this->rawListEntryToStat($entry);
                Logger::debug(__CLASS__,__FUNCTION__,"RAWLISTENTRY ".$res["name"]. " (searching ".$basename.")", $res["stat"]);
                if ($res["name"] == $basename) {
                    AbstractAccessDriver::fixPermissions($node, $res["stat"], array($this, "getRemoteUserId"));
                    $statValue = $res["stat"];
                    return $statValue;
                }
            }
            // Not found : is it the "."
            if($basename == "."){
                // Make at least a readable fake stat
                $fakeStat = stat(AJXP_DATA_PATH);
                return $fakeStat;
            }
        } else {
            // FILE
            $contents = $this->rawList($link, $serverPath, 'f');
            if (count($contents) == 1) {
                $res = $this->rawListEntryToStat($contents[0]);
                AbstractAccessDriver::fixPermissions($node, $res["stat"], array($this, "getRemoteUserId"));
                $statValue = $res["stat"];
                Logger::debug(__CLASS__,__FUNCTION__,"STAT FILE $serverPath", $statValue);
                return $statValue;
            }
        }
        return null;
    }

    /**
     * @param string $url
     * @param int $options
     * @return bool
     * @throws PydioException
     * @throws \Exception
     */
    public function dir_opendir ($url , $options )
    {
        if(isSet(self::$dirContent[$url])){
            array_push(self::$dirContentLoopPath, $url);
            return true;
        }
        $parts = $this->parseUrl($url);
        $link = $this->createFTPLink();
        $serverPath = InputFilter::securePath($this->path . "/" . $parts["path"]);
        $contents = $this->rawList($link, $serverPath);
        $folders = $files = array();
        foreach ($contents as $entry) {
            $result = $this->rawListEntryToStat($entry);
            AbstractAccessDriver::fixPermissions(new AJXP_Node($url), $result["stat"], array($this, "getRemoteUserId"));
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
        Logger::debug(__CLASS__, __FUNCTION__, "OPENDIR ", $folders);
        array_push(self::$dirContentLoopPath, $url);
        self::$dirContent[$url] = $folders;
        self::$dirContentKeys[$url] = array_keys($folders);
        self::$dirContentIndex[$url] = 0;
        return true;
    }

    /**
     *
     */
    public function dir_closedir  ()
    {
        Logger::debug(__CLASS__,__FUNCTION__,"CLOSEDIR");
        $loopPath = array_pop(self::$dirContentLoopPath);
        self::$dirContentIndex[$loopPath] = 0;
        //Make a simple rewind, keep in cache
        //self::$dirContent[$loopPath] = null;
        //self::$dirContentKeys[$loopPath] = null;
    }

    /**
     * @return bool
     */
    public function dir_readdir ()
    {
        $loopPath = self::$dirContentLoopPath[count(self::$dirContentLoopPath)-1];
        $index = self::$dirContentIndex[$loopPath];
        self::$dirContentIndex[$loopPath] ++;
        if (isSet(self::$dirContentKeys[$loopPath][$index])) {
            return self::$dirContentKeys[$loopPath][$index];
        } else {
            return false;
        }
    }

    /**
     *
     */
    public function dir_rewinddir ()
    {
        $loopPath = self::$dirContentLoopPath[count(self::$dirContentLoopPath)-1];
        self::$dirContentIndex[$loopPath] = 0;
    }

    /**
     * @param $link
     * @param $serverPath
     * @param string $target
     * @param bool $retry
     * @return array
     */
    protected function rawList($link, $serverPath, $target = 'd', $retry = true)
    {
        if ($target == 'f') {
            $parentDir = PathUtils::forwardSlashDirname($serverPath);
            $fileName = PathUtils::forwardSlashBasename($serverPath);
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

    /**
     * @param $entry
     * @param bool $filterStatPerms
     * @return array
     */
    protected function rawListEntryToStat($entry, $filterStatPerms = false)
    {
        $info = array();
        $monthes = array_flip( $this->monthes );
        $vinfo = preg_split("/[\s]+/", $entry);
        Logger::debug(__CLASS__,__FUNCTION__,"RAW LIST", $entry);
        $statValue = array();
        $fileperms = '';
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

    /**
     * @param $url
     * @param bool $forceLogin
     * @return array
     * @throws PydioException
     * @throws \Exception
     */
    protected function parseUrl($url, $forceLogin = false)
    {
        // URL MAY BE ajxp.ftp://username:password@host/path
        $urlParts = UrlUtils::safeParseUrl($url);
        $node = new AJXP_Node($url);
        $this->repositoryId = $node->getRepositoryId();
        $repository = $node->getRepository();
        if ($repository == null) {
            throw new \Exception("Cannot find repository for dynamic ftp authentication.");
        }
        $ctx = $node->getContext();
        $credentials = MemorySafe::tryLoadingCredentialsFromSources($node->getContext());
        $this->user = $credentials["user"];
        $this->password = $credentials["password"];
        if ($this->user=="") {
            if (isSet($urlParts["user"]) && isset($urlParts["pass"])) {
                $this->user       = rawurldecode($urlParts["user"]);
                $this->password   = rawurldecode($urlParts["pass"]);
            }else{
                throw new PydioException("Cannot find user/pass for FTP access!");
            }
        }
        if ($repository->getContextOption($node->getContext(), "DYNAMIC_FTP") == "TRUE" && isSet($_SESSION["AJXP_DYNAMIC_FTP_DATA"])) {
            $data               = $_SESSION["AJXP_DYNAMIC_FTP_DATA"];
            $this->host         = $data["FTP_HOST"];
            $this->path         = $data["PATH"];
            $this->secure       = ($data["FTP_SECURE"] == "TRUE"?true:false);
            $this->port         = ($data["FTP_PORT"]!=""?intval($data["FTP_PORT"]):($this->secure?22:21));
            $this->ftpActive    = ($data["FTP_DIRECT"] == "TRUE"?true:false);
            $this->repoCharset  = $data["CHARSET"];
        } else {
            $this->host         = $ctx->getRepository()->getContextOption($ctx, "FTP_HOST");
            $this->path         = $ctx->getRepository()->getContextOption($ctx, "PATH");
            $this->secure       = ($ctx->getRepository()->getContextOption($ctx, "FTP_SECURE") == "TRUE"?true:false);
            $this->port         = ($ctx->getRepository()->getContextOption($ctx, "FTP_PORT")!=null?intval($ctx->getRepository()->getContextOption($ctx, "FTP_PORT")):($this->secure?22:21));
            $this->ftpActive    = ($ctx->getRepository()->getContextOption($ctx, "FTP_DIRECT") == "TRUE"?true:false);
            $this->repoCharset  = $ctx->getRepository()->getContextOption($ctx, "CHARSET") OR "";
        }

        // Test Connexion and server features
        global $_SESSION;
        $cacheKey = $repository->getId()."_ftpCharset";
        if (!isset($_SESSION[$cacheKey]) || !strlen($_SESSION[$cacheKey]) || $forceLogin) {
            $features = $this->getServerFeatures($node->getContext());
            $ctxCharset = SessionService::getContextCharset($node->getRepositoryId());
            if(empty($ctxCharset)) {
                SessionService::setContextCharset($node->getRepositoryId(), $features["charset"]);
                $_SESSION[$cacheKey] = $features["charset"];
            }else{
                $_SESSION[$cacheKey] = $ctxCharset;
            }
        }
        return $urlParts;
    }

    /**
     * @param AJXP_Node $node
     * @return array
     */
    public static function getResolvedOptionsForNode($node){
        return ["TYPE" => "php"];
    }



    /**
     * @param AJXP_Node $node
     * @return array Array(UID, GID) to be used to compute permission
     */
    public function getRemoteUserId($node)
    {
        $repoUid = $node->getRepository()->getContextOption($node->getContext(), "UID");
        if (!empty($repoUid)) {
            return array($repoUid, "-1");
        }
        return array($this->user, "-1");
    }

    /**
     * @param $url
     * @return string
     * @throws PydioException
     * @throws \Exception
     */
    protected function buildRealUrl($url)
    {
        if (!isSet($this->user)) {
            $parts = $this->parseUrl($url);
        } else {
            // parseUrl already called before (rename case).
            $parts = UrlUtils::safeParseUrl($url);
        }
        $serverPath = InputFilter::securePath("/$this->path/" . $parts["path"]);
        if($this->secure){
            $protocol = 'ftps';
        }
        else{
            $protocol = 'ftp';
        }
        $url = $protocol.'://'.urlencode($this->user).':'.urlencode($this->password).'@'.$this->host.':'.$this->port.$serverPath;
        return $url;
    }

    /**
     * This method retrieves the FTP server features as described in RFC2389
     *    A decent FTP server support MLST command to list file using UTF-8 encoding
     * @param ContextInterface $ctx
     * @return array of features (see code)
     * @throws PydioException
     * @throws \Exception
     */
    protected function getServerFeatures(ContextInterface $ctx)
    {
        $link = $this->createFTPLink();

        if ($ctx->getRepository()->getContextOption($ctx, "CREATE") == true) {
            // Test if root exists and create it otherwise
            $serverPath = InputFilter::securePath($this->path . "/");
            $testCd = @ftp_chdir($link, $serverPath);
            if ($testCd !== true) {
                $res = @ftp_mkdir($link, $serverPath);
                if(!$res) throw new \Exception("Cannot create path on remote server!");
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

    /**
     * @return bool|resource
     * @throws PydioException
     */
    protected function createFTPLink()
    {
        // If connexion exist and is still connected
        if(is_array($_SESSION["FTP_CONNEXIONS"])
            && array_key_exists($this->repositoryId, $_SESSION["FTP_CONNEXIONS"])
            && @ftp_systype($_SESSION["FTP_CONNEXIONS"][$this->repositoryId])){
                Logger::debug(__CLASS__,__FUNCTION__,"Using stored FTP Session");
                return $_SESSION["FTP_CONNEXIONS"][$this->repositoryId];
            }
        Logger::debug(__CLASS__,__FUNCTION__,"Creating new FTP Session");
        $link = FALSE;
           //Connects to the FTP.
           if ($this->secure) {
               $link = @ftp_ssl_connect($this->host, $this->port);
           } else {
            $link = @ftp_connect($this->host, $this->port);
           }
        if (!$link) {
            throw new PydioException("Cannot connect to FTP server ($this->host, $this->port)");
         }
        //register_shutdown_function('ftp_close', $link);
        @ftp_set_option($link, FTP_TIMEOUT_SEC, 10);
        if (!@ftp_login($link,$this->user,$this->password)) {
            throw new WorkspaceAuthRequired($this->repositoryId, "Cannot login to FTP server with user $this->user");
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

    /**
     * @param $permissions
     * @param bool $filterForStat
     * @return int
     */
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
