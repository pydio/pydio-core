<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * Generic caching system that can be used by the plugins. Use the static factory getItem() to generate
 * a actual cached instance.
 * @package Pydio
 * @subpackage Core
 */
class AJXP_Cache
{
    private static $instance;

    protected $cacheDir;
    protected $cacheId;
    protected $masterFile;
    protected $dataCallback;
    protected $idComputerCallback;

    /**
     * Create an AJXP_Cache instance
     * @param string $pluginId
     * @param string $filepath
     * @param Function $dataCallback A function to generate the data cache. If no callback provided, will simply use the content of the master item as the cache data
     * @param string $idComputerCallback A function to generate the ID of the cache. If not provided, will generate a random hash
     * @return AJXP_Cache
     */
    public static function getItem($pluginId, $filepath, $dataCallback=null, $idComputerCallback = null)
    {
        if ($dataCallback == null) {
            $dataCallback = array("AJXP_Cache", "simpleCopy");
        }
        return new AJXP_Cache($pluginId,$filepath, $dataCallback, $idComputerCallback);
    }

    /**
     * The default dataCallback
     * @static
     * @param string $master
     * @param string $target
     * @return void
     */
    public static function simpleCopy($master, $target)
    {
        file_put_contents($target, file_get_contents($master));
    }

    /**
     * Clear a cache item associated with the master filepath
     * @static
     * @param String $pluginId
     * @param String $filepath
     * @return void
     */
    public static function clearItem($pluginId, $filepath)
    {
        $inst = new AJXP_Cache($pluginId,$filepath, false);
        if (file_exists($inst->getId())) {
            @unlink($inst->getId());
        }
    }

    /**
     * Actual Cache object. Should not be used directly, but via the factory static method getItem()
     * @param $pluginId
     * @param $filepath
     * @param $dataCallback
     * @param null $idComputerCallback
     * @return void
     */
    public function AJXP_Cache($pluginId, $filepath, $dataCallback, $idComputerCallback = NULL)
    {
        $this->cacheDir = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR);
        $this->masterFile = $filepath;
        $this->dataCallback = $dataCallback;
        if ($idComputerCallback != null) {
            $this->idComputerCallback = $idComputerCallback;
        }
        $this->cacheId = $this->buildCacheId($pluginId, $filepath);
    }

    /**
     * Load the actual data, either from the cache or from the master, and save it in the cache if necessary.
     * @return string
     */
    public function getData()
    {
        if (!$this->hasCachedVersion()) {
            $result = call_user_func($this->dataCallback, $this->masterFile, $this->cacheId);
            if ($result !== false) {
                $this->touch();
            }
        } else {

        }
        return file_get_contents($this->cacheId);
    }

    /**
     * Check if the cache dir is writeable
     * @return bool
     */
    public function writeable()
    {
        return is_dir($this->cacheDir) && is_writeable($this->cacheDir);
    }

    /**
     * The unique ID of the item
     * @return string
     */
    public function getId()
    {
        return $this->cacheId;
    }

    /**
     * Check whether a cached version of the master file exists or not
     * @return bool
     */
    public function hasCachedVersion()
    {
        $modifTime = filemtime($this->masterFile);
        if (file_exists($this->cacheId) && filemtime($this->cacheId) >= $modifTime) {
            return true;
        }
        return false;
    }

    /**
     * Refresh the cached version modif date to the master modif date
     * @return void
     */
    public function touch()
    {
        @touch($this->cacheId, filemtime($this->masterFile));
    }

    /**
     * Generate an ID for the cached file, either using the idComputerCallback, or a simple hash function.
     * @param $pluginId
     * @param $filePath
     * @return string
     */
    protected function buildCacheId($pluginId, $filePath)
    {
        if (!is_dir($this->cacheDir."/".$pluginId)) {
            mkdir($this->cacheDir."/".$pluginId, 0755);
        }
        $root =  $this->cacheDir ."/".$pluginId."/";
        if (isSet($this->idComputerCallback)) {
            $hash = call_user_func($this->idComputerCallback, $filePath);
        } else {
            $info = pathinfo($filePath);
            $hash = md5($filePath).(!empty($info["extension"])?".".$info["extension"]:"");
        }
        return $root.$hash;
    }


}
