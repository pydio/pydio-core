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


defined('AJXP_EXEC') or die('Access not allowed');

class Task
{
    const STATUS_PENDING = 1;
    const STATUS_RUNNING = 2;
    const STATUS_COMPLETE = 4;
    const STATUS_FAILED = 8;
    const STATUS_PAUSED = 16;

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $userId;
    /**
     * @var string
     */
    public $repositoryId;
    /**
     * @var string
     */
    public $repositoryIdentifier;

    /**
     * @var int
     */
    public $status;
    /**
     * @var Schedule
     */
    public $schedule;
    /**
     * @var string
     */
    public $action;
    /**
     * @var array
     */
    public $parameters;

    public function __construct()
    {
        $this->status = self::STATUS_PENDING;
        $this->parameters = [];
        $this->schedule = Schedule::scheduleNow();
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param string $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return string
     */
    public function getRepositoryId()
    {
        return $this->repositoryId;
    }

    /**
     * @param string $repositoryId
     */
    public function setRepositoryId($repositoryId)
    {
        $this->repositoryId = $repositoryId;
    }

    /**
     * @return string
     */
    public function getRepositoryIdentifier()
    {
        return $this->repositoryIdentifier;
    }

    /**
     * @param string $repositoryIdentifier
     */
    public function setRepositoryIdentifier($repositoryIdentifier)
    {
        $this->repositoryIdentifier = $repositoryIdentifier;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return Schedule
     */
    public function getSchedule()
    {
        return $this->schedule;
    }

    /**
     * @param Schedule $schedule
     */
    public function setSchedule($schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }


}