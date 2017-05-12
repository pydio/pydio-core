<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

namespace Pydio\Log\Implementation;

use Pydio\Core\Model\ContextInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Standard logger. Writes logs into text files
 * @package AjaXplorer_Plugins
 * @subpackage Log
 */
class SysLogDriver extends TextLogDriver
{
    /**
     * @var Integer File handle to currently open log file.
     */
    public $fileHandle = false;

    /**
     * @var array stack of log messages to be written when file becomes available.
     */
    public $stack;

    /**
     * @var String identifer
     */
    public $signature;

    /**
     * Close file handle on objects destructor.
     */
    public function __destruct()
    {
        if ($this->fileHandle !== false) {
            $this->close();
        }
        parent::__destruct();
    }

    /**
     * If the plugin is cloned, make sure to renew the $fileHandle
     */
    public function __clone()
    {
        $this->close();
        $this->open();
        parent::__clone();
    }

    /**
     * Initialise storage: check and/or make log folder and file.
     */
    public function initStorage()
    {
        $this->open();
    }

    /**
     * Open log file for append, and flush out buffered messages to the file.
     */
    public function open()
    {
        $this->fileHandle = openlog($this->signature, LOG_ODELAY | LOG_PID, LOG_LOCAL0);
        if ($this->fileHandle !== false && count($this->stack)) {
            $this->stackFlush();
        }
    }

    /**
     * Initialise the text log driver.
     *
     * Sets the user defined options.
     * Makes sure that the folder and file exist, and makes them if they don't.
     *
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        $this->stack = array();
        $this->fileHandle = false;

        $this->signature = $this->options["IDENTIFIER"];

        $this->initStorage();

    }

    /**
     * Write text to the log file.
     *
     * If write is not allowed because the file is not yet open, the message is buffered until
     * file becomes available.
     *
     * @param String $level Log severity: one of LOG_LEVEL_* (DEBUG,INFO,NOTICE,WARNING,ERROR)
     * @param String $ip The client ip
     * @param String $user The user login
     * @param String $repositoryId current repository ID
     * @param String $source The source of the message
     * @param String $prefix The prefix of the message
     * @param String $message The message to log
     * @param array $nodePathes
     */
    public function write2($level, $ip, $user, $repositoryId, $source, $prefix, $message, $nodePathes = array())
    {
        //syslog already take care of timestamp and log severity
        $textMessage = "$ip\t$user\t$source\t$prefix\t$message";

        switch ($level) {
            case LOG_LEVEL_DEBUG:
                $sysLevel = LOG_DEBUG;
                break;
            case LOG_LEVEL_INFO:
                $sysLevel = LOG_INFO;
                break;
            case LOG_LEVEL_NOTICE:
                $sysLevel = LOG_NOTICE;
                break;
            case LOG_LEVEL_WARNING:
                $sysLevel = LOG_WARNING;
                break;
            case LOG_LEVEL_ERROR:
                $sysLevel = LOG_ERR;
                break;
            default:
                $sysLevel = LOG_LEVEL_INFO;
                break;
        }


        if ($this->fileHandle !== false) {

            if (count($this->stack)) $this->stackFlush();
            syslog($sysLevel, $textMessage);

        } else {
            $this->stack[] = array($sysLevel, $textMessage);
        }

    }

    /**
     * Flush the stack/buffer of messages that couldn't be written earlier.
     *
     */
    public function stackFlush()
    {
        // Flush stack for messages that could have been written before the file opening.
        foreach ($this->stack as $message) {
            syslog($message[0], $message[1]);
        }
        $this->stack = array();
    }

    /**
     * closes the handle to the log file
     *
     * @access public
     */
    public function close()
    {
        if ($this->fileHandle) closelog();
    }

    /**
     * List available logs in XML format.
     *
     * This method prints the response.
     *
     * @param String $nodeName Name of the XML node to use as response.
     * @param Integer $year The year to list.
     * @param Integer $month The month to list.
     * @param string $rootPath
     * @return null
     * @internal param bool $print
     */
    public function listLogFiles($nodeName = "file", $year = null, $month = null, $rootPath = "/logs")
    {
        return ["$rootPath/see" => [
            "date" => "", "icon" => "", "display" => "Logs are not readable via this GUI, they are sent directly to your system logger daemon.",
            "text" => "Logs are not readable via this GUI, they are sent directly to your system logger daemon.", "is_file" => 1, "filename" => "$rootPath/see"
        ]];
    }

    /**
     * Get a log in XML format.
     *
     * @param $parentDir
     * @param String $date Date in m-d-y format.
     * @param String $nodeName The name of the node to use for each log item.
     * @param string $rootPath
     * @param int $cursor
     * @return null
     */
    public function listLogs($parentDir, $date, $nodeName = "log", $rootPath = "/logs", $cursor = -1)
    {
    }
}
