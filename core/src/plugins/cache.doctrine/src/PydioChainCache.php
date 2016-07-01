<?php
/**
 * Created by PhpStorm.
 * User: ghecquet
 * Date: 29/06/16
 * Time: 10:07
 */

namespace Pydio\Cache\Doctrine\Ext;


use Doctrine\Common\Cache\ChainCache;

class PydioChainCache extends ChainCache implements PatternClearableCache {
    /**
     * @var Redis
     */
    protected $cacheProviders;

    protected $internalNamespace;
    protected $internalNamespaceVersion;


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
        foreach ($this->cacheProviders as $cache) {

            if(!($cache instanceof PatternClearableCache)) {
                break;
            }

            /** @var PatternClearableCache $cache */
            $pattern = $cache->deleteKeysStartingWith($pattern);
        }
    }

    /**
     * @param string $namespace
     * @return void
     */
    public function setNamespace($namespace) {
        foreach ($this->cacheProviders as $cache) {
            $cache->setNamespace($namespace);
        }
    }
}