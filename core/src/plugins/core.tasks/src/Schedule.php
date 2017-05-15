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

use Cron\CronExpression;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class Schedule
 * @package Pydio\Tasks
 */
class Schedule implements \JsonSerializable
{
    const TYPE_RECURRENT = 1;
    const TYPE_ONCE_NOW = 2;
    const TYPE_ONCE_DEFER = 4;

    /**
     * @var int
     */
    private $type;

    /**
     * @var string
     */
    private $value;

    /**
     * Schedule constructor.
     * @param $type
     * @param string $value
     */
    public function __construct($type, $value = ""){
        $this->type = $type;
        $this->value = $value;
    }

    /**
     * Detect if a task should run now
     * @param int $recurringTimeInterval
     * @return bool
     */
    public function shouldRunNow($recurringTimeInterval = 0){

        if($this->type === self::TYPE_ONCE_NOW){
            return true;
        }
        if($this->type !== self::TYPE_RECURRENT){
            return false;
        }

        // Recurring task case : check CRON expression
        $cron = CronExpression::factory($this->value);

        $nowDate = new \DateTime();
        $nowDate->setTimestamp(time());

        $lastExecDate = new \DateTime();
        $lastExecDate->setTimestamp($lastExec = time() - 60 * $recurringTimeInterval);

        $nextRunDate = $cron->getNextRunDate($lastExecDate);
        return ($nextRunDate >= $lastExecDate && $nextRunDate < $nowDate);

    }

    /**
     * @return Schedule
     */
    public static function scheduleNow(){
        return new Schedule(self::TYPE_ONCE_NOW);
    }

    /**
     * @return Schedule
     */
    public static function scheduleDeferred(){
        return new Schedule(self::TYPE_ONCE_DEFER);
    }

    /**
     * @param $recurringDescriptor
     * @return Schedule
     */
    public static function scheduleRecurring($recurringDescriptor){
        return new Schedule(self::TYPE_RECURRENT, $recurringDescriptor);
    }

    /**
     * @return int
     */
    public function getType(){
        return $this->type;
    }

    /**
     * @return string
     */
    public function getValue(){
        return $this->value;
    }

    /**
     * @param $data
     * @return Schedule
     */
    public static function fromJson($data){
        if(is_array($data)){
            return new Schedule($data["type"], $data["value"]);
        }else{
            return new Schedule($data->type, $data->value);
        }
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize(){
        return ["type" => $this->type, "value" => $this->value];
    }
}