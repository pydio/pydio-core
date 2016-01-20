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
use Guzzle\Plugin\Log\LogPlugin;

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
        if(!file_exists($this->getBaseDir()."/aws.phar")) throw new Exception("Cannot find AWS PHP SDK v2. Make sure the aws.phar package is installed inside access.s3 plugin.");
    }

    /**
     * @param bool $register
     * @return array|bool|void
     * Override parent to register underlying wrapper (s3) as well
     */
    public function detectStreamWrapper($register = false){

        if(isSet($this->repository)){
            require_once("aws.phar");
            $options = array(
                'key'    => $this->repository->getOption("API_KEY"),
                'secret' => $this->repository->getOption("SECRET_KEY")
            );
            $signatureVersion = $this->repository->getOption("SIGNATURE_VERSION");
            if(!empty($signatureVersion) && $signatureVersion != "-1"){
                $options['signature'] = $signatureVersion;
            }
            $baseURL = $this->repository->getOption("STORAGE_URL");
            if(!empty($baseURL)){
                $options["base_url"] = $baseURL;
            }else{
                $options["region"] = $this->repository->getOption("REGION");
            }
            $proxy = $this->repository->getOption("PROXY");
            if(!empty($proxy)){
                $options['request.options'] = array('proxy' => $proxy);
            }
            $this->s3Client = S3Client::factory($options);
            $this->s3Client->registerStreamWrapper();
        }
        return parent::detectStreamWrapper($register);
    }

    public function initRepository()
    {
        $this->detectStreamWrapper(true);

        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        $recycle = $this->repository->getOption("RECYCLE_BIN");
        ConfService::setConf("PROBE_REAL_SIZE", false);
        $this->urlBase = "pydio://".$this->repository->getId();

        if ($recycle!= "" && !is_dir($this->urlBase. "/" . $recycle . "/")) {
            @mkdir($this->urlBase. "/" . $recycle . "/", 0777, true);
            if(!is_dir($this->urlBase. "/" . $recycle . "/")) {
                throw new AJXP_Exception("Cannot create recycle bin folder. Please check repository configuration or that your folder is writeable!");
            } else {
                $this->setHiddenAttribute(new AJXP_Node($this->urlBase. "/" . $recycle . "/"));
            }
        }

        if ($recycle != "") {
            RecycleBinManager::init($this->urlBase, "/".$recycle);
        }
    }

    /**
     * @return Aws\Common\Client\AbstractClient
     */
    public function getS3Service(){
        return $this->s3Client;
    }

    /**
     * @param String $directoryPath
     * @param Repository $repositoryResolvedOptions
     * @return int
     */
    public function directoryUsage($directoryPath, $repositoryResolvedOptions){
        $client = $this->getS3Service();
        $bucket = (isSet($repositoryResolvedOptions["CONTAINER"])?$repositoryResolvedOptions["CONTAINER"]:$this->repository->getOption("CONTAINER"));
        $path   = (isSet($repositoryResolvedOptions["PATH"])?$repositoryResolvedOptions["PATH"]:"");
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
     * Parse
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
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
     * @throws Exception
     */
    protected function appendUploadedData($folder, $source, $target){

        $already_existed = false;
        if($source == $target){
            throw new Exception("Something nasty happened: trying to copy $source into itself, it will create a loop!");
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

    public function makeSharedRepositoryOptions($httpVars, $repository)
    {
        $newOptions = parent::makeSharedRepositoryOptions($httpVars, $repository);
        $newOptions["CONTAINER"] = $this->repository->getOption("CONTAINER");
        return $newOptions;
    }

}
