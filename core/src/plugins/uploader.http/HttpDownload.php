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
namespace Pydio\Uploader\Processor;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StatHelper;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Controller\UnixProcess;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Remote files downloader
 * @package AjaXplorer_Plugins
 * @subpackage Downloader
 */
class HttpDownload extends Plugin
{
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return bool
     * @throws \Exception
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function switchAction(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response)
    {
        //$this->logInfo("DL file", $httpVars);
        $httpVars = $request->getParsedBody();
        $action = $request->getAttribute("action");
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $userSelection = UserSelection::fromContext($request->getAttribute("ctx"), $httpVars);
        $dir = InputFilter::decodeSecureMagic($httpVars["dir"]);
        $currentDirUrl = $userSelection->currentBaseUrl().$dir."/";
        $dlURL = null;
        if (isSet($httpVars["file"])) {
            $parts = parse_url($httpVars["file"]);
            $getPath = $parts["path"];
            $basename = basename($getPath);
            //$dlURL = $httpVars["file"];
        }else if (isSet($httpVars["dlfile"])) {
            $dlFile = $userSelection->currentBaseUrl(). InputFilter::decodeSecureMagic($httpVars["dlfile"]);
            $realFile = file_get_contents($dlFile);
            if(empty($realFile)) throw new \Exception("cannot find file $dlFile for download");
            $parts = parse_url($realFile);
            $getPath = $parts["path"];
            $basename = basename($getPath);
            //$dlURL = $realFile;
        }else{
            throw new \Exception("Missing argument, either file or dlfile");
        }
        /** @var AbstractAccessDriver $fsDriver */
        $fsDriver = PluginsService::getInstance($ctx)->getUniqueActivePluginForType("access");
        $fsDriver->filterUserSelectionToHidden($ctx, array($basename));

        switch ($action) {
            case "external_download":

                // Preparing the task
                $taskId = $request->getAttribute("pydio-task-id");
                if(empty($taskId)) {
                    $task = TaskService::actionAsTask($request->getAttribute("ctx"), "external_download", $httpVars, [], Task::FLAG_HAS_PROGRESS);
                    $task->setActionLabel(LocaleService::getMessages(), 'httpdownloader.1');
                    TaskService::getInstance()->enqueueTask($task, $request, $response);
                    break;
                }

                // Parameters
                $url = $httpVars["file"];
                $node = new AJXP_Node($currentDirUrl.$basename);

                // Work
                try {
                    $this->externalDownload(
                        $url,  // Original url for the file
                        $node, // Destination node
                        function ($msg, $progress) use ($taskId) {
                            TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_RUNNING, sprintf($msg, $progress), null, $progress);
                        } //  Showing progress for the download
                    );
                } catch (\Exception $e) {
                    // In case of a problem, removing the task
                    TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_FAILED, $e->getMessage());
                    return false;
                }

                // Ending the task
                if (isset($dlFile) && isSet($httpVars["delete_dlfile"]) && is_file($dlFile)) {
                    Controller::applyHook("node.before_path_change", array(new AJXP_Node($dlFile)));
                    unlink($dlFile);
                    Controller::applyHook("node.change", array(new AJXP_Node($dlFile), null, false));
                }
                $mess = LocaleService::getMessages();
                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_COMPLETE, $mess["httpdownloader.8"]);
                Controller::applyHook("node.change", array(null, $node, false), true);

            break;
            case "update_dl_data":

                $file = InputFilter::decodeSecureMagic($httpVars["file"]);
                header("text/plain");
                if (is_file($currentDirUrl.$file)) {
                    $node = new AJXP_Node($currentDirUrl.$file);
                    $filesize = filesize($node->getUrl());
                    echo $filesize;
                } else {
                    echo "stop";
                }

            break;
            case "stop_dl":
                $newName = "__".str_replace(".dlpart", ".ser", $basename);
                $hiddenFilename = $currentDirUrl.$newName;
                $data = @unserialize(@file_get_contents($hiddenFilename));
                header("text/plain");
                $this->logDebug("Getting $hiddenFilename",$data);
                if (isSet($data["pid"])) {
                    $process = new UnixProcess();
                    $process->setPid($data["pid"]);
                    $process->stop();
                    unlink($hiddenFilename);
                    unlink($currentDirUrl.$basename);
                    echo 'stop';
                } else {
                    echo 'failed';
                }
            break;
            default:
            break;
        }

        return false;
    }


    /**
     * @param string $url
     * @param AJXP_Node $node
     * @param $updateFn
     * @throws \Exception
     */
    public function externalDownload($url, $node, $updateFn) {

        $client = new \GuzzleHttp\Client(['base_url' => $url]);

        $response = $client->get();

        if ($response->getStatusCode() !== 200) {
            throw new \Exception("There was a problem retrieving the file from the server");
        }

        $totalSize = -1;

        $contentDisposition = $response->getHeader("Content-Disposition");
        $contentLength = $response->getHeader("Content-Length");

        if (strstr($contentDisposition, "filename")!== false) {
            $ar = explode("filename=", $contentDisposition);
            $basename = trim(array_pop($ar));
            $basename = str_replace("\"", "", $basename); // Remove quotes
        }

        if (!empty($contentLength)) {
            $totalSize = intval($contentLength);
            $this->logDebug("Should download $totalSize bytes!");
        }
        if ($totalSize != -1) {
            Controller::applyHook("node.before_create", array($node, $totalSize));
        }

        $basename = basename($node->getPath());
        $tmpFilename = $node->getUrl().".dlpart";
        $hiddenFilename = rtrim($node->getUrl(), $basename) . "__" . $basename .".ser";

        $filename = $node->getUrl();

        $dlData = array(
            "sourceUrl" => $url,
            "totalSize" => $totalSize
        );
        if (isSet($pid)) {
            $dlData["pid"] = $pid;
        }

        //file_put_contents($hiddenFilename, serialize($dlData));
        $fpHid=fopen($hiddenFilename,"w");
        fputs($fpHid,serialize($dlData));
        fclose($fpHid);

        // NOW READ RESPONSE
        $readBodySize = 0;
        $currentPercent = 0;
        $destStream = fopen($tmpFilename, "w");

        $body = $response->getBody();

        // Copy-ing the body to the destination file
        while (!$body->eof()) {
            $part = $body->read(4096);
            $readBytes = strlen($part);

            fwrite($destStream, $part, $readBytes);

            $readBodySize += $readBytes;

            if($totalSize > 0) {
                $newPercent = round(100 * $readBodySize / $totalSize);
                if($newPercent > $currentPercent) {
                    $updateFn("Downloading ... %d %%", $newPercent);
                }

                $currentPercent = $newPercent;
            }
        }

        fclose($destStream);
        rename($tmpFilename, $filename);
        unlink($hiddenFilename);
    }

    /**
     * @param AJXP_Node $ajxpNode
     */
    public function detectDLParts(&$ajxpNode)
    {
        if (!preg_match("/\.dlpart$/i",$ajxpNode->getUrl())) {
            return;
        }
        $basename = basename($ajxpNode->getUrl());
        $newName = "__".str_replace(".dlpart", ".ser", $basename);
        $hidFile = str_replace($basename, $newName, $ajxpNode->getUrl());
        if (is_file($hidFile)) {
            $data = unserialize(file_get_contents($hidFile));
            if ($data["totalSize"] != -1) {
                $ajxpNode->target_bytesize = $data["totalSize"];
                $ajxpNode->target_filesize = StatHelper::roundSize($data["totalSize"]);
                $ajxpNode->process_stoppable = (isSet($data["pid"])?"true":"false");
            }
        }
    }
}
