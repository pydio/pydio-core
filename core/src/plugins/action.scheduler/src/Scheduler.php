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

namespace Pydio\Action\Scheduler;

use DOMNode;
use DOMXPath;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\Controller\CliRunner;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Message\ReloadMessage;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;

use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\FileHelper;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\Plugin;

use Cron\CronExpression;
use Pydio\Tasks\Schedule;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class AjxpScheduler
 * @package Pydio\Action\Scheduler
 */
class Scheduler extends Plugin
{
    public $db;

    /**
     * Construction method
     *
     * @param string $id
     * @param string $baseDir
     */
    public function __construct($id, $baseDir)
    {
        parent::__construct($id, $baseDir);

    }

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        if (!$ctx->hasUser()) return;
        if ($ctx->getUser()->getGroupPath() != "/") {
            $this->enabled = false;
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getDbFile()
    {
        if (!isSet($this->db)) {
            $this->db = $this->getPluginWorkDir(true) . "/calendar.json";
        }
        return $this->db;
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

    /**
     * @param \Pydio\Core\Model\ContextInterface $ctx
     * @param DOMNode $contribNode
     */
    public function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if ($contribNode->nodeName != "actions") return;
        $actionXpath = new DOMXPath($contribNode->ownerDocument);
        $paramList = $actionXpath->query('action[@name="scheduler_addTask"]/processing/standardFormDefinition/param[@name="repository_id"]', $contribNode);
        if (!$paramList->length) return;
        $paramNode = $paramList->item(0);
        $sVals = array();
        $repos = RepositoryService::listAllRepositories(true);
        foreach ($repos as $repoId => $repoObject) {
            $sVals[] = $repoId . "|" . StringHelper::xmlEntities($repoObject->getDisplay());
        }
        $sVals[] = "*|All Repositories";
        $paramNode->attributes->getNamedItem("choices")->nodeValue = implode(",", $sVals);

        if (!UsersService::usersEnabled() || !$ctx->hasUser()) return;
        $paramList = $actionXpath->query('action[@name="scheduler_addTask"]/processing/standardFormDefinition/param[@name="user_id"]', $contribNode);
        if (!$paramList->length) return;
        $paramNode = $paramList->item(0);
        $paramNode->attributes->getNamedItem("default")->nodeValue = $ctx->getUser()->getId();
    }

    /**
     * @param $tId
     * @return mixed
     * @throws Exception
     */
    public function getTaskById($tId)
    {
        $tasks = FileHelper::loadSerialFile($this->getDbFile(), false, "json");
        foreach ($tasks as $task) {
            if (!empty($task["task_id"]) && $task["task_id"] == $tId) {
                return $task;
            }
        }
        throw new Exception("Cannot find task");
    }

    /**
     * @param $taskId
     * @param $status
     * @param string $statusMessage
     */
    public function setTaskStatus($taskId, $status, $statusMessage)
    {
        $tData = $this->getTaskById($taskId);
        if(isSet($tData["job_id"])){
            $runningTask = TaskService::getInstance()->getTaskById($tData["job_id"]);
            $runningTask->setStatus($status);
            $runningTask->setStatusMessage($statusMessage);
            TaskService::getInstance()->updateTask($runningTask);
        }
    }

    /**
     * @param $taskId
     * @return array|bool
     */
    public function getTaskStatus($taskId)
    {
        $tData = $this->getTaskById($taskId);
        if(isSet($tData["job_id"])) {
            $runningTask = TaskService::getInstance()->getTaskById($tData["job_id"]);
            if($runningTask === null){
                return [Task::STATUS_PENDING, "Pending"];
            }
            return [$runningTask->getStatus(), $runningTask->getStatusMessage()];
        }
        return [Task::STATUS_PENDING, "Pending"];
    }

    /**
     * @return int
     */
    public function countCurrentlyRunning()
    {
        $tasks = FileHelper::loadSerialFile($this->getDbFile(), false, "json");
        $count = 0;
        foreach ($tasks as $task) {
            $s = $this->getTaskStatus($task["task_id"]);
            if ($s !== false && $s[0] == "RUNNING") {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param ContextInterface $ctx
     * @param $taskId
     * @param null $status
     * @param int $currentlyRunning
     * @param bool $forceStart
     * @return bool
     * @throws Exception
     */
    public function runTask(ContextInterface $ctx, $taskId, $status = null, &$currentlyRunning = -1, $forceStart = false)
    {
        // TODO : Set MasterInterval as config, or detect last execution?
        $masterInterval = 1;
        $maximumProcesses = 2;

        $task = TaskService::getInstance()->getTaskById($taskId);
        $runningChildren = array_filter($task->getChildrenTasks(), function($child){
            return ($child->getStatus() !== Task::STATUS_COMPLETE && $child->getStatus() !== Task::STATUS_FAILED);
        });
        $shouldRunNow = $task->getSchedule()->shouldRunNow($masterInterval);
        $alreadyRunning = count($runningChildren) > 0;

        if (($shouldRunNow && !$alreadyRunning) || $forceStart) {

            $job = clone $task;
            $job->setId(StringHelper::createGUID());
            $job->setParentId($task->getId());
            $job->setStatus(Task::STATUS_PENDING);
            $job->setStatusMessage("Starting...");
            TaskService::getInstance()->createTask($job, new Schedule(Schedule::TYPE_ONCE_NOW));
            if($job->getUserId() !== $ctx->getUser()->getId()){
                $uId = $job->getUserId();
                $job->setUserId($ctx->getUser()->getId());
                $job->setImpersonateUsers($uId);
            }
            CliRunner::applyTaskInBackground($job);

            $currentlyRunning++;
            return true;
        }
        return false;
    }

    /**
     * @param $data1
     * @param $data2
     * @return int
     */
    public function sortTasksByPriorityStatus($data1, $data2)
    {
        if (is_array($data1["status"]) && in_array("QUEUED", $data1["status"])) return -1;
        if (is_array($data2["status"]) && in_array("QUEUED", $data2["status"])) return 1;
        return 0;
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $action = $requestInterface->getAttribute("action");
        /** @var ContextInterface $ctx */
        $ctx    = $requestInterface->getAttribute("ctx");

        switch ($action) {

            //------------------------------------
            // SHARING FILE OR FOLDER
            //------------------------------------
            case "scheduler_runAll":

                $message = "";
                $tasks = TaskService::getInstance()->getScheduledTasks();
                foreach($tasks as $task){
                    $res = $this->runTask($ctx, $task->getId());
                    if($res){
                        $message .= "Launching " . $task->getLabel() . " \n ";
                    }
                }
                if (empty($message)) $message = "Nothing to do";
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream([new UserMessage($message), new ReloadMessage()]));

                break;

            case "scheduler_runTask":

                $err = -1;
                $tId = InputFilter::sanitize($requestInterface->getParsedBody()["task_id"], InputFilter::SANITIZE_ALPHANUM);
                $this->runTask($ctx, $tId, null, $err, true);
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream([new UserMessage("Launching task now"), new ReloadMessage()]));

                break;

            case "scheduler_generateCronExpression":

                $phpCmd = ConfService::getGlobalConf("CLI_PHP");
                $rootInstall = AJXP_INSTALL_PATH . DIRECTORY_SEPARATOR . "cmd.php";
                $logFile = AJXP_CACHE_DIR . DIRECTORY_SEPARATOR . "cmd_outputs" . DIRECTORY_SEPARATOR . "cron_commands.log";
                $cronTiming = "*/5 * * * *";
                HTMLWriter::charsetHeader("text/plain", "UTF-8");
                print "$cronTiming $phpCmd $rootInstall -r=ajxp_conf -u=" . $ctx->getUser()->getId() . " -p=YOUR_PASSWORD_HERE -a=scheduler_runAll >> $logFile";

                break;

            default:
                break;
        }

    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @throws Exception
     */
    public function handleTasks(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $action     = $requestInterface->getAttribute("action");
        $ctx        = $requestInterface->getAttribute("ctx");
        $httpVars   = $requestInterface->getParsedBody();

        switch ($action) {

            case "scheduler_addTask":

                $taskLabel = $httpVars["label"];
                $cronValue = $httpVars["schedule"];
                // Should throw an error if cron format is invalid
                CronExpression::factory($cronValue);
                $actionName = $httpVars["action_name"];
                $repositoryId = $httpVars["repository_id"];
                $i = 1;
                while (array_key_exists("repository_id_" . $i, $httpVars)) {
                    $repositoryId .= "," . $httpVars["repository_id_" . $i];
                    $i++;
                }
                $userId = $httpVars["user_id"];
                $parameters = array();
                if (!empty($httpVars["param_name"]) && !empty($httpVars["param_value"])) {
                    $parameters[$httpVars["param_name"]] = $httpVars["param_value"];
                }
                foreach ($httpVars as $key => $value) {
                    if (preg_match('/^param_name_/', $key)) {
                        $paramIndex = str_replace("param_name_", "", $key);
                        if (preg_match('/ajxptype/', $paramIndex)) continue;
                        if (preg_match('/replication/', $paramIndex)) continue;
                        if (isSet($httpVars["param_value_" . $paramIndex])) {
                            $parameters[$value] = $httpVars["param_value_" . $paramIndex];
                        }
                    }
                }

                if(isSet($httpVars["task_id"])){
                    $edit = true;
                    $task = TaskService::getInstance()->getTaskById(InputFilter::sanitize($httpVars["task_id"], InputFilter::SANITIZE_ALPHANUM));
                    $task->setAction($actionName);
                    $task->setParameters($parameters);
                }else{
                    $edit = false;
                    $task = new Task();
                    $task->setId(StringHelper::createGUID());
                    $task->setType(Task::TYPE_ADMIN);
                }
                $task->setStatus(Task::STATUS_PENDING);
                $task->setStatusMessage("Scheduled");
                $task->setAction($actionName);
                $task->setParameters($parameters);
                $task->setLabel($taskLabel);
                $task->setWsId($repositoryId);
                $task->setUserId($userId);
                $task->setSchedule(new Schedule(Schedule::TYPE_RECURRENT, $cronValue));
                if($edit) {
                    TaskService::getInstance()->updateTask($task);
                }else{
                    TaskService::getInstance()->createTask($task, $task->getSchedule());
                }

                $responseInterface = $responseInterface->withBody(new SerializableResponseStream([new UserMessage("Successfully added/edited task"), new ReloadMessage()]));
                break;

            case "scheduler_removeTask" :

                $task = TaskService::getInstance()->getTaskById(InputFilter::sanitize($httpVars["task_id"], InputFilter::SANITIZE_ALPHANUM));
                if($task !== null){
                    $children = $task->getChildrenTasks();
                    if(!empty($children)){
                        throw new PydioException("This task has currently jobs running, please wait that they are finished");
                    }
                    TaskService::getInstance()->deleteTask($task->getId());
                }
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream([new UserMessage("Successfully removed task"), new ReloadMessage()]));
                break;

            case "scheduler_loadTask":

                $task = TaskService::getInstance()->getTaskById(InputFilter::sanitize($httpVars["task_id"], InputFilter::SANITIZE_ALPHANUM));
                if(empty($task)){
                    throw new PydioException("Cannot find task");
                }
                $tData = [
                    "task_id"       => $task->getId(),
                    "action_name"   => $task->getAction(),
                    "label"         => $task->getLabel(),
                    "schedule"      => $task->getSchedule()->getValue(),
                    "user_id"       => $task->getUserId()
                ];
                $parameters = $task->getParameters();
                $repoId = $task->getWsId();

                $index = 0;
                foreach ($parameters as $pName => $pValue) {
                    if ($index == 0) {
                        $tData["param_name"] = $pName;
                        $tData["param_value"] = $pValue;
                    } else {
                        $tData["param_name_" . $index] = $pName;
                        $tData["param_value_" . $index] = $pValue;
                    }
                    $index++;
                }
                if (strpos($repoId, ",") !== false) {
                    $ids = explode(",", $repoId);
                    $tData["repository_id"] = $ids[0];
                    for ($i = 1; $i < count($ids); $i++) {
                        $tData["repository_id_" . $i] = $ids[$i];
                    }
                }else{
                    $tData["repository_id"] = $repoId;
                }

                $responseInterface = new JsonResponse($tData);

                break;

            default:
                break;
        }

    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function fakeLongTask(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $httpVars = $requestInterface->getParsedBody();
        $taskId = $requestInterface->getAttribute("pydio-task-id");
        $seconds = (isSet($httpVars["time_length"]) ? intval($httpVars["time_length"]) : 2);
        $this->logInfo(__FUNCTION__, "Running Fake task on " . $requestInterface->getAttribute("ctx")->getRepositoryId());
        $responseInterface->getBody()->write('STARTING FAKE TASK');
        TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_RUNNING, "Currently waiting for $seconds seconds");
        sleep($seconds);
        TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_COMPLETE, "Finished waiting");
        $responseInterface->getBody()->write('ENDIND FAKE TASK');
    }


    /**
     * @param ContextInterface $ctx
     * @param $configTree
     */
    public function placeConfigNode(ContextInterface $ctx, &$configTree)
    {
        $mess = LocaleService::getMessages();
        if (isSet($configTree["parameters"])) {

            $configTree["parameters"]["CHILDREN"]["scheduler"] = array(
                "AJXP_MIME" => "scheduler_zone",
                "LABEL" => "action.scheduler.18",
                "DESCRIPTION" => "action.scheduler.22",
                "ICON" => "preferences_desktop.png",
                "METADATA" => array(
                    "icon_class" => "icon-time",
                    "component" => "Scheduler.Board"
                ),
                "LIST" => array($this, "listTasks")
            );

        } else if (isSet($configTree["admin"])) {
            $configTree["admin"]["CHILDREN"]["scheduler"] = array(
                "LABEL" => $mess["action.scheduler.18"],
                "AJXP_MIME" => "scheduler_zone",
                "DESCRIPTION" => $mess["action.scheduler.22"],
                "ICON" => "scheduler/ICON_SIZE/player_time.png",
                "LIST" => array($this, "listTasks"));
        }
    }

    /**
     * @param $httpVars
     * @param $rootPath
     * @param $relativePath
     * @param null $paginationHash
     * @param null $findNodePosition
     * @param null $aliasedDir
     * @return NodesList
     * @throws Exception
     */
    public function listTasks($httpVars, $rootPath, $relativePath, $paginationHash = null, $findNodePosition=null, $aliasedDir=null)
    {
        $dateFormat = LocaleService::getMessages()["date_format"];
        $nodesList = new NodesList("/$rootPath/$relativePath");
        $nodesList->initColumnsData("filelist", "list", "action.scheduler_list")
            ->appendColumn("action.scheduler.12", "ajxp_label")
            ->appendColumn("action.scheduler.2", "schedule")
            ->appendColumn("action.scheduler.1", "action_name")
            ->appendColumn("action.scheduler.4s", "repository_id")
            ->appendColumn("action.scheduler.17", "user_id")
            ->appendColumn("action.scheduler.3", "NEXT_EXECUTION")
            ->appendColumn("action.scheduler.14", "LAST_EXECUTION")
            ->appendColumn("action.scheduler.13", "STATUS");

        $basePath = "/$rootPath/$relativePath";
        $tasks = TaskService::getInstance()->getScheduledTasks();
        foreach($tasks as $task){

            $node = $this->taskToNode($task, $basePath, $dateFormat);
            $children = $task->getChildrenTasks();
            $running = [];
            /** @var \DateTime $lastRunDate */
            $lastRunDate = null;
            foreach ($children as $child){
                if($child->getStatus() !== Task::STATUS_COMPLETE){
                    $running[] = $child;
                }
                $runDate = $child->getStatusChangeDate();
                if($runDate !== null && $lastRunDate <= $runDate){
                    $lastRunDate = $runDate;
                }
            }
            if(count($running)){
                $node->mergeMetadata(["LAST_EXECUTION" => "Jobs running"]);
            }
            $nodesList->addBranch($node);
            if($lastRunDate !== null){
                $node->mergeMetadata(["LAST_EXECUTION" => $lastRunDate->format($dateFormat)]);
            }
            foreach($running as $cTask){
                $nodesList->addBranch($this->taskToNode($cTask, $basePath, $dateFormat, true));
            }

        }

        return $nodesList;

    }

    /**
     * @param Task $task
     * @param $basePath
     * @param $dateFormat
     * @param bool $isChild
     * @return AJXP_Node
     */
    protected function taskToNode(Task $task, $basePath, $dateFormat, $isChild = false){

        $s = $task->getSchedule()->getValue();
        $meta = [
            "task_id"       => $task->getId(),
            "icon"          => "scheduler/ICON_SIZE/task.png",
            "ajxp_mime"     => ($isChild ? "scheduler_task":"scheduler_task"), // TODO: introduce a different mime for jobs?
            "schedule"      => $s,
            "text"          => ($isChild ? " ---- job running" : $task->getLabel()),
            "label"         => ($isChild ? " ---- job running" : $task->getLabel()),
            "action_name"   => $task->getAction(),
            "repository_id" => $task->getWsId(),
            "user_id"       => $task->getUserId(),
            "STATUS"        => $task->getStatusMessage()
        ];
        $cron = CronExpression::factory($s);
        $next = $cron->getNextRunDate();
        $meta["NEXT_EXECUTION"] = ($isChild ? "-" : $next->format($dateFormat));
        $meta["LAST_EXECUTION"] = "-";

        $key = $basePath."/".$task->getId();
        return new AJXP_Node($key, $meta);
    }

    /**
     * @param $taskId
     * @param $label
     * @param $schedule
     * @param $actionName
     * @param $repositoryIds
     * @param $userId
     * @param $paramsArray
     * @throws Exception
     */
    public function addOrUpdateTask($taskId, $label, $schedule, $actionName, $repositoryIds, $userId, $paramsArray)
    {
        $tasks = FileHelper::loadSerialFile($this->getDbFile(), false, "json");
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
        if (isSet($theIndex)) $tasks[$theIndex] = $data;
        else $tasks[] = $data;
        FileHelper::saveSerialFile($this->getDbFile(), $tasks, true, false, "json");

    }

    /**
     * @param $taskId
     * @throws Exception
     */
    public function removeTask($taskId)
    {
        $tasks = FileHelper::loadSerialFile($this->getDbFile(), false, "json");
        foreach ($tasks as $index => $task) {
            if ($task["task_id"] == $taskId) {
                unset($tasks[$index]);
                break;
            }
        }
        FileHelper::saveSerialFile($this->getDbFile(), $tasks, true, false, "json");
    }

}