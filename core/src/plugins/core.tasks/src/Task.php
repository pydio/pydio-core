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
    public $wsId;
    /**
     * @var string
     */
    public $wsIdentifier;

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

    /**
     * @var array
     */
    public $nodes = [];

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
    public function getWsId()
    {
        return $this->wsId;
    }

    /**
     * @param string $wsId
     */
    public function setWsId($wsId)
    {
        $this->wsId = $wsId;
    }

    /**
     * @return string
     */
    public function getWsIdentifier()
    {
        return $this->wsIdentifier;
    }

    /**
     * @param string $wsIdentifier
     */
    public function setWsIdentifier($wsIdentifier)
    {
        $this->wsIdentifier = $wsIdentifier;
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

    public function attachToNode($nodePath){
        $this->nodes[] = $nodePath;
    }


}