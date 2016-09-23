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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Tasks\Providers;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Tasks\ITasksProvider;
use Pydio\Tasks\Schedule;
use Pydio\Tasks\Task;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class SqlTasksProvider
 * @package Pydio\Tasks\Providers
 */
class SqlTasksProvider implements ITasksProvider
{
    /**
     * Serialize task for storing in ajxp_tasks
     * @param Task $task
     * @param bool $removeId
     * @return array
     */
    protected function taskToDBValues(Task $task, $removeId = false){
        $values = [
            "type"              => $task->getType(),
            "parent_uid"        => $task->getParentId(),
            "flags"             => $task->getFlags(),
            "label"             => $task->getLabel(),
            "user_id"            => $task->getUserId(),
            "ws_id"              => $task->getWsId(),
            "status"            => $task->getStatus(),
            "status_msg"        => $task->getStatusMessage(),
            "progress"          => $task->getProgress(),
            "schedule"          => $task->getSchedule()->getType(),
            "schedule_value"    => $task->getSchedule()->getValue(),
            "action"            => $task->getAction(),
            "parameters"        => json_encode($task->getParameters()),
            "nodes"             => ""
        ];
        if(count($task->nodes)){
            $values["nodes"] = "|||".implode("|||", $task->nodes)."|||";
        }
        if(!$removeId){
            // This is a creation
            $values["creation_date"] = time();
            $values = array_merge(["uid" => $task->getId()], $values);
        }else{
            $values["status_update"] = time();
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
        $task->setType($values["type"]);
        if(!empty($values["parent_uid"])){
            $task->setParentId($values["parent_uid"]);
        }
        $task->setFlags($values["flags"]);
        $task->setLabel($values["label"]);
        $task->setUserId($values["user_id"]);
        $task->setWsId($values["ws_id"]);
        $task->setStatus($values["status"]);
        $task->setStatusMessage($values["status_msg"]);
        $task->setProgress($values["progress"]);
        $task->setSchedule(new Schedule($values["schedule"], $values["schedule_value"]));
        $task->setAction($values["action"]);
        $task->setCreationDate($values["creation_date"]);
        $task->setStatusChangeDate($values["status_update"]);
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
        return null;
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
     * @return Task[]
     */
    public function getScheduledTasks(){
        return $this->getTasks(null, null, -1, Schedule::TYPE_RECURRENT, Task::TYPE_ADMIN, AJXP_FILTER_EMPTY);
    }

    /**
     * @param string $taskId
     * @return Task[]
     */
    public function getChildrenTasks($taskId){
        $tasks = [];
        $where = [];
        $where[] = array("[parent_uid] = %s", $taskId);
        $res = \dibi::query('SELECT * FROM [ajxp_tasks] WHERE %and', $where);
        foreach ($res->fetchAll() as $row) {
            $tasks[] = $this->taskFromDBValues($row);
        }
        return $tasks;
    }


    /**
     * @param UserInterface $user
     * @param RepositoryInterface $repository
     * @return \Pydio\Tasks\Task[]
     */
    public function getCurrentRunningTasks($user = null, $repository = null)
    {
        $tasks = [];
        $where = [];
        if($user !== null){
            $where[] = array("[user_id] = %s", $user->getId());
        }
        if($repository !== null){
            $where[] = array("[ws_id] = %s", $repository->getId());
        }
        $where[] = array("[status] IN (1,2,8,16)");
        $res = \dibi::query('SELECT * FROM [ajxp_tasks] WHERE %and', $where);
        foreach ($res->fetchAll() as $row) {
            $tasks[] = $this->taskFromDBValues($row);
        }
        return $tasks;
    }

    /**
     * @param AJXP_Node $node
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
     * @param UserInterface $user
     * @param RepositoryInterface $repository
     * @param int $status
     * @param int $scheduleType
     * @param int $taskType
     * @param string $parentUid
     * @return \Pydio\Tasks\Task[]
     */
    public function getTasks($user = null, $repository = null, $status = -1, $scheduleType = -1, $taskType = Task::TYPE_USER, $parentUid = "")
    {
        $tasks = [];
        $where = [];
        if($user !== null){
            $where[] = array("[user_id] = %s", $user->getId());
        }
        if($repository !== null){
            $where[] = array("[ws_id] = %s", $repository->getId());
        }
        if($status !== -1){
            $where[] = array("[status] = %i", $status);
        }
        if($scheduleType !== -1){
            $where[] = array("[schedule] = %i", $scheduleType);
        }
        if($taskType !== -1){
            $where[] = array("[type] = %i", $taskType);
        }
        if(!empty($parentUid)){
            if($parentUid === AJXP_FILTER_EMPTY){
                $where[] = array("[parent_uid] IS NULL");
            }else{
                $where[] = array("[parent_uid] = %s", $parentUid);
            }
        }
        $res = \dibi::query('SELECT * FROM [ajxp_tasks] WHERE %and', $where);
        foreach ($res->fetchAll() as $row) {
            $tasks[] = $this->taskFromDBValues($row);
        }
        return $tasks;

    }
}
