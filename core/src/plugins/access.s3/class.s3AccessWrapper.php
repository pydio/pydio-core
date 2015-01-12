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
use Aws\S3\S3Client;

require_once(AJXP_INSTALL_PATH."/plugins/access.fs/class.fsAccessWrapper.php");

/**
 * Encapsulation of the PEAR webDAV client
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class s3AccessWrapper extends fsAccessWrapper
{
    public static $lastException;

    /**
     * Initialize the stream from the given path.
     * Concretely, transform ajxp.s3:// into s3://
     *
     * @param string $path
     * @return mixed Real path or -1 if currentListing contains the listing : original path converted to real path
     */
    protected static function initPath($path, $streamType, $storeOpenContext = false, $skipZip = false)
    {
        $url = parse_url($path);
        $repoId = $url["host"];
        $repoObject = ConfService::getRepositoryById($repoId);
        if (!isSet($repoObject)) {
            $e = new Exception("Cannot find repository with id ".$repoId);
            self::$lastException = $e;
            throw $e;
        }
        $basePath = $repoObject->getOption("PATH");
        $baseContainer = $repoObject->getOption("CONTAINER");
        if(!empty($basePath)){
            $baseContainer.=rtrim($basePath, "/");
        }
        $p = "s3://".$baseContainer.str_replace("//", "/", $url["path"]);
        return $p;
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
        $stat = @stat($this->initPath($path, "file"));
        if($stat == null) return null;
        if ($stat["mode"] == 0666) {
            $stat[2] = $stat["mode"] |= 0100000; // S_ISREG
        }

        $parsed = parse_url($path);
        if ($stat["mtime"] == $stat["ctime"]  && $stat["ctime"] == $stat["atime"] && $stat["atime"] == 0 && $parsed["path"] != "/") {
            //AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Nullifying stats");
            //return null;
        }
        return $stat;

        // Non existing file
           return null;
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
            $this->dH = @opendir($this->realPath);
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
        $tmpFile = AJXP_Utils::getAjxpTmpDir()."/".md5(time()).".".pathinfo($path, PATHINFO_EXTENSION);
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
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Should load ".$path);
        $fp = fopen($path, "r");
        if(!is_resource($fp)) return;
        while (!feof($fp)) {
            $data = fread($fp, 4096);
            fwrite($stream, $data, strlen($data));
        }
        fclose($fp);
    }

    public static function changeMode($path, $chmodValue){}

    public function rename($from, $to){
        if(is_dir($from)){
            AJXP_Logger::debug(__CLASS__, __FUNCTION__, "S3 Renaming dir $from to $to");
            require_once("aws.phar");

            $fromUrl = parse_url($from);
            $repoId = $fromUrl["host"];
            $repoObject = ConfService::getRepositoryById($repoId);
            if (!isSet($repoObject)) {
                $e = new Exception("Cannot find repository with id ".$repoId);
                self::$lastException = $e;
                throw $e;
            }
            // Get a client
            $options = array(
                'key'    => $repoObject->getOption("API_KEY"),
                'secret' => $repoObject->getOption("SECRET_KEY")
            );
            $baseURL = $repoObject->getOption("STORAGE_URL");
            if(!empty($baseURL)){
                $options["base_url"] = $baseURL;
            }else{
                $options["region"] = $repoObject->getOption("REGION");
            }
            $s3Client = S3Client::factory($options);

            $bucket = $repoObject->getOption("CONTAINER");
            $basePath = $repoObject->getOption("PATH");
            $fromKeyname   = trim(str_replace("//", "/", $basePath.parse_url($from, PHP_URL_PATH)),'/');
            $toKeyname   = trim(str_replace("//", "/", $basePath.parse_url($to, PHP_URL_PATH)), '/');

            // Perform a batch of CopyObject operations.
            $batch = array();
            $iterator = $s3Client->getIterator('ListObjects', array(
                'Bucket'     => $bucket,
                'Prefix'     => $fromKeyname."/"
            ));
            $toDelete = array();
            AJXP_Logger::debug(__CLASS__, __FUNCTION__, "S3 Got iterator looking for prefix ".$fromKeyname."/ , and toKeyName=".$toKeyname);
            foreach ($iterator as $object) {

                $currentFrom = $object['Key'];
                $currentTo = $toKeyname.substr($currentFrom, strlen($fromKeyname));
                AJXP_Logger::debug(__CLASS__, __FUNCTION__, "S3 Should move one object ".$currentFrom. " to  new key :".$currentTo);
                $batch[] = $s3Client->getCommand('CopyObject', array(
                    'Bucket'     => $bucket,
                    'Key'        => "{$currentTo}",
                    'CopySource' => "{$bucket}/".rawurlencode($currentFrom),
                ));
                $toDelete[] = $currentFrom;
            }

            try {

                AJXP_Logger::debug(__CLASS__, __FUNCTION__, "S3 Execute batch on ".count($batch)." objects");
                $successful = $s3Client->execute($batch);

                $failed = array();
                $iterator->rewind();
                $clear = new \Aws\S3\Model\ClearBucket($s3Client, $bucket);
                $clear->setIterator($iterator);
                $clear->clear();

            } catch (\Guzzle\Service\Exception\CommandTransferException $e) {

                $successful = $e->getSuccessfulCommands();
                $failed = $e->getFailedCommands();

            }
            if(count($failed)){
                foreach($failed as $c){
                    // $c is a Aws\S3\Command\S3Command
                    AJXP_Logger::error("S3Wrapper", __FUNCTION__, "Error while copying: ".$c->getOperation()->getServiceDescription());
                }
                self::$lastException = new Exception("Failed moving folder: ".count($failed));
                return false;
            }
            return true;
        }else{
            AJXP_Logger::debug(__CLASS__, __FUNCTION__, "S3 Execute standard rename on ".$from." to ".$to);
            return parent::rename($from, $to);
        }
    }

}
