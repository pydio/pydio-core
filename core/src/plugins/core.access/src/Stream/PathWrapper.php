<?php
/*
 * Copyright 2007-2015 Abstrium SAS <team (at) pyd.io>
 * This file is part of the Pydio Enterprise Distribution.
 * It is subject to the End User License Agreement that you should have
 * received and accepted along with this distribution.
 */

namespace Pydio\Access\Core\Stream;

defined('AJXP_EXEC') or die('Access not allowed');

use AJXP_SchemeTranslatorWrapper;
use AJXP_Utils;
use CacheService;
use ConfService;

class PathWrapper extends AJXP_SchemeTranslatorWrapper
{
    const CACHE_KEY='PathWrapperParams';
    const CACHE_EXPIRY_TIME = 10000;

    /*
     * @array localParams
     */
    protected static $localParams = [];

    public static function applyInitPathHook($url)
    {
        $params = [];
        $parts = AJXP_Utils::safeParseUrl($url);

        if (! ($params = self::getLocalParams(self::CACHE_KEY . $url)) &&
            ! ($params = CacheService::fetch(self::CACHE_KEY . $url))) {

            // Nothing in cache
            $repositoryId = $parts["host"];
            $repository = ConfService::getRepositoryById($parts["host"]);
            if ($repository == null) {
                throw new Exception("Cannot find repository");
            }

            $configHost = $repository->getOption('HOST');
            $configPath = $repository->getOption('PATH');

            $params['path'] = $parts['path'];
            $params['basepath'] = $configPath;

            $params['itemname'] = basename($params['path']);

            // Special case for root dir
            if (empty($params['path']) || $params['path'] == '/') {
                $params['path'] = '/';
            }

            $params['path'] = dirname($params['path']);
            $params['fullpath'] = rtrim($params['path'], '/') . '/' . $params['itemname'];
            $params['base_url'] = $configHost;

            $params['keybase'] = $repositoryId . $params['fullpath'];
            $params['key'] = md5($params['keybase']);

            self::addLocalParams(self::CACHE_KEY . $url, $params);
            CacheService::save(self::CACHE_KEY . $url, $params, self::CACHE_EXPIRY_TIME);
        }

        $repoData = self::actualRepositoryWrapperData($parts["host"]);
        $repoProtocol = $repoData['protocol'];

        $default = stream_context_get_options(stream_context_get_default());
        $default[$repoProtocol]['path'] = $params;

        $default[$repoProtocol]['client']->setDefaultUrl($configHost);
        stream_context_set_default($default);
    }

    /**
     * @return array
     */
    public function getLocalParams($key)
    {
        return isset(static::$localParams[$key]) ? static::$localParams[$key] : false;
    }

    /**
     * @param $key
     * @param $value
     * @internal param array $localParams
     */
    public function addLocalParams($key, $value)
    {
        static::$localParams[$key] = $value;
    }


}
