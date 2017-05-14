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
namespace Pydio\Access\Driver\StreamProvider\SFTP;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;

use Pydio\Access\Driver\StreamProvider\FS\FsAccessWrapper;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die( 'Access not allowed');


require_once(AJXP_INSTALL_PATH . "/plugins/access.fs/FsAccessWrapper.php");

/**
 * Callback for ssh2_connect in case of disconnexion.
 *
 * @param integer $code
 * @param String $message
 * @param String $language
 */
function disconnectedSftp($code, $message, $language)
{
    Logger::info(__CLASS__,"SSH2.FTP.disconnected",$message);
    throw new \Exception('SSH2.FTP : disconnected'.$message, $code);
}

/**
 * @param $message
 * @throws \Exception
 */
function ignoreSftp($message)
{
    Logger::info(__CLASS__,"SSH2.FTP.ignore",$message);
    throw new \Exception('SSH2.FTP : ignore'.$message);
}

/**
 * @param $message
 * @param $language
 * @param $always_display
 * @throws \Exception
 */
function debugSftp($message, $language, $always_display)
{
    Logger::info(__CLASS__,"SSH2.FTP.debug",$message);
    throw new \Exception('SSH2.FTP : debug'.$message);
}

/**
 * @param $packet
 * @throws \Exception
 */
function macerrorSftp($packet)
{
    Logger::info(__CLASS__,"SSH2.FTP.macerror","");
    throw new \Exception('SSH2.FTP : macerror'.$packet);
}



/**
 * Plugin to access an ftp server over SSH
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class SFTPAccessWrapper extends FsAccessWrapper
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
     * @throws \Exception
     * @return mixed Real path or -1 if currentListing contains the listing : original path converted to real path
     */
    protected static function initPath($path, $streamType="", $sftpResource = false, $skipZip = false)
    {
        $node = new AJXP_Node($path);
        $repoObject = $node->getRepository();
        if(!isSet($repoObject)) {
            throw new \Exception("Cannot find repository with id ".$node->getRepositoryId());
        }
        $path = $node->getPath();
        $ctx = $node->getContext();
        // MAKE SURE THERE ARE NO // OR PROBLEMS LIKE THAT...
        $basePath = $repoObject->getContextOption($ctx, "PATH");
        if ($basePath[strlen($basePath)-1] == "/") {
            $basePath = substr($basePath, 0, -1);
        }
        if ($basePath[0] != "/") {
            $basePath = "/$basePath";
        }
        $path = InputFilter::securePath($path);
        if ($path[0] == "/") {
            $path = substr($path, 1);
        }
        // SHOULD RETURN ssh2.sftp://Resource #23/server/path/folder/path
        // BUT SINCE PHP 5.6 there's a php issue, wrap resource in intval()
        return  "ssh2.sftp://".intval(self::getSftpResource($ctx)).$basePath."/".$path;
    }

    /**
     * Get ssh2 connection
     *
     * @param ContextInterface $context
     * @return Resource
     */
    protected static function getSftpResource($context)
    {
        $repoObject = $context->getRepository();
        if (isSet(self::$sftpResource) && self::$resourceRepoId == $repoObject->getId()) {
            return self::$sftpResource;
        }
        $callbacks = array('disconnect' => "disconnectedSftp",
                            'ignore' 	=> "ignoreSftp",
                            'debug' 	=> "debugSftp",
                            'macerror'	=> "macerrorSftp");
        $remote_serv = $repoObject->getContextOption($context, "SERV");
        $remote_port = $repoObject->getContextOption($context, "PORT");
        $credentials = MemorySafe::tryLoadingCredentialsFromSources($context);
        $remote_user = $credentials["user"];
        $remote_pass = $credentials["password"];

        $connection = ssh2_connect($remote_serv, intval($remote_port), array(), $callbacks);
        ssh2_auth_password($connection, $remote_user, $remote_pass);
        self::$sftpResource = ssh2_sftp($connection);
        self::$resourceRepoId = $repoObject->getId();
        return self::$sftpResource;
    }

    /**
     * @param AJXP_Node $node
     * @return array
     * @throws \Exception
     */
    public static function getResolvedOptionsForNode($node)
    {
        $options = [
            "TYPE" => "sftp"
        ];
        $repoObject = $node->getRepository();
        $context = $node->getContext();
        $credentials = MemorySafe::tryLoadingCredentialsFromSources($context);
        $options["USER"] = $credentials["user"];
        $options["PASSWORD"] = $credentials["password"];
        $options["SERVER"]  = $repoObject->getContextOption($context, "SERV");
        $options["PORT"]  = intval($repoObject->getContextOption($context, "PORT"));
        $options["PATH"]  = intval($repoObject->getContextOption($context, "PATH"));

        return $options;
    }

    /**
     * Opens the stream
     * Diff with parent class : do not "securePath", as it removes double slash
     *
     * @param String $path Maybe in the form "ajxp.fs://repositoryId/pathToFile"
     * @param String $mode
     * @param array $options
     * @param array $context
     * @return resource
     */
    public function stream_open($path, $mode, $options, &$context)
    {
        try {
            $this->realPath = $this->initPath($path);
        } catch (\Exception $e) {
            Logger::error(__CLASS__,"stream_open", "Error while opening stream $path");
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
        AbstractAccessDriver::fixPermissions(new AJXP_Node($path), $stat, array($this, "detectRemoteUserId"));
        return $stat;
    }

    /**
     * @param AJXP_Node $node
     * @return array
     */
    public function detectRemoteUserId($node)
    {
        list($connection, $remote_base_path) = self::getSshConnection($node->getContext());
        $stream = ssh2_exec($connection, "id");
        if ($stream !== false) {
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            fclose($stream);

            if (trim($output != "")) {
                //$res = sscanf($output, "uid=%i(%s) gid=%i(%s) groups=%i(%s)");
                preg_match_all("/(\w*)=(\w*)\(([\w-]*)\)/", $output, $matches);
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
            $tmpFile = ApplicationState::getTemporaryFolder() ."/".md5(time());
            $tmpHandle = fopen($tmpFile, "wb");
            self::copyFileInStream($path, $tmpHandle);
            fclose($tmpHandle);
            return $tmpFile;
        } else {
            return self::initPath($path);
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
     * @param String $url
     * @return bool
     */
    public static function isSeekable($url)
    {
        return false;
    }

    /**
     * Override parent function, testing feof() does not seem to work.
     * We may have performance problems on big files here.
     *
     * @param String $path
     * @param resource $stream
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
     * @param string $path
     * @return bool
     * @throws \Exception
     */
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
     * @param integer $chmodValue
     */
    public static function changeMode($path, $chmodValue)
    {
        $node = new AJXP_Node($path);
        list($connection, $remote_base_path) = self::getSshConnection($node->getContext());
        ssh2_exec($connection,'chmod '.decoct($chmodValue).' '.$remote_base_path.$node->getPath());
    }

    /**
     * @param ContextInterface $ctx
     * @return array
     */
    public static function getSshConnection(ContextInterface $ctx)
    {
        $repoObject  = $ctx->getRepository();
        $remote_serv = $repoObject->getContextOption($ctx, "SERV");
        $remote_port = $repoObject->getContextOption($ctx, "PORT");
        $credentials = MemorySafe::tryLoadingCredentialsFromSources($ctx);
        $remote_user = $credentials["user"];
        $remote_pass = $credentials["password"];
        $remote_base_path = $repoObject->getContextOption($ctx, "PATH");

        $callbacks = array('disconnect' => "disconnectedSftp",
                            'ignore' 	=> "ignoreSftp",
                            'debug' 	=> "debugSftp",
                            'macerror'	=> "macerrorSftp");
        $connection = ssh2_connect($remote_serv, intval($remote_port), array(), $callbacks);
        ssh2_auth_password($connection, $remote_user, $remote_pass);
        return array($connection, $remote_base_path);
    }

}
