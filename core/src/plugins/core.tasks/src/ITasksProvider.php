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

use Pydio\Access\Core\Model\AJXP_Node;


use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Interface ITasksProvider
 * @package Pydio\Tasks
 */
interface ITasksProvider
{
    /**
     * @param Task $task
     * @param Schedule $when
     * @return Task
     */
    public function createTask(Task $task, Schedule $when);

    /**
     * @param string $taskId
     * @return Task
     */
    public function getTaskById($taskId);


    /**
     * @param Task $task
     * @return Task
     */
    public function updateTask(Task $task);
    
    /**
     * @param string $taskId
     * @return bool
     */
    public function deleteTask($taskId);


    /**
     * @return Task[]
     */
    public function getPendingTasks();

    /**
     * @return Task[]
     */
    public function getScheduledTasks();

    /**
     * @param UserInterface $user
     * @param RepositoryInterface $repository
     * @return Task[]
     */
    public function getCurrentRunningTasks($user = null, $repository = null);

    /**
     * @param AJXP_Node $node
     * @return Task[]
     */
    public function getActiveTasksForNode(AJXP_Node $node);

    /**
     * @param UserInterface $user
     * @param RepositoryInterface $repository
     * @param int $status
     * @param int $scheduleType
     * @param int $taskType
     * @param string $parentUid
     * @return \Pydio\Tasks\Task[]
     */
    public function getTasks($user = null, $repository = null, $status = -1, $scheduleType = -1, $taskType = Task::TYPE_USER, $parentUid = "");

    /**
     * @param string $taskId
     * @return Task[]
     */
    public function getChildrenTasks($taskId);
}