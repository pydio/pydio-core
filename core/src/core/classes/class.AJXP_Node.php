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
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Atomic representation of a data. This the basic node of the hierarchical data.
 * Encapsulates the path and url, the nature (leaf or not) and the metadata of the node.
 * @package Pydio
 * @subpackage Core
 */
class AJXP_Node
{
    /**
     * @var string URL of the node in the form ajxp.protocol://repository_id/path/to/node
     */
    protected $_url;
    /**
     * @var array The node metadata
     */
    protected $_metadata = array();
    /**
     * @var string Associated wrapper
     */
    protected $_wrapperClassName;
    /**
     * @var array Parsed url fragments
     */
    protected $urlParts = array();
    /**
     * @var string A local representation of a real file, if possible
     */
    protected $realFilePointer;
    /**
     * @var bool Whether the core information of the node is already loaded or not
     */
    protected $nodeInfoLoaded = false;
    /**
     * @var string Details level passed to nodeLoadInfo()
     */
    protected $nodeInfoLevel = "minimal";
    /**
     * @var Repository
     */
    private $_repository;
    /**
     * @var AbstractAccessDriver
     */
    private $_accessDriver;
    /**
     * @var MetaStoreProvider
     */
    private $_metaStore;

    /**
     * @var String
     */
    private $_user;

    /**
     * @var array
     */
    private $_indexableMetaKeys = array();

    /**
     * @param string $url URL of the node in the form ajxp.protocol://repository_id/path/to/node
     * @param array $metadata Node metadata
     */
    public function __construct($url, $metadata = array())
    {
        $this->setUrl($url);
        $this->_metadata = $metadata;
    }

    public function __sleep()
    {
        $t = array_diff(array_keys(get_class_vars("AJXP_Node")), array("_accessDriver", "_repository", "_metaStore"));
        return $t;
    }

    /**
     * @param String $url of the node in the form ajxp.protocol://repository_id/path/to/node
     * @return void
     */
    public function setUrl($url)
    {
        $this->_url = $url;
        // Clean url
        $testExp = explode("//", $url);
        if (count($testExp) > 1) {
            $this->_url = array_shift($testExp)."//";
            $this->_url .= implode("/", $testExp);
        }
        $this->parseUrl();
    }

    public function getRepository()
    {
        if (!isSet($this->_repository)) {
            $this->_repository = ConfService::getRepositoryById($this->urlParts["host"]);
        }
        return $this->_repository;
    }

    /**
     * @return AbstractAccessDriver
     */
    public function getDriver()
    {
        if (!isSet($this->_accessDriver)) {
            $repo = $this->getRepository();
            if ($repo != null) {
                $this->_accessDriver = ConfService::loadDriverForRepository($repo);
            }
        }
        return $this->_accessDriver;
    }

    /**
     * @param AbstractAccessDriver
     */
    public function setDriver($accessDriver)
    {
        $this->_accessDriver = $accessDriver;
    }

    /**
     * @return MetaStoreProvider
     */
    protected function getMetaStore()
    {
        if (!isSet($this->_metaStore)) {
            $this->getDriver();
            $this->_metaStore = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        }
        return $this->_metaStore;
    }

    public function hasMetaStore()
    {
        return ($this->getMetaStore() != false);
    }

    /**
     * @param $nameSpace
     * @param $metaData
     * @param bool $private
     * @param int $scope
     * @param bool $indexable
     */
    public function setMetadata($nameSpace, $metaData, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY, $indexable = false)
    {
        $metaStore = $this->getMetaStore();
        if ($metaStore !== false) {
            $metaStore->setMetadata($this, $nameSpace, $metaData, $private, $scope);
            //$this->mergeMetadata($metaData);
            if ($indexable) {
                if(!isSet($this->_indexableMetaKeys[$private ? "user":"shared"]))$this->_indexableMetaKeys[$private ? "user":"shared"] = array();
                $this->_indexableMetaKeys[$private ? "user":"shared"][$nameSpace] = $nameSpace;
            }
            AJXP_Controller::applyHook("node.meta_change", array(&$this));
        }
    }

    /**
     * @param String $nameSpace
     * @param bool $private
     * @param int $scope
     * @param bool $indexable
     */
    public function removeMetadata($nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY, $indexable = false)
    {
        $metaStore = $this->getMetaStore();
        if ($metaStore !== false) {
            $metaStore->removeMetadata($this, $nameSpace, $private, $scope);
            if ($indexable && isSet($this->_indexableMetaKeys[$private ? "user":"shared"]) && isset($this->_indexableMetaKeys[$private ? "user":"shared"][$nameSpace])) {
                unset($this->_indexableMetaKeys[$private ? "user":"shared"][$nameSpace]);
            }
            AJXP_Controller::applyHook("node.meta_change", array(&$this));
        }
    }

    /**
     * @param String $nameSpace
     * @param bool $private
     * @param int $scope
     * @param bool $indexable
     * @return array
     */
    public function retrieveMetadata($nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY, $indexable = false)
    {
        $metaStore = $this->getMetaStore();
        if ($metaStore !== false) {
            $data = $metaStore->retrieveMetadata($this, $nameSpace, $private, $scope);
            if (!empty($data) && $indexable) {
                if(!isSet($this->_indexableMetaKeys[$private ? "user":"shared"]))$this->_indexableMetaKeys[$private ? "user":"shared"] = array();
                $this->_indexableMetaKeys[$private ? "user":"shared"][$nameSpace] = $nameSpace;
            }
            return $data;
        }
        return array();
    }

    /**
     * @param AJXP_Node $originalNode
     * @param string $nameSpace
     * @param string $operation
     * @param bool $private
     * @param int $scope
     * @param bool $indexable
     * @return array()
     */
    public function copyOrMoveMetadataFromNode($originalNode, $nameSpace, $operation="move", $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY, $indexable = false){

        if($this->getMetaStore() == false || $this->getMetaStore()->inherentMetaMove()){
            return array();
        }
        $metaData = $originalNode->retrieveMetadata($nameSpace, $private, $scope, $indexable);
        if(isSet($metaData) && !empty($metaData)){
            $this->setMetadata($nameSpace, $metaData, $private, $scope, $indexable);
            if($operation == "move"){
                $originalNode->removeMetadata($nameSpace, $private, $scope, $indexable);
            }
            return $metaData;
        }
        return array();

    }

    /**
     * @return AJXP_Node|null
     */
    public function getParent(){

        if(empty($this->urlParts["path"]) || $this->urlParts["path"] == "/"){
            return null;
        }
        $parent = new AJXP_Node(dirname($this->_url));
        $parent->setDriver($this->_accessDriver);
        return $parent;

    }

    public function findMetadataInParent($nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY, $indexable = false){

        $metadata = false;
        $parentNode = $this->getParent();
        if($parentNode != null){
            $metadata = $parentNode->retrieveMetadata($nameSpace, $private, $scope,$indexable);
            if($metadata == false){
                $metadata = $parentNode->findMetadataInParent($nameSpace, $private, $scope, $indexable);
            }else{
                $metadata["SOURCE_NODE"] = $parentNode;
            }
        }
        return $metadata;

    }

    /**
     * @param String $nameSpace
     * @param bool $private
     * @param int $scope
     * @param bool $indexable
     * @param array $collect
     */
    public function collectMetadataInParents($nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY, $indexable = false, &$collect=array()){

        $parentNode = $this->getParent();
        if($parentNode != null){
            $metadata = $parentNode->retrieveMetadata($nameSpace, $private, $scope,$indexable);
            if($metadata != false){
                $metadata["SOURCE_NODE"] = $parentNode;
                $collect[] = $metadata;
            }
            $parentNode->collectMetadataInParents($nameSpace, $private, $scope, $indexable, $collect);
        }

    }

    /**
     * @param array $nameSpaces
     * @param bool $private
     * @param int $scope
     * @param bool $indexable
     * @param array $collect
     */
    public function collectMetadatasInParents($nameSpaces, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY, $indexable = false, &$collect=array()){

        $parentNode = $this->getParent();
        if($parentNode != null){
            $nodeMeta = array();
            foreach($nameSpaces as $nameSpace){
                $metadata = $parentNode->retrieveMetadata($nameSpace, $private, $scope,$indexable);
                if($metadata != false){
                    $nodeMeta[$nameSpace] = $metadata;
                }
            }
            if(count($nodeMeta)){
                $nodeMeta["SOURCE_NODE"] = $parentNode;
                $collect[] = $nodeMeta;
            }
            $parentNode->collectMetadatasInParents($nameSpaces, $private, $scope, $indexable, $collect);
        }

    }

    /**
     * @param bool $boolean Leaf or Collection?
     * @return void
     */
    public function setLeaf($boolean)
    {
        $this->_metadata["is_file"] = $boolean;
    }

    /**
     * @return bool
     */
    public function isLeaf()
    {
        return isSet($this->_metadata["is_file"])?$this->_metadata["is_file"]:true;
    }

    /**
     * @param $label String Main label, will set the metadata "text" key.
     * @return void
     */
    public function setLabel($label)
    {
        $this->_metadata["text"] = $label;
    }

    /**
     * @return string Try to get the metadata "text" key, or the basename of the node path.
     */
    public function getLabel()
    {
        return isSet($this->_metadata["text"])? $this->_metadata["text"] : basename($this->urlParts["path"]);
    }

    /**
     * @return string
     */
    public function getExtension(){
        return strtolower(pathinfo($this->urlParts["path"], PATHINFO_EXTENSION));
    }

    /**
     * @param $string
     * @return bool
     */
    public function hasExtension($string){
        return strcasecmp($string, $this->getExtension()) == 0;
    }

    /**
     * List all set metadata keys
     * @return array
     */
    public function listMetaKeys()
    {
        return array_keys($this->_metadata);
    }

    /**
     * Applies the "node.info" hook, thus going through the plugins that have registered this node, and loading
     * all metadata at once.
     * @param bool $forceRefresh
     * @param bool $contextNode The parent node, if it can be useful for the hooks callbacks
     * @param mixed $details A specification of expected metadata fields, or minimal
     * @return void
     */
    public function loadNodeInfo($forceRefresh = false, $contextNode = false, $details = false)
    {
        if($this->nodeInfoLoaded && $this->nodeInfoLevel != $details){
            $forceRefresh = true;
        }
        if($this->nodeInfoLoaded && !$forceRefresh) return;
        if (!empty($this->_wrapperClassName)) {
            $registered = AJXP_PluginsService::getInstance()->getRegisteredWrappers();
            if (!isSet($registered[$this->getScheme()])) {
                $driver = $this->getDriver();
                if(is_object($driver)) $driver->detectStreamWrapper(true);
            }
        }
        AJXP_Controller::applyHook("node.info", array(&$this, $contextNode, $details));
        $this->nodeInfoLoaded = true;
        $this->nodeInfoLevel = $details;
    }

    /**
     * Get a real reference to the filesystem. Remote wrappers will copy the file locally.
     * This will last the time of the script and will be removed afterward.
     * @return string
     */
    public function getRealFile()
    {
        if (!isset($this->realFilePointer)) {
            $this->realFilePointer = call_user_func(array($this->_wrapperClassName, "getRealFSReference"), $this->_url, true);
            $isRemote = call_user_func(array($this->_wrapperClassName, "isRemote"));
            if ($isRemote) {
                register_shutdown_function(array("AJXP_Utils", "silentUnlink"), $this->realFilePointer);
            }
        }
        return $this->realFilePointer;
    }

    /**
     * @return string URL of the node in the form ajxp.protocol://repository_id/path/to/node
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * @return string The path from the root of the repository
     */
    public function getPath()
    {
        return $this->urlParts["path"];
    }

    public function isRoot()
    {
        return !isset($this->urlParts["path"]) || $this->urlParts["path"] == "/";
    }

    /**
     * @return string The scheme part of the url
     */
    public function getScheme()
    {
        return $this->urlParts["scheme"];
    }

    /**
     * @return string A username
     */
    public function getUser(){
        return $this->_user;
    }

    /**
     * @param string $userId A username
     */
    public function setUser($userId){
        $this->_user = $userId;
        $this->urlParts["user"] = $userId;
        // Update url with a user@workspaceID
        $crt = $this->getScheme()."://".$this->getRepositoryId();
        $this->_url = str_replace($crt, $this->getScheme()."://".$this->_user."@".$this->getRepositoryId(), $this->_url);
    }

    /**
     * @return string A username passed through url
     */
    public function hasUser(){
        return isSet($this->_user);
    }

    /**
     * @return string The repository identifer
     */
    public function getRepositoryId()
    {
        return $this->urlParts["host"];
    }

    /**
     * Pass an array of metadata and merge its content with the current metadata.
     * @param array $metadata
     * @param bool $mergeValues
     * @return void
     */
    public function mergeMetadata($metadata, $mergeValues = false)
    {
        if ($mergeValues) {
            foreach ($metadata as $key => $value) {
                if (isSet($this->_metadata[$key])) {
                    $existingValue = explode(",", $this->_metadata[$key]);
                    if (!in_array($value, $existingValue)) {
                        array_push($existingValue, $value);
                        $this->_metadata[$key] = implode(",", $existingValue);
                    }
                } else {
                    $this->_metadata[$key] = $value;
                }
            }
        } else {
            $this->_metadata = array_merge($this->_metadata, $metadata);
        }
    }

    /**
     * Magic getter for metadata
     * @param $varName
     * @return array|null|string
     */
    public function __get($varName)
    {
        if(strtolower($varName) == "wrapperclassname") return $this->_wrapperClassName;
        if(strtolower($varName) == "url") return $this->_url;
        if(strtolower($varName) == "metadata") return $this->_metadata;
        if(strtolower($varName) == "indexablemetakeys") return $this->_indexableMetaKeys;

        if (isSet($this->_metadata[$varName])) {
            return $this->_metadata[$varName];
        } else {
            return null;
        }
    }

    /**
     * Magic setter for metadata
     * @param $metaName
     * @param $metaValue
     * @return
     */
    public function __set($metaName, $metaValue)
    {
        if (strtolower($metaName) == "metadata") {
            $this->_metadata = $metaValue;
            return;
        }
        if($metaValue == null) unset($this->_metadata[$metaName]);
        else $this->_metadata[$metaName] = $metaValue;
    }

    /**
     * Safe parseUrl implementation
     * @return void
     */
    protected function parseUrl()
    {
        if (strstr($this->_url, "#") !== false) {
            $url = str_replace("#", "__HASH__", $this->_url);
            $this->urlParts = parse_url($url);
            foreach ($this->urlParts as $partKey => $partValue) {
                $this->urlParts[$partKey] = str_replace("__HASH__", "#", $partValue);
            }
        } else {
            $this->urlParts = parse_url($this->_url);
        }

        if (strstr($this->urlParts["scheme"], "ajxp.")!==false) {
            $pServ = AJXP_PluginsService::getInstance();
            $this->_wrapperClassName = $pServ->getWrapperClassName($this->urlParts["scheme"]);
        }
    }

}
