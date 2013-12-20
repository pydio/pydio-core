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




/**
 * AJXP_Plugin to access a remote server using SSH File Transfer Protocol (SFTP) with phpseclib ( http://phpseclib.sourceforge.net/ )
 *
 * @author	warhawk3407 <warhawk3407@gmail.com>
 * @author	Charles du Jeu <contact (at) cdujeu.me>
 * @version	Release: 1.0.2
 */
class sftpPSLAccessDriver extends fsAccessDriver
{

    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    /**
     * initRepository
     */
    public function initRepository()
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        ConfService::setConf("PROBE_REAL_SIZE", false);

        require_once($this->getBaseDir()."/SFTPPSL_StreamWrapper.php");

        $create = $this->repository->getOption("CREATE");
        $path = $this->repository->getOption("PATH");

        $wrapperData = $this->detectStreamWrapper(true);
        $this->wrapperClassName = $wrapperData["classname"];
        $this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
    }

    public function detectStreamWrapper($register = false)
    {
        if ($register) {
            require_once($this->getBaseDir()."/SFTPPSL_StreamWrapper.php");
        }
        return parent::detectStreamWrapper($register);
    }

    /**
     * Parse
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if($contribNode->nodeName != "actions") return ;
        $this->disableArchiveBrowsingContributions($contribNode);
    }

    public function filesystemFileSize($filePath)
    {
        $bytesize = filesize($filePath);
        if ($bytesize < 0) {
            $bytesize = sprintf("%u", $bytesize);
        }
        return $bytesize;
    }

    /**
     * @param $src
     * @param $dest
     * @param $basedir
     * @return zipfile
     */
    public function makeZip ($src, $dest, $basedir)
    {
        @set_time_limit(60);
        require_once(AJXP_BIN_FOLDER."/pclzip.lib.php");
        $filePaths = array();

        $uniqid = uniqid();
        $uniqfolder = '/tmp/ajaxplorer-zip-'.$uniqid;
        mkdir($uniqfolder);

        foreach ($src as $item) {
            $basedir = trim(dirname($item));
            $basename = basename($item);
            $uniqpath = $uniqfolder.'/'.$basename;
            $this->full_copy($this->urlBase.$item, $uniqpath);
            $filePaths[] = array(PCLZIP_ATT_FILE_NAME => $uniqpath,
                                 PCLZIP_ATT_FILE_NEW_SHORT_NAME => $basename);
        }
        $this->logDebug("Pathes", $filePaths);
        $this->logDebug("Basedir", array($basedir));
        $archive = new PclZip($dest);
        $vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_PATH, $uniqfolder, PCLZIP_OPT_NO_COMPRESSION);
        $this->recursiveRmdir($uniqfolder);
        if (!$vList) {
            throw new Exception("Zip creation error : ($dest) ".$archive->errorInfo(true));
        }
        return $vList;
    }

    public function full_copy( $source, $destination )
    {
        if ( is_dir( $source ) ) {
            @mkdir( $destination );
            $directory = dir( $source );
            while ( FALSE !== ( $readdirectory = $directory->read() ) ) {
                if ($readdirectory == '.' || $readdirectory == '..') {
                    continue;
                }
                $PathDir = $source . '/' . $readdirectory;
                if ( is_dir( $PathDir ) ) {
                    $this->full_copy( $PathDir, $destination . '/' . $readdirectory );
                    continue;
                }
                copy( $PathDir, $destination . '/' . $readdirectory );
            }

            $directory->close();
        } else {
            copy( $source, $destination );
        }
    }

    public function recursiveRmdir($path)
    {
        if (is_dir($path)) {
            $path = rtrim($path, '/');
            $subdir = dir($path);
            while (($file = $subdir->read()) !== false) {
                if ($file != '.' && $file != '..') {
                    (!is_link("$path/$file") && is_dir("$path/$file")) ? $this->recursiveRmdir("$path/$file") : unlink("$path/$file");
                }
            }
            $subdir->close();
            rmdir($path);
            return true;
        }
        return false;
    }

    public function isWriteable($dir, $type="dir")
    {
        return is_writable($dir);
    }
}
