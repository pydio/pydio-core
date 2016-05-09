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
 * Description : main access point of the application, this script is called by any Ajax query.
 * Will dispatch the actions on the plugins.
 */
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\SessionService;

include_once("base.conf.php");


header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");


ConfService::registerCatchAll();
ConfService::init();
ConfService::start();


$request = Controller::initServerRequest();
SessionService::start($request);
Controller::requestHandlerDetectAction($request);
Controller::requestHandlerSecureToken($request);

$parameters = $request->getParsedBody();

if (isSet($parameters["tmp_repository_id"])) {
    try{
        ConfService::switchRootDir($parameters["tmp_repository_id"], true);
    }catch(PydioException $e){}
} else if (isSet($_SESSION["SWITCH_BACK_REPO_ID"])) {
    ConfService::switchRootDir($_SESSION["SWITCH_BACK_REPO_ID"]);
    unset($_SESSION["SWITCH_BACK_REPO_ID"]);
}


if (AuthService::usersEnabled()) {

    AuthService::logUser(null, null);
    // Check that current user can access current repository, try to switch otherwise.
    $loggedUser = AuthService::getLoggedUser();
    if ($loggedUser == null || $loggedUser->getId() == "guest") {
        // Now try to log the user with the various credentials that could be detected in the request
        PluginsService::getInstance()->initActivePlugins();
        AuthService::preLogUser($parameters);
        $loggedUser = AuthService::getLoggedUser();
        if($loggedUser == null) $requireAuth = true;
    }
    if ($loggedUser != null) {
           $res = ConfService::switchUserToActiveRepository($loggedUser, (isSet($parameters["tmp_repository_id"])?$parameters["tmp_repository_id"]:"-1"));
           if (!$res) {
               AuthService::disconnect();
               $requireAuth = true;
           }
       }

}

//Set language
$loggedUser = AuthService::getLoggedUser();
if($loggedUser != null && $loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
else if(isSet($request->getCookieParams()["AJXP_lang"])) ConfService::setLanguage($request->getCookieParams()["AJXP_lang"]);

//------------------------------------------------------------
// SPECIAL HANDLING FOR FLEX UPLOADER RIGHTS FOR THIS ACTION
//------------------------------------------------------------
if (AuthService::usersEnabled()) {
    $loggedUser = AuthService::getLoggedUser();
    if ($action == "upload" && ($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRepositoryId()."")) && isSet($_FILES['Filedata'])) {
        header('HTTP/1.0 ' . '410 Not authorized');
        die('Error 410 Not authorized!');
    }
}

// THIS FIRST DRIVERS DO NOT NEED ID CHECK
$authDriver = ConfService::getAuthDriverImpl();
// DRIVERS BELOW NEED IDENTIFICATION CHECK
if (!AuthService::usersEnabled() || ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") || AuthService::getLoggedUser()!=null) {
    $confDriver = ConfService::getConfStorageImpl();
    try{
        $Driver = ConfService::loadRepositoryDriver();
    }catch(Exception $e){
        //AuthService::disconnect();
    }
}
PluginsService::getInstance()->initActivePlugins();

try{
    $response = Controller::run($request);
    if($response !== false && ($response->getBody()->getSize() || $response instanceof \Zend\Diactoros\Response\EmptyResponse)) {
        $emitter = new \Zend\Diactoros\Response\SapiEmitter();
        $emitter->emit($response);
    }
}catch (\Pydio\Core\Exception\AuthRequiredException $authExc){
    if(isSet($requireAuth)){
        throw $authExc;
    }
}

SessionService::close();