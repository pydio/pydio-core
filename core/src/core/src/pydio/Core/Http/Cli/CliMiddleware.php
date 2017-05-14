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
namespace Pydio\Core\Http\Cli;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Controller\ShutdownScheduler;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Exception\WorkspaceNotFoundException;
use Pydio\Core\Http\Middleware\ITopLevelMiddleware;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Http\Server;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;
use Symfony\Component\Console\Output\OutputInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class CliMiddleware
 * Dedicated Middleware for interacting with Symfony Command
 * @package Pydio\Core\Http\Cli
 */
class CliMiddleware implements ITopLevelMiddleware
{
    /**
     * @param ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @return \Psr\Http\Message\ResponseInterface
     * @param callable|null $next
     * @throws WorkspaceNotFoundException
     */
    public function handleRequest(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface, callable $next = null){

        /**
         * @var OutputInterface
         */
        $output = $requestInterface->getAttribute("cli-output");
        $options = $requestInterface->getAttribute("cli-options");
        $statusFile = (!empty($options["s"]) ? $options["s"] : false);
        $taskId = $requestInterface->getAttribute("pydio-task-id");

        try {

            $responseInterface = Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

            $this->emitResponse($requestInterface, $responseInterface);

        } catch (AuthRequiredException $e){

            $output->writeln("<error>Authentication Failed</error>");
            if($statusFile !== false){
                file_put_contents($statusFile, "ERROR:Authentication Failed.");
            }
            if(!empty($taskId)){
                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_FAILED, "Authentication Failed");
            }

        } catch (PydioException $e){

            $output->writeln("<error>".$e->getMessage()."</error>");
            if($statusFile !== false){
                file_put_contents($statusFile, "ERROR:".$e->getMessage());
            }
            if(!empty($taskId)){
                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_FAILED, $e->getMessage());
            }

        } catch (\Exception $e){

            $output->writeln("<error>".$e->getMessage()."</error>");
            if($statusFile !== false){
                file_put_contents($statusFile, "ERROR:".$e->getMessage());
            }
            if(!empty($taskId)){
                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_FAILED, $e->getMessage());
            }

        }

        if(!empty($taskId)){
            $task = TaskService::getInstance()->getTaskById($taskId);
            // Update status if required. 
            if($task->getStatus() === Task::STATUS_RUNNING){
                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_COMPLETE, "Finished");
            }
        }
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return void
     */
    public function emitResponse(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface){

        if($responseInterface !== false && $responseInterface->getBody() && $responseInterface->getBody() instanceof SerializableResponseStream){
            // For the moment, use XML by default
            // Todo: Create A CLI Serializer for pretty printing?
            if($requestInterface->getParsedBody()["format"] == "json"){
                $responseInterface->getBody()->setSerializer(SerializableResponseStream::SERIALIZER_TYPE_JSON);
            }
        }
        $output = $requestInterface->getAttribute("cli-output");
        if(!empty($output)){
            $output->writeln("".$responseInterface->getBody());
        }else{
            echo "".$responseInterface->getBody();
        }
        $logHooks = isSet($requestInterface->getParsedBody()["cli-show-hooks"]) ? $output: null;
        ShutdownScheduler::getInstance()->callRegisteredShutdown($logHooks);

    }
}