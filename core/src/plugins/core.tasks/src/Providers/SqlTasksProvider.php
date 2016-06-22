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
namespace Pydio\Tasks\Providers;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\Repository;
use Pydio\Conf\Core\AbstractUser;

use Pydio\Tasks\ITasksProvider;
use Pydio\Tasks\Schedule;
use Pydio\Tasks\Task;

defined('AJXP_EXEC') or die('Access not allowed');




class SqlTasksProvider implements ITasksProvider
{

    protected function taskToDBValues(Task $task, $removeId = false){
        $values = [
            "flags"     => $task->getFlags(),
            "label"     => $task->getLabel(),
            "userId"    => $task->getUserId(),
            "wsId"      => $task->getWsId(),
            "status"    => $task->getStatus(),
            "status_msg"=> $task->getStatusMessage(),
            "progress"  => $task->getProgress(),
            "schedule"  => json_encode($task->getSchedule()),
            "action"    => $task->getAction(),
            "parameters"=> json_encode($task->getParameters()),
            "nodes"     => ""
        ];
        if(count($task->nodes)){
            $values["nodes"] = "|||".implode("|||", $task->nodes)."|||";
        }
        if(!$removeId){
            $values = array_merge(["uid" => $task->getId()], $values);
        }
        return $values;
    }

    /**
     * @param \DibiRow $values
     * @return Task
     */
    protected function taskFromDBValues(\DibiRow $values){
        $task = new Task();
        $task->setId($values["uid"]);
        $task->setFlags($values["flags"]);
        $task->setLabel($values["label"]);
        $task->setUserId($values["userId"]);
        $task->setWsId($values["wsId"]);
        $task->setStatus($values["status"]);
        $task->setStatusMessage($values["status_msg"]);
        $task->setProgress($values["progress"]);
        $task->setSchedule(Schedule::fromJson($values["schedule"]));
        $task->setAction($values["action"]);
        $task->setParameters(json_decode($values["parameters"], true));
        $nodes = explode("|||", trim($values["nodes"], "|||"));
        foreach ($nodes as $node) {
            if(!empty($node)) $task->attachToNode($node);
        }
        return $task;
    }

    /**
     * @param Task $task
     * @param Schedule $when
     * @return Task
     */
    public function createTask(Task $task, Schedule $when)
    {
        \dibi::query("INSERT INTO [ajxp_tasks] ", $this->taskToDBValues($task));
    }

    /**
     * @param string $taskId
     * @return Task
     */
    public function getTaskById($taskId)
    {
        $res = \dibi::query('SELECT * FROM [ajxp_tasks] WHERE [uid]=%s', $taskId);
        foreach ($res->fetchAll() as $row) {
            return $this->taskFromDBValues($row);
        }
    }

    /**
     * @param Task $task
     * @return Task
     */
    public function updateTask(Task $task)
    {
        try{
            \dibi::query("UPDATE [ajxp_tasks] SET ", $this->taskToDBValues($task, true), " WHERE [uid] =%s", $task->getId());
        }catch (\DibiException $ex){
            $sql = $ex->getSql();
        }
    }
    
    /**
     * @param string $taskId
     * @return bool
     */
    public function deleteTask($taskId)
    {
        \dibi::query("DELETE FROM [ajxp_tasks] WHERE uid=%s", $taskId);
    }

    /**
     * @return Task[]
     */
    public function getPendingTasks()
    {
        return $this->getTasks(null, null, Task::STATUS_PENDING);
    }

    /**
     * @param AbstractUser $user
     * @param Repository $repository
     * @return Task[]
     */
    public function getCurrentRunningTasks($user, $repository)
    {
        $tasks = [];
        $where = [];
        $where[] = array("[userId] = %s", $user->getId());
        $where[] = array("[wsId] = %s", $repository->getId());
        $where[] = array("[status] IN (1,2,8,16)");
        $res = \dibi::query('SELECT * FROM [ajxp_tasks] WHERE %and', $where);
        foreach ($res->fetchAll() as $row) {
            $tasks[] = $this->taskFromDBValues($row);
        }
        return $tasks;
    }

    /**
     * @param AJXP_Node $node
     * @param $active
     * @return \Pydio\Tasks\Task[]
     */
    public function getActiveTasksForNode(AJXP_Node $node)
    {
        $tasks = [];
        try{
            $res = \dibi::query('SELECT * FROM [ajxp_tasks] WHERE [nodes] LIKE %s AND [status] NOT IN (1,4,8)', "%|||".$node->getUrl()."|||%");
            foreach ($res->fetchAll() as $row) {
                $tasks[] = $this->taskFromDBValues($row);
            }
        }catch(\DibiException $e){
            $sql = $e->getSql();
        }
        return $tasks;
    }

    /**
     * @param AbstractUser $user
     * @param Repository $repository
     * @param int $status
     * @return Task[]
     */
    public function getTasks($user = null, $repository = null, $status = -1)
    {
        $tasks = [];
        $where = [];
        if($user !== null){
            $where[] = array("[userId] = %s", $user->getId());
        }
        if($repository !== null){
            $where[] = array("[wsId] = %s", $repository->getId());
        }
        if($status !== -1){
            $where[] = array("[status] = %i", $status);
        }
        $res = \dibi::query('SELECT * FROM [ajxp_tasks] WHERE %and', $where);
        foreach ($res->fetchAll() as $row) {
            $tasks[] = $this->taskFromDBValues($row);
        }
        return $tasks;

    }
}