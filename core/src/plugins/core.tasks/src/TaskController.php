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
namespace Pydio\Tasks;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Controller\CliRunner;

use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\SimpleRestResourceRouter;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\SqlTableProvider;


use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\DBHelper;
use Pydio\Core\Utils\Vars\OptionsHelper;
use Pydio\Core\Utils\Vars\StringHelper;

use Pydio\Tasks\Providers\SqlTasksProvider;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class TaskController
 * Main controller for TaskService actions
 * @package Pydio\Tasks
 */
class TaskController extends Plugin implements SqlTableProvider
{

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        TaskService::getInstance()->setProvider(new SqlTasksProvider());
    }

    /**
     * @param ServerRequestInterface $request
     * @param Task $task
     */
    protected function initTaskFromApi(ServerRequestInterface $request, Task &$task){
        $params = $request->getParsedBody();
        $taskData = json_decode($params["task"], true);
        /** @var Task $task */
        $task = SimpleRestResourceRouter::cast($task, $taskData);
        if(isSet($params["taskId"])) {
            $task->setId($params["taskId"]);
        } else {
            $task->setId(StringHelper::createGUID());
        }
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");

        if(isSet($params["target-users"]) && isSet($params["target-repositories"]) && $ctx->getUser()->isAdmin()){
            $task->setUserId($ctx->getUser()->getId());
            $task->setImpersonateUsers($params["target-users"]);
            $task->setWsId($params["target-repositories"]);
        }else{
            $task->setUserId($ctx->getUser()->getId());
            $task->setWsId($ctx->getRepositoryId());
        }
        if(count($task->nodes)){
            foreach($task->nodes as $index => $path){
                $task->nodes[$index] = "pydio://".$task->getUserId()."@".$task->getWsId().$path;
            }
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws \Pydio\Core\Exception\UserNotFoundException
     */
    public function route(ServerRequestInterface &$request, ResponseInterface &$response){

        $action = $request->getAttribute("action");
        $taskService = TaskService::getInstance();
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        if(!$ctx->hasUser() || !$ctx->hasRepository()){
            return;
        }
        switch ($action){

            case "tasks_list":
                
                $params = $request->getParsedBody();
                if(isSet($params["scope"]) && $ctx->getUser()->isAdmin()){
                    $userObject = $repoObject = null;
                    if($params["scope"] === "repository" && !empty($params["repository_id"])){
                        $repoObject = RepositoryService::getRepositoryById($params["repository_id"]);
                    }else if($params["scope"] === "user" && !empty($params["user_id"])){
                        $userObject = UsersService::getUserById($params["user_id"]);
                    }
                    $tasks = $taskService->getCurrentRunningTasks($userObject, $repoObject);
                }else{
                    $tasks = $taskService->getCurrentRunningTasks($ctx->getUser(), $ctx->getRepository());
                }
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
                    CliRunner::applyTaskInBackground($newTask);
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

        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        if(!$ctx->hasUser() || !$ctx->hasRepository()) return;

        $respType = &$responseInterface->getBody();
        if(!$respType instanceof \Pydio\Core\Http\Response\SerializableResponseStream && !$respType->getSize()){
            $respType = new \Pydio\Core\Http\Response\SerializableResponseStream();
            $responseInterface = $responseInterface->withBody($respType);
        }
        if($respType instanceof \Pydio\Core\Http\Response\SerializableResponseStream){
            $taskList = TaskService::getInstance()->getCurrentRunningTasks($ctx->getUser(), $ctx->getRepository());
            $respType->addChunk(new TaskListMessage($taskList));
        }

    }

    /**
     * Install SQL table using a dibi driver data
     * @param $param array("SQL_DRIVER" => $dibiDriverData)
     * @return mixed
     */
    public function installSQLTables($param)
    {
        $p = OptionsHelper::cleanDibiDriverParameters($param["SQL_DRIVER"]);
        return DBHelper::runCreateTablesQuery($p, $this->getBaseDir() . "/create.sql");
    }

}