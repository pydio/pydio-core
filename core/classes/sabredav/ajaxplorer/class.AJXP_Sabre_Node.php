<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.core
 */
class AJXP_Sabre_Node implements Sabre_DAV_INode, Sabre_DAV_IProperties
{

    /**
     * @var Repository
     */
    protected $repository;
    /**
     * @var AjxpWebdavProvider
     */
    protected $accessDriver;

    /**
     * @var String
     */
    protected $url;
    protected $path;

    function __construct($path, $repository, $accessDriver = null){
        $this->repository = $repository;
        if($accessDriver == null){
            ConfService::switchRootDir($repository->getUniqueId());
            ConfService::getConfStorageImpl();
            $this->accessDriver = ConfService::loadRepositoryDriver();
            if(!$this->accessDriver instanceof AjxpWebdavProvider){
                throw new ezcBaseFileNotFoundException( $this->repository->getUniqueId() );
            }
            $this->accessDriver->detectStreamWrapper(true);
        }else{
            $this->accessDriver = $accessDriver;
        }
        $this->path = $path;
        $this->url = $this->accessDriver->getRessourceUrl($path);
    }

    /**
     * Deleted the current node
     *
     * @return void
     */
    function delete(){



        AJXP_Logger::debug("Delete? ".$this->path);
        ob_start();
        try{
            AJXP_Controller::findActionAndApply("delete", array(
                "dir"       => dirname($this->path),
                "file_0"    => $this->path
            ), array());
        }catch(Exception $e){

        }
        $result = ob_get_flush();
        AJXP_Logger::debug("RESULT : ".$result);

    }

    /**
     * Returns the name of the node.
     *
     * This is used to generate the url.
     *
     * @return string
     */
    function getName(){
        return basename($this->url);
    }

    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    function setName($name){
        AJXP_Controller::findActionAndApply("rename", array(
            "filename_new"      => $name,
            "dir"               => dirname($this->path),
            "file"              => $this->path
        ), array());

    }

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getLastModified(){
        return filemtime($this->url);
    }


    /**
     * Updates properties on this node,
     *
     * The properties array uses the propertyName in clark-notation as key,
     * and the array value for the property value. In the case a property
     * should be deleted, the property value will be null.
     *
     * This method must be atomic. If one property cannot be changed, the
     * entire operation must fail.
     *
     * If the operation was successful, true can be returned.
     * If the operation failed, false can be returned.
     *
     * Deletion of a non-existent property is always successful.
     *
     * Lastly, it is optional to return detailed information about any
     * failures. In this case an array should be returned with the following
     * structure:
     *
     * array(
     *   403 => array(
     *      '{DAV:}displayname' => null,
     *   ),
     *   424 => array(
     *      '{DAV:}owner' => null,
     *   )
     * )
     *
     * In this example it was forbidden to update {DAV:}displayname.
     * (403 Forbidden), which in turn also caused {DAV:}owner to fail
     * (424 Failed Dependency) because the request needs to be atomic.
     *
     * @param array $mutations
     * @return bool|array
     */
    function updateProperties($mutations){

        AJXP_Logger::debug("UPDATE PROPERTIES", $mutations);
        $metaStore = $this->getMetastore();

        foreach($mutations as $p => $data){
            list($namespace, $pname) = explode("}", ltrim($p, "{"));
            if($namespace != "DAV:" && $metaStore){
                list($pname, $pvalue) = explode("=", $pname);
                $data = $metaStore->retrieveMetadata(new AJXP_Node($this->url), "SABRE_DAV:".$namespace, false, AJXP_METADATA_SCOPE_REPOSITORY);
                $data[$pname] = $pvalue;
                $metaStore->setMetadata(new AJXP_Node($this->url), "SABRE_DAV:".$namespace, $data, false, AJXP_METADATA_SCOPE_REPOSITORY);
                AJXP_Logger::debug("UPDATED Metadata for ". $p, $data);
            }
        }

        return true;
    }

    /**
     * Returns a list of properties for this nodes.
     *
     * The properties list is a list of propertynames the client requested,
     * encoded in clark-notation {xmlnamespace}tagname
     *
     * If the array is empty, it means 'all properties' were requested.
     *
     * @param array $properties
     * @return void
     */
    function getProperties($properties){

        $metaStore = $this->getMetastore();
        $arr = array();
        foreach($properties as $p){
            list($namespace, $pname) = explode("}", ltrim($p, "{"));
            if($namespace != "DAV:" && $metaStore){
                $data = $metaStore->retrieveMetadata(new AJXP_Node($this->url), "SABRE_DAV:".$namespace, false, AJXP_METADATA_SCOPE_REPOSITORY);
                if(!isSet($arr[200]))$arr[200] = array();
                $arr[200][$pname] = $data[$pname];
                AJXP_Logger::debug("Metadata for ". $p, $data);
            }
        }
        return $arr;
    }

    /**
     * @return MetaStoreProvider|bool
     */
    protected function getMetastore(){
        $metaStore = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if($metaStore === false) return false;
        $metaStore->initMeta($this->accessDriver);
        return $metaStore;
    }


}
