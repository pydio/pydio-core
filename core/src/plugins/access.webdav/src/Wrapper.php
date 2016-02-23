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
 *
 */
namespace Pydio\Access\WebDAV;

defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(AJXP_INSTALL_PATH."/plugins/access.fs/class.fsAccessWrapper.php");

/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class Wrapper extends \fsAccessWrapper
{

    const PROTOCOL = "pydio.dav";

    /**
     * Initialize the stream from the given path.
     * Concretely, transform ajxp.dav:// into dav://
     *
     * @param string $path
     * @return mixed Real path or -1 if currentListing contains the listing : original path converted to real path
     */
    protected static function initPath($path, $streamType, $storeOpenContext = false, $skipZip = false)
    {
        $url = parse_url($path);
        $repoId = $url["host"];
        $repository = \ConfService::getRepositoryById($repoId);

        if (!isSet($repository)) {
            $e = new Exception("Cannot find repository with id ".$repoId);
            self::$lastException = $e;
            throw $e;
        }

        $credentials = \AJXP_Safe::tryLoadingCredentialsFromSources($url, $repository);

        $default = stream_context_get_options(stream_context_get_default());

        $default[self::PROTOCOL]['user'] = $credentials['user'];
        $default[self::PROTOCOL]['password'] = $credentials['password'];

        if (!isset($credentials['user'])) {
            throw new \AJXP_Exception("Cannot find user/pass for Remote server access!");
        }

        $client = $default[self::PROTOCOL]['client'];
        if (!isset($client)) {
            throw new Exception("Client not configured");
        }

        $client->setConfig('defaults/request_options/auth', [$username, $password]);
        stream_context_set_default($default);

        $url['scheme'] = self::PROTOCOL;
        return \AJXP_Utils::getSanitizedUrl($url);
    }

}
