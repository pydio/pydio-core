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
define('AJXP_EXEC', true);
include_once("base.conf.php");

$pServ = AJXP_PluginsService::getInstance();
ConfService::$useSession = false;
AuthService::$useSession = false;

ConfService::init();
ConfService::start();

$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());

/**
 * @var Pydio\OCS\OCSPlugin $coreLoader
 */
$coreLoader = $pServ->getPluginById("core.ocs");
$configs = $coreLoader->getConfigs();

$uri = $_SERVER["REQUEST_URI"];
$parts = explode("/", trim(parse_url($uri, PHP_URL_PATH), "/"));
$root = array_shift($parts);
if( $root == "ocs-provider"){

    $services = array();
    $coreLoader->publishServices();

}else if($root == "ocs"){

    if(count($parts) < 2){
        $response = $coreLoader->buildResponse("fail", "400", "Wrong URI");
        $coreLoader->sendResponse($response);
        return;
    }

    $version = array_shift($parts);
    if($version != "v2"){
        $response = $coreLoader->buildResponse("fail", "400", "Api version not supported - Please switch to v2.");
        $coreLoader->sendResponse($response);
        return;
    }
    $endpoint = array_shift($parts);
    $coreLoader->route($endpoint, $parts, array_merge($_GET, $_POST));

}