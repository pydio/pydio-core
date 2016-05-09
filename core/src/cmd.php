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
 * Description : Command line access of the framework.
 */
use Pydio\Auth\Core\AJXP_Safe;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Log\Core\AJXP_Logger;

if (php_sapi_name() !== "cli") {
    die("This is the command line version of the framework, you are not allowed to access this page");
}

include_once("base.conf.php");

ConfService::init();
ConfService::start();

$optArgs = array();
$options = array();
$regex = '/^-(-?)([a-zA-z0-9_]*)=(.*)/';
foreach ($argv as $key => $argument) {
    //echo("$key => $argument \n");
    if (preg_match($regex, $argument, $matches)) {
        if ($matches[1] == "-") {
            $optArgs[trim($matches[2])] = TextEncoder::toUTF8(trim($matches[3]));
        } else {
            $options[trim($matches[2])] = TextEncoder::toUTF8(trim($matches[3]));
        }
    }
}

$optUser = $options["u"];
if (!empty($optUser)) {

    if (isSet($options["p"])) {
        $optPass = $options["p"];
    } else {
        // Consider "u" is a crypted version of u:p
        $optToken = $options["t"];
        $cKey = ConfService::getCoreConf("AJXP_CLI_SECRET_KEY", "conf");
        if(empty($cKey)) $cKey = "\1CDAFx¨op#";
        $optUser = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($optToken.$cKey), base64_decode($optUser), MCRYPT_MODE_ECB), "\0");
        $env = getenv("AJXP_SAFE_CREDENTIALS");
        if(!empty($env)){
            $array = AJXP_Safe::getCredentialsFromEncodedString($env);
            if(isSet($array["user"]) && $array["user"] == $optUser){
                unset($optToken);
                $optPass = $array["password"];
            }
        }
    }
    if (strpos($optUser,",") !== false) {
        $originalOptUser = $optUser;
        $nextUsers = explode(",", $optUser);
        $optUser = array_shift($nextUsers);
        $nextUsers = implode(",",$nextUsers);
    } else if (strpos($optUser, "queue:") === 0) {
        $optUserQueue = substr($optUser, strlen("queue:"));
        $optUser = false;
        //echo("QUEUE : ".$optUserQueue);
        if (is_file($optUserQueue)) {
            $lines = file($optUserQueue);
            if (count($lines) && !empty($lines[0])) {
                $allUsers = explode(",", $lines[0]);
                $optUser = array_shift($allUsers);
                file_put_contents($optUserQueue, implode(",", $allUsers));
            }
        }
        if ($optUser === false) {
            if (is_file($optUserQueue)) {
                unlink($optUserQueue);
            }
            die("No more users inside queue");
        }
    }
}


$optStatusFile = $options["s"] OR false;
$optAction = $options["a"];
$optRepoId = $options["r"] OR false;
if (strpos($optRepoId,",") !== false) {
    $nextRepositories = explode(",", $optRepoId);
    $optRepoId = array_shift($nextRepositories);
    $nextRepositories = implode(",", $nextRepositories);
}

//echo("REPOSITORY : ".$optRepoId." USER : ".$optUser."\n");

$optDetectUser = $options["detect_user"] OR false;
$detectedUser = false;

if ($optRepoId !== false) {
    $repository = ConfService::getRepositoryById($optRepoId);
    if ($repository == null) {
        $repository = ConfService::getRepositoryByAlias($optRepoId);
        if ($repository != null) {
            $optRepoId =($repository->isWriteable()?$repository->getUniqueId():$repository->getId());
        }
    }
    if ($optDetectUser != false) {
        $path = $repository->getOption("PATH", true);
        if (strpos($path, "AJXP_USER") !== false) {
            $path = str_replace(
                array("AJXP_INSTALL_PATH", "AJXP_DATA_PATH", "/"),
                array(AJXP_INSTALL_PATH, AJXP_DATA_PATH, DIRECTORY_SEPARATOR),
                $path
            );
            $parts = explode("AJXP_USER", $path);
            if(count($parts) == 1) $parts[1] = "";
            $first = str_replace("\\", "\\\\", $parts[0]);
            $last = str_replace("\\", "\\\\", $parts[1]);
            if (preg_match("/$first(.*)$last.*/", $optDetectUser, $matches)) {
                $detectedUser = $matches[1];
            }
        }
    }
    try{
        ConfService::switchRootDir($optRepoId, true);
    }catch(PydioException $e){}
} else {
    if ($optStatusFile) {
        file_put_contents($optStatusFile, "ERROR:You must pass a -r argument specifying either a repository id or alias");
    }
    die("You must pass a -r argument specifying either a repository id or alias");
}

if (AuthService::usersEnabled() && !empty($optUser)) {
    $seed = AuthService::generateSeed();
    if ($seed != -1) {
        $optPass = md5(md5($optPass).$seed);
    }
    $loggingResult = AuthService::logUser($optUser, $optPass, isSet($optToken), false, $seed);
    // Check that current user can access current repository, try to switch otherwise.
    $loggedUser = AuthService::getLoggedUser();
    if ($loggedUser != null && $detectedUser !== false && $loggedUser->isAdmin()) {
        AuthService::disconnect();
        AuthService::logUser($detectedUser, "empty", true, false, "");
        $loggedUser = AuthService::getLoggedUser();
    }

    if ($loggedUser != null) {
        ConfService::switchRootDir($optRepoId, true);
        /*
        $res = ConfService::switchUserToActiveRepository($loggedUser, $optRepoId);
        if (!$res) {
            AuthService::disconnect();
            $requireAuth = true;
        }
        */
    }
    if (isset($loggingResult) && $loggingResult != 1) {
        XMLWriter::header();
        XMLWriter::loggingResult($loggingResult, false, false, "");
        XMLWriter::close();
        if ($optStatusFile) {
            file_put_contents($optStatusFile, "ERROR:No user logged");
        }
    }
} else {
    AJXP_Logger::debug(ConfService::getCurrentRepositoryId());
}

//Set language
$loggedUser = AuthService::getLoggedUser();
$mess = ConfService::getMessages();

// THIS FIRST DRIVERS DO NOT NEED ID CHECK
$authDriver = ConfService::getAuthDriverImpl();
// DRIVERS BELOW NEED IDENTIFICATION CHECK
if (!AuthService::usersEnabled() || ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") || AuthService::getLoggedUser()!=null) {
    $confDriver = ConfService::getConfStorageImpl();
    $loadRepo = ConfService::getRepository();
    $Driver = ConfService::loadDriverForRepository($loadRepo);
}
PluginsService::getInstance()->initActivePlugins();

$fakeRequest = \Zend\Diactoros\ServerRequestFactory::fromGlobals(array(), array(), $optArgs)->withAttribute("action", $optAction);
try{
    $response = Controller::run($fakeRequest);
    if($response !== false && ($response->getBody()->getSize() || $response instanceof \Zend\Diactoros\Response\EmptyResponse)) {
        echo $response->getBody();
    }
}catch (Exception $e){
    echo "ERROR : ".$e->getMessage()."\n";
    echo print_r($e->getTraceAsString())."\n";
}

//echo("NEXT REPO ".$nextRepositories." (".$options["r"].")\n");
//echo("NEXT USERS ".$nextUsers." ( ".$originalOptUser." )\n");
if (!empty($nextUsers) || !empty($nextRepositories) || !empty($optUserQueue) ) {

    if (!empty($nextUsers)) {
        sleep(1);
        $process = Controller::applyActionInBackground($options["r"], $optAction, $optArgs, $nextUsers, $optStatusFile);
        if ($process != null && is_a($process, "Pydio\\Core\\Utils\\UnixProcess") && isSet($optStatusFile)) {
            file_put_contents($optStatusFile, "RUNNING:".$process->getPid());
        }
    }
    if (!empty($optUserQueue)) {
        sleep(1);
        //echo("Should go to next with $optUserQueue");
        $process = Controller::applyActionInBackground($options["r"], $optAction, $optArgs, "queue:".$optUserQueue, $optStatusFile);
        if ($process != null && is_a($process, "Pydio\\Core\\Utils\\UnixProcess") && isSet($optStatusFile)) {
            file_put_contents($optStatusFile, "RUNNING:".$process->getPid());
        }
    }
    if (!empty($nextRepositories)) {
        sleep(1);
        $process = Controller::applyActionInBackground($nextRepositories, $optAction, $optArgs, $originalOptUser, $optStatusFile);
        if ($process != null && is_a($process, "Pydio\\Core\\Utils\\UnixProcess") && isSet($optStatusFile)) {
            file_put_contents($optStatusFile, "RUNNING:".$process->getPid());
        }
    }

} else if (isSet($optStatusFile)) {

    $status = explode(":", file_get_contents($optStatusFile));
    file_put_contents($optStatusFile, "FINISHED".(in_array("QUEUED", $status)?":QUEUED":""));

}
