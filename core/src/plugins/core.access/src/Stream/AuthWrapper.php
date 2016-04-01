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

class AuthWrapper extends AJXP_SchemeTranslatorWrapper
{
    public static function applyInitPathHook($url, $context = 'core') {
        $urlParts = AJXP_Utils::safeParseUrl($url);

        $repository = ConfService::getRepositoryById($urlParts["host"]);
        if ($repository == null) {
            throw new Exception("Cannot find repository");
        }

        $credentials = AJXP_Safe::tryLoadingCredentialsFromSources($urlParts, $repository);
        $user = $credentials["user"];
        $password = $credentials["password"];

        if ($user == "") {
            throw new Exception("Cannot find user/pass for Remote Access!");
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
