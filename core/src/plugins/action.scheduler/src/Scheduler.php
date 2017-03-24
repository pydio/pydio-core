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
 * The latest code can be found at <https://pydio.com>.
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
            //throw new Exception("The command line must be supported. See 'Pydio Core Options'.");
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
                $this->migrateLegacyTasks();
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

            case "scheduler_checkConfig":

                $responseInterface = new JsonResponse(["OK" => ConfService::backgroundActionsSupported()]);

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
                $task->setStatus(Task::STATUS_TEMPLATE);
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
                    foreach ($children as $child){
                        if($child->getStatus() === Task::STATUS_RUNNING){
                            throw new PydioException("This task has currently jobs running, please wait that they are finished");
                        }
                        TaskService::getInstance()->deleteTask($child->getId());
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
                "LABEL" => "action.scheduler.18".(ConfService::backgroundActionsSupported()?"":"e"),
                "DESCRIPTION" => "action.scheduler.22",
                "ICON" => "preferences_desktop.png",
                "METADATA" => array(
                    "icon_class" => "icon-time",
                    "component" => "AdminScheduler.Dashboard"
                ),
                "LIST" => array($this, "listTasks")
            );

        } else if (isSet($configTree["admin"])) {
            $configTree["admin"]["CHILDREN"]["scheduler"] = array(
                "LABEL" => $mess["action.scheduler.18".(ConfService::backgroundActionsSupported()?"":"e")],
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
        $this->migrateLegacyTasks();
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

        if($isChild){
            $label = ($task->getStatus() === Task::STATUS_FAILED ? "JOB ERROR" : "JOB RUNNING");
            $mime = ($task->getStatus() === Task::STATUS_FAILED ? "scheduler_error_task" : "scheduler_running_task");
            $meta = [
                "task_id"       => $task->getId(),
                "icon"          => "scheduler/ICON_SIZE/task.png",
                "ajxp_mime"     => $mime,
                "text"          => $label,
                "label"         => $label,
                "schedule"      => $task->getStatusMessage(),
                "action_name"   => "",
                "repository_id" => "",
                "user_id"       => "",
                "STATUS"        => "",
                "NEXT_EXECUTION"     => "",
                "LAST_EXECUTION"     => "Started on ". $task->getCreationDate()->format($dateFormat),
            ];
        }else{
            $s = $task->getSchedule()->getValue();
            $meta = [
                "task_id"       => $task->getId(),
                "icon"          => "scheduler/ICON_SIZE/task.png",
                "ajxp_mime"     =>  "scheduler_task",
                "schedule"      => $s,
                "text"          => $task->getLabel(),
                "label"         => $task->getLabel(),
                "action_name"   => $task->getAction(),
                "repository_id" => $task->getWsId(),
                "user_id"       => $task->getUserId(),
                "parameters"    => json_encode($task->getParameters()),
                "STATUS"        => $task->getStatusMessage()
            ];
            $cron = CronExpression::factory($s);
            $next = $cron->getNextRunDate();
            $meta["NEXT_EXECUTION"] = $next->format($dateFormat);
            $meta["LAST_EXECUTION"] = "-";
        }

        $key = $basePath."/".$task->getId();
        return new AJXP_Node($key, $meta);
    }

    /**
     * Migrate old JSON file format to TaskService
     */
    protected function migrateLegacyTasks(){
        $dbFile = $this->getDbFile();
        if(!file_exists($dbFile)) return;
        $tasks = FileHelper::loadSerialFile($dbFile, false, "json");
        foreach ($tasks as $tData){
            $t = new Task();
            $t->setId(StringHelper::createGUID());
            $t->setLabel($tData["label"]);
            $t->setAction($tData["action_name"]);
            $t->setParameters($tData["PARAMS"]);
            $t->setSchedule(new Schedule(Schedule::TYPE_RECURRENT, $tData["schedule"]));
            $t->setUserId($tData["user_id"]);
            $t->setWsId($tData["repository_id"]);
            $t->setType(Task::TYPE_ADMIN);
            $t->setStatus(Task::STATUS_TEMPLATE);
            $t->setStatusMessage("Scheduled");
            TaskService::getInstance()->createTask($t, $t->getSchedule());
        }
        @unlink($dbFile);
    }

}