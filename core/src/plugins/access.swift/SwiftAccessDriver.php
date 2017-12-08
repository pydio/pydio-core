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
namespace Pydio\Access\Driver\StreamProvider\Swift;

defined('AJXP_EXEC') or die( 'Access not allowed');
use DOMNode;
use \OpenStack\Bootstrap;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\RecycleBinManager;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;

/**
 * Plugin to access a webdav enabled server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class SwiftAccessDriver extends FsAccessDriver
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
            throw new \Exception("Php version 5.3+ is required for this plugin (must support namespaces)");
        }
        if(!file_exists($this->getBaseDir()."/openstack-sdk-php/vendor/autoload.php")){
            throw new \Exception("You must download the openstack-sdk-php and install it with Composer for this plugin");
        }

    }

    /**
     * @param ContextInterface $contextInterface
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        require_once($this->getBaseDir()."/openstack-sdk-php/vendor/autoload.php");

        Bootstrap::useStreamWrappers();

        Bootstrap::setConfiguration(array(
            'username'              => $this->repository->getContextOption($contextInterface, "USERNAME"),
            'password'              => $this->repository->getContextOption($contextInterface, "PASSWORD"),
            'tenantid'              => $this->repository->getContextOption($contextInterface, "TENANT_ID"),
            'endpoint'              => $this->repository->getContextOption($contextInterface, "ENDPOINT"),
            'openstack.swift.region'=> $this->repository->getContextOption($contextInterface, "REGION"),
            'transport.ssl.verify'  => false
        ));


        $recycle    = $this->repository->getContextOption($contextInterface, "RECYCLE_BIN");
        ConfService::setConf("PROBE_REAL_SIZE", false);
        $this->urlBase = $contextInterface->getUrlBase();
        if ($recycle != "") {
            RecycleBinManager::init($contextInterface->getUrlBase(), "/".$recycle);
        }
        foreach ($this->exposeRepositoryOptions as $paramName){
            $this->exposeConfigInManifest($paramName, $contextInterface->getRepository()->getContextOption($contextInterface, $paramName));
        }

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
     * @param AJXP_Node $node
     * @return bool
     */
    public function isWriteable(AJXP_Node $node)
    {
        return true;
    }

    /**
     * @param AJXP_Node $node See parent function
     * @param bool|false $parentNode
     * @param bool|false $details
     */
    public function loadNodeInfo(&$node, $parentNode = false, $details = false)
    {
        parent::loadNodeInfo($node, $parentNode, $details);
        if (!$node->isLeaf()) {
            $node->setLabel(rtrim($node->getLabel(), "/"));
        }
    }

    /**
     * @return bool
     */
    public function isRemote()
    {
        return true;
    }

    /**
     * @param ContextInterface $ctx
     * @param array $httpVars
     * @return array
     * @throws \Exception
     */
    public function makeSharedRepositoryOptions(ContextInterface $ctx, $httpVars)
    {
        $newOptions = parent::makeSharedRepositoryOptions($ctx, $httpVars);
        $newOptions["CONTAINER"] = $this->repository->getContextOption($ctx, "CONTAINER");
        return $newOptions;
    }

}
