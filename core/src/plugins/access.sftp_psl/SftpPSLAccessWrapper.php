<?php
/*
 * Copyright 2013 Nikita ROUSSEAU <warhawk3407@gmail.com>
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
 */
namespace Pydio\Access\Driver\StreamProvider\SFTP_PSL;

use Net_SSH2;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;

use Pydio\Access\Driver\StreamProvider\FS\FsAccessWrapper;
use Pydio\Auth\Core\MemorySafe;

use Pydio\Core\Exception\PydioException;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\UrlUtils;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die( 'Access not allowed' );

require_once(AJXP_INSTALL_PATH . "/plugins/access.fs/FsAccessWrapper.php");
require_once(AJXP_INSTALL_PATH."/plugins/access.sftp_psl/phpseclib/SSH2.php");

/**
 * Plugin to access a remote server using SSH File Transfer Protocol (SFTP) with phpseclib ( http://phpseclib.sourceforge.net/ )
 *
 * @author	warhawk3407 <warhawk3407@gmail.com>
 * @author	Charles du Jeu <contact (at) cdujeu.me>
 * @version	Release: 1.0.2
 */
class SftpPSLAccessWrapper extends FsAccessWrapper
{

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
     * Initialize the stream from the given path.
     */
    protected static function initPath($path, $streamType = '', $storeOpenContext = false, $skipZip = true)
    {
        $url = UrlUtils::safeParseUrl($path);
        $node = new AJXP_Node($url);
        $ctx  = $node->getContext();
        $repoObject = $node->getRepository();
        if(empty($repoObject)) {
            throw new \Exception("Cannot find repository with id ".$node->getRepositoryId());
        }
        $path = $node->getPath();

        $basePath   = $repoObject->getContextOption($ctx, "PATH");
        $host       = $repoObject->getContextOption($ctx, "SFTP_HOST");
        $port       = $repoObject->getContextOption($ctx, "SFTP_PORT");

        $credentials = MemorySafe::tryLoadingCredentialsFromSources($node->getContext());
        $user = $credentials["user"];
        $pass = $credentials["password"];

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
        $options["SERVER"]  = $repoObject->getContextOption($context, "SFTP_HOST");
        $options["PORT"]  = intval($repoObject->getContextOption($context, "SFTP_PORT"));
        $options["PATH"]  = intval($repoObject->getContextOption($context, "PATH"));

        return $options;
    }
    
    
    /**
     * @inheritdoc
     */
    public function stream_open($path, $mode, $options, &$context)
    {
        try {
            $this->realPath = $this->initPath($path);
        } catch (\Exception $e) {
            Logger::error(__CLASS__,"stream_open", "Error while opening stream $path");
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
     * @param string $path
     * @param mixed $options
     * @return bool
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

    /**
     * @param string $path
     * @return bool
     * @throws PydioException
     * @throws \Exception
     */
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
        AbstractAccessDriver::fixPermissions(new AJXP_Node($path), $stat, array($this, "detectRemoteUserId"));
        return $stat;
    }

    /**
     * @param AJXP_Node $node
     * @return array
     */
    public function detectRemoteUserId($node)
    {
        $repoObject = $node->getRepository();
        $ctx = $node->getContext();
        $host = $repoObject->getContextOption($ctx, "SFTP_HOST");
        $port = $repoObject->getContextOption($ctx, "SFTP_PORT");

        $credentials = MemorySafe::tryLoadingCredentialsFromSources($node->getContext());
        $user = $credentials["user"];
        $pass = $credentials["password"];

        $ssh2 = new Net_SSH2($host, $port);
        if ($ssh2->login($user, $pass)) {
            $output = $ssh2->exec( 'id' );

            $ssh2->disconnect();

            if (trim($output != "")) {
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
