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

include_once("base.conf.php");

//set_error_handler(array("AJXP_XMLWriter", "catchError"), E_ALL & ~E_NOTICE & ~E_STRICT );
//set_exception_handler(array("AJXP_XMLWriter", "catchException"));

$pServ = AJXP_PluginsService::getInstance();
ConfService::$useSession = false;
AuthService::$useSession = false;

ConfService::init();
ConfService::start();

$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());
$authDriver = ConfService::getAuthDriverImpl();
ConfService::currentContextIsRestAPI("api");
AJXP_PluginsService::getInstance()->initActivePlugins();

function applyTask($userId, $repoId, $actionName, $parameters){

    print($userId." - ".$repoId." - ".$actionName." - ");
    print("Log User\n");
    AuthService::logUser($userId, "", true);
    print("Find Repo\n");
    if($repoId == 'pydio'){
        ConfService::switchRootDir();
        $repo = ConfService::getRepository();
    }else{
        $repo = ConfService::findRepositoryByIdOrAlias($repoId);
        if ($repo == null) {
            throw new Exception("Cannot find repository with ID ".$repoId);
        }
        ConfService::switchRootDir($repo->getId());
    }
    // DRIVERS BELOW NEED IDENTIFICATION CHECK
    print("Load Driver\n");
    if (!AuthService::usersEnabled() || ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth") || AuthService::getLoggedUser()!=null) {
        ConfService::getConfStorageImpl();
        ConfService::loadDriverForRepository($repo);
    }
    print("Init plugins\n");
    AJXP_PluginsService::getInstance()->initActivePlugins();

    //print "Current repos are" . implode(", ", array_keys(ConfService::getAccessibleRepositories()))."\n";

    print("Apply Action\n");
    $xmlResult = AJXP_Controller::findActionAndApply($actionName, $parameters, []);
    if (!empty($xmlResult) && !headers_sent()) {
        AJXP_XMLWriter::header();
        print($xmlResult);
        AJXP_XMLWriter::close();
    }

    print("Empty ShutdownScheduler!\n");
    AJXP_ShutdownScheduler::getInstance()->callRegisteredShutdown();

    print("Invalidate\n");
    ConfService::getInstance()->invalidateLoadedRepositories();
    print("Disconnect\n");
    AuthService::disconnect();
    AJXP_PluginsService::updateXmlRegistry(null, true);

}

function deQueue(){
    $fName = AJXP_DATA_PATH."/plugins/mq.serial/worker-queue";
    if(file_exists($fName)){
        $data = file_get_contents($fName);
        if(!empty($data)){
            $decoded = json_decode($data, true);
            if(!empty($decoded) && is_array($decoded) && count($decoded)){
                $task = array_pop($decoded);
                file_put_contents($fName, json_encode($decoded));
                return $task;
            }
        }
    }
    return false;
}

$method = isset($argv[1]) ? $argv[1] : 'nsq';

if($method == "nsq"){

    include("plugins/core.mq/vendor/autoload.php");
    $logger = new nsqphp\Logger\Stderr;
    $dedupe = new nsqphp\Dedupe\OppositeOfBloomFilterMemcached;
    $lookup = new nsqphp\Lookup\FixedHosts('localhost:4150');
    $requeueStrategy = new nsqphp\RequeueStrategy\FixedDelay;
    $nsq = new nsqphp\nsqphp($lookup, $dedupe, $requeueStrategy, $logger);
    $channel = isset($argv[1]) ? $argv[1] : 'foo';
    $nsq->subscribe('pydio', $channel, function($msg) {
        echo "READ\t" . $msg->getId() . "\t" . $msg->getPayload() . "\n";
        $payload = json_decode($msg->getPayload(), true);
        $data = $payload["data"];
        applyTask($data["user_id"], $data["repository_id"], $data["action"], $data["parameters"]);
    });
    $nsq->run();


}else{

    while(true){

        $task = deQueue();
        if($task !== false){
            try{
                print "--------------------------------------\n";
                print "Applying task ".$task["actionName"]."\n";
                print_r($task);
                applyTask($task["userId"], $task["repoId"], $task["actionName"], $task["parameters"]);
            }catch (Exception $e){
                print "Error : ".$e->getMessage()."\n";
            }
            flush();
        }else{
            print "--------- nothing to do \n";
        }
        print("5\r");
        sleep(1);
        print("4\r");
        sleep(1);
        print("3\r");
        sleep(1);
        print("2\r");
        sleep(1);
        print("1\r");
        sleep(1);

    }
}

