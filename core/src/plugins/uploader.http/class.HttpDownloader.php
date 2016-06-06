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
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Utils\UnixProcess;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Remote files downloader
 * @package AjaXplorer_Plugins
 * @subpackage Downloader
 */
class HttpDownloader extends Plugin
{
    public function switchAction(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response)
    {
        //$this->logInfo("DL file", $httpVars);
        $httpVars = $request->getParsedBody();
        $action = $request->getAttribute("action");
        $userSelection = UserSelection::fromContext($request->getAttribute("ctx"), $httpVars);
        $dir = Utils::decodeSecureMagic($httpVars["dir"]);
        $currentDirUrl = $userSelection->currentBaseUrl().$dir."/";
        $dlURL = null;
        if (isSet($httpVars["file"])) {
            $parts = parse_url($httpVars["file"]);
            $getPath = $parts["path"];
            $basename = basename($getPath);
            //$dlURL = $httpVars["file"];
        }else if (isSet($httpVars["dlfile"])) {
            $dlFile = $userSelection->currentBaseUrl().Utils::decodeSecureMagic($httpVars["dlfile"]);
            $realFile = file_get_contents($dlFile);
            if(empty($realFile)) throw new Exception("cannot find file $dlFile for download");
            $parts = parse_url($realFile);
            $getPath = $parts["path"];
            $basename = basename($getPath);
            //$dlURL = $realFile;
        }else{
            throw new Exception("Missing argument, either file or dlfile");
        }

        switch ($action) {
            case "external_download":

                $taskId = $request->getAttribute("pydio-task-id");
                if(empty($taskId)){
                    $task = TaskService::actionAsTask("external_download", $httpVars, "", "", [], Task::FLAG_HAS_PROGRESS | Task::FLAG_STOPPABLE);
                    TaskService::getInstance()->enqueueTask($task, $request, $response);
                    break;
                }

                require_once(AJXP_BIN_FOLDER."/lib/http_class/http_class.php");
                session_write_close();

                $httpClient = new http_class();
                $arguments = array();
                $httpClient->GetRequestArguments($httpVars["file"], $arguments);
                $err = $httpClient->Open($arguments);
                $collectHeaders = array(
                    "ajxp-last-redirection" => "",
                    "content-disposition"	=> "",
                    "content-length"		=> ""
                );
                if(!empty($err)){
                    throw new Exception($err);
                }

                $err = $httpClient->SendRequest($arguments);
                $httpClient->follow_redirect = true;

                $pidHiddenFileName = $currentDirUrl.".".$basename.".pid";
                if (is_file($pidHiddenFileName)) {
                    $pid = file_get_contents($pidHiddenFileName);
                    @unlink($pidHiddenFileName);
                }

                if(!empty($err)){
                    $httpClient->Close();
                    throw new Exception($err);
                }

                $httpClient->ReadReplyHeaders($collectHeaders);

                $totalSize = -1;
                if (!empty($collectHeaders["content-disposition"]) && strstr($collectHeaders["content-disposition"], "filename")!== false) {
                    $ar = explode("filename=", $collectHeaders["content-disposition"]);
                    $basename = trim(array_pop($ar));
                    $basename = str_replace("\"", "", $basename); // Remove quotes
                }
                if (!empty($collectHeaders["content-length"])) {
                    $totalSize = intval($collectHeaders["content-length"]);
                    $this->logDebug("Should download $totalSize bytes!");
                }
                if ($totalSize != -1) {
                    $node = new AJXP_Node($currentDirUrl.$basename);
                    Controller::applyHook("node.before_create", array($node, $totalSize));
                }

                $tmpFilename = $currentDirUrl.$basename.".dlpart";
                $hiddenFilename = $currentDirUrl."__".$basename.".ser";
                $filename = $currentDirUrl.$basename;

                $dlData = array(
                    "sourceUrl" => $getPath,
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
                $checkStopEvery = 1024*1024;
                $destStream = fopen($tmpFilename, "w");
                while (true) {
                    $body = "";
                    $error = $httpClient->ReadReplyBody($body, 4096);
                    if($error != "" || strlen($body) == 0) {
                        break;
                    }
                    fwrite($destStream, $body, strlen($body));
                    $readBodySize += strlen($body);
                    if($totalSize > 0){
                        $newPercent = round(100 * $readBodySize / $totalSize);
                        if(!empty($taskId) && $newPercent > $currentPercent){
                            TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_RUNNING, "Downloading ... " . $newPercent . " %", null, $newPercent);
                        }
                        $currentPercent = $newPercent;
                    }
                    if(!empty($taskId) && $readBodySize >= $checkStopEvery &&  $readBodySize % $checkStopEvery === 0
                        && TaskService::getInstance()->getTaskById($taskId)->getStatus() === Task::STATUS_PAUSED){
                        break;
                    }
                }
                fclose($destStream);
                rename($tmpFilename, $filename);
                unlink($hiddenFilename);
                $httpClient->Close();

                if (isset($dlFile) && isSet($httpVars["delete_dlfile"]) && is_file($dlFile)) {
                    Controller::applyHook("node.before_path_change", array(new AJXP_Node($dlFile)));
                    unlink($dlFile);
                    Controller::applyHook("node.change", array(new AJXP_Node($dlFile), null, false));
                }
                $mess = ConfService::getMessages();
                Controller::applyHook("node.change", array(null, new AJXP_Node($filename), false), true);
                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_COMPLETE, $mess["httpdownloader.8"]);


            break;
            case "update_dl_data":
                $file = Utils::decodeSecureMagic($httpVars["file"]);
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
                $ajxpNode->target_filesize = Utils::roundSize($data["totalSize"]);
                $ajxpNode->process_stoppable = (isSet($data["pid"])?"true":"false");
            }
        }
    }
}
