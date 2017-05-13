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

use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Controller\ShutdownScheduler;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\RepositoryService;

use Pydio\Tasks\Task;

include_once("base.conf.php");

$pServ = PluginsService::getInstance();

ConfService::init();
ConfService::start();

$authDriver = ConfService::getAuthDriverImpl();
ApplicationState::setSapiRestBase("/api");
PluginsService::getInstance()->initActivePlugins();

/**
 * @param Task $task
 * @param nsqphp\Logger\Stderr $logger
 * @throws Exception
 */
function applyTask($task, $logger){

    set_error_handler(function ($errno , $errstr , $errfile , $errline) use ($task){
        if(error_reporting() == 0) return;
        \Pydio\Tasks\TaskService::getInstance()->updateTaskStatus($task->getId(), Task::STATUS_FAILED, $errstr);
    }, E_ALL & ~E_NOTICE & ~E_STRICT);

    $userId = $task->getUserId();
    $repoId = $task->getWsId();
    $actionName = $task->getAction();
    $parameters = $task->getParameters();

    print($userId." - ".$repoId." - ".$actionName." - \n");
    $logger->debug("Log User");
    $user = AuthService::logUser($userId, "", true);
    $logger->debug("Find Repo");
    if($repoId == 'pydio'){
        $userRepositories = \Pydio\Core\Services\UsersService::getRepositoriesForUser($user);
        if(empty($userRepositories)){
            throw new \Pydio\Core\Exception\NoActiveWorkspaceException();
        }
        $repo = array_shift($userRepositories);
    }else{
        $repo = RepositoryService::findRepositoryByIdOrAlias($repoId);
        if ($repo == null) {
            \Pydio\Tasks\TaskService::getInstance()->updateTaskStatus($task->getId(), Task::STATUS_FAILED, "Cannot find repository");
            $logger->error("Cannot find repository with ID ".$repoId);
            return;
        }
        //ConfService::switchRootDir($repo->getId());
    }
    $logger->debug("Init plugins");
    $newCtx = \Pydio\Core\Model\Context::contextWithObjects($user, $repo);
    PluginsService::getInstance($newCtx);

    $fakeRequest = \Zend\Diactoros\ServerRequestFactory::fromGlobals(array(), array(), $parameters)
        ->withAttribute("ctx", $newCtx)
        ->withAttribute("action", $actionName)
        ->withAttribute("pydio-task-id", $task->getId());
    ;
    try{
        $response = Controller::run($fakeRequest);
        if($response !== false && ($response->getBody()->getSize() || $response instanceof \Zend\Diactoros\Response\EmptyResponse)) {
            echo $response->getBody();
        }
    }catch (Exception $e){
        $logger->error("ERROR : ".$e->getMessage());
        $logger->error($e->getTraceAsString());
        \Pydio\Tasks\TaskService::getInstance()->updateTaskStatus($task->getId(), Task::STATUS_FAILED, $e->getMessage());
    }

    $logger->debug("Empty ShutdownScheduler!");
    ShutdownScheduler::getInstance()->callRegisteredShutdown();

    $logger->debug("Disconnecting");
    AuthService::disconnect();
    $repo->driverInstance = null;
    $logger->debug("Clear Plugins Registry");
    PluginsService::clearInstance($newCtx);
    $logger->debug("Clear Loaded Repositories");
    ConfService::getInstance()->invalidateLoadedRepositories();

    restore_error_handler();

}

function listen(){

    $logger = new nsqphp\Logger\Stderr;
    $dedupe = new nsqphp\Dedupe\OppositeOfBloomFilterMemcached;
    $lookup = new nsqphp\Lookup\FixedHosts('localhost:4150');
    $requeueStrategy = new nsqphp\RequeueStrategy\FixedDelay;
    $nsq = new nsqphp\nsqphp($lookup, $dedupe, $requeueStrategy, $logger);

    $channel = 'worker';

    $nsq->subscribe('task', $channel, function(\nsqphp\Message\Message $msg) use ($logger) {
        $logger->debug("READ\t" . $msg->getId() . "\t" . $msg->getPayload());
        $data = json_decode($msg->getPayload(), true);
        if(isSet($data["pending_task"])){
            $taskId = $data["pending_task"];
            $task = \Pydio\Tasks\TaskService::getInstance()->getTaskById($taskId);
            if($task instanceof Task ){
                if($task->getStatus() == Task::STATUS_PENDING){
                    $logger->info("--------------------------------------");
                    $logger->info("Applying task ".$data["actionName"]);
                    try{
                        applyTask($task, $logger);
                        // ALTERNATIVE : SEND IN BG 
                        //$task->setSchedule(new Schedule(Schedule::TYPE_ONCE_NOW));
                        //Controller::applyActionInBackground($task->getWsId(), $task->getAction(), $task->getParameters(), $task->getUserId(), "", $task->getId());
                    }catch (Exception $e){
                        $logger->error("Error : ".$e->getMessage());
                        $logger->error("Error : ".$e->getTraceAsString());
                    }
                }else{
                    $logger->debug("Skipping Task, status is not pending ". $task->getStatus());
                }
            }else{
                $logger->debug("Skipping, cannot find task for id ". $taskId);
            }
        }

    });

    try{
        $nsq->run();
    }catch (Exception $e){
        $logger->error("Socket Error ".$e->getMessage());
        $logger->error("Restarting listener");
        listen();
    }
}

listen();