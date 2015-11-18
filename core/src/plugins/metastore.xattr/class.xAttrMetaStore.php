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
class xAttrMetaStore extends AJXP_AbstractMetaSource implements MetaStoreProvider
{
    protected $rootPath;

    public function performChecks()
    {
        if (!function_exists("xattr_list")) {
            throw new Exception("The PHP Xattr Extension does not seem to be loaded");
        }
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

    /**
     * @param bool $private
     * @param AJXP_Node $node
     * @return string
     */
    protected function getUserId($private, $node)
    {
        if(!$private) return AJXP_METADATA_SHAREDUSER;
        if($node->hasUser()) return $node->getUser();
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
        $key = $this->getMetaKey($nameSpace, $scope, $this->getUserId($private, $ajxpNode));
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
        $key = $this->getMetaKey($nameSpace, $scope, $this->getUserId($private, $ajxpNode));
        if (!xattr_supported($path)) {
            throw new Exception("Filesystem does not support Extended Attributes!");
        }
        xattr_remove($path, $key);
    }

    /**
     * @abstract
     * @param AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param bool|String $private
     * @param int $scope
     * @return array()
     */
    public function retrieveMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $path = $ajxpNode->getRealFile();
        if(!file_exists($path)) return array();
        if (!xattr_supported($path)) {
            //throw new Exception("Filesystem does not support Extended Attributes!");
            return array();
        }
        if($private === AJXP_METADATA_ALLUSERS){
            $startKey = $this->getMetaKey($nameSpace, $scope, "");
            $arrMeta = array();
            $keyList = xattr_list($path);
            foreach($keyList as $k){
                if(strpos($k, $startKey) === 0){
                    $mData = xattr_get($path, $k);
                    $decData = unserialize(base64_decode($mData));
                    if(is_array($decData)) $arrMeta = array_merge_recursive($arrMeta, $decData);
                }
            }
            return $arrMeta;
        }else{
            $key = $this->getMetaKey($nameSpace, $scope, $this->getUserId($private, $ajxpNode));
            $data = xattr_get($path, $key);
            $data = unserialize(base64_decode($data));
            if( empty($data) || !is_array($data)) return array();
            return $data;
        }
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    public function enrichNode(&$ajxpNode)
    {
    }
}
