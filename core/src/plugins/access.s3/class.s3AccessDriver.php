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
 * AJXP_Plugin to access a webdav enabled server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class s3AccessDriver extends fsAccessDriver
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
        // Check CURL, OPENSSL & AWS LIBRARY
        if(!extension_loaded("curl")) throw new Exception("Cannot find php_curl extension!");
        if(!extension_loaded("openssl")) throw new Exception("Cannot find openssl extension!");
        if(!file_exists($this->getBaseDir()."/aws-sdk/sdk.class.php")) throw new Exception("Cannot find AWS PHP SDK. Make sure it is installed in the aws-sdk folder.");
       }


    public function initRepository()
    {
        require_once($this->getBaseDir()."/aS3StreamWrapper/lib/wrapper/aS3StreamWrapper.class.php");
        if (!in_array("s3", stream_get_wrappers())) {
            $wrapper = new aS3StreamWrapper();
            $wrapper->register(array('protocol' => 's3',
                  'acl' => AmazonS3::ACL_OWNER_FULL_CONTROL,
                  'key' => $this->repository->getOption("API_KEY"),
                  'secretKey' => $this->repository->getOption("SECRET_KEY"),
                  'region' => $this->repository->getOption("REGION")));
        }

        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

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

    public function loadNodeInfo(&$node, $parentNode = false, $details = false)
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

}
