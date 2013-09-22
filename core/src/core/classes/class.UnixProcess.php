<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>, Cyril Russo
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
defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * Utilitary to launch a process and keep track of it
 * @package Pydio
 * @subpackage Core
 */
class UnixProcess
{
    /**
     * @var string
     */
    private $pid;
    /**
     * @var string
     */
    private $command;
    /**
     * @var string
     */
    private $output;

    /**
     * @param bool|string $cl Command to execute
     * @param bool|string $output A file in which to redirect the output. Send to /dev/null if false.
     */
    public function __construct($cl=false, $output=false)
    {
        if ($output != false) {
              $this->output = $output;
        } else {
               $this->output = "/dev/null";
        }
        if ($cl != false) {
            $this->command = $cl;
            $this->runCom();
        }
    }
    /**
     * Run the command
     * @return void
     */
    private function runCom()
    {
        $command = $this->command.' > '.$this->output.' 2>&1 & echo $!';
        exec($command ,$op);
        $this->pid = (int) $op[0];
        $this->command = $command;
    }
    /**
     * Processid setter
     * @param $pid
     * @return void
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * Processid getter
     * @return string
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * Try to get status from command line by running "ps -p PID"
     * @return bool
     */
    public function status()
    {
        $command = 'ps -p '.$this->pid;
        exec($command,$op);
        if (!isset($op[1]))return false;
        else return true;
    }
    /**
     * Start the command
     * @return bool
     */
    public function start()
    {
        if ($this->command != '')$this->runCom();
        else return true;
    }
    /**
     * Try to kill the process via command line.
     * @return bool
     */
    public function stop()
    {
        $command = 'kill '.$this->pid;
        exec($command);
        if ($this->status() == false)return true;
        else return false;
    }
}
