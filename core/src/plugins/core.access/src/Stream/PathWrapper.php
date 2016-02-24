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
use AJXP_Safe;
use AJXP_Utils;
use ConfService;

class PathWrapper extends AJXP_SchemeTranslatorWrapper
{
    public static function initPath($url)
    {
        $params = [];
        $parts = AJXP_Utils::safeParseUrl($url);

        $repository = ConfService::getRepositoryById($parts["host"]);
        if ($repository == null) {
            throw new Exception("Cannot find repository");
        }

        $configHost = $repository->getOption('HOST');
        $configPath = $repository->getOption('PATH');

        $params['path'] = $parts['path'];
        $params['basepath'] = $configPath;
        $params['fullpath'] = dirname($configPath . $params['path']);
        $params['itemname'] = basename($params['path']);

        // Special case for root dir
        if (empty($params['path']) || $params['path'] == '/') {
            $params['fullpath'] = $params['basepath'];
            $params['path'] = '/';
        }

        $params['path'] = dirname($params['path']);

        $params['base_url'] = $configHost;

        $repoData = self::actualRepositoryWrapperData($parts["host"]);
        $repoProtocol = $repoData['protocol'];

        $default = stream_context_get_options(stream_context_get_default());
        $default[$repoProtocol]['path'] = $params;

        $default[$repoProtocol]['client']->setDefaultUrl($configHost);
        stream_context_set_default($default);
    }

}
