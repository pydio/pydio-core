<?php
/*
 * Copyright 2007-2017 Charles du Jeu <contact (at) cdujeu.me>
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
namespace Pydio\Access\Metastore\Implementation;

use Pydio\Access\Core\Model\AJXP_Node;

use Pydio\Access\Meta\Core\AbstractMetaSource;
use Pydio\Access\Metastore\Core\IMetaStoreProvider;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die( 'Access not allowed');


/**
 * Class XAttrMetaStore
 * @package Pydio\Access\Metastore\Implementation
 */
class XAttrMetaStore extends AbstractMetaSource implements IMetaStoreProvider
{
    protected $rootPath;

    public function performChecks()
    {
        if (!function_exists("xattr_list")) {
            throw new \Exception("The PHP Xattr Extension does not seem to be loaded");
        }
    }

    /**
     * @param $namespace
     * @param $scope
     * @param $user
     * @return string
     */
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
        if($node->hasUser()) return $node->getUserId();
        return "shared";
    }

    /**
     * @abstract
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
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
        if (!\xattr_supported($path)) {
            throw new \Exception("Filesystem does not support Extended Attributes!");
        }
        $value = base64_encode(serialize($metaData));
        \xattr_set($path, $key, $value);

    }

    /**
     * @abstract
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param bool $private
     * @param int $scope
     * @return array|void
     * @throws \Exception
     */
    public function removeMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $path = $ajxpNode->getRealFile();
        if(!file_exists($path)) return;
        $key = $this->getMetaKey($nameSpace, $scope, $this->getUserId($private, $ajxpNode));
        if (!\xattr_supported($path)) {
            throw new \Exception("Filesystem does not support Extended Attributes!");
        }
        \xattr_remove($path, $key);
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
        if (!\xattr_supported($path)) {
            //throw new Exception("Filesystem does not support Extended Attributes!");
            return array();
        }
        if($private === AJXP_METADATA_ALLUSERS){
            $startKey = $this->getMetaKey($nameSpace, $scope, "");
            $arrMeta = array();
            $keyList = \xattr_list($path);
            foreach($keyList as $k){
                if(strpos($k, $startKey) === 0){
                    $mData = \xattr_get($path, $k);
                    $decData = StringHelper::safeUnserialize(base64_decode($mData));
                    if(is_array($decData)) $arrMeta = array_merge_recursive($arrMeta, $decData);
                }
            }
            return $arrMeta;
        }else{
            $key = $this->getMetaKey($nameSpace, $scope, $this->getUserId($private, $ajxpNode));
            $data = \xattr_get($path, $key);
            $data = StringHelper::safeUnserialize(base64_decode($data));
            if( empty($data) || !is_array($data)) return array();
            return $data;
        }
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @return void
     */
    public function enrichNode(&$ajxpNode)
    {
    }
}
