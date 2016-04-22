<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */

defined('AJXP_EXEC') or die('Access not allowed');


class CacheStreamLayer extends AJXP_SchemeTranslatorWrapper
{
    public static function clearStatCache($path){
        $scheme = parse_url($path, PHP_URL_SCHEME);
        CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, str_replace($scheme . "://", "stat://", $path));
    }
    public static function clearDirCache($path){
        $scheme = parse_url($path, PHP_URL_SCHEME);
        CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, str_replace($scheme . "://", "list://", $path));
    }

    private $currentListingOrig = null;
    private $currentListingRead = null;

    private $currentBufferId = null;
    private $currentBuffer = array();

    protected function computeCacheId($path, $type){

        $node = new AJXP_Node($path);
        $repo = $node->getRepository();
        if($repo == null) return "failed-id";
        $scope = $repo->securityScope();
        $additional = "";
        if($scope === "USER"){
            $additional = AuthService::getLoggedUser()->getId()."@";
        }else if($scope == "GROUP"){
            $additional =  ltrim(str_replace("/", "__", AuthService::getLoggedUser()->getGroupPath()), "__")."@";
        }
        return str_replace("pydio.cache://", $type."://".$additional, $path);

    }

    // Keep listing in cache
    public function dir_opendir($path, $options)
    {
        $id = $this->computeCacheId($path, "list");
        if(CacheService::contains(AJXP_CACHE_SERVICE_NS_NODES, $id)){
            $this->currentListingRead = $this->currentListingOrig = CacheService::fetch(AJXP_CACHE_SERVICE_NS_NODES, $id);
            return true;
        }
        $this->currentBufferId = $id;
        $this->currentBuffer = array();
        return parent::dir_opendir($path, $options);
    }

    public function dir_readdir()
    {
        if($this->currentListingRead !== null){
            if(count($this->currentListingRead)) return array_shift($this->currentListingRead);
            else return false;
        }
        $value = parent::dir_readdir();
        if($value !== false){
            $this->currentBuffer[] = $value;
        }
        return $value;
    }

    public function dir_closedir()
    {
        if($this->currentListingRead !== null){
            $this->currentListingRead = $this->currentListingOrig = null;
            return;
        }
        if(isSet($this->currentBufferId)){
            CacheService::save(AJXP_CACHE_SERVICE_NS_NODES, $this->currentBufferId, $this->currentBuffer);
        }
        parent::dir_closedir();
    }

    public function dir_rewinddir()
    {
        if($this->currentListingOrig !== null){
            $this->currentListingRead = $this->currentListingOrig;
            return true;
        }
        if(isSet($this->currentBuffer)){
            $this->currentBuffer = array();
        }
        return parent::dir_rewinddir();
    }

    public function url_stat($path, $flags)
    {
        $id = $this->computeCacheId($path, "stat");
        if(CacheService::contains(AJXP_CACHE_SERVICE_NS_NODES, $id)){
            $stat = CacheService::fetch(AJXP_CACHE_SERVICE_NS_NODES, $id);
            if(is_array($stat)) return $stat;
        }
        $stat = parent::url_stat($path, $flags);
        CacheService::save(AJXP_CACHE_SERVICE_NS_NODES, $id, $stat);
        return $stat;
    }
}