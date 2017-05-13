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

namespace Pydio\Action\Compression;

use Exception;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesDiff;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessWrapper;

use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Pydio\Core\Http\Message\JsActionTrigger;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class PowerFSController extends Plugin
{

    public function performChecks()
    {
        if(ApplicationState::hasMinisiteHash() && ApplicationState::getSapiRestBase() !== null){
            throw new Exception("Disable during link download");
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws Exception
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response)
    {
        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $httpVars = $request->getParsedBody();
        $dir = $httpVars["dir"] OR "";
        $dir = InputFilter::decodeSecureMagic($dir);
        if ($dir == "/") $dir = "";
        $selection = UserSelection::fromContext($ctx, $httpVars);
        $urlBase = $ctx->getUrlBase();
        $mess = LocaleService::getMessages();

        $bodyStream = new \Pydio\Core\Http\Response\SerializableResponseStream();
        if ($request->getAttribute("action") != "postcompress_download") {
            $response = $response->withBody($bodyStream);
        }

        switch ($request->getAttribute("action")) {

            case "postcompress_download":

                $archive = ApplicationState::getTemporaryFolder() . DIRECTORY_SEPARATOR . $httpVars["ope_id"] . "_" . InputFilter::decodeSecureMagic($httpVars["archive_name"], InputFilter::SANITIZE_FILENAME);
                // This is  a real filename on a local FS => toStorageEncoding
                $archive = TextEncoder::toStorageEncoding($archive);
                $archiveName = $httpVars["archive_name"];
                if (is_file($archive)) {
                    $fileReader = new \Pydio\Core\Http\Response\FileReaderResponse($archive);
                    $fileReader->setLocalName($archiveName);
                    $fileReader->setPreReadCallback(function () use ($archive) {
                        register_shutdown_function("unlink", $archive);
                    });
                    $response = $response->withBody($fileReader);
                } else {
                    $response = $response->withHeader("Content-type", "text/html");
                    $response->getBody()->write("<script>alert('".str_replace("'", "\'", $mess["powerfs.5"])."');</script>");
                }
                break;

            case "compress" :
            case "precompress" :

                $archiveName = InputFilter::decodeSecureMagic($httpVars["archive_name"], InputFilter::SANITIZE_FILENAME);
                $taskId = $request->getAttribute("pydio-task-id");

                if ($taskId === null) {
                    $task = TaskService::actionAsTask($ctx, $request->getAttribute("action"), $httpVars);
                    $task->setLabel($mess['powerfs.1']);
                    $task->setFlags(Task::FLAG_HAS_PROGRESS);
                    TaskService::getInstance()->enqueueTask($task, $request, $response);
                    return;
                }

                $rootDir = FsAccessWrapper::getRealFSReference($urlBase) . TextEncoder::toStorageEncoding($dir);
                // List all files
                $todo = array();
                $args = array();
                $replaceSearch = array($rootDir, "\\");
                $replaceReplace = array("", "/");
                foreach ($selection->getFiles() as $selectionFile) {
                    $selectionFile = TextEncoder::toStorageEncoding($selectionFile);
                    $baseFile = $selectionFile;
                    $args[] = escapeshellarg(substr($selectionFile, strlen(TextEncoder::toStorageEncoding($dir)) + ($dir == "/" ? 0 : 1)));
                    $selectionFile = FsAccessWrapper::getRealFSReference($urlBase . $selectionFile);
                    $todo[] = ltrim(str_replace($replaceSearch, $replaceReplace, $selectionFile), "/");
                    if (is_dir($selectionFile)) {
                        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($selectionFile), RecursiveIteratorIterator::SELF_FIRST);
                        foreach ($objects as $name => $object) {
                            $todo[] = str_replace($replaceSearch, $replaceReplace, $name);
                        }
                    }
                    if (trim($baseFile, "/") == "") {
                        // ROOT IS SELECTED, FIX IT
                        $args = array(escapeshellarg(basename($rootDir)));
                        $rootDir = dirname($rootDir);
                        break;
                    }
                }
                $cmdSeparator = ((PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows") ? "&" : ";");
                $opeId = substr(md5(time()), 0, 10);
                $originalArchiveParam = $archiveName;
                if ($request->getAttribute("action") == "precompress") {
                    $archiveName = ApplicationState::getTemporaryFolder() . DIRECTORY_SEPARATOR . $opeId . "_" . $archiveName;
                }
                chdir($rootDir);
                $cmd = $this->getContextualOption($ctx, "ZIP_PATH") . " -r " . escapeshellarg(TextEncoder::toStorageEncoding($archiveName)) . " " . implode(" ", $args);
                /** @var \Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver $fsDriver */
                $fsDriver = PluginsService::getInstance($ctx)->getUniqueActivePluginForType("access");
                $c = $fsDriver->getConfigs();
                if ((!isSet($c["SHOW_HIDDEN_FILES"]) || $c["SHOW_HIDDEN_FILES"] == false) && stripos(PHP_OS, "win") === false) {
                    $cmd .= " -x .\*";
                }
                $cmd .= " " . $cmdSeparator . " echo ZIP_FINISHED";
                $proc = popen($cmd, "r");
                $toks = array();
                $handled = array();
                $finishedEchoed = false;
                $percent = 0;
                while (!feof($proc)) {
                    set_time_limit(20);
                    $results = fgets($proc, 256);
                    if (strlen($results) == 0) {
                    } else {
                        $tok = strtok($results, "\n");
                        while ($tok !== false) {
                            $toks[] = $tok;
                            if ($tok == "ZIP_FINISHED") {
                                $finishedEchoed = true;
                            } else {
                                $test = preg_match('/(\w+): (.*) \(([^\(]+)\) \(([^\(]+)\)/', $tok, $matches);
                                if ($test !== false) {
                                    $handled[] = $matches[2];
                                }
                            }
                            $tok = strtok("\n");
                        }
                        if ($finishedEchoed) $newPercent = 100;
                        else $newPercent = min(round(count($handled) / count($todo) * 100), 100);
                        if($newPercent !== $percent){
                            TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_RUNNING, str_replace("%s", $percent, $mess["powerfs.4"]), null, $percent);
                        }
                        $percent = $newPercent;
                    }
                    // avoid a busy wait
                    if ($percent < 100) usleep(1);
                }
                pclose($proc);
                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_COMPLETE, "");
                if ($request->getAttribute("action") === "compress") {
                    $newNode = new AJXP_Node($urlBase . $dir . "/" . $archiveName);
                    $nodesDiff = new NodesDiff();
                    $nodesDiff->add($newNode);
                    Controller::applyHook("msg.instant", array($ctx, $nodesDiff->toXML()));
                } else {
                    $archiveName = str_replace("'", "\'", $originalArchiveParam);
                    $jsCode = " PydioApi.getClient().downloadSelection(null, 'postcompress_download', {ope_id:'" . $opeId . "',archive_name:'" . $archiveName . "'}); ";
                    $actionTrigger = new JsActionTrigger($jsCode, 0);
                    Controller::applyHook("msg.instant", array($ctx, $actionTrigger->toXML(), $ctx->getUser()->getId()));

                }

                break;

            default:
                break;
        }

    }
}
