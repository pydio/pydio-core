<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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

namespace Pydio\OCS\Server\Dav;

defined('AJXP_EXEC') or die('Access not allowed');

use Sabre;

class Server
{
    public function start($baseUri = "/"){

        $rootCollection =  new \AJXP_Sabre_Collection("/", null, null);
        $server = new Sabre\DAV\Server($rootCollection);
        $server->setBaseUri($baseUri);

        $authBackend = new AuthSharingBackend($rootCollection);
        $authPlugin = new Sabre\DAV\Auth\Plugin($authBackend, \ConfService::getCoreConf("WEBDAV_DIGESTREALM"));
        $server->addPlugin($authPlugin);

        if (!is_dir(AJXP_DATA_PATH."/plugins/server.sabredav")) {
            mkdir(AJXP_DATA_PATH."/plugins/server.sabredav", 0755);
            $fp = fopen(AJXP_DATA_PATH."/plugins/server.sabredav/locks", "w");
            fwrite($fp, "");
            fclose($fp);
        }

        $lockBackend = new Sabre\DAV\Locks\Backend\File(AJXP_DATA_PATH."/plugins/server.sabredav/locks");
        $lockPlugin = new Sabre\DAV\Locks\Plugin($lockBackend);
        $server->addPlugin($lockPlugin);

        if (\ConfService::getCoreConf("WEBDAV_BROWSER_LISTING")) {
            $browerPlugin = new \AJXP_Sabre_BrowserPlugin((isSet($repository)?$repository->getDisplay():null));
            $extPlugin = new Sabre\DAV\Browser\GuessContentType();
            $server->addPlugin($browerPlugin);
            $server->addPlugin($extPlugin);
        }
        try {
            $server->exec();
        } catch ( \Exception $e ) {
            \AJXP_Logger::error(__CLASS__,"Exception",$e->getMessage());
        }
    }

}