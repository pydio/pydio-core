<?php
/*
 * Copyright 2007-2015 Abstrium SAS <team (at) pyd.io>
 * This file is part of the Pydio Enterprise Distribution.
 * It is subject to the End User License Agreement that you should have
 * received and accepted along with this distribution.
 */

namespace Pydio\Access\Core\Stream;

defined('AJXP_EXEC') or die('Access not allowed');

use Pydio\Access\Core\AJXP_SchemeTranslatorWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Auth\Core\AJXP_Safe;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Utils\Utils;

class AuthWrapper extends AJXP_SchemeTranslatorWrapper
{
    public static function applyInitPathHook($url, $context = 'core') {
        $urlParts = Utils::safeParseUrl($url);

        $repository = RepositoryService::getRepositoryById($urlParts["host"]);
        if ($repository == null) {
            throw new \Exception("Cannot find repository");
        }

        $credentials = AJXP_Safe::tryLoadingCredentialsFromSources(AJXP_Node::contextFromUrl($url));
        $user = $credentials["user"];
        $password = $credentials["password"];

        if ($user == "") {
            throw new \Exception("Cannot find user/pass for Remote Access!");
        }

        $repoData = self::actualRepositoryWrapperData($urlParts["host"]);
        $repoProtocol = $repoData['protocol'];

        $default = stream_context_get_options(stream_context_get_default());

        $auth = [
            'user' => $user,
            'password' => $password
        ];

        $default[$repoProtocol]['auth'] = $auth;
        $default[$repoProtocol]['client']->setAuth($auth);

        stream_context_set_default($default);
    }


}
