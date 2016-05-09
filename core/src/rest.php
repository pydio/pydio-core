<?php
/*
 * Copyright 2007-2013 Charles du Jeu <contact (at) cdujeu.me>
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
 * Description : Real RESTful API access
 */
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\PluginFramework\PluginsService;

include_once("base.conf.php");

ConfService::registerCatchAll();

$pServ = PluginsService::getInstance();
ConfService::$useSession = false;
AuthService::$useSession = false;

ConfService::init();
ConfService::start();

PluginsService::getInstance()->initActivePlugins();

AuthService::preLogUser(array_merge($_GET, $_POST));
if(AuthService::getLoggedUser() == null){
    header('HTTP/1.0 401 Unauthorized');
    echo 'You are not authorized to access this API.';
    exit;
}
$authDriver = ConfService::getAuthDriverImpl();
ConfService::currentContextIsRestAPI("api");

$request = Controller::initServerRequest(true);
$repoID = $request->getAttribute("rest_repository_id");
if($repoID == 'pydio'){
    ConfService::switchRootDir();
    $repo = ConfService::getRepository();
}else{
    $repo = ConfService::findRepositoryByIdOrAlias($repoID);
    if ($repo == null) {
        die("Cannot find repository with ID ".$repoID);
    }
    if(!ConfService::repositoryIsAccessible($repo->getId(), $repo, AuthService::getLoggedUser(), false, true)){
        header('HTTP/1.0 401 Unauthorized');
        echo 'You are not authorized to access this workspace.';
        exit;
    }
    ConfService::switchRootDir($repo->getId());
}
// DRIVERS BELOW NEED IDENTIFICATION CHECK
if (!AuthService::usersEnabled() || ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") || AuthService::getLoggedUser()!=null) {
    $confDriver = ConfService::getConfStorageImpl();
    $Driver = ConfService::loadDriverForRepository($repo);
}
PluginsService::getInstance()->initActivePlugins();


$action = Controller::parseRestParameters($request);
$response = Controller::run($request, $action);
if($response !== false && ($response->getBody()->getSize() || $response instanceof \Zend\Diactoros\Response\EmptyResponse)) {
    $emitter = new \Zend\Diactoros\Response\SapiEmitter();
    $emitter->emit($response);
}
