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
require_once("aws.phar");
use Aws\S3\S3Client;

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
    protected $s3Client;

    public function performChecks()
    {
        // Check CURL, OPENSSL & AWS LIBRARY
        if(!extension_loaded("curl")) throw new Exception("Cannot find php_curl extension!");
        if(!extension_loaded("openssl")) throw new Exception("Cannot find openssl extension!");
        if(!file_exists($this->getBaseDir()."/aws-sdk/sdk.class.php")) throw new Exception("Cannot find AWS PHP SDK. Make sure it is installed in the aws-sdk folder.");
       }


    public function initRepository()
    {
        $options = array(
            'key'    => $this->repository->getOption("API_KEY"),
            'secret' => $this->repository->getOption("SECRET_KEY")
        );
        $baseURL = $this->repository->getOption("STORAGE_URL");
        if(!empty($baseURL)){
            $options["base_url"] = $baseURL;
        }else{
            $options["region"] = $this->repository->getOption("REGION");
        }
        $this->s3Client = S3Client::factory($options);
        $this->s3Client->registerStreamWrapper();

        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        $recycle = $this->repository->getOption("RECYCLE_BIN");
        ConfService::setConf("PROBE_REAL_SIZE", false);
        $wrapperData = $this->detectStreamWrapper(true);
        $this->wrapperClassName = $wrapperData["classname"];
        $this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
        if ($recycle != "") {
            RecycleBinManager::init($this->urlBase, "/".$recycle);
        }
    }

    public function getS3Service(){
        return $this->s3Client;
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

    public static function isRemote()
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
