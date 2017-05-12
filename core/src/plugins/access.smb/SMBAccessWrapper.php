<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
namespace Pydio\Access\Driver\StreamProvider\SMB;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessWrapper;
use Pydio\Auth\Core\MemorySafe;


use Pydio\Core\Exception\WorkspaceAuthRequired;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\VarsFilter;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(AJXP_INSTALL_PATH . "/plugins/access.fs/FsAccessWrapper.php");

/**
 * AJXP_Wrapper encapsulating calls to the smbclient command line tool
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class SMBAccessWrapper extends FsAccessWrapper
{
    /**
     * Initialize the stream from the given path.
     * Concretely, transform ajxp.smb:// into smb://
     *
     * @param string $path
     * @param $streamType
     * @param bool $storeOpenContext
     * @param bool $skipZip
     * @return mixed Real path or -1 if currentListing contains the listing : original path converted to real path
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
            $hostOption = $user->getMergedRole()->filterParameterValue("access.smb", "HOST", $node->getRepositoryId(), null);
            if(!empty($hostOption)) $hostOption = VarsFilter::filter($hostOption, $node->getContext());
        }
        if(empty($hostOption)) {
            $hostOption = $repoObject->getContextOption($node->getContext(), "HOST");
        }
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
        }else if($repoObject->getContextOption($node->getContext(), "ANONYMOUS_FORBIDDEN") === true){
            throw new WorkspaceAuthRequired($repoObject->getId());
        }
        $basePath = $repoObject->getContextOption($node->getContext(), "PATH");
        $fullPath = "smbclient://".$credentials.$host."/";//.$basePath."/".$path;
        if ($basePath!="") {
           $fullPath.=trim($basePath, "/\\" );
           }
           if ($path!="") {
           $fullPath.= (($path[0] == "/")? "" : "/").$path;
           }

        return $fullPath;
    }


    /**
     * @param AJXP_Node $node
     * @return array
     * @throws \Exception
     */
    public static function getResolvedOptionsForNode($node)
    {
        $options = [
            "TYPE" => "smb"
        ];
        $repoObject = $node->getRepository();
        $context = $node->getContext();
        $credentials = MemorySafe::tryLoadingCredentialsFromSources($context);
        $options["USER"] = $credentials["user"];
        $options["PASSWORD"] = $credentials["password"];
        // Fix if the host is defined as //MY_HOST/path/to/folder
        $user = $node->getUser();
        if($user != null){
            $hostOption = $user->getMergedRole()->filterParameterValue("access.smb", "HOST", $node->getRepositoryId(), null);
            if(!empty($hostOption)) $hostOption = VarsFilter::filter($hostOption, $node->getContext());
        }
        if(empty($hostOption)) {
            $hostOption = $repoObject->getContextOption($node->getContext(), "HOST");
        }
        $options["HOST"]  = $hostOption;
        $options["DOMAIN"]  = $repoObject->getContextOption($context, "DOMAIN");
        $options["PATH"]  = $repoObject->getContextOption($context, "PATH");

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function stream_open($path, $mode, $options, &$context)
    {
        try {
            $this->realPath = $this->initPath($path, "file");
        } catch (\Exception $e) {
            Logger::error(__CLASS__,"stream_open", "Error while opening stream $path");
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
     * @param string $path
     * @param mixed $options
     * @return resource
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
    /**
     * @param string $tmpDir
     * @param string $tmpFile
     */
    public static function removeTmpFile($tmpDir, $tmpFile)
    {
        if(is_file($tmpFile)) unlink($tmpFile);
        if(is_dir($tmpDir)) rmdir($tmpDir);
    }

    /**
     * Close wrapper
     */
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

    /**
     * @param string $path
     * @param bool $persistent
     * @return mixed|string
     * @throws \Exception
     */
    public static function getRealFSReference($path, $persistent = false)
    {
        if ($persistent) {
            $tmpFile = ApplicationState::getTemporaryFolder() ."/".md5(time());
            $tmpHandle = fopen($tmpFile, "wb");
            self::copyFileInStream($path, $tmpHandle);
            fclose($tmpHandle);
            return $tmpFile;
        } else {
            $realPath = self::initPath($path, "file");
            return $realPath;
        }
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
     * @param string $path
     * @param resource $stream
     */
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

    /**
     * @param string $path
     * @param number $chmodValue
     */
    public static function changeMode($path, $chmodValue){
    }
}
