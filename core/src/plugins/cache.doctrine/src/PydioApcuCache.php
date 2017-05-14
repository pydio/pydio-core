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

use Doctrine\Common\Cache\ApcuCache;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class PydioApcuCache
 * @package Pydio\Cache\Doctrine\Ext
 */
class PydioApcuCache extends ApcuCache implements PatternClearableCache
{
    protected $internalNamespace;
    protected $internalNamespaceVersion;

    /**
     * Prefixes the passed id with the configured namespace value.
     *
     * @param string $id The id to namespace.
     *
     * @return string The namespaced id.
     */
    private function namespacedIdAsPattern($id) {
        
        return sprintf('%s\['.preg_quote($id, "/"), $this->internalNamespace);
    }

    /**
     * @param string $pattern
     * @return bool
     */
    public function deleteKeysStartingWith($pattern) {
        $pattern = '/^'.$this->namespacedIdAsPattern($pattern).'/';
        //SAMPLE /^pydio-unique-id_nodes_\[list\:\/\/1/
        if(class_exists("\\APCIterator")){
            $iterator = new \APCIterator('user', $pattern);
        }else if(class_exists("\\APCUIterator")){
            $iterator = new \APCUIterator($pattern);
        }else{
            error_log("Trying to delete cache entry using pattern, but could not find either APCIterator or APCUIterator");
            return;
        }
        foreach ($iterator as $data) {
            $this->doDelete($data['key']);
        }
    }

    /**
     * @param string $namespace
     * @return void
     */
    public function setNamespace($namespace)
    {
        parent::setNamespace($namespace);
        $this->internalNamespace = $namespace;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetchMultiple(array $keys)
    {
        $test = apcu_fetch($keys);
        if($test === false) return [];
        else return $test;
    }


}