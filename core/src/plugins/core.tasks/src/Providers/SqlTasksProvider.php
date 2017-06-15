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
namespace Pydio\Tasks\Providers;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Utils\Vars\UrlUtils;
use Pydio\Log\Core\Logger;
use Pydio\Tasks\ITasksProvider;
use Pydio\Tasks\Schedule;
use Pydio\Tasks\Task;
use \dibi as dibi;

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
        $modifiers = [
            "[type]"              => "%i",
            "[parent_uid]"        => "%s",
            "[flags]"             => "%i",
            "[label]"             => "%s",
            "[user_id]"            => "%s",
            "[ws_id]"              => "%s",
            "[status]"            => "%i",
            "[status_msg]"        => "%s",
            "[progress]"          => "%i",
            "[schedule]"          => "%i",
            "[schedule_value]"    => "%s",
            "[action]"            => "%s",
            "[parameters]"        => "%bin",
        ];
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
            "parameters"        => gzdeflate(json_encode($task->getParameters()), 9),
        ];
        if(!$removeId){
            // This is a creation
            $values["creation_date"] = time();
            $modifiers["[creation_date]"] = "%i";
            $values = array_merge(["uid" => $task->getId()], $values);
            $modifiers = array_merge(["[uid]" => "%s"], $modifiers);
        }else{
            $values["status_update"] = time();
            $modifiers["[status_update]"] = "%i";
        }
        return [$modifiers, $values];
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
        $task->setParameters(json_decode(gzinflate($values["parameters"]), true));
        $this->loadTaskNodes($task);
        return $task;
    }

    /**
     * @param Task $task
     * @param bool $update
     */
    protected function insertOrUpdateNodes($task, $update = false){
        if($update){
            dibi::query("DELETE FROM [ajxp_tasks_nodes] WHERE [task_uid]=%s", $task->getId());
        }
        foreach($task->nodes as $nodeUrl){
            $nodePath = UrlUtils::mbParseUrl($nodeUrl, PHP_URL_PATH);
            if(empty($nodePath)) $nodePath = "/";
            $nodeBaseUrl = preg_replace('/'. preg_quote($nodePath, '/') . '$/', "", $nodeUrl);
            $values = [
                "task_uid"      => $task->getId(),
                "node_base_url" => $nodeBaseUrl,
                "node_path"     => $nodePath
            ];
            dibi::query("INSERT INTO [ajxp_tasks_nodes] ", $values);
        }
    }

    /**
     * @param Task $task
     */
    protected function loadTaskNodes(&$task){
        $rows = dibi::query("SELECT [node_base_url],[node_path] FROM [ajxp_tasks_nodes] WHERE [task_uid] = %s", $task->getId())->fetchAll();
        foreach($rows as $dibiRow){
            $task->attachToNode($dibiRow['node_base_url'].$dibiRow['node_path']);
        }
    }

    /**
     * @param Task $task
     * @param Schedule $when
     * @return Task
     */
    public function createTask(Task $task, Schedule $when)
    {
        list($modifiers, $values) = $this->taskToDBValues($task);
        $fields = implode(",", array_keys($modifiers));
        $mods   = implode(",", array_values($modifiers));
        $values = array_values($values);
        array_unshift($values, "INSERT INTO [ajxp_tasks] (".$fields.") VALUES (".$mods.")");
        call_user_func_array(["dibi", "query"], $values);
        $this->insertOrUpdateNodes($task);
    }

    /**
     * @param string $taskId
     * @return Task
     */
    public function getTaskById($taskId)
    {
        $res = dibi::query('SELECT * FROM [ajxp_tasks] WHERE [uid]=%s', $taskId);
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
            list($modifiers, $values) = $this->taskToDBValues($task, true);
            $mods = [];
            foreach($modifiers as $field => $mod){
                $mods[] = "$field=$mod";
            }
            $values = array_values($values);
            $values[] = $task->getId();
            array_unshift($values, "UPDATE [ajxp_tasks] SET ".implode(",", $mods)." WHERE [uid] =%s");
            call_user_func_array(["dibi", "query"], $values);
            $this->insertOrUpdateNodes($task, true);
        }catch (\DibiException $ex){
            Logger::error(__CLASS__, __FUNCTION__, "Error while updating task: ".$ex->getSql());
        }
    }
    
    /**
     * @param string $taskId
     * @return bool
     */
    public function deleteTask($taskId)
    {
        dibi::query("DELETE FROM [ajxp_tasks] WHERE [uid]=%s", $taskId);
        dibi::query("DELETE FROM [ajxp_tasks_nodes] WHERE [task_uid]=%s", $taskId);
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
        $res = dibi::query('SELECT * FROM [ajxp_tasks] WHERE %and', $where);
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
        $res = dibi::query('SELECT * FROM [ajxp_tasks] WHERE %and', $where);
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
            $res = dibi::query("SELECT * FROM [ajxp_tasks],[ajxp_tasks_nodes] WHERE 
                [ajxp_tasks_nodes].[node_base_url] = %s 
                AND [ajxp_tasks_nodes].[node_path] = %s
                AND [status] NOT IN (1,4,8)", rtrim($node->getContext()->getUrlBase(), '/'), $node->getPath());
            foreach ($res->fetchAll() as $row) {
                $tasks[] = $this->taskFromDBValues($row);
            }
        }catch(\DibiException $e){
            Logger::error(__CLASS__, __FUNCTION__, "Error while retrieving task for node: ".$e->getSql());
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
        $res = dibi::query('SELECT * FROM [ajxp_tasks] WHERE %and', $where);
        foreach ($res->fetchAll() as $row) {
            $tasks[] = $this->taskFromDBValues($row);
        }
        return $tasks;

    }
}
