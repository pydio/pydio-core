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
namespace Pydio\Core\Http;

use AJXP_Sabre_AuthBackendBasic;
use AJXP_Sabre_AuthBackendDigest;
use AJXP_Sabre_BrowserPlugin;
use AJXP_Sabre_Collection;
use AJXP_Sabre_RootCollection;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Log\Core\AJXP_Logger;
use Sabre\DAV as DAV;

defined('AJXP_EXEC') or die('Access not allowed');


class DAVServer
{
    public static function handleRoute($baseURI){

        ConfService::init();
        ConfService::start();
        
        if (!ConfService::getCoreConf("WEBDAV_ENABLE")) {
            die('You are not allowed to access this service');
        }

        PluginsService::getInstance()->initActivePlugins();

        spl_autoload_register(function ($className){
            if (strpos($className,'AJXP_Sabre_')===0) {
                include AJXP_BIN_FOLDER. '/sabredav/ajaxplorer/class.' . $className . '.php';
            }
        });
        include AJXP_BIN_FOLDER.'/sabredav/lib/Sabre/autoload.php';

        $requestUri = $_SERVER["REQUEST_URI"];
        if (substr($requestUri, 0, strlen($baseURI)) != $baseURI)
        {
            $baseURI = substr($requestUri, 0, stripos($requestUri, $baseURI)) . $baseURI;
        }
        $end = trim(substr($requestUri, strlen($baseURI."/")));
        $rId = null;
        if ((!empty($end) || $end ==="0") && $end[0] != "?") {

            $parts = explode("/", $end);
            $pathBase = $parts[0];
            $repositoryId = $pathBase;

            $repository = ConfService::getRepositoryById($repositoryId);
            if ($repository == null) {
                $repository = ConfService::getRepositoryByAlias($repositoryId);
                if ($repository != null) {
                    $repositoryId = $repository->getId();
                }
            }
            if ($repository == null) {
                AJXP_Logger::debug("not found, dying $repositoryId");
                die('You are not allowed to access this service');
            }

            $rId = $repositoryId;
            $rootDir =  new AJXP_Sabre_Collection("/", $repository, null);
            $server = new DAV\Server($rootDir);
            $server->setBaseUri($baseURI."/".$pathBase);


        } else {

            $rootDir = new AJXP_Sabre_RootCollection("root");
            $server = new DAV\Server($rootDir);
            $server->setBaseUri($baseURI);

        }

        if((AJXP_Sabre_AuthBackendBasic::detectBasicHeader() || ConfService::getCoreConf("WEBDAV_FORCE_BASIC"))
            && ConfService::getAuthDriverImpl()->getOptionAsBool("TRANSMIT_CLEAR_PASS")){
            $authBackend = new AJXP_Sabre_AuthBackendBasic($rId);
        } else {
            $authBackend = new AJXP_Sabre_AuthBackendDigest($rId);
        }
        $authPlugin = new DAV\Auth\Plugin($authBackend, ConfService::getCoreConf("WEBDAV_DIGESTREALM"));
        $server->addPlugin($authPlugin);

        if (!is_dir(AJXP_DATA_PATH."/plugins/server.sabredav")) {
            mkdir(AJXP_DATA_PATH."/plugins/server.sabredav", 0755);
            $fp = fopen(AJXP_DATA_PATH."/plugins/server.sabredav/locks", "w");
            fwrite($fp, "");
            fclose($fp);
        }

        $lockBackend = new DAV\Locks\Backend\File(AJXP_DATA_PATH."/plugins/server.sabredav/locks");
        $lockPlugin = new DAV\Locks\Plugin($lockBackend);
        $server->addPlugin($lockPlugin);

        if (ConfService::getCoreConf("WEBDAV_BROWSER_LISTING")) {
            $browerPlugin = new AJXP_Sabre_BrowserPlugin((isSet($repository)?$repository->getDisplay():null));
            $extPlugin = new DAV\Browser\GuessContentType();
            $server->addPlugin($browerPlugin);
            $server->addPlugin($extPlugin);
        }
        try {
            $server->exec();
        } catch ( \Exception $e ) {
            AJXP_Logger::error(__CLASS__,"Exception",$e->getMessage());
        }
    }
}