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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Controller;

use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Crypto;
use Pydio\Log\Core\Logger;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class CliRunner
 * @package Pydio\Core\Controller
 */
class CliRunner
{

    /**
     * Apply a Task in background
     *
     * @param Task $task
     */
    public static function applyTaskInBackground(Task $task)
    {

        $parameters = $task->getParameters();
        $task->setStatus(Task::STATUS_RUNNING);
        TaskService::getInstance()->updateTask($task);
        self::applyActionInBackground($task->getContext(), $task->getAction(), $parameters, "", $task->getId(), $task->getImpersonateUsers());

    }

    /**
     * Launch a command-line version of the framework by passing the actionName & parameters as arguments.
     * @static
     * @param ContextInterface $ctx
     * @param String $actionName
     * @param array $parameters
     * @param string $statusFile
     * @param string $taskId
     * @param string $impersonateUsers
     * @return null|UnixProcess
     */
    public static function applyActionInBackground(ContextInterface $ctx, $actionName, $parameters, $statusFile = "", $taskId = null, $impersonateUsers = null)
    {
        $repositoryId = $ctx->getRepositoryId();
        $user = $ctx->hasUser() ? $ctx->getUser()->getId() : "shared";

        $token = md5(time());
        $logDir = AJXP_CACHE_DIR . "/cmd_outputs";
        if (!is_dir($logDir)) mkdir($logDir, 0755);
        $logFile = $logDir . "/" . $token . ".out";

        if (UsersService::usersEnabled()) {
            $user = Crypto::encrypt($user, Crypto::buildKey($token , Crypto::getCliSecret()));
        }
        $robustInstallPath = str_replace("/", DIRECTORY_SEPARATOR, AJXP_INSTALL_PATH);
        $cmd = ConfService::getGlobalConf("CLI_PHP") . " " . $robustInstallPath . DIRECTORY_SEPARATOR . "cmd.php -u=$user -t=$token -a=$actionName -r=$repositoryId";
        if($impersonateUsers !== null){
            $cmd .= " -i=".$impersonateUsers;
        }
        /* Inserted next 3 lines to quote the command if in windows - rmeske*/
        if (PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows") {
            $cmd = ConfService::getGlobalConf("CLI_PHP") . " " . chr(34) . $robustInstallPath . DIRECTORY_SEPARATOR . "cmd.php" . chr(34) . " -u=$user -t=$token -a=$actionName -r=$repositoryId";
        }
        if (!empty($statusFile)) {
            $cmd .= " -s=" . $statusFile;
        }
        if (!empty($taskId)) {
            $cmd .= " -k=" . $taskId;
        }
        foreach ($parameters as $key => $value) {
            if ($key == "action" || $key == "get_action") continue;
            if (is_array($value)) {
                $index = 0;
                foreach ($value as $v) {
                    $cmd .= " --file_" . $index . "=" . escapeshellarg($v);
                    $index++;
                }
            } else {
                $cmd .= " --$key=" . escapeshellarg($value);
            }
        }
        $envSet = MemorySafe::setEnvForContext($ctx);
        // NOW RUN COMMAND
        $res = self::runCommandInBackground($cmd, $logFile);

        if($envSet){
            MemorySafe::clearEnv();
        }
        return $res;
    }

    /**
     * @param $cmd
     * @param $logFile
     * @param $forceLog
     * @return UnixProcess|null
     */
    public static function runCommandInBackground($cmd, $logFile, $forceLog = false)
    {
        if (PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows") {
            if (AJXP_SERVER_DEBUG || $forceLog) $cmd .= " > " . $logFile;
            if (class_exists("COM") && ConfService::getGlobalConf("CLI_USE_COM")) {
                $WshShell = new \COM("WScript.Shell");
                $WshShell->Run("cmd /C $cmd", 0, false);
            } else {
                $basePath = str_replace("/", DIRECTORY_SEPARATOR, AJXP_INSTALL_PATH);
                $tmpBat = implode(DIRECTORY_SEPARATOR, array($basePath, "data", "tmp", md5(time()) . ".bat"));
                $cmd = "@chcp 1252 > nul \r\n" . $cmd;
                $cmd .= "\n DEL " . chr(34) . $tmpBat . chr(34);
                Logger::debug("Writing file $cmd to $tmpBat");
                file_put_contents($tmpBat, $cmd);
                pclose(popen('start /b "CLI" "' . $tmpBat . '"', 'r'));
            }
            return null;
        } else {
            $process = new UnixProcess($cmd, (AJXP_SERVER_DEBUG || $forceLog ? $logFile : null));
            Logger::debug("Starting process and sending output dev null");
            return $process;
        }
    }
}