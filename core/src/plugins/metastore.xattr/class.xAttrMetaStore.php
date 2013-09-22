<?php
/*
 * Copyright 2007-2012 Charles du Jeu <contact (at) cdujeu.me>
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
 * Use an xattr-enabled filesystem to store metadata
 * @package AjaXplorer_Plugins
 * @subpackage Metastore
 */
class xAttrMetaStore extends AJXP_Plugin implements MetaStoreProvider
{
    /**
     * @var fsAccessDriver
     */
    protected $accessDriver;
    protected $rootPath;

    public function performChecks()
    {
        if (!function_exists("xattr_list")) {
            throw new Exception("The PHP Xattr Extension does not seem to be loaded");
        }
    }

    public function initMeta($accessDriver)
    {
        $this->accessDriver = $accessDriver;
        //$this->rootPath = ConfService::getRepository()->getOption("PATH");
    }

    private function getMetaKey($namespace, $scope, $user)
    {
        return strtolower($namespace."-".$scope."-".$user);
    }

    /**
     * @abstract
     * @return bool
     */
    public function inherentMetaMove()
    {
        return true;
    }

    protected function getUserId($private)
    {
        if(!$private) return AJXP_METADATA_SHAREDUSER;
        if(AuthService::usersEnabled()) return AuthService::getLoggedUser()->getId();
        return "shared";
    }

    /**
     * @abstract
     * @param AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param array $metaData
     * @param bool $private
     * @param int $scope
     */
    public function setMetadata($ajxpNode, $nameSpace, $metaData, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $path = $ajxpNode->getRealFile();
        if(!file_exists($path)) return;
        $key = $this->getMetaKey($nameSpace, $scope, $this->getUserId($private));
        if (!xattr_supported($path)) {
            throw new Exception("Filesystem does not support Extended Attributes!");
        }
        $value = base64_encode(serialize($metaData));
        xattr_set($path, $key, $value);

    }

    /**
     * @abstract
     * @param AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param bool $private
     * @param int $scope
     */
    public function removeMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $path = $ajxpNode->getRealFile();
        if(!file_exists($path)) return;
        $key = $this->getMetaKey($nameSpace, $scope, $this->getUserId($private));
        if (!xattr_supported($path)) {
            throw new Exception("Filesystem does not support Extended Attributes!");
        }
        xattr_remove($path, $key);
    }

    /**
     * @abstract
     * @param AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param bool $private
     * @param int $scope
     */
    public function retrieveMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $path = $ajxpNode->getRealFile();
        if(!file_exists($path)) return array();
        $key = $this->getMetaKey($nameSpace, $scope, $this->getUserId($private));
        if (!xattr_supported($path)) {
            //throw new Exception("Filesystem does not support Extended Attributes!");
            return array();
        }
        $data = xattr_get($path, $key);
        $data = unserialize(base64_decode($data));
        if( empty($data) || !is_array($data)) return array();
        return $data;
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    public function enrichNode(&$ajxpNode)
    {
    }
}
