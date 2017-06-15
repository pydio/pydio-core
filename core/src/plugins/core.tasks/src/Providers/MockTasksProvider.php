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
use Pydio\Access\Core\Model\Repository;
use Pydio\Conf\Core\AbstractUser;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Tasks\Task;
use Pydio\Tasks\Schedule;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class MockTasksProvider
 * @package Pydio\Tasks\Providers
 */
class MockTasksProvider implements \Pydio\Tasks\ITasksProvider
{

    /**
     * @param Task $task
     * @param Schedule $when
     * @return Task
     */
    public function createTask(Task $task, Schedule $when)
    {
        $task->setSchedule($when);
        $task->setId("newly-generated-id");
        return $task;
    }

    /**
     * @param string $taskId
     * @return Task
     */
    public function getTaskById($taskId)
    {
        $fakeTask = new Task();
        $fakeTask->setId($taskId);
        $fakeTask->setAction("fake_action");
        return $fakeTask;
    }

    /**
     * @param Task $task
     * @return Task
     */
    public function updateTask(Task $task)
    {
        return $task;
    }
    
    /**
     * @param string $taskId
     * @return bool
     */
    public function deleteTask($taskId)
    {
        return true;
    }

    /**
     * @return Task[]
     */
    public function getPendingTasks()
    {
        $t1 = new Task();
        $t1->setAction("fake-task-action");
        $t1->setId("fake-task-id");
        return [$t1];
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @return \Pydio\Tasks\Task[]
     */
    public function getActiveTasksForNode(AJXP_Node $node)
    {
        $t1 = new Task();
        $t1->setAction("fake-task-action");
        $t1->setId("fake-task-id");
        return [$t1];
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
        $t1 = new Task();
        $t1->setAction("fake-task-action");
        $t1->setId("fake-task-id");
        return [$t1];
    }

    /**
     * @param AbstractUser $user
     * @param Repository $repository
     * @return \Pydio\Tasks\Task[]
     */
    public function getCurrentRunningTasks($user = null, $repository = null){
        return [];
    }

    /**
     * @return Task[]
     */
    public function getScheduledTasks(){
        return [];
    }

    /**
     * @param string $taskId
     * @return Task[]
     */
    public function getChildrenTasks($taskId){
    }

}