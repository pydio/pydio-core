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
//require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/access.fs/class.fsAccessDriver.php");

/**
 * AJXP_Plugin to access a webdav enabled server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class webdavAccessDriver extends fsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    public function performChecks()
    {
        if (!AJXP_Utils::searchIncludePath('HTTP/WebDAV/Client.php')) {
            throw new Exception("The PEAR HTTP_WebDAV_Client package must be installed!");
        }
    }

    public function initRepository()
    {
        @include_once("HTTP/WebDAV/Client.php");
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        if (!class_exists('HTTP_WebDAV_Client_Stream')) {
            throw new Exception("You must have Pear HTTP/WebDAV/Client package installed to use this access driver!");
        }
        $create = $this->repository->getOption("CREATE");
        $path = $this->repository->getOption("PATH");
        $recycle = $this->repository->getOption("RECYCLE_BIN");
        ConfService::setConf("PROBE_REAL_SIZE", false);
        /*
        if ($create == true) {
            if(!is_dir($path)) @mkdir($path);
            if (!is_dir($path)) {
                throw new AJXP_Exception("Cannot create root path for repository (".$this->repository->getDisplay()."). Please check repository configuration or that your folder is writeable!");
            }
            if ($recycle!= "" && !is_dir($path."/".$recycle)) {
                @mkdir($path."/".$recycle);
                if (!is_dir($path."/".$recycle)) {
                    throw new AJXP_Exception("Cannot create recycle bin folder. Please check repository configuration or that your folder is writeable!");
                }
            }
        } else {
            if (!is_dir($path)) {
                throw new AJXP_Exception("Cannot find base path ($path) for your repository! Please check the configuration!");
            }
        }
        */
        $wrapperData = $this->detectStreamWrapper(true);
        $this->wrapperClassName = $wrapperData["classname"];
        $this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
        if (!is_dir($this->urlBase)) {
            if (webdavAccessWrapper::$lastException) {
                throw webdavAccessWrapper::$lastException;
            }
            throw new AJXP_Exception("Cannot connect to the WebDAV server ($path). Please check the configuration!");
        }
        if ($recycle != "") {
            RecycleBinManager::init($this->urlBase, "/".$recycle);
        }
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

}
