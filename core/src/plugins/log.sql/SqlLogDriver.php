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

use DateTime;
use dibi;
use DibiDateTime;
use DibiException;
use Exception;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Utils\DBHelper;
use Pydio\Core\Utils\FileHelper;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\OptionsHelper;
use Pydio\Core\Utils\Vars\PathUtils;
use Pydio\Core\PluginFramework\SqlTableProvider;
use Pydio\Log\Core\AbstractLogDriver;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * SQL Logging Plugin
 * Requires php bcmath (for inet_dtop/inet_ptod) enabled and php version 5.1 (for DateTime class) minimum
 * @package AjaXplorer_Plugins
 * @subpackage Log
 */
class SqlLogDriver extends AbstractLogDriver implements SqlTableProvider
{
    /**
     * @var array
     */
    private $sqlDriver;
    private $queries;

    /**
     * Initialize the driver.
     *
     * Gives the driver a chance to set up it's connection / file resource etc..
     *
     * @param ContextInterface $ctx
     * @param array $options
     * @throws \Pydio\Core\Exception\DBConnectionException
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        $this->sqlDriver = OptionsHelper::cleanDibiDriverParameters($options["SQL_DRIVER"]);
        try {
            if (!dibi::isConnected()) {
                dibi::connect($this->sqlDriver);
            }
        } catch (DibiException $e) {
            throw new \Pydio\Core\Exception\DBConnectionException();
        }
        $this->queries = FileHelper::loadSerialFile($this->getBaseDir() . "/queries.json", false, "json");
    }

    public function performChecks()
    {
        if (!isSet($this->options)) return;
        $test = OptionsHelper::cleanDibiDriverParameters($this->options["SQL_DRIVER"]);
        if (!count($test)) {
            throw new Exception("Please define an SQL connexion in the core configuration");
        }
    }

    /**
     * Find users last connections from log table
     * @param array $userIds
     * @return array
     */
    public function usersLastConnection($userIds)
    {
        $res = dibi::query("SELECT [user], MAX([logdate]) AS date_max FROM [ajxp_log] WHERE [user] IN (%s) GROUP BY [user]", $userIds);
        $all = $res->fetchPairs("user", "date_max");
        return $all;
    }

    /**
     * @return array
     */
    public function getDriverConfig(){
        return $this->sqlDriver;
    }

    /**
     * @param $actionName
     * @param $httpVars
     * @param $fileVars
     */
    public function exposeQueries($actionName, &$httpVars, &$fileVars)
    {

        header('Content-type: application/json');
        echo json_encode($this->queries);

    }

    /**
     * @param $queryName
     * @return bool
     */
    private function getQuery($queryName)
    {
        foreach ($this->queries as $q) {
            if (isset($q["NAME"]) && $q["NAME"] == $queryName) return $q;
        }
        return false;
    }

    /**
     * @param $queryName
     * @param $start
     * @param $count
     * @param string $frequency
     * @param array $additionalFilters
     * @return array|\DibiRow[]
     * @throws Exception
     */
    protected function processOneQuery($queryName, $start, $count, $frequency = "auto", $additionalFilters = array())
    {

        $query = $this->getQuery($queryName);
        if ($query === false) {
            throw new Exception("Cannot find query " . $queryName);
        }
        $pg = ($this->sqlDriver["driver"] == "postgre");

        $mess = LocaleService::getMessages();

        $format = 'Y-m-d 00:00:00';
        $endFormat = 'Y-m-d 23:59:59';
        $dKeyFormat = $mess["date_relative_date_format"];
        $ref = time();
        $last = $start + $count;
        $startDate = date($format, strtotime("-$last day", $ref));
        $endDate = date($endFormat, strtotime("-$start day", $ref));
        $dateCursor = "logdate > '$startDate' AND logdate <= '$endDate'";
        foreach ($additionalFilters as $filterField => $filterValue) {
            $comparator = (strpos($filterValue, "%") !== false ? "LIKE" : "=");
            $dateCursor .= " AND [" . InputFilter::sanitize($filterField, InputFilter::SANITIZE_ALPHANUM) . "] $comparator '" . InputFilter::sanitize($filterValue, InputFilter::SANITIZE_EMAILCHARS) . "'";
        }

        $q = $query["SQL"];
        $q = str_replace("AJXP_CURSOR_DATE", $dateCursor, $q);
        if ($pg) {
            $q = str_replace("ORDER BY logdate DESC", "ORDER BY DATE(logdate) DESC", $q);
        }

        //$q .= " LIMIT $start, $count";
        $res = dibi::query($q);
        $all = $res->fetchAll();
        $allDates = array();

        if (isSet($query["AXIS"]) && $query["AXIS"]["x"] == "Date") {
            if ($frequency == "auto") {
                if ($count > 70) $frequency = "month";
                else if ($count > 21) $frequency = "week";
            }
            $groupedSums = array();
            if ($frequency == "week") {
                $groupByFormat = "W";
            } else if ($frequency == "month") {
                $groupByFormat = "n";
            }
        }

        foreach ($all as $row => &$data) {
            // PG: Recapitalize keys
            if ($pg) {
                $newData = array();
                foreach ($data as $k => $v) {
                    $newData[ucfirst($k)] = $v;
                }
                $data = $newData;
            }
            if (isSet($data["File"])) {
                $data["File"] = PathUtils::forwardSlashBasename($data["File"]);
            }
            if (isSet($data["Date"])) {
                if ($data["Date"] instanceof DibiDateTime) {
                    $tStamp = $data["Date"]->getTimestamp();
                } else {
                    $tStamp = strtotime($data["Date"]);
                }
                $key = date($dKeyFormat, $tStamp);
                $data["Date_sortable"] = $tStamp;
                $data["Date"] = $key;
                $allDates[$key] = true;
                if (isSet($groupByFormat)) {
                    $newKey = date($groupByFormat, $tStamp);
                    if (!isSet($groupedSums[$newKey])) {
                        $groupedSums[$newKey] = $data;
                    } else {
                        $valueKey = $query["AXIS"]["y"];
                        $groupedSums[$newKey][$valueKey] += $data[$valueKey];
                    }
                }
            }
        }

        if (isSet($query["AXIS"]) && $query["AXIS"]["x"] == "Date") {
            for ($i = 0; $i < $count; $i++) {
                $dateCurs = $start + $i;
                $timeDate = strtotime("-$dateCurs day", $ref);
                $dateK = date($dKeyFormat, $timeDate);
                if (isSet($groupByFormat)) {
                    $newKey = date($groupByFormat, $timeDate);
                    if (!isSet($groupedSums[$newKey])) {
                        array_push($all, array("Date" => $dateK, "Date_sortable" => $timeDate));
                    }
                } else {
                    if (!isSet($allDates[$dateK])) {
                        array_push($all, array("Date" => $dateK, "Date_sortable" => $timeDate));
                    }
                }
            }
        }

        if (isSet($query["FIGURE"]) && isSet($all[0][$query["FIGURE"]])) {
            $f = $all[0][$query["FIGURE"]];
            if ($f > 1000) $f = number_format($f / 1000, 1, ".", " ") . 'K';
            $all[0] = array($query["FIGURE"] => $f);
        }

        if (isSet($groupByFormat) && isSet($groupedSums)) {
            return array_values($groupedSums);
        } else {
            return $all;
        }

    }

    /**
     * @param $actionName
     * @param $httpVars
     * @param $fileVars
     * @throws Exception
     */
    public function processQuery($actionName, &$httpVars, &$fileVars)
    {

        session_write_close();
        $query_name = $httpVars["query_name"];
        $start = 0;
        $count = 30;
        $frequency = (isSet($httpVars["frequency"]) ? InputFilter::sanitize($httpVars["frequency"], InputFilter::SANITIZE_ALPHANUM) : "auto");
        if (isSet($httpVars["start"])) $start = intval($httpVars["start"]);
        if (isSet($httpVars["count"])) $count = intval($httpVars["count"]);
        $additionalFilters = array();
        if (isSet($httpVars["user"])) $additionalFilters["user"] = InputFilter::sanitize($httpVars["user"], InputFilter::SANITIZE_EMAILCHARS);
        if (isSet($httpVars["ws_id"])) $additionalFilters["repository_id"] = InputFilter::sanitize($httpVars["ws_id"], InputFilter::SANITIZE_ALPHANUM);
        if (isSet($httpVars["filename_filter"])) {
            $additionalFilters["basename"] = str_replace("*", "%", InputFilter::sanitize($httpVars["filename_filter"], InputFilter::SANITIZE_FILENAME));
        }
        if (isSet($httpVars["dirname_filter"])) {
            $additionalFilters["dirname"] = str_replace("*", "%", InputFilter::sanitize($httpVars["dirname_filter"], InputFilter::SANITIZE_DIRNAME));
        }


        $queries = explode(",", $query_name);
        $meta = array();
        if (count($queries) == 1) {
            $qName = InputFilter::sanitize($query_name, InputFilter::SANITIZE_ALPHANUM);
            $all = $this->processOneQuery($qName, $start, $count, $frequency, $additionalFilters);
            $qDef = $this->getQuery($qName);
            if ($qDef !== false && isSet($qDef['AXIS'])) {
                unset($qDef["SQL"]);
                $meta[$qName] = $qDef;
            }
        } else {
            $all = array();
            foreach ($queries as $qName) {
                $qName = InputFilter::sanitize($qName, InputFilter::SANITIZE_ALPHANUM);
                $all[$qName] = $this->processOneQuery($qName, $start, $count, $frequency, $additionalFilters);
                $qDef = $this->getQuery($qName);
                if ($qDef !== false && isSet($qDef['AXIS'])) {
                    unset($qDef["SQL"]);
                    $meta[$qName] = $qDef;
                }
            }
        }

        //$qry = "SELECT FOUND_ROWS() AS NbRows";
        //$res = dibi::query($qry);
        $total_count = 1000; //$res->fetchSingle();

        header('Content-type: application/json');
        $links = array();

        if ($start > $count) {
            $links[] = array('rel' => 'first', 'cursor' => 0, 'count' => $count);
        }
        if ($start > 0) {
            $prev = max(0, $start - $count);
            $links[] = array('rel' => 'previous', 'cursor' => $prev, 'count' => $count);
        }
        if ($start < $total_count) {
            $next = $start + $count;
            $links[] = array('rel' => 'next', 'cursor' => $next, 'count' => $count);
        }
        if ($start < $total_count - $count) {
            $last = $total_count - ($total_count % $count);
            //$links[] = array('rel' => 'last', 'cursor' => $last, 'count' => $count);
        }
        $hLinks = array();
        foreach ($links as $link) {
            $hLinks[] = '<http://localhost/api/ajxp_conf/analytic_query/' . $query_name . '/' . $link["cursor"] . '/' . $link["cursor"] . '>; rel="' . $link["rel"] . '"';
        }
        header('Link: ' . implode(",", $hLinks));

        $envelope = array("links" => $links, "data" => $all, "meta" => $meta);
        echo json_encode($envelope);

    }


    /**
     * Format a table row into an xml list of nodes for the log treeview
     *
     * @param String $node Name of the xml node
     * @param String $icon Icon to use for the list item
     * @param String $dateattrib
     * @param String $display
     * @param String $text Text displayed in the listview but not the treeview.
     * @param String $filename
     * @param Integer $is_file 0|1 to indicate whether this list item is a file or not.
     *
     * @return String Formatted XML node for insertion into the treeview.
     */
    public function formatXmlLogList($node, $icon, $dateattrib, $display, $text, $filename, $is_file = 0)
    {
        return "<$node icon=\"{$icon}\" date=\"{$dateattrib}\" display=\"{$display}\" text=\"{$text}\" is_file=\"{$is_file}\" filename=\"{$filename}\"/>";
    }

    /**
     * Format a table row into an xml list of nodes for the log reader
     *
     * @param String $node Name of the xml node
     * @param String $icon Icon to use for the list item
     * @param String $dateattrib
     * @param String $filename Source of the list, usually a filename
     * @param String $remote_ip Client IP that was logged
     * @param String $log_level Log level of the item
     * @param String $user User who was logged in
     * @param $source
     * @param String $action The action the user performed.
     * @param String $params Parameters to the action
     * @param string $rootPath
     * @param null $rowId
     * @return String Formatted XML node for insertion into the log reader
     *
     */
    public function formatXmlLogItem($node, $icon, $dateattrib, $filename, $remote_ip, $log_level, $user, $source, $action, $params, $rootPath = "/logs", $rowId = null)
    {
        $remote_ip = $this->inet_dtop($remote_ip);
        $log_unixtime = strtotime($dateattrib);
        $log_datetime = date("m-d-y", $log_unixtime) . " " . date("G:i:s", $log_unixtime);
        $log_year = date('Y', $log_unixtime);
        $log_month = date('m', $log_unixtime);
        $log_date = date("m-d-y", $log_unixtime);

        $meta = [
            "icon" => $icon,
            "date" => $log_datetime,
            "ajxp_modiftime" => $log_unixtime,
            "is_file" => true,
            "filename" => "{$rootPath}/{$log_year}/{$log_month}/{$log_date}/{$log_datetime}",
            "ajxp_mime" => "log",
            "ip" => $remote_ip,
            "level" => $log_level,
            "user" => $user,
            "action" => $action,
            "source" => $source,
            "params" => $params
        ];
        if($rowId !== null){
            $meta["cursor"] = $rowId;
        }

        return $meta;

    }

    /**
     * Write an entry to the log.
     *
     * @param String $level Log severity: one of LOG_LEVEL_* (DEBUG,INFO,NOTICE,WARNING,ERROR)
     * @param String $ip The client ip
     * @param String $user The user login
     * @param String $repositoryId current repository ID
     * @param String $source The source of the message
     * @param String $prefix The prefix of the message
     * @param String $message The message to log
     * @param array $nodesPathes
     */
    public function write2($level, $ip, $user, $repositoryId, $source, $prefix, $message, $nodesPathes = array())
    {
        if ($prefix == "Log In" && $message == "context=API") {
            // Limit the number of logs
            $test = dibi::query('SELECT [logdate] FROM [ajxp_log] WHERE [user]=%s AND [message]=%s AND [params]=%s ORDER BY [logdate] DESC %lmt %ofs', $user, $prefix, $message, 1, 0);
            $lastInsert = $test->fetchSingle();
            $now = new DateTime('NOW');
            if ($lastInsert instanceof DibiDateTime) {
                $lastTimestamp = $lastInsert->getTimestamp();
            } else {
                $lastTimestamp = strtotime($lastInsert);
            }
            if ($lastInsert !== false && $now->getTimestamp() - $lastTimestamp < 60 * 60) {
                // IGNORING, LIMIT API LOGINS TO ONE PER HOUR, OR IT WILL FILL THE LOGS
                return;
            }
        }
        $files = array(array("dirname" => "", "basename" => ""));
        if (InputFilter::detectXSS($message)) {
            $message = "XSS Detected in Message!";
        } else if (count($nodesPathes)) {
            $files = array();
            foreach ($nodesPathes as $path) {
                $parts = pathinfo($path);
                $files[] = array("dirname" => $parts["dirname"], "basename" => $parts["basename"]);
            }
        }
        foreach ($files as $fileDef) {
            $log_row = Array(
                'logdate' => new DateTime('NOW'),
                'remote_ip' => $this->inet_ptod($ip),
                'severity' => strtoupper((string)$level),
                'user' => $user,
                'source' => $source,
                'message' => $prefix,
                'params' => $message,
                'repository_id' => $repositoryId,
                'device' => $_SERVER['HTTP_USER_AGENT'],
                'dirname' => $fileDef["dirname"],
                'basename' => $fileDef["basename"]
            );
            //we already handle exception for write2 in core.log
            dibi::query('INSERT INTO [ajxp_log]', $log_row);
        }
    }

    /**
     * List available log files in XML
     *
     * @param string $nodeName
     * @param null $year
     * @param null $month
     * @param string $rootPath
     * @return array|\String[]
     * @throws DibiException
     * @throws PydioException
     * @internal param bool $print
     */
    public function listLogFiles($nodeName = "file", $year = null, $month = null, $rootPath = "/logs")
    {
        $data = array();

        switch ($this->sqlDriver["driver"]) {
            case "sqlite":
            case "sqlite3":
                $yFunc = "strftime('%Y', [logdate])";
                $mFunc = "strftime('%m', [logdate])";
                $dFunc = "date([logdate])";
                break;
            case "mysqli":
            case "mysql":
                $yFunc = "YEAR([logdate])";
                $mFunc = "MONTH([logdate])";
                $dFunc = "DATE([logdate])";
                break;
            case "postgre":
                $yFunc = "EXTRACT(YEAR FROM [logdate])";
                $mFunc = "EXTRACT(MONTH FROM [logdate])";
                $dFunc = "DATE([logdate])";
                break;
            default:
                throw new PydioException("ERROR!, DB driver " . $this->sqlDriver["driver"] . " not supported yet in __FUNCTION__");
        }

        try {
            if ($month != null) { // Get days

                //cal_days_in_month(CAL_GREGORIAN, $month, $year)
                $start_time = mktime(0, 0, 0, $month, 1, $year);
                $end_time = mktime(0, 0, 0, $month + 1, 1, $year);

                $q = 'SELECT
                    DISTINCT ' . $dFunc . ' AS logdate
                    FROM [ajxp_log]
                    WHERE [logdate] >= %t AND [logdate] < %t';
                $result = dibi::query($q, $start_time, $end_time);

                foreach ($result as $r) {
                    $log_time = strtotime($r['logdate']);

                    $fullYear = date('Y', $log_time);
                    $logM = date('m', $log_time);
                    $date = $r['logdate'];
                    if ($date instanceof DibiDateTime) {
                        $date = $date->format("Y-m-d");
                    }
                    $key = "$rootPath/$fullYear/$logM/$date";
                    $data[$key] = [
                        "icon"              => "toggle_log.png",
                        "date"              => $date,
                        "is_file"           => false,
                        "ajxp_mime"         => "datagrid",
                        "grid_datasource"   => "get_action=ls&dir=" . urlencode($key),
                        "grid_header_title" => "Application Logs for $date",
                        "grid_actions"      => "refresh,filter,copy_as_text"
                    ];
                }

            } else if ($year != null) { // Get months
                $year_start_time = mktime(0, 0, 0, 1, 1, $year);
                $year_end_time = mktime(0, 0, 0, 1, 1, $year + 1);

                $q = 'SELECT
                    DISTINCT ' . $yFunc . ' AS year,
                    ' . $mFunc . ' AS month
                    FROM [ajxp_log]
                    WHERE [logdate] >= %t AND [logdate] < %t';
                $result = dibi::query($q, $year_start_time, $year_end_time);

                foreach ($result as $r) {
                    /* We always recreate a unix timestamp while looping because it provides us with a uniform way to format the date.
                     * The month returned by the database will not be zero-padded and causes problems down the track when DateTime zero pads things */
                    $month_time = mktime(0, 0, 0, $r['month'], 1, $r['year']);

                    $fullYear = date('Y', $month_time);
                    $logMDisplay = date('F', $month_time);
                    $logM = date('m', $month_time);

                    //"<$node icon=\"{$icon}\" date=\"{$dateattrib}\" display=\"{$display}\" text=\"{$text}\" is_file=\"{$is_file}\" filename=\"{$filename}\"/>";
                    //$data[$r['month']] = $this->formatXmlLogList($nodeName, 'x-office-calendar.png', $logM, $logMDisplay, $logMDisplay, "$rootPath/$fullYear/$logM");
                    $key = "$rootPath/$fullYear/$logM";
                    $data[$key] = [
                        "icon"      => "x-office-calendar.png",
                        "text"      => $logMDisplay,
                        "date"      => $logM,
                        "display"   => $logMDisplay,
                        "is_file"   => false
                    ];

                }

            } else {

                // Append Analytics Node
                $data[$rootPath . "/0000_all_analytics"] = [
                    "icon" => "graphs_viewer/ICON_SIZE/analytics.png",
                    "ajxp_mime" => "ajxp_graphs",
                    "is_file"   => true,
                    "text"      => "Analytics Dashboard"
                ];

                // Get years
                $q = 'SELECT
                    DISTINCT ' . $yFunc . ' AS year
                    FROM [ajxp_log]';
                $result = dibi::query($q);

                foreach ($result as $r) {
                    $fullYear = $r['year'];
                    $nodeKey = "$rootPath/$fullYear";
                    $data[$nodeKey] = [
                        "icon"      => "x-office-calendar.png",
                        "text"      => $fullYear,
                        "date"      => $fullYear,
                        "display"   => $fullYear,
                        "is_file"   => false
                    ];

                }
            }
        } catch (DibiException $e) {
            throw $e;
        }

        return $data;
    }

    /**
     * List log contents in XML
     *
     * @param $parentDir
     * @param String $date Assumed to be m-d-y format.
     * @param string $nodeName
     * @param string $rootPath
     * @param int $cursor
     * @return \array[]
     */
    public function listLogs($parentDir, $date, $nodeName = "log", $rootPath = "/logs", $cursor = -1)
    {
        $start_time = strtotime($date);
        $end_time = mktime(0, 0, 0, date('m', $start_time), date('d', $start_time) + 1, date('Y', $start_time));
        $logs = [];

        try {
            if ($cursor != -1) {
                $q = 'SELECT * FROM [ajxp_log] WHERE [id] > %i ORDER BY [logdate] DESC';
                $result = dibi::query($q, $cursor);
            } else {
                $q = 'SELECT * FROM [ajxp_log] WHERE [logdate] BETWEEN %t AND %t ORDER BY [logdate] DESC';
                $result = dibi::query($q, $start_time, $end_time);
            }
            $currentCount = 1;
            foreach ($result as $r) {

                if (isSet($buffer) && $buffer["user"] == $r["user"] && $buffer["message"] == $r["message"] && $buffer["params"] == $r["params"]) {
                    $currentCount++;
                    continue;
                }
                if (isSet($buffer)) {
                    $meta = $this->formatXmlLogItem(
                        $nodeName,
                        'toggle_log.png',
                        $buffer['logdate'],
                        $date,
                        $buffer['remote_ip'],
                        $buffer['severity'],
                        $buffer['user'],
                        $buffer['source'],
                        $buffer['message'] . ($currentCount > 1 ? " (" . $currentCount . ")" : ""),
                        $buffer['params'],
                        $rootPath,
                        $buffer['id']
                    );
                    $logs[$meta["filename"]] = $meta;
                }
                $meta = $this->formatXmlLogItem(
                    $nodeName,
                    'toggle_log.png',
                    $r['logdate'],
                    $date,
                    $r['remote_ip'],
                    $r['severity'],
                    $r['user'],
                    $r['source'],
                    $r['message'],
                    $r['params'],
                    $rootPath,
                    $r['id']
                );
                $logs[$meta["filename"]] = $meta;

                $currentCount = 1;
                $buffer = $r;

            }

        } catch (DibiException $e) {
            //echo get_class($e), ': ', $e->getMessage(), "\n";
        }
        return $logs;
    }

    // IPV4/6 <--> DEC Lovingly lifted from stackoverflow, credit to Sander Marechal
    // Requires bcmath

    /**
     * Convert an IP address from presentation to decimal(39,0) format suitable for storage in MySQL
     *
     * @param string $ip_address An IP address in IPv4, IPv6 or decimal notation
     * @return string The IP address in decimal notation
     */
    public function inet_ptod($ip_address)
    {
        return $ip_address;
        /*
        // IPv4 address
        if (strpos($ip_address, ':') === false && strpos($ip_address, '.') !== false) {
            $ip_address = '::' . $ip_address;
        }

        // IPv6 address
        if (strpos($ip_address, ':') !== false) {
            $network = inet_pton($ip_address);
            $parts = unpack('N*', $network);

            foreach ($parts as &$part) {
                    if ($part < 0) {
                            $part = bcadd((string) $part, '4294967296');
                    }

                    if (!is_string($part)) {
                            $part = (string) $part;
                    }
            }

            $decimal = $parts[4];
            $decimal = bcadd($decimal, bcmul($parts[3], '4294967296'));
            $decimal = bcadd($decimal, bcmul($parts[2], '18446744073709551616'));
            $decimal = bcadd($decimal, bcmul($parts[1], '79228162514264337593543950336'));

            return $decimal;
        }

        // Decimal address
        return $ip_address;
        */
    }

    /**
     * Convert an IP address from decimal format to presentation format
     *
     * @param string $decimal An IP address in IPv4, IPv6 or decimal notation
     * @return string The IP address in presentation format
     */
    public function inet_dtop($decimal)
    {
        return $decimal;
        /*
        // IPv4 or IPv6 format
        if (strpos($decimal, ':') !== false || strpos($decimal, '.') !== false) {
            return $decimal;
        }

        // Decimal format
        $parts = array();
        $parts[1] = bcdiv($decimal, '79228162514264337593543950336', 0);
        $decimal = bcsub($decimal, bcmul($parts[1], '79228162514264337593543950336'));
        $parts[2] = bcdiv($decimal, '18446744073709551616', 0);
        $decimal = bcsub($decimal, bcmul($parts[2], '18446744073709551616'));
        $parts[3] = bcdiv($decimal, '4294967296', 0);
        $decimal = bcsub($decimal, bcmul($parts[3], '4294967296'));
        $parts[4] = $decimal;

        foreach ($parts as &$part) {
            if (bccomp($part, '2147483647') == 1) {
                    $part = bcsub($part, '4294967296');
            }

            $part = (int) $part;
        }

        $network = pack('N4', $parts[1], $parts[2], $parts[3], $parts[4]);
        $ip_address = inet_ntop($network);

        // Turn IPv6 to IPv4 if it's IPv4
        if (preg_match('/^::\d+.\d+.\d+.\d+$/', $ip_address)) {
            return substr($ip_address, 2);
        }

        return $ip_address;
        */
    }

    /**
     * @param array $param
     * @return string
     * @throws Exception
     */
    public function installSQLTables($param)
    {
        $p = OptionsHelper::cleanDibiDriverParameters($param["SQL_DRIVER"]);
        return DBHelper::runCreateTablesQuery($p, $this->getBaseDir() . "/create.sql");
    }

}
