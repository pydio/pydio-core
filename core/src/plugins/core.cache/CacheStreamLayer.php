<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Cache\Core;


use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\SchemeTranslatorWrapper;
use Pydio\Core\Services\CacheService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class CacheStreamLayer
 * StreamWrapper using CacheService to cache data
 * @package Pydio\Cache\Core
 */
class CacheStreamLayer extends SchemeTranslatorWrapper
{
    /**
     * @param $path
     */
    public static function clearStatCache($path) {
        $options = AbstractCacheDriver::getOptionsForNode(new AJXP_Node($path), "stat");

        CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $options["id"]);
    }

    /**
     * @param $path
     */
    public static function clearDirCache($path) {
        $options = AbstractCacheDriver::getOptionsForNode(new AJXP_Node($path), "list");

        CacheService::delete(AJXP_CACHE_SERVICE_NS_NODES, $options["id"]);
    }

    private $currentListingOrig = null;
    private $currentListingRead = null;

    private $currentBufferOptions = null;
    private $currentBuffer = array();

    /**
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function dir_opendir($path, $options) {

        $node = new AJXP_Node($path);
        if($node->getRepositoryId() === 'inbox'){
            return parent::dir_opendir($path, $options);
        }
        $options = AbstractCacheDriver::getOptionsForNode($node, "list");

        if(CacheService::contains(AJXP_CACHE_SERVICE_NS_NODES, $options["id"])) {
            $this->currentListingRead = $this->currentListingOrig = CacheService::fetch(AJXP_CACHE_SERVICE_NS_NODES, $options["id"]);
            return true;
        }

        $this->currentBufferOptions = $options;
        $this->currentBuffer = array();

        return parent::dir_opendir($path, $options);

    }

    /**
     * @return bool|mixed|string
     */
    public function dir_readdir() {

        if($this->currentListingRead !== null) {
            if(count($this->currentListingRead)) return array_shift($this->currentListingRead);
            else return false;
        }

        $value = parent::dir_readdir();
        if($value !== false){
            $this->currentBuffer[] = $value;
        }

        return $value;
    }

    /**
     * Close handle
     */
    public function dir_closedir() {

        if($this->currentListingRead !== null) {
            $this->currentListingRead = $this->currentListingOrig = null;
            return;
        }

        if(isSet($this->currentBufferOptions)) {
            CacheService::save(AJXP_CACHE_SERVICE_NS_NODES, $this->currentBufferOptions["id"], $this->currentBuffer, $this->currentBufferOptions["timelimit"]);
        }

        parent::dir_closedir();
    }

    /**
     * Rewind handle
     * @return bool
     */
    public function dir_rewinddir() {

        if($this->currentListingOrig !== null) {
            $this->currentListingRead = $this->currentListingOrig;
            return true;
        }

        if(isSet($this->currentBuffer)) {
            $this->currentBuffer = array();
        }

        return parent::dir_rewinddir();
    }

    /**
     * @param string $path
     * @param int $flags
     * @return array|bool|mixed
     */
    public function url_stat($path, $flags) {

        $options = AbstractCacheDriver::getOptionsForNode(new AJXP_Node($path), "stat");

        if(CacheService::contains(AJXP_CACHE_SERVICE_NS_NODES, $options["id"])) {
            $stat = CacheService::fetch(AJXP_CACHE_SERVICE_NS_NODES, $options["id"]);
            if(is_array($stat)) return $stat;
        }

        $stat = parent::url_stat($path, $flags);
        CacheService::save(AJXP_CACHE_SERVICE_NS_NODES, $options["id"], $stat, $options["timelimit"]);

        return $stat;
    }
}