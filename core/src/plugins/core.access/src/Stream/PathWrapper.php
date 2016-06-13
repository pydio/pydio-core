<?php
/*
 * Copyright 2007-2015 Abstrium SAS <team (at) pyd.io>
 * This file is part of the Pydio Enterprise Distribution.
 * It is subject to the End User License Agreement that you should have
 * received and accepted along with this distribution.
 */

namespace Pydio\Access\Core\Stream;

use Pydio\Access\Core\AJXP_SchemeTranslatorWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Utils\Utils;

defined('AJXP_EXEC') or die('Access not allowed');


class PathWrapper extends AJXP_SchemeTranslatorWrapper
{
    const CACHE_KEY='PathWrapperParams';

    /*
     * @array localParams
     */
    protected static $localParams = [];

    public static function applyInitPathHook($url, $context = 'core')
    {
        return;
        $params = [];
        $parts = Utils::safeParseUrl($url);

        if (! ($params = self::getLocalParams(self::CACHE_KEY . $url)) ) {

            // Nothing in cache
            $repositoryId = $parts["host"];
            $repository = RepositoryService::getRepositoryById($parts["host"]);
            if ($repository == null) {
                throw new \Exception("Cannot find repository");
            }
            $ctx = AJXP_Node::contextFromUrl($url);
            $configHost = $repository->getContextOption($ctx, 'HOST');
            $configPath = $repository->getContextOption($ctx, 'PATH');

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
