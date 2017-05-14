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
namespace Pydio\Core\Http\Dav;

use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Log\Core\Logger;
use Sabre\DAV as DAV;
use Sabre\DAV\Exception\Forbidden;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class DAVServer
 * @package Pydio\Core\Http\Dav
 */
class DAVServer
{
    /**
     * @var ContextInterface
     */
    private static $context;


    /**
     * @param $baseURI
     * @param $davRoute
     * @throws Forbidden
     */
    public static function handleRoute($baseURI, $davRoute){

        ConfService::init();
        ConfService::start();
        self::$context = Context::emptyContext();

        if (!ConfService::getGlobalConf("WEBDAV_ENABLE")) {
            die('You are not allowed to access this service');
        }

        PluginsService::getInstance(self::$context)->initActivePlugins();

        $baseURI = $baseURI . $davRoute;
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

            $repository = RepositoryService::getRepositoryById($repositoryId);
            if ($repository == null) {
                $repository = RepositoryService::getRepositoryByAlias($repositoryId);
                if ($repository != null) {
                    $repositoryId = $repository->getId();
                }
            }
            if ($repository == null) {
                die('You are not allowed to access this service '.$repositoryId);
            }

            self::$context->setRepositoryId($repositoryId);
            $rootDir =  new Collection("/", self::$context);
            $server = new DAV\Server($rootDir);
            $server->setBaseUri($baseURI."/".$pathBase);


        } else {

            $rootDir = new RootCollection("root");
            $rootDir->setContext(self::$context);
            $server = new DAV\Server($rootDir);
            $server->setBaseUri($baseURI);

        }
        $server->httpResponse = new DAVResponse();

        if(AuthBackendBasic::detectBasicHeader() || ConfService::getGlobalConf("WEBDAV_FORCE_BASIC")){
            $authBackend = new AuthBackendBasic(self::$context);
        } else {
            $authBackend = new AuthBackendDigest(self::$context);
        }
        $authPlugin = new DAV\Auth\Plugin($authBackend, ConfService::getGlobalConf("WEBDAV_DIGESTREALM"));
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

        if (ConfService::getGlobalConf("WEBDAV_BROWSER_LISTING")) {
            $browerPlugin = new BrowserPlugin((isSet($repository)?$repository->getDisplay():null));
            $extPlugin = new DAV\Browser\GuessContentType();
            $server->addPlugin($browerPlugin);
            $server->addPlugin($extPlugin);
        }
        try {
            $server->exec();
        } catch ( \Exception $e ) {
            Logger::error(__CLASS__,"Exception",$e->getMessage());
        }
    }
}