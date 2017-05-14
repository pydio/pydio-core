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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Cache\Doctrine\Ext;


use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\ChainCache;

/**
 * Class PydioChainCache
 * @package Pydio\Cache\Doctrine\Ext
 */
class PydioChainCache extends ChainCache implements PatternClearableCache {
    /**
     * @var CacheProvider[]
     */
    protected $cacheProviders;

    protected $internalNamespace;
    protected $internalNamespaceVersion;

    /**
     * PydioChainCache constructor.
     * @param array|\Doctrine\Common\Cache\CacheProvider[] $cacheProviders
     */
    public function __construct($cacheProviders)
    {
        parent::__construct($cacheProviders);

        $this->cacheProviders = $cacheProviders;
    }

    /**
     * @param string $pattern
     * @return bool
     */
    public function deleteKeysStartingWith($pattern) {
        $result = false;
        foreach ($this->cacheProviders as $cache) {

            if(!($cache instanceof PatternClearableCache)) {
                continue;
            }

            /** @var PatternClearableCache $cache */
            $result &= $cache->deleteKeysStartingWith($pattern);
        }
        return $result;
    }

    /**
     * @param string $namespace
     * @return void
     */
    public function setNamespace($namespace) {
        parent::setNamespace($namespace);
        foreach ($this->cacheProviders as $cache) {
            $cache->setNamespace($namespace);
        }
    }

    /**
     * @return mixed
     */
    public function allProvidersSupportPatternDeletion(){
        return array_reduce($this->cacheProviders, function($carry, $item){
            return $carry && $item instanceof PatternClearableCache;
        }, true);
    }

    /**
     * @return bool
     */
    public function oneProviderRequiresHttpDeletion(){
        foreach($this->cacheProviders as $provider){
            if($provider instanceof PydioApcuCache) return true;
        }
        return false;
    }
}