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
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Conf\Core\AbstractAjxpUser;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Tasks\Schedule;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;

defined('AJXP_EXEC') or die( 'Access not allowed');

class CoreIndexer extends Plugin {

    private $verboseIndexation = false;
    private $currentTaskId;

    public function debug($message = ""){
        $this->logDebug("core.indexer", $message);
        if($this->verboseIndexation && ConfService::currentContextIsCommandLine()){
            print($message."\n");
        }
    }

    public function applyAction(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {

        $actionName = $requestInterface->getAttribute("action");
        $httpVars   = $requestInterface->getParsedBody();
        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx        = $requestInterface->getAttribute("ctx");
        $this->currentTaskId = $requestInterface->getAttribute("pydio-task-id") OR null;

        if ($actionName !== "index") return null;

        $userSelection = UserSelection::fromContext($ctx, $httpVars);
        if($userSelection->isEmpty()){
            $userSelection->addFile("/");
        }
        $nodes = $userSelection->buildNodes();

        if (isSet($httpVars["verbose"]) && $httpVars["verbose"] == "true") {
            $this->verboseIndexation = true;
        }
        $taskId = $requestInterface->getAttribute("pydio-task-id");
        if (empty($taskId)) {
            $task = TaskService::actionAsTask($ctx, "index", $httpVars, [$nodes[0]->getUrl()], Task::FLAG_STOPPABLE | Task::FLAG_RESUMABLE);
            $task->setSchedule(new Schedule(Schedule::TYPE_ONCE_DEFER));
            TaskService::getInstance()->enqueueTask($task, $requestInterface, $responseInterface);
            $responseInterface = $responseInterface->withBody(new \Pydio\Core\Http\Response\SerializableResponseStream(new \Pydio\Core\Http\Message\UserMessage("Indexation launched")));
            return $responseInterface;
        }
        // GIVE BACK THE HAND TO USER
        session_write_close();

        foreach($nodes as $node){

            $dir = $node->getPath() == "/" || is_dir($node->getUrl());
            // SIMPLE FILE
            if(!$dir){
                try{
                    $this->debug("Indexing - node.index ".$node->getUrl());
                    Controller::applyHook("node.index", array($node));
                    if($this->currentTaskId){
                        TaskService::getInstance()->updateTaskStatus($this->currentTaskId, Task::STATUS_COMPLETE, "Done");
                    }
                }catch (Exception $e){
                    $this->debug("Error Indexing Node ".$node->getUrl()." (".$e->getMessage().")");
                }
            }else{
                try{
                    $this->recursiveIndexation($ctx, $node);
                }catch (Exception $e){
                    $this->debug("Indexation of ".$node->getUrl()." interrupted by error: (".$e->getMessage().")");
                }
            }

        }

        return null;
    }

    /**
     *
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param int $depth
     * @throws Exception
     */
    public function recursiveIndexation(ContextInterface $ctx, $node, $depth = 0)
    {
        $repository = $node->getRepository();
        $messages = LocaleService::getMessages();
        $user = $ctx->getUser();
        if($depth == 0){
            $this->debug("Starting indexation - node.index.recursive.start  - ". memory_get_usage(true) ."  - ". $node->getUrl());
            $this->setIndexStatus("RUNNING", str_replace("%s", TextEncoder::toUTF8($node->getPath()), $messages["core.index.8"]), $repository, $user);
            Controller::applyHook("node.index.recursive.start", array($node));
        }else{
            if($this->isInterruptRequired($repository, $user)){
                $this->debug("Interrupting indexation! - node.index.recursive.end - ". $node->getUrl());
                Controller::applyHook("node.index.recursive.end", array($node));
                $this->releaseStatus($repository, $user);
                throw new Exception("User interrupted");
            }
        }

        if(!ConfService::currentContextIsCommandLine()) @set_time_limit(120);
        $url = $node->getUrl();
        $this->debug("Indexing Node parent node ".$url);
        $this->setIndexStatus("RUNNING", str_replace("%s", TextEncoder::toUTF8($node->getPath()), $messages["core.index.8"]), $repository, $user);
        if($node->getPath() != "/"){
            try {
                Controller::applyHook("node.index", array($node));
            } catch (Exception $e) {
                $this->debug("Error Indexing Node ".$url." (".$e->getMessage().")");
            }
        }

        $handle = opendir($url);
        if ($handle !== false) {
            while ( ($child = readdir($handle)) != false) {
                if($child[0] == ".") continue;
                $childNode = new AJXP_Node(rtrim($url, "/")."/".$child);
                $childUrl = $childNode->getUrl();
                if(is_dir($childUrl)){
                    $this->debug("Entering recursive indexation for ".$childUrl);
                    $this->recursiveIndexation($ctx, $childNode, $depth + 1);
                }else{
                    try {
                        $this->debug("Indexing Node ".$childUrl);
                        Controller::applyHook("node.index", array($childNode));
                    } catch (Exception $e) {
                        $this->debug("Error Indexing Node ".$childUrl." (".$e->getMessage().")");
                    }
                }
            }
            closedir($handle);
        } else {
            $this->debug("Cannot open $url!!");
        }
        if($depth == 0){
            $this->debug("End indexation - node.index.recursive.end - ". memory_get_usage(true) ."  -  ". $node->getUrl());
            $this->setIndexStatus("RUNNING", "Indexation finished, cleaning...", $repository, $user, false);
            Controller::applyHook("node.index.recursive.end", array($node));
            $this->releaseStatus($repository, $user);
            $this->debug("End indexation - After node.index.recursive.end - ". memory_get_usage(true) ."  -  ". $node->getUrl());
        }
    }


    /**
     * @param \Pydio\Access\Core\Model\Repository $repository
     * @param UserInterface $user
     * @return string
     */
    protected function buildIndexLockKey($repository, $user){
        $scope = $repository->securityScope();
        $key = $repository->getId();
        if($scope == "USER"){
            $key .= "-".$user->getId();
        }else if($scope == "GROUP"){
            $key .= "-".ltrim(str_replace("/", "__", $user->getGroupPath()), "__");
        }
        return $key;
    }


    /**
     * @param String $status
     * @param String $message
     * @param Repository $repository
     * @param UserInterface $user
     * @param boolean $stoppable
     */
    protected function setIndexStatus($status, $message, $repository, $user, $stoppable = true)
    {
        if(isSet($this->currentTaskId)){
            TaskService::getInstance()->updateTaskStatus($this->currentTaskId, Task::STATUS_RUNNING, $message, $stoppable);
        }
        $iPath = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes";
        if(!is_dir($iPath)) mkdir($iPath,0755, true);
        $f = $iPath."/.indexation_status-".$this->buildIndexLockKey($repository, $user);
        $this->debug("Updating file ".$f." with status $status - $message");
        file_put_contents($f, strtoupper($status).":".$message);
    }

    /**
     * @param Repository $repository
     * @param UserInterface $user
     * @return array Array(STATUS, Message)
     */
    protected function getIndexStatus($repository, $user)
    {
        $f = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/.indexation_status-".$this->buildIndexLockKey($repository, $user);
        if (file_exists($f)){
            return explode(":", file_get_contents($f));
        }else{
            return array("", "");
        }
    }

    /**
     * @param Repository $repository
     * @param UserInterface $user
     */
    protected function releaseStatus($repository, $user)
    {
        if(isSet($this->currentTaskId)){
            TaskService::getInstance()->updateTaskStatus($this->currentTaskId, Task::STATUS_COMPLETE, "Done");
        }
        $f = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/.indexation_status-".$this->buildIndexLockKey($repository, $user);
        $this->debug("Removing file ".$f);
        @unlink($f);
    }


    /**
     * @param Repository $repository
     * @param UserInterface $user
     */
    protected function requireInterrupt($repository, $user)
    {
        $this->setIndexStatus("INTERRUPT", "Interrupt required by user", $repository, $user);
    }

    /**
     * @param Repository $repository
     * @param UserInterface $user
     * @return boolean
     */
    protected function isInterruptRequired($repository, $user)
    {
        if(isSet($this->currentTaskId)){
            $task = TaskService::getInstance()->getTaskById($this->currentTaskId);
            return ($task->getStatus() == Task::STATUS_PAUSED);
        }
        list($status, $message) = $this->getIndexStatus($repository, $user);
        return ($status == "INTERRUPT");
    }

} 