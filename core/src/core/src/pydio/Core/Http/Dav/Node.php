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
 */
namespace Pydio\Core\Http\Dav;

use \Sabre;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\IAjxpWrapperProvider;
use Pydio\Access\Core\Model\Repository;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Controller\Controller;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Access\Metastore\Core\IMetaStoreProvider;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package Pydio
 * @subpackage SabreDav
 */
class Node implements Sabre\DAV\INode, Sabre\DAV\IProperties
{

    /** @var  ContextInterface */
    protected $context;


    /**
     * @var IAjxpWrapperProvider
     */
    protected $accessDriver;

    /**
     * @var String
     */
    protected $url;
    protected $path;

    /**
     * Node constructor.
     * @param $path
     * @param ContextInterface $context
     */
    public function __construct($path, $context)
    {
        $this->context = $context;
        $this->path = $path;
    }

    /**
     * @return ContextInterface
     */
    public function getContext(){
        return $this->context;
    }

    /**
     * @param Repository $repository
     */
    public function updateRepository($repository){
        $this->repository = $repository;
    }

    /**
     * @return IAjxpWrapperProvider
     * @throws \Sabre\DAV\Exception\NotFound
     */
    public function getAccessDriver()
    {
        $driver = $this->context->getRepository()->getDriverInstance($this->context);
        if(empty($driver)){
            $n = new AJXP_Node($this->getUrl());
            return $n->getDriver();
        }else{
            return $driver;
        }
    }

    /**
     * @return String
     */
    public function getUrl()
    {
        if (!isSet($this->url)) {
            $this->url = $this->context->getUrlBase().$this->path;
        }
        return $this->url;
    }

    /**
     * Deleted the current node
     *
     * @return void
     */
    public function delete()
    {
        ob_start();
        try {
            $request = new \Zend\Diactoros\ServerRequest();
            $request = $request
                ->withParsedBody([
                    "dir"       => dirname($this->path),
                    "file_0"    => $this->path
                ])
                ->withAttribute("action", "delete")
                ->withAttribute("api", "session")
                ->withAttribute("ctx", $this->context);
            Controller::run($request);
        } catch (\Exception $e) {

        }
        ob_get_flush();
        $this->putResourceData(array());

    }

    /**
     * Returns the name of the node.
     *
     * This is used to generate the url.
     *
     * @return string
     */
    public function getName()
    {
        return basename($this->getUrl());
    }

    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name)
    {
        $data = $this->getResourceData();
        ob_start();
        $request = new \Zend\Diactoros\ServerRequest();
        $request = $request
            ->withParsedBody([
                "filename_new"      => $name,
                "dir"               => dirname($this->path),
                "file"              => $this->path
            ])
            ->withAttribute("action", "rename")
            ->withAttribute("api", "session")
            ->withAttribute("ctx", $this->context)
        ;
        Controller::run($request);
        ob_get_flush();
        $this->putResourceData(array());
        $this->putResourceData($data, dirname($this->getUrl())."/".$name);
    }

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    public function getLastModified()
    {
        return filemtime($this->getUrl());
    }


    /**
     * Updates properties on this node,
     *
     * @param array $properties
     * @see Sabre\DAV\IProperties::updateProperties
     * @return bool|array
     */
    public function updateProperties($properties)
    {
        $resourceData = $this->getResourceData();

        foreach ($properties as $propertyName=>$propertyValue) {

            // If it was null, we need to delete the property
            if (is_null($propertyValue)) {
                if (isset($resourceData['properties'][$propertyName])) {
                    unset($resourceData['properties'][$propertyName]);
                }
            } else {
                $resourceData['properties'][$propertyName] = $propertyValue;
            }

        }

        //AJXP_Logger::debug("Saving Data", $resourceData);
        $this->putResourceData($resourceData);
        return true;
    }

    /**
     * Returns a list of properties for this nodes.;
     *
     * The properties list is a list of propertynames the client requested, encoded as xmlnamespace#tagName, for example: http://www.example.org/namespace#author
     * If the array is empty, all properties should be returned
     *
     * @param array $properties
     * @return array
     */
    public function getProperties($properties)
    {
        $resourceData = $this->getResourceData();

        // if the array was empty, we need to return everything
        if (!$properties) return $resourceData['properties'];

        $props = array();
        foreach ($properties as $property) {
            if (isset($resourceData['properties'][$property])) $props[$property] = $resourceData['properties'][$property];
        }

        return $props;

    }

    /**
     * Metadata manager
     * @param $array
     * @param null $newURL
     */
    protected function putResourceData($array, $newURL = null)
    {
        $metaStore = $this->getMetastore();
        if ($metaStore != false) {
            $metaStore->setMetadata(new AJXP_Node(($newURL!=null?$newURL:$this->getUrl())), "SABRE_DAV", $array, false, AJXP_METADATA_SCOPE_GLOBAL);
        }

    }
    
    /**
     * Metadata manager
     * @return array
     */
    protected function getResourceData()
    {
        $metaStore = $this->getMetastore();
        $data = array();
        if ($metaStore != false) {
            $data = $metaStore->retrieveMetadata(new AJXP_Node($this->getUrl()), "SABRE_DAV", false, AJXP_METADATA_SCOPE_GLOBAL);
        }
        if (!isset($data['properties'])) $data['properties'] = array();
        return $data;
    }


    /**
     * @return IMetaStoreProvider|bool
     */
    protected function getMetastore()
    {
        /** @var IMetaStoreProvider $metaStore */
        $metaStore = PluginsService::getInstance($this->context)->getUniqueActivePluginForType("metastore");
        if($metaStore === false) return false;
        $metaStore->initMeta($this->context, $this->getAccessDriver());
        return $metaStore;
    }


}
