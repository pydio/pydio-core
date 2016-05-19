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

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesDiff;
use Pydio\Access\Core\Model\Repository;
use Pydio\Conf\Core\AbstractAjxpUser;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Utils;

defined('AJXP_EXEC') or die('Access not allowed');

class TaskService implements ITasksProvider
{
    /**
     * @var ITasksProvider
     */
    private $realProvider;

    /**
     * @var TaskService
     */
    private static $instance;

    public function setProvider(ITasksProvider $provider){
        $this->realProvider = $provider;
    }

    /**
     * @return TaskService
     */
    public static function getInstance(){
        if(!isSet(self::$instance)){
            self::$instance = new TaskService();
        }
        return self::$instance;
    }


    public function enqueueTask(Task $task){
        
    }

    protected function publishTaskUpdate(Task $task){

        $json = Utils::xmlEntities(json_encode($task));
        if(count($task->nodes)){
            $nodesDiff = new NodesDiff();
            foreach($task->nodes as $url){
                $n = new AJXP_Node($url);
                $n->loadNodeInfo(true, false, "all");
                $nodesDiff->update($n);
            }
        }
        $xmlString = "";
        if(isSet($nodesDiff)){
            $xmlString = $nodesDiff->toXML();
        }
        Controller::applyHook("msg.instant", array("<task id='".$task->getId()."' data='".$json."'/>".$xmlString, $task->getWsId(), $task->getUserId()));

    }

    public function enqueueActionAsTask($actionName, $parameters, $repoId = "", $user = "", $nodePathes = []){

        if (empty($user)) {
            if(AuthService::usersEnabled() && AuthService::getLoggedUser() !== null) {
                $user = AuthService::getLoggedUser()->getId();
            }else {
                $user = "shared";
            }
        }
        if(empty($repoId)){
            $repoId = ConfService::getCurrentRepositoryId();
        }
        $task = new Task();
        $task->setLabel("Launching action ".$actionName);
        $task->setId(Utils::createGUID());
        $task->setUserId($user);
        $task->setWsId($repoId);
        $task->setStatus(Task::STATUS_PENDING);
        $task->setStatusMessage("Starting...");
        $task->setAction($actionName);
        $task->setParameters($parameters);
        if(count($nodePathes)){
            array_map(function ($node) use ($task){
                $task->attachToNode($node);
            }, $nodePathes);
        }
        TaskService::getInstance()->createTask($task, Schedule::scheduleNow());

        return $task;

    }

    /**
     * @param string $taskId
     * @param integer $status
     * @param string $message
     * @param bool|null $stoppable
     * @return Task
     * @throws PydioException
     */
    public function updateTaskStatus($taskId, $status, $message, $stoppable = null){
        $t = self::getTaskById($taskId);
        if(empty($t)){
            throw new PydioException("Cannot find task with this id");
        }
        $t->setStatus($status);
        $t->setStatusMessage($message);
        if($stoppable !== null){
            $f = $t->isResumable() ? Task::FLAG_RESUMABLE : 0;
            $f = $t->hasProgress() ? $f | Task::FLAG_HAS_PROGRESS : $f;
            $f = $stoppable ? $f | Task::FLAG_STOPPABLE : $f;
            $t->setFlags($f);
        }
        return self::updateTask($t);
    }

    /**
     * @param Task $task
     * @param Schedule $when
     * @return Task
     */
    public function createTask(Task $task, Schedule $when)
    {
        $res = $this->realProvider->createTask($task, $when);
        $this->publishTaskUpdate($task);
        return $res;
    }

    /**
     * @param string $taskId
     * @return Task
     */
    public function getTaskById($taskId)
    {
        return $this->realProvider->getTaskById($taskId);
    }

    /**
     * @param Task $task
     * @return Task
     */
    public function updateTask(Task $task)
    {
        $res = $this->realProvider->updateTask($task);
        $this->publishTaskUpdate($task);
        return $res;
    }

    /**
     * @param string $taskId
     * @return bool
     */
    public function deleteTask($taskId)
    {
        return $this->realProvider->deleteTask($taskId);
    }

    /**
     * @return Task[]
     */
    public function getPendingTasks()
    {
        return $this->realProvider->getPendingTasks();
    }

    /**
     * @param AJXP_Node $node
     * @return Task[]
     */
    public function getActiveTasksForNode(AJXP_Node $node)
    {
        return $this->realProvider->getActiveTasksForNode($node);
    }

    /**
     * @param AbstractAjxpUser $user
     * @param Repository $repository
     * @param int $status
     * @return Task[]
     */
    public function getTasks($user = null, $repository = null, $status = -1)
    {
        return $this->realProvider->getTasks($user, $repository, $status);
    }
}