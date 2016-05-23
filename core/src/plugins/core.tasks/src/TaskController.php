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
use Pydio\Core\Controller\Controller;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\SimpleRestResourceRouter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Utils;
use Pydio\Tasks\Providers\SqlTasksProvider;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');

class TaskController extends Plugin
{

    public function init($options)
    {
        parent::init($options);
        TaskService::getInstance()->setProvider(new SqlTasksProvider());
    }

    protected function initTaskFromApi(ServerRequestInterface $request, Task &$task){
        $params = $request->getParsedBody();
        $taskData = json_decode($params["task"], true);
        $task = SimpleRestResourceRouter::cast($task, $taskData);
        if(isSet($params["taskId"])) {
            $task->setId($params["taskId"]);
        } else {
            $task->setId(Utils::createGUID());
        }
        $task->setUserId(AuthService::getLoggedUser()->getId());
        $task->setWsId(ConfService::getCurrentRepositoryId());
        if(count($task->nodes)){
            foreach($task->nodes as $index => $path){
                $task->nodes[$index] = "pydio://".$task->getWsId().$path;
            }
        }
    }

    public function route(ServerRequestInterface &$request, ResponseInterface &$response){

        $action = $request->getAttribute("action");
        $taskService = TaskService::getInstance();
        switch ($action){
            case "tasks_list":
                $tasks = $taskService->getCurrentRunningTasks(AuthService::getLoggedUser(), ConfService::getRepository());
                $response = new JsonResponse($tasks);
                break;
            case "task_info":
                $task = $taskService->getTaskById($request->getParsedBody()["taskId"]);
                $response = new JsonResponse($task);
                break;
            case "task_create":
                $newTask = new Task();
                $this->initTaskFromApi($request, $newTask);
                $taskService->createTask($newTask, $newTask->getSchedule());
                if($newTask->getSchedule()->shouldRunNow()){
                    Controller::applyTaskInBackground($newTask);
                }
                $response = new JsonResponse($newTask);
                break;
            case "task_update":
                $taskData = $request->getParsedBody()["request_body"];
                $newTask = $taskService->getTaskById($request->getParsedBody()["taskId"]);
                $newTask = SimpleRestResourceRouter::cast($newTask, $taskData);
                $taskService->updateTask($newTask);
                $response = new JsonResponse($newTask);
                break;
            case "task_toggle_status":
                $task = $taskService->getTaskById($request->getParsedBody()["taskId"]);
                if(!empty($task)){
                    $status = intval($request->getParsedBody()["status"]);
                    if($status != $task->getStatus()){
                        $task->setStatus($status);
                        $taskService->updateTask($task);
                    }
                }
                break;
            case "task_delete":
                if($taskService->deleteTask($request->getParsedBody()["taskId"])){
                    $response = new JsonResponse(new UserMessage("Ok"));
                }
                break;
            default:
                break;
        }

    }

    /**
     * @param AJXP_Node $node
     * @param bool $isContextNode
     * @param string $details
     */
    public function attachTasksToNode(AJXP_Node &$node, $isContextNode = false, $details = "all"){
        if($details == "all"){
            $t = TaskService::getInstance()->getActiveTasksForNode($node);
            if(count($t)){
                $ids = array_map(function (Task $el) {
                    return $el->getId();
                }, $t);
                $node->mergeMetadata([
                    "tasks"=> implode(",",$ids),
                    "overlay_icon" => "task-running.png",
                    "overlay_class" => "mdi mdi-radar"
                ], true);
            }
        }
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function enrichConsumeChannel(ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface){

        $respType = &$responseInterface->getBody();
        if(!$respType instanceof \Pydio\Core\Http\Response\SerializableResponseStream && !$respType->getSize()){
            $respType = new \Pydio\Core\Http\Response\SerializableResponseStream();
            $responseInterface = $responseInterface->withBody($respType);
        }
        if($respType instanceof \Pydio\Core\Http\Response\SerializableResponseStream){
            $taskList = TaskService::getInstance()->getCurrentRunningTasks(AuthService::getLoggedUser(), ConfService::getRepository());
            $respType->addChunk(new TaskListMessage($taskList));
        }

    }

    
}