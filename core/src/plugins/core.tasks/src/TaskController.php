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
namespace Pydio\Tasks;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\SimpleRestResourceRouter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Tasks\Tests\MockTasksProvider;

defined('AJXP_EXEC') or die('Access not allowed');

class TaskController extends Plugin
{
    private $resources = [
        "resourceName" => "tasks",
        "parameterName" => "task_id",
        "crudCallbacks" => [
            "CREATE" => 'createTask',
            "RETRIEVE_MANY" => 'getTasks',
            "RETRIEVE_ONE" => 'getTask',
            "UPDATE" => 'updateTask',
            "DELETE" => 'deleteTask'
        ],
        "linkedResources" => [
            [
                "parameterName" => "message_id",
                "resourceName" => "messages",
                "crudCallbacks" => [
                    "RETRIEVE_MANY" => 'getMessagesforTask',
                    "RETRIEVE_ONE" => 'getMessageforTask',
                ],
                "additionalRoutes" => [
                    ["method" => "GET", "route" => "/messages/{message_id}/flag", "callback" => "flagMessageForTask"]
                ]
            ]
        ],
        "additionalRoutes" => [
            ["method" => "GET", "route" => "/tasks/{task_id}/start", "callback" => "startTask"]
        ]
    ];

    public function route(ServerRequestInterface &$request, ResponseInterface &$response){

        $router = new SimpleRestResourceRouter($this, $this->resources, [
            "cacheFile" => $this->getPluginCacheDir(true, true) . '/route.cache',
            "cacheDisabled" => false
        ]);
        $result = $router->route($request, $response);
        if($result === false){
            throw new PydioException("Could not find any route for ".$router->getURIForRequest($request));
        }

    }

    public function getTasks(ServerRequestInterface &$request, ResponseInterface &$response){
        $mock = new MockTasksProvider();
        return $mock->getPendingTasks();
    }

    public function getTask(ServerRequestInterface &$request, ResponseInterface &$response){
        $mock = new MockTasksProvider();
        return $mock->getTaskById($request->getParsedBody()["task_id"]);
    }

    public function createTask(ServerRequestInterface &$request, ResponseInterface &$response){
        $postedTask = $request->getParsedBody()["postedObject"];
        $t = new Task();
        $t = SimpleRestResourceRouter::cast($t, $postedTask);
        $t->schedule = Schedule::fromJson($t->schedule);
        return $t;
    }

    public function updateTask(ServerRequestInterface &$request, ResponseInterface &$response){
        $taskId = $request->getParsedBody()["task_id"];
        $postedTask = $request->getParsedBody()["postedObject"];
        $t = new Task();
        $t = SimpleRestResourceRouter::cast($t, $postedTask);
        $t->schedule = Schedule::fromJson($t->schedule);
        $t->setId($taskId);
        $mock = new MockTasksProvider();
        return $mock->updateTask($t);
    }

    public function deleteTask(ServerRequestInterface &$request, ResponseInterface &$response){
        $taskId = $request->getParsedBody()["task_id"];
        $mock = new MockTasksProvider();
        if($mock->deleteTask($taskId)){
            return ["success" => "Deleted object $taskId"];
        }else{
            return ["error" => "there was an error trying to delete object $taskId"];
        }
    }

    public function startTask(ServerRequestInterface &$request, ResponseInterface &$response){
        return ["Task ".$request->getParsedBody()["task_id"]." started"];
    }

    public function getMessagesForTask(ServerRequestInterface &$request, ResponseInterface &$response){
        return ["Messages for task ".$request->getParsedBody()["task_id"].""];
    }

    public function getMessageForTask(ServerRequestInterface &$request, ResponseInterface &$response){
        return ["Message ".$request->getParsedBody()["message_id"]." for task ".$request->getParsedBody()["task_id"].""];
    }

    public function flagMessageForTask(ServerRequestInterface &$request, ResponseInterface &$response){
        return ["Flag Message ".$request->getParsedBody()["message_id"]." for task ".$request->getParsedBody()["task_id"].""];
    }

}