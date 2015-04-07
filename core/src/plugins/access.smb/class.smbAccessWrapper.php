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
 * AJXP_Wrapper encapsulating calls to the smbclient command line tool
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class smbAccessWrapper extends fsAccessWrapper
{
    /**
     * Initialize the stream from the given path.
     * Concretely, transform ajxp.smb:// into smb://
     *
     * @param string $path
     * @return mixed Real path or -1 if currentListing contains the listing : original path converted to real path
     */
    protected static function initPath($path, $streamType, $storeOpenContext = false, $skipZip = false)
    {
        $url = parse_url($path);
        $repoId = $url["host"];
        $repoObject = ConfService::getRepositoryById($repoId);
        if(!isSet($repoObject)) throw new Exception("Cannot find repository with id ".$repoId);
        $path = $url["path"];
        // Fix if the host is defined as //MY_HOST/path/to/folder
        $hostOption = AuthService::getFilteredRepositoryOption("access.smb", $repoObject, "HOST");
        $host = str_replace("//", "", $hostOption);
        $credentials = "";
        $safeCreds = AJXP_Safe::tryLoadingCredentialsFromSources($url, $repoObject);
        if ($safeCreds["user"] != "" && $safeCreds["password"] != "") {
            $login = $safeCreds["user"];
            $pass = $safeCreds["password"];
            $_SESSION["AJXP_SESSION_REMOTE_PASS"] = $pass;
            $credentials = "$login:$pass@";
            $domain = $repoObject->getOption("DOMAIN");
            if($domain != "") {
                if((strcmp(substr($domain, -1), "/") === 0) || (strcmp(substr($domain, -1), "\\") === 0)){
                    $credentials = $domain.$credentials;
                }else{
                    $credentials = $domain."/".$credentials;
                }
            }
        }
        $basePath = $repoObject->getOption("PATH");
        $fullPath = "smb://".$credentials.$host."/";//.$basePath."/".$path;
        if ($basePath!="") {
           $fullPath.=trim($basePath, "/\\" );
           }
           if ($path!="") {
           $fullPath.= (($path[0] == "/")? "" : "/").$path;
           }

        return $fullPath;
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
            $this->realPath = $this->initPath($path, "file");
        } catch (Exception $e) {
            AJXP_Logger::error(__CLASS__,"stream_open", "Error while opening stream $path");
            return false;
        }
        if ($this->realPath == -1) {
            $this->fp = -1;
            return true;
        } else {
            $this->fp = fopen($this->realPath, $mode, $options);
            //AJXP_Logger::debug(__CLASS__,__FUNCTION__,"I opened an smb stream.");
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
        $this->realPath = $this->initPath($path, "dir", true);
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


    // DUPBLICATE STATIC FUNCTIONS TO BE SURE
    // NOT TO MESS WITH self:: CALLS

    public static function removeTmpFile($tmpDir, $tmpFile)
    {
        if(is_file($tmpFile)) unlink($tmpFile);
        if(is_dir($tmpDir)) rmdir($tmpDir);
    }

    protected static function closeWrapper()
    {
        if (self::$crtZip != null) {
            self::$crtZip = null;
            self::$currentListing  = null;
            self::$currentListingKeys = null;
            self::$currentListingIndex = null;
            self::$currentFileKey = null;
        }
    }

    public static function getRealFSReference($path, $persistent = false)
    {
        if ($persistent) {
            $tmpFile = AJXP_Utils::getAjxpTmpDir()."/".md5(time());
            $tmpHandle = fopen($tmpFile, "wb");
            self::copyFileInStream($path, $tmpHandle);
            fclose($tmpHandle);
            return $tmpFile;
        } else {
            $realPath = self::initPath($path, "file");
            return $realPath;
        }
    }

    public static function isRemote()
    {
        return true;
    }

    public static function copyFileInStream($path, $stream)
    {
        $fp = fopen(self::getRealFSReference($path), "rb");
        if(!is_resource($fp)) return;
        while (!feof($fp)) {
            $data = fread($fp, 4096);
            fwrite($stream, $data, strlen($data));
        }
        fclose($fp);
    }

    public static function changeMode($path, $chmodValue)
    {
        //$realPath = self::initPath($path, "file");
        //chmod($realPath, $chmodValue);
    }
}
