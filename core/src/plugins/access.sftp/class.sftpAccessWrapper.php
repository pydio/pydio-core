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


require_once(AJXP_INSTALL_PATH."/plugins/access.fs/class.fsAccessWrapper.php");

/**
 * Callback for ssh2_connect in case of disconnexion.
 *
 * @param integer $code
 * @param String $message
 * @param String $language
 */
function disconnectedSftp($code, $message, $language)
{
    AJXP_Logger::info(__CLASS__,"SSH2.FTP.disconnected",$message);
    throw new Exception('SSH2.FTP : disconnected'.$message, $code);
}

function ignoreSftp($message)
{
    AJXP_Logger::info(__CLASS__,"SSH2.FTP.ignore",$message);
    throw new Exception('SSH2.FTP : ignore'.$message);
}

function debugSftp($message, $language, $always_display)
{
    AJXP_Logger::info(__CLASS__,"SSH2.FTP.debug",$message);
    throw new Exception('SSH2.FTP : debug'.$message);
}

function macerrorSftp($packet)
{
    AJXP_Logger::info(__CLASS__,"SSH2.FTP.macerror","");
    throw new Exception('SSH2.FTP : macerror'.$packet);
}



/**
 * AJXP_Plugin to access an ftp server over SSH
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class sftpAccessWrapper extends fsAccessWrapper
{
    private static $sftpResource;
    private static $resourceRepoId;

    /**
     * Initialize the stream from the given path.
     * Concretely, transform ajxp.webdav:// into webdav://
     *
     * @param string $path
     * @param string $streamType
     * @param bool $sftpResource
     * @param bool $skipZip
     * @throws Exception
     * @return mixed Real path or -1 if currentListing contains the listing : original path converted to real path
     */
    protected static function initPath($path, $streamType="", $sftpResource = false, $skipZip = false)
    {
        $url = AJXP_Utils::safeParseUrl($path);
        $repoId = $url["host"];
        $repoObject = ConfService::getRepositoryById($repoId);
        if(!isSet($repoObject)) throw new Exception("Cannot find repository with id ".$repoId);
        $path = $url["path"];
        // MAKE SURE THERE ARE NO // OR PROBLEMS LIKE THAT...
        $basePath = $repoObject->getOption("PATH");
        if ($basePath[strlen($basePath)-1] == "/") {
            $basePath = substr($basePath, 0, -1);
        }
        if ($basePath[0] != "/") {
            $basePath = "/$basePath";
        }
        $path = AJXP_Utils::securePath($path);
        if ($path[0] == "/") {
            $path = substr($path, 1);
        }
        // SHOULD RETURN ssh2.sftp://Resource #23/server/path/folder/path
        return  "ssh2.sftp://".self::getSftpResource($repoObject).$basePath."/".$path;
    }

    /**
     * Get ssh2 connection
     *
     * @param Repository $repoObject
     * @return Resource
     */
    protected static function getSftpResource($repoObject)
    {
        if (isSet(self::$sftpResource) && self::$resourceRepoId == $repoObject->getId()) {
            return self::$sftpResource;
        }
        $callbacks = array('disconnect' => "disconnectedSftp",
                            'ignore' 	=> "ignoreSftp",
                            'debug' 	=> "debugSftp",
                            'macerror'	=> "macerrorSftp");
        $remote_serv = $repoObject->getOption("SERV");
        $remote_port = $repoObject->getOption("PORT");
        $credentials = AJXP_Safe::tryLoadingCredentialsFromSources(array(), $repoObject);
        $remote_user = $credentials["user"];
        $remote_pass = $credentials["password"];

        $connection = ssh2_connect($remote_serv, intval($remote_port), array(), $callbacks);
        ssh2_auth_password($connection, $remote_user, $remote_pass);
        self::$sftpResource = ssh2_sftp($connection);
        self::$resourceRepoId = $repoObject->getId();
        return self::$sftpResource;
    }

    /**
     * Opens the stream
     * Diff with parent class : do not "securePath", as it removes double slash
     *
     * @param String $path Maybe in the form "ajxp.fs://repositoryId/pathToFile"
     * @param String $mode
     * @param array $options
     * @param array $context
     * @return unknown
     */
    public function stream_open($path, $mode, $options, &$context)
    {
        try {
            $this->realPath = $this->initPath($path);
        } catch (Exception $e) {
            AJXP_Logger::error(__CLASS__,"stream_open", "Error while opening stream $path");
            return false;
        }
        $this->fp = fopen($this->realPath, $mode, $options);
        return ($this->fp !== false);
    }

    /**
     * Stats the given path.
     *
     * @param string $path
     * @param mixed $flags
     * @return array
     */
    public function url_stat($path, $flags)
    {
        $realPath = self::initPath($path);
        $stat = @stat($realPath);
        $parts = AJXP_Utils::safeParseUrl($path);
        $repoObject = ConfService::getRepositoryById($parts["host"]);

        AbstractAccessDriver::fixPermissions($stat, $repoObject, array($this, "detectRemoteUserId"));

        return $stat;
    }

    public function detectRemoteUserId($repository)
    {
        list($connection, $remote_base_path) = self::getSshConnection("/", $repository);
        $stream = ssh2_exec($connection, "id");
        if ($stream !== false) {
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            fclose($stream);

            if (trim($output != "")) {
                $res = sscanf($output, "uid=%i(%s) gid=%i(%s) groups=%i(%s)");
                preg_match_all("/(\w*)=(\w*)\((\w*)\)/", $output, $matches);
                if (count($matches[0]) == 3) {
                    $uid = $matches[2][0];
                    $gid = $matches[2][1];
                    /*
                    $groups = $matches[2][2];
                    $uName = $matches[3][0];
                    $gName = $matches[3][1];
                    $groupsName = $matches[3][2];
                    */
                    return array($uid, $gid);
                }
            }
        }
        return array(null,null);
    }

    /**
     * Opens a handle to the dir
     * Fix PEAR by being sure it ends up with "/", to avoid
     * adding the current dir to the children list.
     *
     * @param String $path
     * @param array $options
     * @return resource
     */
    public function dir_opendir ($path , $options )
    {
        $this->realPath = $this->initPath($path, true);
        $this->dH = @opendir($this->realPath);
        return $this->dH !== false;
    }


    // DUPBLICATE STATIC FUNCTIONS TO BE SURE
    // NOT TO MESS WITH self:: CALLS
    /**
     * Remove a temporary file
     *
     * @param String $tmpDir
     * @param String $tmpFile
     */
    public static function removeTmpFile($tmpDir, $tmpFile)
    {
        if(is_file($tmpFile)) unlink($tmpFile);
        if(is_dir($tmpDir)) rmdir($tmpDir);
    }

    /**
     * Implementation of AjxpStream
     *
     * @param String $path
     * @return string
     */
    public static function getRealFSReference($path, $persistent = false)
    {
        if ($persistent) {
            $tmpFile = AJXP_Utils::getAjxpTmpDir()."/".md5(time());
            $tmpHandle = fopen($tmpFile, "wb");
            self::copyFileInStream($path, $tmpHandle);
            fclose($tmpHandle);
            return $tmpFile;
        } else {
            return self::initPath($path);
        }
    }

    public static function isRemote()
    {
        return true;
    }

    /**
     * Override parent function, testing feof() does not seem to work.
     * We may have performance problems on big files here.
     *
     * @param String $path
     * @param Stream $stream
     */
    public static function copyFileInStream($path, $stream)
    {
        $src = fopen(self::initPath($path), "rb");
        while ($content = fread($src, 5120)) {
            fputs($stream, $content, strlen($content));
            if(strlen($content) == 0) break;
        }
        fclose($src);
    }


    public function unlink($path)
    {
        // Male sur to return true on success.
        $this->realPath = $this->initPath($path, "file", false, true);
        @unlink($this->realPath);
        if (is_file($this->realPath)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Specific case for chmod : not supported natively by ssh2.sftp protocole
     * we have to recreate an ssh2 connexion.
     *
     * @param string $path
     * @param long $chmodValue
     */
    public static function changeMode($path, $chmodValue)
    {
        $url = AJXP_Utils::safeParseUrl($path);
        list($connection, $remote_base_path) = self::getSshConnection($path);
        //var_dump('chmod '.decoct($chmodValue).' '.$remote_base_path.$url['path']);
        ssh2_exec($connection,'chmod '.decoct($chmodValue).' '.$remote_base_path.$url['path']);
    }

    public static function getSshConnection($path, $repoObject = null)
    {
        if ($repoObject != null) {
            $url = array();
        } else {
            $url = AJXP_Utils::safeParseUrl($path);
            $repoId = $url["host"];
            $repoObject = ConfService::getRepositoryById($repoId);
        }
        $remote_serv = $repoObject->getOption("SERV");
        $remote_port = $repoObject->getOption("PORT");
        $credentials = AJXP_Safe::tryLoadingCredentialsFromSources($url, $repoObject);
        $remote_user = $credentials["user"];
        $remote_pass = $credentials["password"];
        $remote_base_path = $repoObject->getOption("PATH");

        $callbacks = array('disconnect' => "disconnectedSftp",
                            'ignore' 	=> "ignoreSftp",
                            'debug' 	=> "debugSftp",
                            'macerror'	=> "macerrorSftp");
        $connection = ssh2_connect($remote_serv, intval($remote_port), array(), $callbacks);
        ssh2_auth_password($connection, $remote_user, $remote_pass);
        return array($connection, $remote_base_path);
    }

}
