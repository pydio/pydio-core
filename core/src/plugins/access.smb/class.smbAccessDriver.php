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

/**
 * AJXP_Plugin to access a samba server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class smbAccessDriver extends fsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    public function initRepository()
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }
        $smbclientPath = $this->driverConf["SMBCLIENT"];
        define ('SMB4PHP_SMBCLIENT', $smbclientPath);

        $smbtmpPath = $this->driverConf["SMB_PATH_TMP"];
        define ('SMB4PHP_SMBTMP', $smbtmpPath);
		
        require_once($this->getBaseDir()."/smb.php");

        $create = $this->repository->getOption("CREATE");
        $recycle = $this->repository->getOption("RECYCLE_BIN");

        $wrapperData = $this->detectStreamWrapper(true);
        $this->wrapperClassName = $wrapperData["classname"];
        $this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
    }

    public function detectStreamWrapper($register = false)
    {
        if ($register) {
            require_once($this->getBaseDir()."/smb.php");
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
        if ($contribNode->nodeName != "actions" || (isSet($this->pluginConf["SMB_ENABLE_ZIP"]) && $this->pluginConf["SMB_ENABLE_ZIP"] == true)) {
            return ;
        }
        $this->disableArchiveBrowsingContributions($contribNode);
    }

    public function makeZip ($src, $dest, $basedir)
    {
        @set_time_limit(0);
        require_once(AJXP_BIN_FOLDER."/pclzip.lib.php");
        $filePaths = array();
        foreach ($src as $item) {
            $realFile = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase.(($item[0] == "/")? "" : "/").AJXP_Utils::securePath($item));
            $basedir = trim(dirname($realFile))."/";
            $filePaths[] = array(PCLZIP_ATT_FILE_NAME => $realFile,
                PCLZIP_ATT_FILE_NEW_SHORT_NAME => basename($item));
        }
        $this->logDebug("Pathes", $filePaths);
        $this->logDebug("Basedir", array($basedir));
        self::$filteringDriverInstance = $this;
        $archive = new PclZip($dest);
        $vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_PATH, $basedir, PCLZIP_OPT_NO_COMPRESSION, PCLZIP_OPT_ADD_TEMP_FILE_ON);
        if (!$vList) {
            throw new Exception("Zip creation error : ($dest) ".$archive->errorInfo(true));
        }
        self::$filteringDriverInstance = null;
        return $vList;
    }

    public function filesystemFileSize($filePath)
    {
        $bytesize = filesize($filePath);
        if ($bytesize < 0) {
            $bytesize = sprintf("%u", $bytesize);
        }
        return $bytesize;
    }

    public function isWriteable($dir, $type="dir")
    {
        if(substr_count($dir, '/') == 3 && $dir[strlen($dir) - 1] == '/') $rc = true;
    else $rc = is_writable($dir);
    return $rc;
    }
}
