<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class AjxpScheduler extends AJXP_Plugin
{
    public $db;

    public function __construct($id, $baseDir)
    {
        parent::__construct($id, $baseDir);

    }

    public function init($options)
    {
        parent::init($options);
        $u = AuthService::getLoggedUser();
        if($u == null) return;
        if ($u->getGroupPath() != "/") {
            $this->enabled = false;
        }
    }

    public function getDbFile()
    {
        if (!isSet($this->db)) {
            $this->db = $this->getPluginWorkDir(true). "/calendar.json" ;
        }
        return $this->db;
    }

    public function unserialize($serialized)
    {
        parent::unserialize($serialized);
    }


    public function performChecks()
    {
        if (!ConfService::backgroundActionsSupported()) {
            throw new Exception("The command line must be supported. See 'Pydio Core Options'.");
        }
        if (!is_dir(dirname($this->getDbFile()))) {
            throw new Exception("Could not create the db folder!");
        }
    }

    public function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if($contribNode->nodeName != "actions") return;
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $paramList = $actionXpath->query('action[@name="scheduler_addTask"]/processing/standardFormDefinition/param[@name="repository_id"]', $contribNode);
        if(!$paramList->length) return;
        $paramNode = $paramList->item(0);
        $sVals = array();
        $repos = ConfService::getRepositoriesList();
        foreach ($repos as $repoId => $repoObject) {
            $sVals[] = $repoId."|". AJXP_Utils::xmlEntities($repoObject->getDisplay());
        }
        $sVals[] = "*|All Repositories";
        $paramNode->attributes->getNamedItem("choices")->nodeValue = implode(",", $sVals);

        if(!AuthService::usersEnabled() || AuthService::getLoggedUser() == null) return;
        $paramList = $actionXpath->query('action[@name="scheduler_addTask"]/processing/standardFormDefinition/param[@name="user_id"]', $contribNode);
        if(!$paramList->length) return;
        $paramNode = $paramList->item(0);
        $paramNode->attributes->getNamedItem("default")->nodeValue = AuthService::getLoggedUser()->getId();
    }

    public function getTaskById($tId)
    {
        $tasks = AJXP_Utils::loadSerialFile($this->getDbFile(), false, "json");
        foreach ($tasks as $task) {
            if ( !empty($task["task_id"]) && $task["task_id"] == $tId) {
                return $task;
            }
        }
        throw new Exception("Cannot find task");
    }

    public function setTaskStatus($taskId, $status, $preserveModeDate =false)
    {
        $statusFile = AJXP_CACHE_DIR."/cmd_outputs/task_".$taskId.".status";
        if($preserveModeDate) $mtime = filemtime($statusFile);
        file_put_contents($statusFile, $status);
        if($preserveModeDate) @touch($statusFile, $mtime);
    }

    public function getTaskStatus($taskId)
    {
        $statusFile = AJXP_CACHE_DIR."/cmd_outputs/task_".$taskId.".status";
        if (file_exists($statusFile)) {
            $c = explode(":", file_get_contents($statusFile));
            if ($c[0] == "RUNNING" && isSet($c[1]) && is_numeric($c[1])) {
                $process = new UnixProcess();
                $process->setPid(intval($c[1]));
                $s = $process->status();
                if ($s === false) {
                    // Process was probably killed!
                    $this->setTaskStatus($taskId, "KILLED", true);
                    return array("KILLED");
                }
            }
            return $c;
        }
        return false;
    }

    public function countCurrentlyRunning()
    {
        $tasks = AJXP_Utils::loadSerialFile($this->getDbFile(), false, "json");
        $count = 0;
        foreach ($tasks as $task) {
            $s = $this->getTaskStatus($task["task_id"]);
            if ($s !== false && $s[0] == "RUNNING") {
                $count++;
            }
        }
        return $count;
    }

    public function runTask($taskId, $status = null, &$currentlyRunning = -1, $forceStart=false)
    {
        $data = $this->getTaskById($taskId);
        $mess = ConfService::getMessages();
        $timeArray = $this->getTimeArray($data["schedule"]);

        // TODO : Set MasterInterval as config, or detect last execution?
        $masterInterval = 1;
        $maximumProcesses = 2;

        $now = time();
        $lastExec = time()-60*$masterInterval;
        $res = $this->getNextExecutionTimeForScript($lastExec, $timeArray);
        $test = date("Y-m-d H:i", $lastExec). " -- ".date("Y-m-d H:i", $res)." --  ".date("Y-m-d H:i", $now);

        $alreadyRunning = false;
        $queued = false;
        if($status == null) $status = $this->getTaskStatus($taskId);
        if ($status !== false) {
            if ($status[0] == "RUNNING") {
                $alreadyRunning = true;
            } else if (in_array("QUEUED", $status)) {
                $queued = true; // Run now !
            }
        }
        if ($res >= $lastExec && $res < $now && !$alreadyRunning && $currentlyRunning >= $maximumProcesses) {
            $this->setTaskStatus($taskId, "QUEUED", true);
            $alreadyRunning = true;
            $queued = false;
        }
        if ( ( $res >= $lastExec && $res < $now && !$alreadyRunning ) || $queued || $forceStart) {
            if ($data["user_id"] == "*/*" || $data["user_id"] == "*") {
                // Recurse all groups and put them into a queue file
                $allUsers = array();
                if($data["user_id"] == "*"){
                    $allUsers = $this->listUsersIds();
                }else{
                    $this->gatherUsers($allUsers, "/");
                }
                $tmpQueue = AJXP_CACHE_DIR."/cmd_outputs/queue_".$taskId."";
                echo "Queuing ".count($allUsers)." users in file ".$tmpQueue."\n";
                file_put_contents($tmpQueue, implode(",", $allUsers));
                $data["user_id"] = "queue:".$tmpQueue;
            }
            if ($data["repository_id"] == "*") {
                $data["repository_id"] = implode(",", array_keys(ConfService::getRepositoriesList()));
            }
            $process = AJXP_Controller::applyActionInBackground(
                $data["repository_id"],
                $data["action_name"],
                $data["PARAMS"],
                $data["user_id"],
                AJXP_CACHE_DIR."/cmd_outputs/task_".$taskId.".status");
            if ($process != null && is_a($process, "UnixProcess")) {
                $this->setTaskStatus($taskId, "RUNNING:".$process->getPid());
            } else {
                $this->setTaskStatus($taskId, "RUNNING");
            }
            $currentlyRunning ++;
            return true;
        }
        return false;
    }

    protected function listUsersIds($baseGroup = "/"){
        $authDriver = ConfService::getAuthDriverImpl();
        $pairs = $authDriver->listUsers($baseGroup);
        return array_keys($pairs);
    }

    protected function gatherUsers(&$users, $startGroup="/")
    {
        $u = $this->listUsersIds($startGroup);
        $users = array_merge($users, $u);
        $g = AuthService::listChildrenGroups($startGroup);
        if (count($g)) {
            foreach ($g as $gName => $gLabel) {
                $this->gatherUsers($users, $startGroup.$gName);
            }
        }
    }


    public function sortTasksByPriorityStatus($data1, $data2)
    {
        if(is_array($data1["status"]) && in_array("QUEUED", $data1["status"])) return -1;
        if(is_array($data2["status"]) && in_array("QUEUED", $data2["status"])) return 1;
        return 0;
    }

    public function switchAction($action, $httpVars, $postProcessData)
    {
        switch ($action) {

            //------------------------------------
            // SHARING FILE OR FOLDER
            //------------------------------------
            case "scheduler_runAll":

                $tasks = AJXP_Utils::loadSerialFile($this->getDbFile(), false, "json");
                $message = "";
                $startRunning = $this->countCurrentlyRunning();
                $statuses = array();
                foreach ($tasks as $index => $task) {
                    $tasks[$index]["status"] = $this->getTaskStatus($task["task_id"]);
                }
                usort($tasks, array($this, "sortTasksByPriorityStatus"));
                foreach ($tasks as $task) {
                    if (isSet($task["task_id"])) {
                        $res = $this->runTask($task["task_id"], $task["status"], $startRunning);
                        if ($res) {
                            $message .= "Running ".$task["label"]." \n ";
                        }
                    }
                }
                if(empty($message)) $message = "Nothing to do";

                if (ConfService::currentContextIsCommandLine()) {
                    print(date("Y-m-d H:i:s")."\t".$message."\n");
                } else {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::sendMessage($message, null);
                    AJXP_XMLWriter::reloadDataNode();
                    AJXP_XMLWriter::close();
                }

            break;

            case "scheduler_runTask":

                $err = -1;
                $this->runTask($httpVars["task_id"], null, $err, true);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::close();

            break;

            case "scheduler_generateCronExpression":

                $phpCmd = ConfService::getCoreConf("CLI_PHP");
                $rootInstall = AJXP_INSTALL_PATH.DIRECTORY_SEPARATOR."cmd.php" ;
                $logFile = AJXP_CACHE_DIR.DIRECTORY_SEPARATOR."cmd_outputs".DIRECTORY_SEPARATOR."cron_commands.log";
                $cronTiming = "*/5 * * * *";
                HTMLWriter::charsetHeader("text/plain", "UTF-8");
                print "$cronTiming $phpCmd $rootInstall -r=ajxp_conf -u=".AuthService::getLoggedUser()->getId()." -p=YOUR_PASSWORD_HERE -a=scheduler_runAll >> $logFile";

            break;

            default:
            break;
        }

    }

    public function placeConfigNode(&$configTree)
    {
        $mess = ConfService::getMessages();
        if (isSet($configTree["admin"])) {
            $configTree["admin"]["CHILDREN"]["scheduler"] = array(
                "LABEL"         => $mess["action.scheduler.18"],
                "AJXP_MIME"     => "scheduler_zone",
                "DESCRIPTION"   => $mess["action.scheduler.22"],
                "ICON"          => "scheduler/ICON_SIZE/player_time.png",
                "LIST"          => array($this, "listTasks"));
        }
    }

    public function listTasks($action, $httpVars, $postProcessData)
    {
        $mess =ConfService::getMessages();
        AJXP_XMLWriter::renderHeaderNode("/admin/scheduler", "Scheduler", false, array("icon" => "scheduler/ICON_SIZE/player_time.png"));
        AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist" switchDisplayMode="list"  template_name="action.scheduler_list">
                 <column messageId="action.scheduler.12" attributeName="ajxp_label" sortType="String"/>
                 <column messageId="action.scheduler.2" attributeName="schedule" sortType="String"/>
                 <column messageId="action.scheduler.1" attributeName="action_name" sortType="String"/>
                 <column messageId="action.scheduler.4s" attributeName="repository_id" sortType="String"/>
                 <column messageId="action.scheduler.17" attributeName="user_id" sortType="String"/>
                 <column messageId="action.scheduler.3" attributeName="NEXT_EXECUTION" sortType="String"/>
                 <column messageId="action.scheduler.14" attributeName="LAST_EXECUTION" sortType="String"/>
                 <column messageId="action.scheduler.13" attributeName="STATUS" sortType="String"/>
        </columns>');
        $tasks = AJXP_Utils::loadSerialFile($this->getDbFile(), false, "json");
        foreach ($tasks as $task) {

            $timeArray = $this->getTimeArray($task["schedule"]);
            $res = $this->getNextExecutionTimeForScript(time(), $timeArray);
                $task["NEXT_EXECUTION"] = date($mess["date_format"], $res);
                $task["PARAMS"] = implode(", ", $task["PARAMS"]);
                $task["icon"] = "scheduler/ICON_SIZE/task.png";
                $task["ajxp_mime"] = "scheduler_task";
                $sFile = AJXP_CACHE_DIR."/cmd_outputs/task_".$task["task_id"].".status";
                if (is_file($sFile)) {
                    $s = $this->getTaskStatus($task["task_id"]);
                    $task["STATUS"] = implode(":", $s);
                    $task["LAST_EXECUTION"] = date($mess["date_format"], filemtime($sFile));
                } else {
                    $task["STATUS"] = "n/a";
                    $task["LAST_EXECUTION"] = "n/a";
                }

                AJXP_XMLWriter::renderNode("/admin/scheduler/".$task["task_id"],
                    (isSet($task["label"])?$task["label"]:"Action ".$task["action_name"]),
                    true,
                    $task
                );
            }
        AJXP_XMLWriter::close();

    }

    public function getTimeArray($schedule)
    {
        $parts = explode(" ", $schedule);
        if(count($parts)!=5) throw new Exception("Invalid Schedule Format ($schedule)");
        $timeArray['minutes'] = $parts[0];
        $timeArray['hours'] = $parts[1];
        $timeArray['days'] = $parts[2];
        $timeArray['dayWeek'] = $parts[3];
        $timeArray['months'] = $parts[4];
        return $timeArray;
    }


    public function addOrUpdateTask($taskId, $label, $schedule, $actionName, $repositoryIds, $userId, $paramsArray)
    {
        $tasks = AJXP_Utils::loadSerialFile($this->getDbFile(), false, "json");
        if (isSet($taskId)) {
            foreach ($tasks as $index => $task) {
                if ($task["task_id"] == $taskId) {
                    $data = $task;
                    $theIndex = $index;
                }
            }
        }
        if (!isSet($theIndex)) {
            $data = array();
            $data["task_id"] = substr(md5(time()), 0, 16);
        }
        $data["label"] = $label;
        $data["schedule"] = $schedule;
        $data["action_name"] = $actionName;
        $data["repository_id"] = $repositoryIds;
        $data["user_id"] = $userId;
        $data["PARAMS"] = $paramsArray;
        if(isSet($theIndex)) $tasks[$theIndex] = $data;
        else $tasks[] = $data;
        AJXP_Utils::saveSerialFile($this->getDbFile(), $tasks, true, false, "json");

    }

    public function removeTask($taskId)
    {
        $tasks = AJXP_Utils::loadSerialFile($this->getDbFile(), false, "json");
        foreach ($tasks as $index => $task) {
            if ($task["task_id"] == $taskId) {
                unset($tasks[$index]);
                break;
            }
        }
        AJXP_Utils::saveSerialFile($this->getDbFile(), $tasks, true, false, "json");
    }

    public function handleTasks($action, $httpVars, $fileVars)
    {
        $tasks = AJXP_Utils::loadSerialFile($this->getDbFile(), false, "json");
        switch ($action) {
            case "scheduler_addTask":
                if (isSet($httpVars["task_id"])) {
                    foreach ($tasks as $index => $task) {
                        if ($task["task_id"] == $httpVars["task_id"]) {
                            $data = $task;
                            $theIndex = $index;
                        }
                    }
                }
                if (!isSet($theIndex)) {
                    $data = array();
                    $data["task_id"] = substr(md5(time()), 0, 16);
                }
                $data["label"] = $httpVars["label"];
                $data["schedule"] = $httpVars["schedule"];
                $data["action_name"] = $httpVars["action_name"];
                $data["repository_id"] =$httpVars["repository_id"];
                $i = 1;
                while (array_key_exists("repository_id_".$i, $httpVars)) {
                    $data["repository_id"].=",".$httpVars["repository_id_".$i];
                    $i++;
                }
                $data["user_id"] = $httpVars["user_id"];
                $data["PARAMS"] = array();
                if (!empty($httpVars["param_name"]) && !empty($httpVars["param_value"])) {
                    $data["PARAMS"][$httpVars["param_name"]] = $httpVars["param_value"];
                }
                foreach ($httpVars as $key => $value) {
                    if (preg_match('/^param_name_/', $key)) {
                        $paramIndex = str_replace("param_name_", "", $key);
                        if(preg_match('/ajxptype/', $paramIndex)) continue;
                        if(preg_match('/replication/', $paramIndex)) continue;
                        if (isSet($httpVars["param_value_".$paramIndex])) {
                            $data["PARAMS"][$value] = $httpVars["param_value_".$paramIndex];
                        }
                    }
                }
                if(isSet($theIndex)) $tasks[$theIndex] = $data;
                else $tasks[] = $data;
                AJXP_Utils::saveSerialFile($this->getDbFile(), $tasks, true, false, "json");

                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage("Successfully added/edited task", null);
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::close();

            break;

            case "scheduler_removeTask" :

                $this->removeTask($httpVars["task_id"]);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage("Successfully removed task", null);
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::close();

            break;

            case "scheduler_loadTask":

                $found = false;
                foreach ($tasks as $task) {
                    if ($task["task_id"] == $httpVars["task_id"]) {
                        $index = 0;
                        $found = true;
                        foreach ($task["PARAMS"] as $pName => $pValue) {
                            if ($index == 0) {
                                $task["param_name"] = $pName;
                                $task["param_value"] = $pValue;
                            } else {
                                $task["param_name_".$index] = $pName;
                                $task["param_value_".$index] = $pValue;
                            }
                            $index ++;
                        }
                        unset($task["PARAMS"]);
                        if (strpos($task["repository_id"], ",") !== false) {
                            $ids = explode(",", $task["repository_id"]);
                            $task["repository_id"] = $ids[0];
                            for ($i = 1; $i<count($ids);$i++) {
                                $task["repository_id_".$i] = $ids[$i];
                            }
                        }
                        break;
                    }
                }
                if ($found) {
                    HTMLWriter::charsetHeader("application/json");
                    echo json_encode($task);
                }

            break;

            default:
            break;
        }
        //var_dump($tasks);

    }

    public function fakeLongTask($action, $httpVars, $fileVars)
    {
        $minutes = (isSet($httpVars["time_length"])?intval($httpVars["time_length"]):2);
        $this->logInfo(__FUNCTION__, "Running Fake task on ".AuthService::getLoggedUser()->getId());
        print('STARTING FAKE TASK');
        sleep($minutes * 30);
        print('ENDIND FAKE TASK');
    }

    public function getNextExecutionTimeForScript($referenceTime, $timeArray)
    {
        $a=null; $m=null; $j=null; $h=null; $min=null;

        $aNow = date("Y", $referenceTime);
        $mNow = date("m", $referenceTime);
        $jNow = date("d", $referenceTime);
        $hNow = date("H", $referenceTime);
        $minNow = date("i", $referenceTime)+1;

        $a = $aNow;
        $m = $mNow - 1;

        while ($this->nextMonth($timeArray, $a, $m, $j, $h, $min) != -1) {			/* on parcourt tous les mois de l'intervalle demandé */							/* jusqu'à trouver une réponse convanable */
            if ($m != $mNow || $a != $aNow) {			/*si ce n'est pas ce mois ci */
                $j = 0;
                if ($this->nextDay($timeArray, $a, $m, $j, $h, $min) == -1) {	/* le premier jour trouvé sera le bon. */					/*  -1 si l'intersection entre jour de semaine */
                    /* et jour du mois est nulle */
                    continue;			/* ...auquel cas on passe au mois suivant */
                } else {					/* s'il y a un jour */
                    $h=-1;
                    $this->nextHour($timeArray, $a, $m, $j, $h, $min);	/* la première heure et la première minute conviendront*/
                    $min = -1;
                    $this->nextMinute($timeArray, $a, $m, $j, $h, $min);
                    return mktime($h, $min, 0, $m, $j, $a);
                }
            } else {						/* c'est ce mois ci */
                $j = $jNow-1;
                while ($this->nextDay($timeArray, $a, $m, $j, $h, $min) != -1) {	/* on cherche un jour à partir d'aujourd'hui compris */
                    if ($j > $jNow) {			/* si ce n'est pas aujourd'hui */				/* on prend les premiers résultats */
                        $h=-1;
                        $this->nextHour($timeArray, $a, $m, $j, $h, $min);
                        $min = -1;
                        $this->nextMinute($timeArray, $a, $m, $j, $h, $min);
                        return mktime($h, $min, 0, $m, $j, $a);
                    }
                    if ($j == $jNow) {		/* même algo pour les heures et les minutes */
                        $h = $hNow - 1;
                        while ($this->nextHour($timeArray, $a, $m, $j, $h, $min) != -1) {
                            if ($h > $hNow) {
                                $min = -1;
                                $this->nextMinute($timeArray, $a, $m, $j, $h, $min);
                                return mktime($h, $min, 0, $m, $j, $a);
                            }
                            if ($h == $hNow) {
                                $min = $minNow - 1;
                                while ($this->nextMinute($timeArray, $a, $m, $j, $h, $min) != -1) {
                                    if ($min > $minNow) { return mktime($h, $min, 0, $m, $j, $a); }

                                    /* si c'est maintenant, on l'éxécute directement */
                                    if ($min == $minNow) {
                                        return $referenceTime;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function parseFormat($min, $max, $intervalle)
    {
        $retour = Array();

        if ($intervalle == '*') {
            for($i=$min; $i<=$max; $i++) $retour[$i] = TRUE;
            return $retour;
        } else {
            for($i=$min; $i<=$max; $i++) $retour[$i] = FALSE;
        }
        if ($intervalle[0] == "/") {
            // Transform Repeat pattern into range
            $repeat = intval(ltrim($intervalle, "/"));
            $values= array();
            for ($i=$min;$i<=$max;$i++) {
                if(($i % $repeat) == 0) $values[] = $i;
            }
            $intervalle = implode(",", $values);
        }

        $intervalle = array_map("trim", explode(',', $intervalle));
        foreach ($intervalle as $val) {
            $val = array_map("trim", explode('-', $val));
            if (isset($val[0]) && isset($val[1])) {
                if ($val[0] <= $val[1]) {
                    for($i=$val[0]; $i<=$val[1]; $i++) $retour[$i] = TRUE;	/* ex : 9-12 = 9, 10, 11, 12 */
                } else {
                    for($i=$val[0]; $i<=$max; $i++) $retour[$i] = TRUE;	/* ex : 10-4 = 10, 11, 12... */
                    for($i=$min; $i<=$val[1]; $i++) $retour[$i] = TRUE;	/* ...et 1, 2, 3, 4 */
                }
            } else {
                $retour[$val[0]] = TRUE;
            }
        }
        return $retour;
    }

    public function nextMonth($timeArray, &$a, &$m, &$j, &$h, &$min)
    {
        $valeurs = $this->parseFormat(1, 12, $timeArray['months']);
        do {
            $m++;
            if ($m == 13) {
                $m=1;
                $a++;		/*si on a fait le tour, on réessaye l'année suivante */
            }
        } while ($valeurs[$m] != TRUE);
    }
    public function nextDay($timeArray, &$a, &$m, &$j, &$h, &$min)
    {
        $valeurs = $this->parseFormat(1, 31, $timeArray['days']);
        $valeurSemaine = $this->parseFormat(0, 6, $timeArray['dayWeek']);

        do {
            $j++;

            /* si $j est égal au nombre de jours du mois + 1 */
            if ($j == date('t', mktime(0, 0, 0, $m, 1, $a))+1) { return -1; }

            $js = date('w', mktime(0, 0, 0, $m, $j, $a));
        } while ($valeurs[$j] != TRUE || $valeurSemaine[$js] != TRUE);
    }
    public function nextHour($timeArray, &$a, &$m, &$j, &$h, &$min)
    {
        $valeurs = $this->parseFormat(0, 23, $timeArray['hours']);

        do {
            $h++;
            if ($h == 24) { return -1; }
        } while ($valeurs[$h] != TRUE);
    }

    public function nextMinute($timeArray, &$a, &$m, &$j, &$h, &$min)
    {
        $valeurs = $this->parseFormat(0, 59, $timeArray['minutes']);

        do {
            $min++;
            if ($min == 60) { return -1; }
        } while ($valeurs[$min] != TRUE);
    }
}
