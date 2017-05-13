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


use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class Task
 * @package Pydio\Tasks
 */
class Task
{
    const TYPE_USER     = 1;
    const TYPE_ADMIN    = 2;

    const STATUS_PENDING = 1;
    const STATUS_RUNNING = 2;
    const STATUS_COMPLETE = 4;
    const STATUS_FAILED = 8;
    const STATUS_PAUSED = 16;
    const STATUS_TEMPLATE = 32;
    const STATUS_INTERRUPT = 64;

    const FLAG_STOPPABLE    = 1;
    const FLAG_RESUMABLE    = 2;
    const FLAG_HAS_PROGRESS  = 4;

    /**
     * @var string
     */
    public $id;

    /**
     * A boolean combination
     * of the FLAG_XXX constants
     * @var integer
     */
    public $flags;

    /**
     * @var string
     */
    public $label;

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
    public $status = 1;

    /**
     * @var string
     */
    public $statusMessage;

    /**
     * @var int
     */
    public $progress = -1;

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
     * @var int
     */
    public $type = self::TYPE_USER;

    /**
     * @var string Uid of parent task
     */
    public $parentId;

    /**
     * @var int
     */
    public $statusChangeDate;

    /**
     * @var int
     */
    public $creationDate;

    /**
     * @var string
     */
    private $impersonateUsers;

    /**
     * @var array
     */
    public $nodes = [];

    /**
     * Task constructor.
     */
    public function __construct()
    {
        $this->status = self::STATUS_PENDING;
        $this->parameters = [];
        $this->flags = 0;
        $this->schedule = Schedule::scheduleNow();
    }

    /**
     * @return Task[]
     */
    public function getChildrenTasks(){
        return TaskService::getInstance()->getChildrenTasks($this->id);
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
     * @return int
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * @param int $flags
     */
    public function setFlags($flags)
    {
        $this->flags = $flags;
    }

    /**
     * @return int
     */
    public function isStoppable(){
        return $this->flags & Task::FLAG_STOPPABLE;
    }

    /**
     * @return int
     */
    public function isResumable(){
        return $this->flags & Task::FLAG_RESUMABLE;
    }

    /**
     * @return int
     */
    public function hasProgress(){
        return $this->flags & Task::FLAG_HAS_PROGRESS;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * Set label like 'Launching task $actionLabel in background'
     * @param array $messages
     * @param string $actionLabelId
     */
    public function setActionLabel($messages, $actionLabelId){
        $this->setLabel(str_replace("%s", $messages[$actionLabelId], $messages[556]));
    }

    /**
     * @return ContextInterface
     */
    public function getContext(){
        return new Context($this->userId, $this->wsId);
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
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->statusMessage;
    }

    /**
     * @param string $statusMessage
     */
    public function setStatusMessage($statusMessage)
    {
        $this->statusMessage = $statusMessage;
    }

    /**
     * @return int
     */
    public function getProgress()
    {
        return $this->progress;
    }

    /**
     * @param int $progress
     */
    public function setProgress($progress)
    {
        $this->progress = $progress;
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

    /**
     * @param $nodePath
     */
    public function attachToNode($nodePath){
        $this->nodes[] = $nodePath;
    }

    /**
     * @return string
     */
    public function getImpersonateUsers()
    {
        return $this->impersonateUsers;
    }

    /**
     * @param string $impersonateUsers
     */
    public function setImpersonateUsers($impersonateUsers)
    {
        $this->impersonateUsers = $impersonateUsers;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getParentId()
    {
        return $this->parentId;
    }

    /**
     * @param string $parentId
     */
    public function setParentId($parentId)
    {
        $this->parentId = $parentId;
    }

    /**
     * @return \DateTime
     */
    public function getStatusChangeDate()
    {
        if(!empty($this->statusChangeDate)){
            $dTime = new \DateTime();
            $dTime->setTimestamp($this->statusChangeDate);
            return $dTime;
        }else{
            return null;
        }
    }

    /**
     * @param int $statusChangeDate
     */
    public function setStatusChangeDate($statusChangeDate)
    {
        $this->statusChangeDate = $statusChangeDate;
    }

    /**
     * @return \DateTime
     */
    public function getCreationDate()
    {
        if(!empty($this->creationDate)){
            $dTime = new \DateTime();
            $dTime->setTimestamp($this->creationDate);
            return $dTime;
        }else{
            return null;
        }
    }

    /**
     * @param int $creationDate
     */
    public function setCreationDate($creationDate)
    {
        $this->creationDate = $creationDate;
    }



}