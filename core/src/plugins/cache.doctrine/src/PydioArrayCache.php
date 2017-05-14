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
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\CacheProvider;

/**
 * Array cache driver with ability to clear keys
 */
class PydioArrayCache extends CacheProvider implements PatternClearableCache
{
    /**
     * @var array[] $data each element being a tuple of [$data, $expiration], where the expiration is int|bool
     */
    private $data = [];

    /**
     * @var int
     */
    private $hitsCount = 0;

    /**
     * @var int
     */
    private $missesCount = 0;

    /**
     * @var int
     */
    private $upTime;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->upTime = time();
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id)
    {
        if (! $this->doContains($id)) {
            $this->missesCount += 1;

            return false;
        }

        $this->hitsCount += 1;

        return $this->data[$id][0];
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id)
    {
        if (! isset($this->data[$id])) {
            return false;
        }

        $expiration = $this->data[$id][1];

        if ($expiration && $expiration < time()) {
            $this->doDelete($id);

            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifeTime = 0)
    {
        $this->data[$id] = [$data, $lifeTime ? time() + $lifeTime : false];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id)
    {
        unset($this->data[$id]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush()
    {
        $this->data = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats()
    {
        return [
            Cache::STATS_HITS             => $this->hitsCount,
            Cache::STATS_MISSES           => $this->missesCount,
            Cache::STATS_UPTIME           => $this->upTime,
            Cache::STATS_MEMORY_USAGE     => null,
            Cache::STATS_MEMORY_AVAILABLE => null,
        ];
    }

    /**
     * @param string $pattern
     * @return bool
     */
    public function deleteKeysStartingWith($pattern) {

        $pat = "[" . $pattern;
        foreach($this->data as $key => $value){
            if(substr($key, 0, strlen($pat)) === $pat){
                unset($this->data[$key]);
            }
        }

    }
}
