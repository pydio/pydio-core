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
use \OpenStack\Bootstrap;
/**
 * AJXP_Plugin to access a webdav enabled server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class swiftAccessDriver extends fsAccessDriver
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
        // Check CURL, OPENSSL & AWS LIBRARY & PHP5.3
        if (version_compare(phpversion(), "5.3.0") < 0) {
            throw new Exception("Php version 5.3+ is required for this plugin (must support namespaces)");
        }
        if(!file_exists($this->getBaseDir()."/openstack-sdk-php/vendor/autoload.php")){
            throw new Exception("You must download the openstack-sdk-php and install it with Composer for this plugin");
        }

    }


    public function initRepository()
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        require_once($this->getBaseDir()."/openstack-sdk-php/vendor/autoload.php");

        Bootstrap::useStreamWrappers();

        Bootstrap::setConfiguration(array(
            'username' => $this->repository->getOption("USERNAME"),
            'password' => $this->repository->getOption("PASSWORD"),
            'tenantid' => $this->repository->getOption("TENANT_ID"),
            'endpoint' => $this->repository->getOption("ENDPOINT"),
            'openstack.swift.region'   => $this->repository->getOption("REGION"),
            'transport.ssl.verify' => false
        ));


        $path = $this->repository->getOption("PATH");
        $recycle = $this->repository->getOption("RECYCLE_BIN");
        ConfService::setConf("PROBE_REAL_SIZE", false);
        $wrapperData = $this->detectStreamWrapper(true);
        $this->wrapperClassName = $wrapperData["classname"];
        $this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
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

    public function isWriteable($dir, $type="dir")
    {
        return true;
    }

    public function loadNodeInfo(AJXP_Node &$node, $parentNode = false, $details = false)
    {
        parent::loadNodeInfo($node, $parentNode, $details);
        if (!$node->isLeaf()) {
            $node->setLabel(rtrim($node->getLabel(), "/"));
        }
    }

    public function filesystemFileSize($filePath)
    {
        $bytesize = filesize($filePath);
        return $bytesize;
    }

    public function isRemote()
    {
        return true;
    }

    public function makeSharedRepositoryOptions($httpVars, $repository)
    {
        $newOptions = parent::makeSharedRepositoryOptions($httpVars, $repository);
        $newOptions["CONTAINER"] = $this->repository->getOption("CONTAINER");
        return $newOptions;
    }

}
