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

namespace Pydio\Action\Compression;

use Exception;
use Phar;
use PharData;
use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;

use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\PathUtils;

use Pydio\Core\PluginFramework\Plugin;

use Pydio\Access\Core\Model\NodesDiff;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Plugin to compress to TAR or TAR.GZ or TAR.BZ2... He can also extract your archives
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class PluginCompression extends Plugin
{
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws Exception
     * @throws PydioException
     * @throws \Pydio\Core\Exception\ActionNotFoundException
     * @throws \Pydio\Core\Exception\AuthRequiredException
     */
    public function receiveAction(\Psr\Http\Message\ServerRequestInterface &$requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");

        $httpVars = $requestInterface->getParsedBody();
        $messages = LocaleService::getMessages();

        $userSelection = UserSelection::fromContext($ctx, $httpVars);
        $nodes = $userSelection->buildNodes();
        $currentDirPath = PathUtils::forwardSlashDirname($userSelection->getUniqueNode()->getPath());
        $currentDirPath = rtrim($currentDirPath, "/") . "/";
        $currentDirUrl = $userSelection->currentBaseUrl() . $currentDirPath;

        $serializableStream = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $responseInterface = $responseInterface->withBody($serializableStream);

        switch ($requestInterface->getAttribute("action")) {

            case "compression":

                $archiveName = InputFilter::decodeSecureMagic($httpVars["archive_name"], InputFilter::SANITIZE_FILENAME);
                $archiveFormat = '.' . InputFilter::sanitize($httpVars["type_archive"], InputFilter::SANITIZE_ALPHANUM);
                $tabTypeArchive = array(".tar", ".tar.gz", ".tar.bz2");
                $acceptedExtension = false;
                foreach ($tabTypeArchive as $extensionArchive) {
                    if ($extensionArchive == $archiveFormat) {
                        $acceptedExtension = true;
                        break;
                    }
                }
                if (!$acceptedExtension) {
                    throw new PydioException($messages["compression.16"]);
                }
                $typeArchive = $httpVars["type_archive"];
                $taskId = $requestInterface->getAttribute("pydio-task-id");
                // LAUNCH IN BACKGROUND AND EXIT
                if (empty($taskId)) {
                    $task = TaskService::actionAsTask($ctx, "compression", $httpVars, [], Task::FLAG_HAS_PROGRESS);
                    $task->setLabel($messages["compression.5"]);
                    $responseInterface = TaskService::getInstance()->enqueueTask($task, $requestInterface, $responseInterface);
                    break;
                }

                $task = TaskService::getInstance()->getTaskById($taskId);
                $postMessageStatus = function ($message, $taskStatus, $progress = null) use ($task) {
                    $this->operationStatus($task, $message, $taskStatus, $progress);
                };

                $maxAuthorizedSize = 4294967296;
                $currentDirUrlLength = strlen($currentDirUrl);
                $tabFolders = array();
                $tabAllRecursiveFiles = array();
                $tabFilesNames = array();
                foreach ($nodes as $node) {
                    $nodeUrl = $node->getUrl();
                    if (is_file($nodeUrl) && filesize($nodeUrl) < $maxAuthorizedSize) {
                        array_push($tabAllRecursiveFiles, $nodeUrl);
                        array_push($tabFilesNames, substr($nodeUrl, $currentDirUrlLength));
                    }
                    if (is_dir($nodeUrl)) {
                        array_push($tabFolders, $nodeUrl);
                    }
                }
                //DO A FOREACH OR IT'S GONNA HAVE SOME SAMES FILES NAMES
                foreach ($tabFolders as $value) {
                    $dossiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($value));
                    foreach ($dossiers as $file) {
                        if ($file->isDir()) {
                            continue;
                        }
                        array_push($tabAllRecursiveFiles, $file->getPathname());
                        array_push($tabFilesNames, substr($file->getPathname(), $currentDirUrlLength));
                    }
                }
                //WE STOP IF IT'S JUST AN EMPTY FOLDER OR NO FILES
                if (empty($tabFilesNames)) {
                    $postMessageStatus($messages["compression.17"], Task::STATUS_FAILED);
                    throw new PydioException($messages["compression.17"]);
                }
                try {
                    $tmpArchiveName = tempnam(ApplicationState::getTemporaryFolder(), "tar-compression") . ".tar";
                    $archive = new PharData($tmpArchiveName);
                } catch (Exception $e) {
                    $postMessageStatus($e->getMessage(), Task::STATUS_FAILED);
                    throw $e;
                }
                $counterCompression = 0;
                //THE TWO ARRAY ARE MERGED FOR THE FOREACH LOOP
                $tabAllFiles = array_combine($tabAllRecursiveFiles, $tabFilesNames);
                foreach ($tabAllFiles as $fullPath => $fileName) {
                    try {
                        $archive->addFile(MetaStreamWrapper::getRealFSReference($fullPath), $fileName);
                        $counterCompression++;
                        $percent = round(($counterCompression / count($tabAllFiles)) * 100, 0, PHP_ROUND_HALF_DOWN);
                        $postMessageStatus(sprintf($messages["compression.6"], $percent . " %"), Task::STATUS_RUNNING, $percent);
                    } catch (Exception $e) {
                        unlink($tmpArchiveName);
                        $postMessageStatus($e->getMessage(), Task::STATUS_FAILED);
                        throw $e;
                    }
                }
                $finalArchive = $tmpArchiveName;
                if ($typeArchive != ".tar") {
                    $archiveTypeCompress = substr(strrchr($typeArchive, "."), 1);
                    $postMessageStatus(sprintf($messages["compression.7"], strtoupper($archiveTypeCompress)), Task::STATUS_RUNNING);
                    if ($archiveTypeCompress == "gz") {
                        $archive->compress(Phar::GZ);
                    } elseif ($archiveTypeCompress == "bz2") {
                        $archive->compress(Phar::BZ2);
                    }
                    $finalArchive = $tmpArchiveName . "." . $archiveTypeCompress;
                }

                $newNode = new AJXP_Node($currentDirUrl . $archiveName);
                $destArchive = $newNode->getRealFile();
                rename($finalArchive, $destArchive);
                Controller::applyHook("node.before_create", array($newNode, filesize($destArchive)));
                if (file_exists($tmpArchiveName)) {
                    unlink($tmpArchiveName);
                    unlink(substr($tmpArchiveName, 0, -4));
                }
                Controller::applyHook("node.change", array(null, $newNode, false), true);
                $postMessageStatus("Finished", Task::STATUS_COMPLETE);

                break;

            case "extraction":

                $fileArchive = InputFilter::sanitize(InputFilter::decodeSecureMagic($httpVars["file"]), InputFilter::SANITIZE_DIRNAME);
                $fileArchive = substr(strrchr($fileArchive, DIRECTORY_SEPARATOR), 1);
                $authorizedExtension = array("tar" => 4, "gz" => 7, "bz2" => 8);
                $acceptedArchive = false;
                $extensionLength = 0;
                $counterExtract = 0;
                $currentAllPydioPath = $currentDirUrl . $fileArchive;
                $pharCurrentAllPydioPath = "phar://" . MetaStreamWrapper::getRealFSReference($currentAllPydioPath);
                $pathInfoCurrentAllPydioPath = pathinfo($currentAllPydioPath, PATHINFO_EXTENSION);
                //WE TAKE ONLY TAR, TAR.GZ AND TAR.BZ2 ARCHIVES
                foreach ($authorizedExtension as $extension => $strlenExtension) {
                    if ($pathInfoCurrentAllPydioPath == $extension) {
                        $acceptedArchive = true;
                        $extensionLength = $strlenExtension;
                        break;
                    }
                }
                if ($acceptedArchive == false) {
                    throw new PydioException($messages["compression.15"]);
                }
                $onlyFileName = substr($fileArchive, 0, -$extensionLength);
                $lastPosOnlyFileName = strrpos($onlyFileName, "-");
                $tmpOnlyFileName = substr($onlyFileName, 0, $lastPosOnlyFileName);
                $counterDuplicate = substr($onlyFileName, $lastPosOnlyFileName + 1);
                if (!is_int($lastPosOnlyFileName) || !is_int($counterDuplicate)) {
                    $tmpOnlyFileName = $onlyFileName;
                    $counterDuplicate = 1;
                }
                while (file_exists($currentDirUrl . $onlyFileName)) {
                    $onlyFileName = $tmpOnlyFileName . "-" . $counterDuplicate;
                    $counterDuplicate++;
                }

                // LAUNCHME IN BACKGROUND
                $taskId = $requestInterface->getAttribute("pydio-task-id");
                // LAUNCH IN BACKGROUND AND EXIT
                if (empty($taskId)) {
                    $task = TaskService::actionAsTask($ctx, "extraction", $httpVars, [], Task::FLAG_HAS_PROGRESS);
                    $task->setLabel($messages["compression.12"]);
                    $responseInterface = TaskService::getInstance()->enqueueTask($task, $requestInterface, $responseInterface);
                    break;
                }
                $task = TaskService::getInstance()->getTaskById($taskId);
                $postMessageStatus = function ($message, $taskStatus, $progress = null) use ($task) {
                    $this->operationStatus($task, $message, $taskStatus, $progress);
                };

                mkdir($currentDirUrl . $onlyFileName, 0777, true);
                chmod(MetaStreamWrapper::getRealFSReference($currentDirUrl . $onlyFileName), 0777);
                try {
                    $archive = new PharData(MetaStreamWrapper::getRealFSReference($currentAllPydioPath));
                    $fichiersArchive = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pharCurrentAllPydioPath));
                    foreach ($fichiersArchive as $file) {
                        $fileGetPathName = $file->getPathname();
                        if ($file->isDir()) {
                            continue;
                        }
                        $fileNameInArchive = substr(strstr($fileGetPathName, $fileArchive), strlen($fileArchive) + 1);
                        try {
                            $archive->extractTo(MetaStreamWrapper::getRealFSReference($currentDirUrl . $onlyFileName), $fileNameInArchive, false);
                        } catch (Exception $e) {
                            $postMessageStatus($e->getMessage(), Task::STATUS_FAILED);
                            throw new PydioException($e);
                        }
                        $counterExtract++;
                        $progress = round(($counterExtract / $archive->count()) * 100, 0, PHP_ROUND_HALF_DOWN);
                        $postMessageStatus(sprintf($messages["compression.13"], $progress . "%"), Task::STATUS_RUNNING, $progress);
                    }
                } catch (Exception $e) {
                    $postMessageStatus($e->getMessage(), Task::STATUS_FAILED);
                    throw new PydioException($e);
                }
                $postMessageStatus("Done", Task::STATUS_COMPLETE, 100);
                $newNode = new AJXP_Node($currentDirUrl . $onlyFileName);
                $nodesDiff = new NodesDiff();
                $nodesDiff->add($newNode);
                Controller::applyHook("msg.instant", array($ctx, $nodesDiff->toXML()));
                $indexRequest = Controller::executableRequest($requestInterface->getAttribute("ctx"), "index", ["file" => $newNode->getPath()]);
                Controller::run($indexRequest);
                break;

            default:
                break;
        }

    }

    /**
     * @param Task $task
     * @param string $message
     * @param integer $taskStatus
     * @param null|integer $progress
     */
    private function operationStatus($task, $message, $taskStatus, $progress = null)
    {
        $task->setStatusMessage($message);
        $task->setStatus($taskStatus);
        if ($progress != null) {
            $task->setProgress($progress);
        }
        TaskService::getInstance()->updateTask($task);
    }
}
