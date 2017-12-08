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
namespace Pydio\Access\Driver\StreamProvider\S3;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\RecycleBinManager;

use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Exception\PydioException;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Plugin to access a webdav enabled server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class S3AccessDriver extends FsAccessDriver
{
    /**
    * @var RepositoryInterface
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;
    protected $s3Client;

    public function performChecks()
    {
        // Check CURL, OPENSSL & AWS LIBRARY
        if(!extension_loaded("curl")) throw new \Exception("Cannot find php_curl extension!");
    }

    /**
     * @param ContextInterface $contextInterface
     * @throws PydioException
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {

        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        $recycle = $contextInterface->getRepository()->getContextOption($contextInterface, "RECYCLE_BIN");
        ConfService::setConf("PROBE_REAL_SIZE", false);
        $this->urlBase = $contextInterface->getUrlBase();

        if ($recycle!= "" && !is_dir($contextInterface->getUrlBase(). "/" . $recycle . "/")) {
            @mkdir($contextInterface->getUrlBase(). "/" . $recycle . "/", 0777, true);
            if(!is_dir($contextInterface->getUrlBase(). "/" . $recycle . "/")) {
                throw new PydioException("Cannot create recycle bin folder. Please check repository configuration or that your folder is writeable!");
            } else {
                $this->setHiddenAttribute(new AJXP_Node($contextInterface->getUrlBase(). "/" . $recycle . "/"));
            }
        }

        if ($recycle != "") {
            RecycleBinManager::init($contextInterface->getUrlBase(), "/".$recycle);
        }

        foreach ($this->exposeRepositoryOptions as $paramName){
            $this->exposeConfigInManifest($paramName, $contextInterface->getRepository()->getContextOption($contextInterface, $paramName));
        }

    }

    /**
     * @return S3Client
     */
    public function getS3Service(){
        return $this->s3Client;
    }

    /**
     * @param AJXP_Node $node
     * @return int
     */
    public function directoryUsage(AJXP_Node $node){
        $client = $this->getS3Service();
        $bucket = $node->getRepository()->getContextOption($node->getContext(), "CONTAINER"); //(isSet($repositoryResolvedOptions["CONTAINER"])?$repositoryResolvedOptions["CONTAINER"]:$this->repository->getOption("CONTAINER"));
        $path   = rtrim($node->getRepository()->getContextOption($node->getContext(), "PATH"), "/").$node->getPath(); //(isSet($repositoryResolvedOptions["PATH"])?$repositoryResolvedOptions["PATH"]:"");
        $objects = $client->getIterator('ListObjects', array(
            'Bucket' => $bucket,
            'Prefix' => $path
        ));

        $usage = 0;
        foreach ($objects as $object) {
            $usage += (double)$object['Size'];
        }
        return $usage;

    }

    /**
     * @inheritdoc
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if($contribNode->nodeName != "actions") return ;
        $this->disableArchiveBrowsingContributions($contribNode);
    }

    /**
     * We have to overwrite original FS function as S3 wrapper does not support "a+" open mode.
     *
     * @param String $folder Folder destination
     * @param String $source Maybe updated by the function
     * @param String $target Existing part to append data
     * @return bool If the target file already existed or not.
     * @throws \Exception
     */
    protected function appendUploadedData($folder, $source, $target){

        $already_existed = false;
        if($source == $target){
            throw new \Exception("Something nasty happened: trying to copy $source into itself, it will create a loop!");
        }
        // S3 does not really support append. Let's grab the remote target first.
        if (file_exists($folder ."/" . $target)) {
            $already_existed = true;
            $this->logDebug("Should copy stream from $source to $target - folder is ($folder)");
            $partO = fopen($folder."/".$source, "r");
            $appendF = fopen($folder ."/". $target, 'a');
            while (!feof($partO)) {
                $buf = fread($partO, 1024);
                fwrite($appendF, $buf);
            }
            fclose($partO);
            fclose($appendF);
            $this->logDebug("Done, closing streams!");
        }
        @unlink($folder."/".$source);
        return $already_existed;

    }


    /**
     * @param AJXP_Node $node
     * @return bool
     */
    public function isWriteable(AJXP_Node $node)
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function isRemote()
    {
        return true;
    }

    /**
     * @param AJXP_Node $node
     * @param bool $parentNode
     * @param bool $details
     * @return void
     */
    public function loadNodeInfo(&$node, $parentNode = false, $details = false)
    {
        parent::loadNodeInfo($node, $parentNode, $details);
        if (!$node->isLeaf()) {
            $node->setLabel(rtrim($node->getLabel(), "/"));
        }
    }

    /**
     * @param ContextInterface $ctx
     * @param array $httpVars
     * @return array
     * @throws \Exception
     */
    public function makeSharedRepositoryOptions(ContextInterface $ctx, $httpVars)
    {
        $newOptions                 = parent::makeSharedRepositoryOptions($ctx, $httpVars);
        $newOptions["CONTAINER"]    = "AJXP_PARENT_OPTION:CONTAINER";
        return $newOptions;
    }

}
