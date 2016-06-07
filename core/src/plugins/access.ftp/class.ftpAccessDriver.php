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
 *
 */
namespace Pydio\Access\Driver\StreamProvider\FTP;

use DOMNode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\RecycleBinManager;
use Pydio\Access\Driver\StreamProvider\FS\fsAccessDriver;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Plugin to access a remote server using the File Transfer Protocol
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class ftpAccessDriver extends fsAccessDriver
{
    public function loadManifest()
    {
        parent::loadManifest();
        // BACKWARD COMPATIBILITY!
        $res = $this->getXPath()->query('//param[@name="USER"] | //param[@name="PASS"] | //user_param[@name="USER"] | //user_param[@name="PASS"]');
        foreach ($res as $node) {
            if ($node->getAttribute("name") == "USER") {
                $node->setAttribute("name", "FTP_USER");
            } else if ($node->getAttribute("name") == "PASS") {
                $node->setAttribute("name", "FTP_PASS");
            }
        }
        $this->reloadXPath();
    }

    /**
     * Parse
     * @param ContextInterface $ctx
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if($contribNode->nodeName != "actions") return ;
        $this->disableArchiveBrowsingContributions($contribNode);
        $this->redirectActionsToMethod($contribNode, array("upload", "next_to_remote", "trigger_remote_copy"), "uploadActions");
    }

    /**
     * @param ContextInterface $contextInterface
     * @throws PydioException
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {

        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }
        $this->detectStreamWrapper(true);
        $this->urlBase = $contextInterface->getUrlBase();
        $recycle = $contextInterface->getRepository()->getContextOption($contextInterface, "RECYCLE_BIN");
        if ($recycle != "") {
            RecycleBinManager::init($contextInterface->getUrlBase(), "/".$recycle);
        }

    }

    public function uploadActions(ServerRequestInterface &$request, ResponseInterface &$response)
    {
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");

        switch ($request->getAttribute("action")) {

            case "next_to_remote":
                $taskId = $request->getAttribute("pydio-task-id");
                if(!$this->hasFilesToCopy($ctx)) {
                    TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_COMPLETE, "");
                    break;
                }

                $fData = $this->getNextFileToCopy($ctx);
                $nextFile = '';
                if ($this->hasFilesToCopy($ctx)) {
                    $nextFile = $this->getFileNameToCopy($ctx);
                }
                $this->logDebug("Base64 : ", array("from"=>$fData["destination"], "to"=>base64_decode($fData['destination'])));
                $destPath = $ctx->getUrlBase().base64_decode($fData['destination'])."/".$fData['name'];
                //$destPath = AJXP_Utils::decodeSecureMagic($destPath);
                // DO NOT "SANITIZE", THE URL IS ALREADY IN THE FORM ajxp.ftp://repoId/filename
                $destPath = TextEncoder::fromPostedFileName($destPath);
                $node = new AJXP_Node($destPath);
                $this->logDebug("Copying file to server", array("from"=>$fData["tmp_name"], "to"=>$destPath, "name"=>$fData["name"]));
                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_RUNNING, "Uploading file ".$fData["name"]);
                try {
                    Controller::applyHook("node.before_create", array(&$node));
                    $fp = fopen($destPath, "w");
                    $fSource = fopen($fData["tmp_name"], "r");
                    while (!feof($fSource)) {
                        fwrite($fp, fread($fSource, 4096));
                    }
                    fclose($fSource);
                    $this->logDebug("Closing target : begin ftp copy");
                    // Make sur the script does not time out!
                    @set_time_limit(240);
                    fclose($fp);
                    $this->logDebug("FTP Upload : end of ftp copy");
                    @unlink($fData["tmp_name"]);
                    Controller::applyHook("node.change", array(null, &$node));

                } catch (\Exception $e) {
                    TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_FAILED, "");
                    $this->logDebug("Error during ftp copy", array($e->getMessage(), $e->getTrace()));
                }
                $this->logDebug("FTP Upload : shoud trigger next or reload nextFile=$nextFile");
                $x = new SerializableResponseStream();
                $response = $response->withBody($x);
                if ($nextFile!='') {
                    //$x->addChunk(new BgActionTrigger("next_to_remote", array(), "Copying file ".TextEncoder::toUTF8($nextFile)." to remote server"));
                    $newTask = TaskService::actionAsTask($ctx, "next_to_remote", []);
                    $response = TaskService::getInstance()->enqueueTask($newTask, $request, $response);
                } else {
                    //$x->addChunk(new BgActionTrigger("reload_node", array(), "Upload done, reloading client."));
                    TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_COMPLETE, "");
                }
            break;

            case "upload":
                $httpVars = $request->getParsedBody();
                $destinationFolder = Utils::securePath("/".$httpVars['dir']);

                /** @var UploadedFileInterface[] $uploadedFiles */
                $uploadedFiles = $request->getUploadedFiles();
                if(!count($uploadedFiles)){
                    $this->writeUploadError($request, "Could not find any uploaded file", 411);
                }
                foreach ($uploadedFiles as $parameterName => $uploadedFile) {

                    if (substr($parameterName, 0, 9) != "userfile_") continue;
                    try {

                        $this->logDebug("Upload : rep_source ", array($destinationFolder));
                        $err = Utils::parseFileDataErrors($uploadedFile, true);
                        if ($err != null) {
                            $errorCode = $err[0];
                            $errorMessage = $err[1];
                            throw new \Exception($errorMessage, $errorCode);
                        }
                        $fileName = $uploadedFile->getClientFilename();

                        if (isSet($httpVars["auto_rename"])) {
                            $destination = $ctx->getUrlBase() . $destinationFolder;
                            $fileName = fsAccessDriver::autoRenameForDest($destination, $fileName);
                        }
                        $boxData = [
                            "name" => $fileName,
                            "destination" => base64_encode($destinationFolder)
                        ];

                        $destCopy = XMLWriter::replaceAjxpXmlKeywords($ctx->getRepository()->getContextOption($ctx, "TMP_UPLOAD"));
                        $this->logDebug("Upload : tmp upload folder", array($destCopy));
                        if (!is_dir($destCopy)) {
                            if (!@mkdir($destCopy)) {
                                $this->logDebug("Upload error : cannot create temporary folder", array($destCopy));
                                throw new PydioException("Warning, cannot create folder for temporary copy", false, 413);
                            }
                        }
                        if (!$this->isWriteable($destCopy)) {
                            $this->logDebug("Upload error: cannot write into temporary folder");
                            throw new PydioException("Warning, cannot write into temporary folder", false, 414);
                            break;
                        }
                        $this->logDebug("Upload : tmp upload folder", array($destCopy));

                        $mess = ConfService::getMessages();
                        $destName = tempnam($destCopy, "");
                        $boxData["tmp_name"] = $destName;
                        $this->copyUploadedData($uploadedFile, $destName, $mess);
                        $this->storeFileToCopy($ctx, $boxData);
                        $this->writeUploadSuccess($request, ["PREVENT_NOTIF" => true]);

                        $task = TaskService::actionAsTask($ctx, "next_to_remote", []);
                        TaskService::getInstance()->enqueueTask($task, $request, $response);

                    } catch (\Exception $e) {
                        $errorCode = $e->getCode();
                        if(empty($errorCode)) $errorCode = 411;
                        $this->writeUploadError($request, $e->getMessage(), $errorCode);
                    }
                }
            break;

            default:
            break;
        }
        return null;
    }

    public function isWriteable($path, $type="dir")
    {
        $parts = parse_url($path);
        $dir = $parts["path"];
        if ($type == "dir" && ($dir == "" || $dir == "/" || $dir == "\\")) { // ROOT, WE ARE NOT SURE TO BE ABLE TO READ THE PARENT
            return true;
        } else {
            return is_writable($path);
        }

    }

    public function deldir($location, $repoData)
    {
        if (is_dir($location)) {
            $dirsToRecurse = array();
            $all=opendir($location);
            while ($file=readdir($all)) {
                if (is_dir("$location/$file") && $file !=".." && $file!=".") {
                    $dirsToRecurse[] = "$location/$file";
                } elseif (!is_dir("$location/$file")) {
                    if (file_exists("$location/$file")) {
                        unlink("$location/$file");
                    }
                    unset($file);
                }
            }
            closedir($all);
            foreach ($dirsToRecurse as $recurse) {
                $this->deldir($recurse, $repoData);
            }
            rmdir($location);
        } else {
            if (file_exists("$location")) {
                $test = @unlink("$location");
                if(!$test) throw new \Exception("Cannot delete file ".$location);
            }
        }
        if (isSet($repoData["recycle"]) && basename(dirname($location)) == $repoData["recycle"]) {
            // DELETING FROM RECYCLE
            RecycleBinManager::deleteFromRecycle($location);
        }
    }


    public function storeFileToCopy(ContextInterface $ctx, $fileData)
    {
        $user = $ctx->getUser();
        $files = $user->getTemporaryData("tmp_upload");
        $this->logDebug("Saving user temporary data", array($fileData));
        $files[] = $fileData;
        $user->saveTemporaryData("tmp_upload", $files);
    }

    public function getFileNameToCopy(ContextInterface $ctx)
    {
            $user = $ctx->getUser();
            $files = $user->getTemporaryData("tmp_upload");
            return $files[0]["name"];
    }

    public function getNextFileToCopy(ContextInterface $ctx)
    {
            if(!$this->hasFilesToCopy($ctx)) return "";
            $user = $ctx->getUser();
            $files = $user->getTemporaryData("tmp_upload");
            $fData = $files[0];
            array_shift($files);
            $user->saveTemporaryData("tmp_upload", $files);
            return $fData;
    }

    public function hasFilesToCopy(ContextInterface $ctx)
    {
            $user = $ctx->getUser();
            $files = $user->getTemporaryData("tmp_upload");
            return (count($files)?true:false);
    }

    public function testParameters($params)
    {
        if (empty($params["FTP_USER"])) {
            throw new PydioException("Even if you intend to use the credentials stored in the session, temporarily set a user and password to perform the connexion test.");
        }
        if ($params["FTP_SECURE"]) {
            $link = @ftp_ssl_connect($params["FTP_HOST"], $params["FTP_PORT"]);
        } else {
            $link = @ftp_connect($params["FTP_HOST"], $params["FTP_PORT"]);
        }
        if (!$link) {
            throw new PydioException("Cannot connect to FTP server (".$params["FTP_HOST"].",". $params["FTP_PORT"].")");
        }
        @ftp_set_option($link, FTP_TIMEOUT_SEC, 10);
        if (!@ftp_login($link,$params["FTP_USER"],$params["FTP_PASS"])) {
            ftp_close($link);
            throw new PydioException("Cannot login to FTP server with user ".$params["FTP_USER"]);
        }
        if (!$params["FTP_DIRECT"]) {
            @ftp_pasv($link, true);
            global $_SESSION;
            $_SESSION["ftpPasv"]="true";
        }
        ftp_close($link);

        return "SUCCESS: Could succesfully connect to the FTP server with user '".$params["FTP_USER"]."'.";
    }


}
