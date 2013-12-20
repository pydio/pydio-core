<?php
/*
 * Copyright 2013 Nikita ROUSSEAU <warhawk3407@gmail.com>
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
defined('AJXP_EXEC') or die( 'Access not allowed' );

require_once(AJXP_INSTALL_PATH."/plugins/access.fs/class.fsAccessWrapper.php");
require_once(AJXP_INSTALL_PATH."/plugins/access.sftp_psl/phpseclib/SSH2.php");

/**
 * AJXP_Plugin to access a remote server using SSH File Transfer Protocol (SFTP) with phpseclib ( http://phpseclib.sourceforge.net/ )
 *
 * @author	warhawk3407 <warhawk3407@gmail.com>
 * @author	Charles du Jeu <contact (at) cdujeu.me>
 * @version	Release: 1.0.2
 */
class sftpPSLAccessWrapper extends fsAccessWrapper
{

    public static function isRemote()
    {
        return true;
    }

    /**
     * Initialize the stream from the given path.
     */
    protected static function initPath($path, $streamType = '', $storeOpenContext = false, $skipZip = true)
    {
        $url = AJXP_Utils::safeParseUrl($path);
        $repoId = $url["host"];
        $path = $url["path"];

        $repoObject = ConfService::getRepositoryById($repoId);

        if(!isSet($repoObject)) throw new Exception("Cannot find repository with id ".$repoId);

        $basePath = $repoObject->getOption("PATH");
        $host = $repoObject->getOption("SFTP_HOST");
        $port = $repoObject->getOption("SFTP_PORT");

        $credentials = AJXP_Safe::tryLoadingCredentialsFromSources($url, $repoObject);
        $user = $credentials["user"];
        $pass = $credentials["password"];

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

        return  "sftp://".$user.':'.$pass.'@'.$host.':'.$port.$basePath."/".$path; // http://username:password@hostname:port/path/file.ext
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

    /**
     * Opens the stream
     * Diff with parent class : do not "securePath", as it removes double slash
     *
     * @param String $path Maybe in the form "ajxp.fs://repositoryId/pathToFile"
     * @param String $mode
     * @param unknown_type $options
     * @param unknown_type $opened_path
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
        if ($this->realPath == -1) {
            $this->fp = -1;
            return true;
        } else {
            $this->fp = fopen($this->realPath, $mode, $options);
            return ($this->fp !== false);
        }
    }

    /**
     * Opens a handle to the dir
     * Fix PEAR by being sure it ends up with "/", to avoid
     * adding the current dir to the children list.
     *
     * @param unknown_type $path
     * @param unknown_type $options
     * @return unknown
     */
    public function dir_opendir ($path , $options )
    {
        $this->realPath = $this->initPath($path);
        if ($this->realPath[strlen($this->realPath)-1] != "/") {
            $this->realPath.="/";
        }
        if (is_string($this->realPath)) {
            $this->dH = opendir($this->realPath);
        } else if ($this->realPath == -1) {
            $this->dH = -1;
        }
        return $this->dH !== false;
    }

    public function unlink($path)
    {
        $this->realPath = $this->initPath($path, "file", false, true);
        @unlink($this->realPath);
        if (is_file($this->realPath)) {
            return false;
        } else {
            return true;
        }
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

    public function detectRemoteUserId($repoObject)
    {
        $host = $repoObject->getOption("SFTP_HOST");
        $port = $repoObject->getOption("SFTP_PORT");

        $credentials = AJXP_Safe::tryLoadingCredentialsFromSources(NULL, $repoObject);
        $user = $credentials["user"];
        $pass = $credentials["password"];

        $ssh2 = new Net_SSH2($host, $port);
        if ($ssh2->login($user, $pass)) {
            $output = $ssh2->exec( 'id' );

            $ssh2->disconnect();

            if (trim($output != "")) {
                $res = sscanf($output, "uid=%i(%s) gid=%i(%s) groups=%i(%s)");
                preg_match_all("/(\w*)=(\w*)\((\w*)\)/", $output, $matches);
                if (count($matches[0]) == 3) {
                    $uid = $matches[2][0];
                    $gid = $matches[2][1];

                    return array($uid, $gid);
                }
            }
        }
        unset($ssh2);

        return array(null,null);
    }

    /**
     * Override parent function.
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

}
