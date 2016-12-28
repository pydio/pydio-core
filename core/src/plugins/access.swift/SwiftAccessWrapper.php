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
namespace Pydio\Access\Driver\StreamProvider\Swift;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessWrapper;

use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\FileHelper;
use Pydio\Core\Utils\Vars\UrlUtils;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(AJXP_INSTALL_PATH . "/plugins/access.fs/FsAccessWrapper.php");

/**
 * Encapsulation of the PEAR webDAV client
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class SwiftAccessWrapper extends FsAccessWrapper
{
    public static $lastException;
    private static $cloudContext;

    /**
     * Initialize the stream from the given path.
     * Concretely, transform ajxp.webdav:// into webdav://
     *
     * @param string $path
     * @return mixed Real path or -1 if currentListing contains the listing : original path converted to real path
     */
    protected static function initPath($path, $streamType, $storeOpenContext = false, $skipZip = false)
    {
        $node = new AJXP_Node($path);
        $ctx = $node->getContext();
        $repoObject = $node->getRepository();
        if (!isSet($repoObject)) {
            $e = new \Exception("Cannot find repository with id ".$node->getRepositoryId());
            self::$lastException = $e;
            throw $e;
        }
        if (self::$cloudContext == null) {
            self::$cloudContext = stream_context_create(
                array("swiftfs" =>
                array(
                    'username'                  => $repoObject->getContextOption($ctx, "USERNAME"),
                    'password'                  => $repoObject->getContextOption($ctx, "PASSWORD"),
                    'tenantid'                  => $repoObject->getContextOption($ctx, "TENANT_ID"),
                    'endpoint'                  => $repoObject->getContextOption($ctx, "ENDPOINT"),
                    'openstack.swift.region'    => $repoObject->getContextOption($ctx, "REGION")
                ))
            );
        }

        $baseContainer = $repoObject->getContextOption($node->getContext(), "CONTAINER");
        $p = "swiftfs://".$baseContainer.str_replace("//", "/", $node->getPath());
        return $p;
    }


    /**
     * @param AJXP_Node $node
     * @return array
     */
    public static function getResolvedOptionsForNode($node)
    {
        return [
            "TYPE" => "php"
        ];
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
        } catch (\Exception $e) {
            Logger::error(__CLASS__,"stream_open", "Error while opening stream $path");
            return false;
        }
        if ($this->realPath == -1) {
            $this->fp = -1;
            return true;
        } else {
            $this->fp = fopen($this->realPath, $mode, $options, self::$cloudContext);
            return ($this->fp !== false);
        }
    }

    /**
     * Stats the given path.
     * Fix PEAR by adding S_ISREG mask when file case.
     *
     * @param unknown_type $path
     * @param unknown_type $flags
     * @return unknown
     */
    public function url_stat($path, $flags)
    {
        // File and zip case
        // AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Stating $path");

        $url = UrlUtils::mbParseUrl($path);
        if(empty($url["path"])){
            // This is root, return fake stat;
            return $this->fakeStat(true);
        }
        //$handle = fopen($this->initPath($path, "file"), "r", null, self::$cloudContext);
        $p = $this->initPath($path, "file");
        $stat = @stat($p);
        //fclose($handle);
        if($stat == null || $stat === false) return null;
        if ($stat["mode"] == 0666) {
            $stat[2] = $stat["mode"] |= 0100000; // S_ISREG
        }
        if ($stat["mtime"] == $stat["ctime"]  && $stat["ctime"] == $stat["atime"] && $stat["atime"] == 0 && $url["path"] != "/") {
            //AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Nullifying stats");
            return null;
        }
        return $stat;

    }

    /**
     * Fake stat data.
     *
     * Under certain conditions we have to return totally trumped-up
     * stats. This generates those.
     */
    protected function fakeStat($dir = false)
    {
        $request_time = time();

        // Set inode type to directory or file.
        $type = $dir ? 040000 : 0100000;
        // Fake world-readable
        $mode = $type + 0777;

        $values = array(
            'dev' => 0,
            'ino' => 0,
            'mode' => $mode,
            'nlink' => 0,
            'uid' => posix_getuid(),
            'gid' => posix_getgid(),
            'rdev' => 0,
            'size' => 0,
            'atime' => $request_time,
            'mtime' => $request_time,
            'ctime' => $request_time,
            'blksize' => -1,
            'blocks' => -1,
        );

        $final = array_values($values) + $values;

        return $final;
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
            $this->dH = opendir($this->realPath, self::$cloudContext);
        } else if ($this->realPath == -1) {
            $this->dH = -1;
        }
        return $this->dH !== false;
    }

    /**
     * @param string $path
     * @param int $mode
     * @param int $options
     * @return bool
     * @throws \Exception
     */
    public function mkdir($path, $mode, $options)
    {
        $this->realPath = $this->initPath($path, "dir", true);
        file_put_contents($this->realPath."/.marker", "tmpdata");
        return true;
    }

    // DUPBLICATE STATIC FUNCTIONS TO BE SURE
    // NOT TO MESS WITH self:: CALLS

    /**
     * @param $tmpDir
     * @param $tmpFile
     */
    public static function removeTmpFile($tmpDir, $tmpFile)
    {
        if(is_file($tmpFile)) unlink($tmpFile, self::$cloudContext);
        if(is_dir($tmpDir)) rmdir($tmpDir, self::$cloudContext);
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

    /**
     * @param string $path
     * @param bool $persistent
     * @return string
     */
    public static function getRealFSReference($path, $persistent = false)
    {
        $tmpFile = ApplicationState::getTemporaryFolder() ."/".md5(time()).".".pathinfo($path, PATHINFO_EXTENSION);
           $tmpHandle = fopen($tmpFile, "wb", null, self::$cloudContext);
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
        return false;
    }

    /**
     * @param string $path
     * @param resource $stream
     */
    public static function copyFileInStream($path, $stream)
    {
        $fp = fopen($path, "r", null, self::$cloudContext);
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
    public static function changeMode($path, $chmodValue)
    {
        // DO NOTHING!
        //$realPath = self::initPath($path, "file");
        //chmod($realPath, $chmodValue);
    }
}
