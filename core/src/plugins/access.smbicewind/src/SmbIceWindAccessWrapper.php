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

namespace Pydio\Access\Driver\StreamProvider\SmbIceWind;

use Pydio\Access\Driver\StreamProvider\FS\fsAccessWrapper;
use Icewind\SMB\NativeServer;
use Icewind\SMB\Server;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Auth\Core\CoreAuthLoader;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Services\CacheService;
use Pydio\Log\Core\Logger;
use Pydio\Meta\Core;
use Pydio\Core\Utils\ApplicationState;
use Icewind\SMB\Exception\Exception;

/**
 * Class smbIcewindAccessWrapper
 * @package Pydio\Access\Driver\StreamProvider\SmbIceWind
 */
class smbIcewindAccessWrapper extends fsAccessWrapper{
    public $dir = array ();
    public $dir_index = -1;
    private $Stream = array();
    private $need_flush = false;
    private $lastStream;
    private $share = null;

    /**
     * @param string $path
     * @param $streamType
     * @param bool|false $storeOpenContext
     * @param bool|false $skipZip
     * @return string
     * @throws \Exception
     */
    protected static function initPath($path, $streamType, $storeOpenContext = false, $skipZip = false)
    {
        $node = new AJXP_Node($path);
        $repoObject = $node->getRepository();
        if(!isSet($repoObject)) {
            throw new \Exception("Cannot find repository with id ".$node->getRepositoryId());
        }
        $path = $node->getPath();

        // Fix if the host is defined as //MY_HOST/path/to/folder
        $user = $node->getUser();
        if($user != null){
            $hostOption = $user->getMergedRole()->filterParameterValue("access.smbicewind", "HOST", $node->getRepositoryId(), null);
            if(!empty($hostOption)) $hostOption = VarsFilter::filter($hostOption, $node->getContext());
        }
        if(empty($hostOption)) {
            $hostOption = $repoObject->getContextOption($node->getContext(), "HOST");
        }

        if(empty($path)) $path = '/';

        $host = str_replace("//", "", $hostOption);
        $credentials = "";
        $safeCreds = MemorySafe::tryLoadingCredentialsFromSources($node->getContext());

        if ($safeCreds["user"] != "" && $safeCreds["password"] != "") {
            $login = $safeCreds["user"];
            $pass = $safeCreds["password"];
            $_SESSION["AJXP_SESSION_REMOTE_PASS"] = $pass;
            $credentials = "$login:$pass@";
            $domain = $repoObject->getContextOption($node->getContext(), "DOMAIN");
            if($domain != "") {
                if((strcmp(substr($domain, -1), "/") === 0) || (strcmp(substr($domain, -1), "\\") === 0)){
                    $credentials = $domain.$credentials;
                }else{
                    $credentials = $domain."/".$credentials;
                }
            }
        }
       // $pass = Utils::decypherStandardFormPassword($node->getContext()->getUser()->getId(),$pass);
        $basePath = $repoObject->getContextOption($node->getContext(),"PATH");
        $fullPath = "smb://".$host."/";//.$basePath."/".$path;
        if ($basePath!="") {
            $fullPath.=trim($basePath, "/\\" );
        }
        if ($path!="") {
            $fullPath.= (($path[0] == "/")? "" : "/").$path;
        }

        return $path;
    }

    /**
     * @param $path
     * @return array
     * @throws \Exception
     */
    public function getShareInfo($path){

        $node = new AJXP_Node($path);
        $repoObject = $node->getRepository();
        if(!isSet($repoObject)) {
            throw new \Exception("Cannot find repository with id ".$node->getRepositoryId());
        }
        $path = $node->getPath();

        // Fix if the host is defined as //MY_HOST/path/to/folder
        $user = $node->getUser();
        if($user != null){
            $hostOption = $user->getMergedRole()->filterParameterValue("access.smbicewind", "HOST", $node->getRepositoryId(), null);
            if(!empty($hostOption)) $hostOption = VarsFilter::filter($hostOption, $node->getContext());
        }
        if(empty($hostOption)) {
            $hostOption = $repoObject->getContextOption($node->getContext(), "HOST");
        }

        if(empty($path)) $path = '/';

        $host = str_replace("//", "", $hostOption);
        $credentials = "";
        $safeCreds = MemorySafe::tryLoadingCredentialsFromSources($node->getContext());

        if ($safeCreds["user"] != "" && $safeCreds["password"] != "") {
            $login = $safeCreds["user"];
            $pass = $safeCreds["password"];
            $domain = $repoObject->getContextOption($node->getContext(), "DOMAIN");
            $FQDNUsername = $login;
            if($domain != ""){
                if((strcmp(substr($domain, -1), "/") === 0) || (strcmp(substr($domain, -1), "\\") === 0)){
                    $FQDNUsername = $domain.$login;
                }else{
                    $FQDNUsername = $domain."/".$login;
                }
            }
        }
        $basePath = $repoObject->getContextOption($node->getContext(),"PATH");
        $fullPath = "smb://".$host."/";//.$basePath."/".$path;
        if ($basePath!="") {
            $fullPath.=trim($basePath, "/\\" );
        }
        if ($path!="") {
            $fullPath.= (($path[0] == "/")? "" : "/").$path;
        }

        if (Server::NativeAvailable()) {
            $server = new NativeServer($host, $FQDNUsername, $pass);
        } else {
            $server = new Server($host, $FQDNUsername, $pass);
        }

        $ret = array();
        $ret["server"] = $server;
        $ret["fullPath"] = $path;
        $ret["host"] = $host;
        $ret["share"] = trim($basePath, '/');


        return $ret;
    }

    /**
     * @param AJXP_Node $node
     * @return array
     * @throws \Exception
     */
    public static function getResolvedOptionsForNode($node){
        return [
            "TYPE"      => "fs",
            "PATH"      => $node->getRepository()->getContextOption($node->getContext(), "PATH"),
            "CHARSET"   => TextEncoder::getEncoding()
        ];
    }

    /**
     * @param $dirPath
     * @return mixed
     */
    public static function patchPathForBaseDir($dirPath)
    {
        return parent::patchPathForBaseDir($dirPath); // TODO: Change the autogenerated stub
    }

    /**
     * @param $dirPath
     * @return mixed
     */
    public static function unPatchPathForBaseDir($dirPath)
    {
        return parent::unPatchPathForBaseDir($dirPath); // TODO: Change the autogenerated stub
    }

    public static function removeTmpFile($tmpDir, $tmpFile)
    {
        parent::removeTmpFile($tmpDir, $tmpFile); // TODO: Change the autogenerated stub
    }

    protected static function closeWrapper()
    {
        parent::closeWrapper(); // TODO: Change the autogenerated stub
    }

    public static function getRealFSReference($path, $persistent = false)
    {
        return $path;
    }

    public static function isRemote()
    {
        return false;
    }

    public static function isSeekable($url)
    {
        return true;
    }

    /**
     * @param string $path
     * @param resource $stream
     */
    public static function copyFileInStream($path, $stream)
    {
        $fp = fopen(self::getRealFSReference($path), "rb");
        if(!is_resource($fp)) return;
        while (!feof($fp)) {
	    if(!ini_get("safe_mode")) @set_time_limit(3600);
            $data = fread($fp, 4096);
            fwrite($stream, $data, strlen($data));
        }
        fclose($fp);
    }

    public static function changeMode($path, $chmodValue)
    {
        //self::initPath($path, )
    }

    /**
     * @param String $path
     * @param String $mode
     * @param string $options
     * @param resource $context
     * @return bool
     * @throws \Exception
     */
    public function stream_open($path, $mode, $options, &$context)
    {
        $shareInfo = $this->getShareInfo($path);
        $share = $this->getShare($shareInfo);

        try {
            switch ($mode) {
                case 'rb':
                case 'r':
                    $this->lastStream = md5($shareInfo["host"] . $shareInfo["share"]);
                    $this->Stream[$this->lastStream] = $share->read($shareInfo["fullPath"]);
                    Logger::debug(__CLASS__, __FUNCTION__, "Open stream for reading: ".$shareInfo["host"] . '/' . $shareInfo["share"]);
                    break;
		        case 'r+':
		        case 'rb+':
                case 'w':
                case 'a':
                case 'ab':
		        case 'ab+':
                case 'a+':
                case 'w+':
                case 'wb':
                    $this->lastStream = md5($shareInfo["host"] . $shareInfo["share"]);
                    $this->Stream[$this->lastStream] = $share->write($shareInfo["fullPath"]);
                    Logger::debug(__CLASS__, __FUNCTION__, "Open stream for reading: ".$shareInfo["host"] . '/' . $shareInfo["share"]);
                    break;
                default:
                    break;
            }
        }
        catch (Exception $e){
            Logger::error(__CLASS__, __FUNCTION__, "Open stream failed: ".$path);
		    return false;
	    }
        return TRUE;
    }

    /**
     * @param int $offset
     * @param int $whence
     * @return int
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return fseek($this->Stream[$this->lastStream], $offset, $whence);
    }

    public function stream_tell()
    {
        return ftell($this->Stream[$this->lastStream]);
    }

    public function stream_stat()
    {
        return fstat($this->Stream[$this->lastStream]);
    }

    /**
     * @param string $path
     * @param int $flags
     * @return bool
     * @throws \Exception
     */
    public function url_stat($path, $flags)
    {
        $shareInfo = $this->getShareInfo($path);
        $share = $this->getShare($shareInfo);
        try {
            if ($shareInfo["fullPath"] === '/')
                return stat(ApplicationState::getAjxpTmpDir());

            $stat = $share->getStat($shareInfo["fullPath"]);
            return $stat;
        }
        catch(Exception $e){
            Logger::error(__CLASS__, __FUNCTION__, "url_stat failed: ". $path);
		    return false;
        }
        return false;
    }

    /**
     * @param string $from
     * @param string $to
     * @return boolean
     * @throws \Exception
     */
    public function rename($from, $to)
    {
        $shareInfo = $this->getShareInfo($from);
        $share = $this->getShare($shareInfo);
        $source = $shareInfo["fullPath"];
        $shareInfo = $this->getShareInfo($to);
        //$share = $shareInfo["server"]->getShare($shareInfo["share"]);
        $dest   = $shareInfo["fullPath"];
        return $share->rename($source, $dest);
    }

    /**
     * @param int $count
     * @return string
     */
    public function stream_read($count)
    {
        return fread($this->Stream[$this->lastStream], $count);
    }

    /**
     * @param string $data
     * @return int
     */
    public function stream_write($data)
    {
        $this->need_flush = TRUE;
        return fwrite($this->Stream[$this->lastStream], $data);
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return feof($this->Stream[$this->lastStream]);
    }

    /**
     * @return bool
     */
    public function stream_close()
    {
        if($this->Stream[$this->lastStream]){
            return fclose($this->Stream[$this->lastStream]);
        }
        else{
            return true;
        }
    }

    /**
     * @return bool
     */
    public function stream_flush()
    {
        return fflush($this->Stream[$this->lastStream]);
    }

    /**
     * @param string $path
     * @return mixed
     * @throws \Exception
     */
    public function unlink($path)
    {
        $shareInfo = $this->getShareInfo($path);
        $share = $this->getShare($shareInfo);
        return $share->del($shareInfo["fullPath"]);
    }

    /**
     * @param string $path
     * @param int $options
     * @return mixed
     * @throws \Exception
     */
    public function rmdir($path, $options)
    {
        $shareInfo = $this->getShareInfo($path);
        $share = $this->getShare($shareInfo);
        return $share->rmdir($shareInfo["fullPath"]);
    }

    public function mkdir($path, $mode, $options)
    {
        $shareInfo = $this->getShareInfo($path);
        $share = $this->getShare($shareInfo);
        return $share->mkdir($shareInfo["fullPath"]);
    }

    /**
     * @param string $path
     * @param int $options
     * @return bool
     * @throws \Exception
     */
    public function dir_opendir($path, $options)
    {
        $this->dir = array();
        $shareInfo = $this->getShareInfo($path);
        $share = $this->getShare($shareInfo);

        if($shareInfo["fullPath"] === '/'){
            $directories = $shareInfo["server"]->listShares();
        }
        else {
            $directories = $share->dir($shareInfo["fullPath"]);
        }
        foreach($directories as $item){
            $this->dir[] = $item->getName();
        }
	    $this->dir_index = 0;

        return TRUE;
    }

    /**
     * @return bool
     */
    public function dir_closedir()
    {
        $this->dir = array();
        $this->dir_index = -1;
        return TRUE;
    }

    /**
     * @return bool
     */
    public function dir_readdir()
    {
        return ($this->dir_index < count($this->dir)) ? $this->dir[$this->dir_index++] : FALSE;
    }

    /**
     *
     */
    public function dir_rewinddir()
    {
        $this->dir_index = 0;
    }

    public function getShare($shareInfo){
        $share = null;
        if(true || empty(CacheService::fetch(AJXP_CACHE_SERVICE_NS_NODES, $shareInfo["host"].$shareInfo["share"]))) {
            CacheService::save(AJXP_CACHE_SERVICE_NS_NODES, $shareInfo["host"].$shareInfo["share"], $shareInfo["server"]->getShare($shareInfo["share"]));
            $share = $shareInfo["server"]->getShare($shareInfo["share"]);
        }
        else{
            $share = CacheService::fetch(AJXP_CACHE_SERVICE_NS_NODES, $shareInfo["host"].$shareInfo["share"]);
        }
        return $share;
    }
}
