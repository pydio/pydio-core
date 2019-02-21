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

use Exception;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StringHelper;

use Pydio\Core\Utils\Vars\VarsFilter;
use Pydio\Log\Core\AbstractLogDriver;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Standard logger. Writes logs into text files
 * @package AjaXplorer_Plugins
 * @subpackage Log
 */
class TextLogDriver extends AbstractLogDriver
{
    /**
     * @var Integer Default permissions, in chmod format.
     */
    public $USER_GROUP_RIGHTS = 0770;

    /**
     * @var resource File handle to currently open log file.
     */
    public $fileHandle;

    /**
     * @var array stack of log messages to be written when file becomes available.
     */
    public $stack;

    /**
     * @var String full path to the directory where logs will be kept, with trailing slash.
     */
    public $storageDir = "";

    /**
     * @var String name of the log file to write.
     */
    public $logFileName = "";


    /**
     * Close file handle on objects destructor.
     */
    public function __destruct()
    {
        if ($this->fileHandle !== false) $this->close();
    }

    /**
     * If the plugin is cloned, make sure to renew the $fileHandle
     */
    public function __clone()
    {
        $this->close();
        $this->open();
    }

    /**
     * Initialise storage: check and/or make log folder and file.
     */
    public function initStorage()
    {
        $storageDir = $this->storageDir;
        if (!file_exists($storageDir)) {
            @mkdir($storageDir);
        }
        $this->open();
    }

    /**
     * Open log file for append, and flush out buffered messages to the file.
     */
    public function open()
    {
        if ($this->storageDir != "") {
            $create = false;
            if (!file_exists($this->storageDir . $this->logFileName)) {
                // file creation
                $create = true;
            }
            $this->fileHandle = @fopen($this->storageDir . $this->logFileName, "at+");
            if ($this->fileHandle === false) {
                error_log("Cannot open log file " . $this->storageDir . $this->logFileName);
            }
            if ($this->fileHandle !== false && count($this->stack)) {
                $this->stackFlush();
            }
            if ($create && $this->fileHandle !== false) {
                $mainLink = $this->storageDir . "ajxp_access.log";
                if (file_exists($mainLink)) {
                    @unlink($mainLink);
                }
                @symlink($this->storageDir . $this->logFileName, $mainLink);
            }
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


        $this->storageDir = isset($this->options['LOG_PATH']) ? $this->options['LOG_PATH'] : "";
        $this->storageDir = VarsFilter::filter($this->storageDir, $ctx);
        $this->storageDir = (rtrim($this->storageDir)) . "/";
        $this->logFileName = isset($this->options['LOG_FILE_NAME']) ? $this->options['LOG_FILE_NAME'] : 'log_' . date('m-d-y') . '.txt';
        $this->USER_GROUP_RIGHTS = isset($this->options['LOG_CHMOD']) ? $this->options['LOG_CHMOD'] : 0770;

        if (preg_match("/(.*)date\('(.*)'\)(.*)/i", $this->logFileName, $matches)) {
            $this->logFileName = $matches[1] . date($matches[2]) . $matches[3];
        }

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
     * @throws Exception
     */
    public function write2($level, $ip, $user, $repositoryId, $source, $prefix, $message, $nodePathes = array())
    {
        if (InputFilter::detectXSS($message)) $message = "XSS Detected in message!";
        $textMessage = date("m-d-y") . " " . date("H:i:s") . "\t";
        $textMessage .= "$ip\t" . strtoupper((string)$level) . "\t$user\t$source\t$prefix\t$message\n";

        if ($this->fileHandle !== false) {
            if (count($this->stack)) $this->stackFlush();
            if (@fwrite($this->fileHandle, $textMessage) === false) {
                throw new Exception("There was an error writing to log file ($this->logFileName)");
            }
        } else {
            $this->stack[] = $textMessage;
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
            @fwrite($this->fileHandle, $message);
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
        if (is_resource($this->fileHandle)) {
            fclose($this->fileHandle);
            $this->fileHandle = FALSE;
        }
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
     */
    public function listLogFiles($nodeName = "file", $year = null, $month = null, $rootPath = "/logs")
    {
        $logs = array();
        if (!is_dir($this->storageDir)) {
            return $logs;
        }
        $years = array();
        $months = array();
        if (($handle = opendir($this->storageDir)) !== false) {
            while ($file = readdir($handle)) {
                if ($file == "index.html" || $file == "ajxp_access.log") continue;
                $split = explode(".", $file);
                if (!count($split) || $split[0] == "") continue;
                $split2 = explode("_", $split[0]);
                $date = $split2[1];
                $dSplit = explode("-", $date);
                $time = mktime(0, 0, 1, intval($dSplit[0]), intval($dSplit[1]), intval($dSplit[2]));
                $display = date("l d", $time);
                $fullYear = date("Y", $time);
                $fullMonth = date("F", $time);
                $logM = $fullMonth;
                if ($year != null && $fullYear != $year) continue;
                if ($month != null && $fullMonth != $month) continue;
                $key = "$rootPath/$fullYear/$fullMonth/$date";
                $logs[$key] = [
                    "icon" => "toggle_log.png",
                    "date" => $display,
                    "text" => $date,
                    "is_file" => false,
                    "filename" => $key,
                    "ajxp_mime"         => "datagrid",
                    "grid_datasource"   => "get_action=ls&dir=" . urlencode($key),
                    "grid_header_title" => "Application Logs for $date",
                    "grid_actions"      => "refresh,filter,copy_as_text"
                ];
                $years["$rootPath/$fullYear"] = [
                    "icon" => "x-office-calendar.png",
                    "date" => $fullYear,
                    "display" => $fullYear,
                    "text" => $fullYear,
                    "is_file" => false,
                    "filename" => "$rootPath/$fullYear"
                ];
                $months["$rootPath/$fullYear/$fullMonth"] = [
                    "icon" => "x-office-calendar.png",
                    "date" => $fullMonth,
                    "display" => $logM,
                    "text" => $fullMonth,
                    "is_file" => false,
                    "filename" => "$rootPath/$fullYear/$fullMonth"
                ];
            }
            closedir($handle);
        }
        $result = $years;
        if ($year != null) {
            $result = $months;
            if ($month != null) {
                $result = $logs;
            }
        }
        krsort($result, SORT_STRING);
        return $result;
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
        $logs = [];
        $fName = $this->storageDir . "log_" . $date . ".txt";
        if (!is_file($fName) || !is_readable($fName)) {
            return $logs;
        }

        $lines = file($fName);
        foreach ($lines as $line) {
            $line = StringHelper::xmlEntities($line);
            $matches = explode("\t", $line, 7);
            if (count($matches) == 6) {
                $matches[6] = $matches[5];
                $matches[5] = $matches[4];
                $matches[4] = $matches[3];
                $matches[3] = "";
            }
            if (count($matches) == 7) {
                $fileName = $parentDir . "/" . $matches[0];
                foreach ($matches as $key => $match) {
                    $match = str_replace("\"", "'", $match);
                    $matches[$key] = $match;
                }
                if (count($matches) < 3) continue;
                // rebuild timestamp
                $date = $matches[0];
                list($m, $d, $Y, $h, $i, $s) = sscanf($date, "%i-%i-%i %i:%i:%i");
                $tStamp = mktime($h, $i, $s, $m, $d, $Y);
                $logs[$fileName] = [
                    "is_file" => true,
                    "ajxp_modiftime" => $tStamp,
                    "filename" => $fileName,
                    "ajxp_mime" => "log",
                    "date" => $matches[0],
                    "ip" => $matches[1],
                    "level" => $matches[2],
                    "user" => $matches[3],
                    "source"=> $matches[4],
                    "action" => $matches[5],
                    "params" => $matches[6],
                    "icon" => "toggle_log.png"
                ];
            }
        }
        return $logs;
    }
}
