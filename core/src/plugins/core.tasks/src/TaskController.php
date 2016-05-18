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
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\SimpleRestResourceRouter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Tasks\Providers\MockTasksProvider;
use Pydio\Tasks\Providers\SqlTasksProvider;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');

class TaskController extends Plugin
{

    public function init($options)
    {
        parent::init($options);
        //TaskService::getInstance()->setProvider(new MockTasksProvider());
        TaskService::getInstance()->setProvider(new SqlTasksProvider());
    }

    public function route(ServerRequestInterface &$request, ResponseInterface &$response){

        $action = $request->getAttribute("action");
        switch ($action){
            case "tasks_list":
                $tasks = TaskService::getInstance()->getPendingTasks();
                $response = new JsonResponse($tasks);
                break;
            case "task_info":
                $task = TaskService::getInstance()->getTaskById($request->getParsedBody()["taskId"]);
                $response = new JsonResponse($task);
                break;
            case "task_create":
                $taskData = json_decode($request->getParsedBody()["task"]);
                $newTask = new Task();
                $newTask->setId($request->getParsedBody()["taskId"]);
                $newTask = SimpleRestResourceRouter::cast($newTask, $taskData);
                TaskService::getInstance()->createTask($newTask, $newTask->getSchedule());
                $response = new JsonResponse($newTask);
                break;
            case "task_update":
                $taskData = $request->getParsedBody()["request_body"];
                $newTask = TaskService::getInstance()->getTaskById($request->getParsedBody()["taskId"]);
                $newTask = SimpleRestResourceRouter::cast($newTask, $taskData);
                TaskService::getInstance()->updateTask($newTask);
                $response = new JsonResponse($newTask);
                break;
            case "task_delete":
                if(TaskService::getInstance()->deleteTask($request->getParsedBody()["taskId"])){
                    $response = new JsonResponse(new UserMessage("Ok"));
                }
                break;
            default:
                break;
        }

    }

    public function attachTasksToNode(AJXP_Node &$node, $isContextNode = false, $details = "all"){
        if($details == "all"){
            $t = TaskService::getInstance()->getTasksForNode($node);
            if(count($t)){
                $ids = array_map(function (Task $el) {
                    return $el->getId();
                }, $t);
                $node->mergeMetadata(["tasks"=> implode(",",$ids)]);
            }
        }
    }

    /*
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
    */

}