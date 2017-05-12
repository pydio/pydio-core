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
namespace Pydio\Access\Meta\Version;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StatHelper;

use Pydio\Core\Controller\HTMLWriter;
use Pydio\Access\Meta\Core\AbstractMetaSource;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Manage versioning using Git
 * @package Pydio\Access\Meta\Version
 */
class GitManager extends AbstractMetaSource
{

    private $repoBase;

    /**
     * @param ContextInterface $ctx
     * @param AbstractAccessDriver $accessDriver
     * @throws \Exception
     */
    public function initMeta(ContextInterface $ctx, AbstractAccessDriver $accessDriver)
    {
        parent::initMeta($ctx, $accessDriver);
        $repo = $ctx->getRepository();
        $this->repoBase = $repo->getContextOption($ctx, "PATH");
        if(empty($this->repoBase)){
            throw new \Exception("Meta.git: cannot find PATH option in repository! Are you sure it's an FS-based workspace?");
        }
        if (!is_dir($this->repoBase.DIRECTORY_SEPARATOR.".git")) {
            $git = new \VersionControl_Git($this->repoBase);
            $git->initRepository();
        }
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     */
    public function applyActions(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        
        $actionName         = $requestInterface->getAttribute("action");
        $ctx                = $requestInterface->getAttribute("ctx");
        $httpVars           = $requestInterface->getParsedBody();
        $x                  = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $responseInterface  = $responseInterface->withBody($x);
        $userSelection      = \Pydio\Access\Core\Model\UserSelection::fromContext($ctx, $httpVars);

        $git = new \VersionControl_Git($this->repoBase);
        switch ($actionName) {
            case "git_history":
                $nodesList = new \Pydio\Access\Core\Model\NodesList();
                $selectedNode = $userSelection->getUniqueNode();
                $file = ltrim($selectedNode->getPath(), "/");
                $res = $this->gitHistory($git, $file);
                $ic = StatHelper::getMimeInfo($selectedNode, false)[1];
                $index = count($res);
                $mess = LocaleService::getMessages();
                foreach ($res as &$commit) {
                    unset($commit["DETAILS"]);
                    $commit["icon"] = $ic;
                    $commit["index"] = $index;
                    $commit["EVENT"] = $mess["meta.git.".$commit["EVENT"]];
                    $commit["text"] = basename($commit["FILE"]);
                    $index --;
                    $n = new AJXP_Node("/".$commit["ID"], $commit);
                    $n->setLeaf(true);
                    $nodesList->addBranch($n);
                }
                $x->addChunk($nodesList);
                break;
            break;

            case "git_revertfile":

                $originalFile = InputFilter::decodeSecureMagic($httpVars["original_file"]);
                $file = InputFilter::decodeSecureMagic($httpVars["file"]);
                $commitId = $httpVars["commit_id"];

                $command = $git->getCommand("cat-file");
                $command->setOption("s", true);
                $command->addArgument($commitId.":".$file);
                $command->execute();

                $command = $git->getCommand("show");
                $command->addArgument($commitId.":".$file);
                $commandLine = $command->createCommandString();
                $outputStream = fopen($this->repoBase.$originalFile, "w");
                $this->executeCommandInStreams($git, $commandLine, $outputStream);
                fclose($outputStream);
                /** @var ContextInterface $ctx */
                $ctx = $requestInterface->getAttribute("ctx");
                $this->commitChanges($ctx);
                $diff = new \Pydio\Access\Core\Model\NodesDiff();
                $node = new AJXP_Node($ctx->getUrlBase()."/".$file);
                $diff->update($node);
                $x->addChunk($diff);
                Controller::applyHook("node.change", [$node, $node]);


            break;

            case "git_getfile":

                $file = InputFilter::decodeSecureMagic($httpVars["file"]);
                $commitId = $httpVars["commit_id"];
                $attach = $httpVars["attach"];

                $command = $git->getCommand("cat-file");
                $command->setOption("s", true);
                $command->addArgument($commitId.":".$file);
                $size = floatval(trim($command->execute()));

                $command = $git->getCommand("show");
                $command->addArgument($commitId.":".$file);
                $commandLine = $command->createCommandString();

                if ($attach == "inline") {
                    $fileExt = substr(strrchr(basename($file), '.'), 1);
                    if (empty($fileExt)) {
                        $fileMime = "application/octet-stream";
                    } else {
                        $regex = "/^([\w\+\-\.\/]+)\s+(\w+\s)*($fileExt\s)/i";
                        $lines = file( AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/editor.browser/resources/other/mime.types");
                        foreach ($lines as $line) {
                            if(substr($line, 0, 1) == '#')
                                continue; // skip comments
                            $line = rtrim($line) . " ";
                            if(!preg_match($regex, $line, $matches))
                                continue; // no match to the extension
                            $fileMime = $matches[1];
                        }
                    }
                    if(empty($fileMime)) $fileMime = "application/octet-stream";
                    $responseInterface = HTMLWriter::responseWithInlineHeaders($responseInterface, basename($file), $size, $fileMime);
                } else {
                    $responseInterface = HTMLWriter::responseWithAttachmentsHeaders($responseInterface, basename($file), $size, false, false);
                }


                $reader = function() use ($git, $commandLine){
                    $outputStream = fopen("php://output", "a");
                    $this->executeCommandInStreams($git, $commandLine, $outputStream);
                    fclose($outputStream);
                    if(intval(ini_get("output_buffering")) > 0){
                        ob_end_flush();
                    }
                };

                $async = new \Pydio\Core\Http\Response\AsyncResponseStream($reader);
                $responseInterface = $responseInterface->withBody($async);

                break;

            break;

            default:
            break;
        }


    }

    /**
     * @param \VersionControl_Git $git
     * @param $commandLine
     * @param $outputStream
     * @param null $errorStream
     * @return string
     */
    protected function executeCommandInStreams($git, $commandLine, $outputStream, $errorStream = null)
    {
        $descriptorspec = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $pipes = array();
        $resource = proc_open($commandLine, $descriptorspec, $pipes, realpath($git->getDirectory()));

        //$stdout = stream_get_contents($pipes[1]);
        //$stderr = stream_get_contents($pipes[2]);
        $bufLength = 4096;
        while ( ($read = fread($pipes[1], $bufLength)) != false ) {
            fputs($outputStream, $read, strlen($read));
        }
        //stream_copy_to_stream($pipes[1], $outputStream);
        if ($errorStream != null) {
            stream_copy_to_stream($pipes[2], $errorStream);
        } else {
            $stderr = stream_get_contents($pipes[2]);
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $status = trim(proc_close($resource));
        return $status;

    }

    /**
     * @param \VersionControl_Git $git
     * @param string $file
     * @return array
     */
    protected function gitHistory($git, $file)
    {
        $command = $git->getCommand("log");
        if(strpos($file, " ") === false){
            // We currently cannot use the follow option if file/folder has a space
            $command->setOption("follow", true);
        }
        $command->setOption("p", true);
        $command->addArgument($file);
        //var_dump($command->createCommandString());
        $res = $command->execute();
        $lines = explode(PHP_EOL, $res);
        $allCommits = array();
        $grabOtherLines  = $grabMessageLines = false;
        while (count($lines)) {
            $line = array_shift($lines);
            if (preg_match("/^commit /i", $line)) {
                if (isSet($currentCommit)) {
                    if (isSet($currentCommit["DETAILS"])) {
                        $currentCommit["DETAILS"] = implode(PHP_EOL, $currentCommit["DETAILS"]);
                    }
                    $allCommits[] = $currentCommit;
                }
                $currentCommit = array();
                $currentCommit["ID"] = substr($line, strlen("commit "));
                $grabMessageLines = false;
                $grabOtherLines = false;
            } else if (preg_match("/^diff --git a\/(.*) b\/(.*)/i", $line, $matches)) {
                $origA = $matches[1];
                $origB = $matches[2];
                $currentCommit["FILE"] = $origB;
                if ($origB != $origA) {
                    if (basename($origB) != basename($origA)) {
                        $currentCommit["EVENT"] = "RENAME";
                    } else if (dirname($origA) != dirname($origB)) {
                        $currentCommit["EVENT"] = "MOVE";
                    }
                } else {
                    $currentCommit["EVENT"] = "MODIFICATION";
                    $currentCommit["DETAILS"] = array();
                    $grabOtherLines = true;
                }
            } else if (preg_match("/^Date: /", $line)) {
                $currentCommit["DATE"] = trim(substr($line, strlen("Date: ")));
                $currentCommit["ajxp_modiftime"] = strtotime(substr($line, strlen("Date: ")));
            } else if ($grabOtherLines) {
                if(isSet($currentCommit) && count($currentCommit["DETAILS"]) >= 10) continue;
                $currentCommit["DETAILS"][] = $line;
            } else if (trim($line) == "") {
                $grabMessageLines = !$grabMessageLines;
            } else if ($grabMessageLines) {
                if(!isSet($currentCommit["MESSAGE"])) $currentCommit["MESSAGE"] = "";
                $currentCommit["MESSAGE"] .= trim($line);
            }
        }
        // $currentCommit
        if(isSet($currentCommit)){
            if (count($currentCommit["DETAILS"]) && substr($currentCommit["DETAILS"][0], 0, strlen("new file")) == "new file") {
                $currentCommit["EVENT"] = "CREATION";
                unset($currentCommit["DETAILS"]);
            }
            $allCommits[] = $currentCommit;
        }
        return $allCommits;
    }


    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $fromNode
     * @param \Pydio\Access\Core\Model\AJXP_Node$toNode
     * @param boolean $copy
     */
    public function changesHook($fromNode=null, $toNode=null, $copy=false)
    {
        $refNode = ($fromNode !== null ? $fromNode : $toNode);
        $this->commitChanges($refNode->getContext());
        return;
    }

    /**
     * @param ContextInterface $ctx
     * @param string $path
     */
    private function commitChanges(ContextInterface $ctx, $path = null)
    {
        $git = new \VersionControl_Git($this->repoBase);
        $command = $git->getCommand("add");
        $command->addArgument(".");
        try {
            $cmd = $command->createCommandString();
            $this->logDebug("Git command ".$cmd);
            $res = $command->execute();
            $this->logDebug("GIT RESULT ADD : ".$res);
        } catch (\Exception $e) {
            $this->logDebug("Error in GIT Command ".$e->getMessage());
        }

        $command = $git->getCommand("commit");
        $command->setOption("a", true);
        $userId = "no user";
        $mail = "mail@mail.com";
        if ($ctx->hasUser()) {
            $userId = $ctx->getUser()->getId();
            $mail = $ctx->getUser()->getPersonalRole()->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "mail@mail.com");
        }
        $command->setOption("m", $userId);
        $command->setOption("author", "$userId <$mail>");
        //$command->addArgument($path);

        try {
            $cmd = $command->createCommandString();
            $this->logDebug("Git command ".$cmd);
            $res = $command->execute();
            $this->logDebug("GIT RESULT COMMIT : ".$res);
        } catch (\Exception $e) {
            $this->logDebug("Error ".$e->getMessage());
        }
    }

}
