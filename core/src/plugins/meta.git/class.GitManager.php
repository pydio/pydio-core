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

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Manage versioning using Git
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class GitManager extends AJXP_AbstractMetaSource
{

    private $repoBase;

    public function performChecks()
    {
        $ex = AJXP_Utils::searchIncludePath("VersionControl/Git.php");
        if (!$ex) {
            throw new Exception("Cannot find PEAR library VersionControl/Git");
        }
    }

    /**
     * @param AbstractAccessDriver $accessDriver
     * @throws Exception
     */
    public function initMeta($accessDriver)
    {
        parent::initMeta($accessDriver);
        require_once("VersionControl/Git.php");
        $repo = $accessDriver->repository;
        $this->repoBase = $repo->getOption("PATH");
        if(empty($this->repoBase)){
            throw new Exception("Meta.git: cannot find PATH option in repository! Are you sure it's an FS-based workspace?");
        }
        if (!is_dir($this->repoBase.DIRECTORY_SEPARATOR.".git")) {
            $git = new VersionControl_Git($this->repoBase);
            $git->initRepository();
        }
    }

    public function applyActions($actionName, $httpVars, $fileVars)
    {
        $git = new VersionControl_Git($this->repoBase);
        switch ($actionName) {
            case "git_history":
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $file = ltrim($file, "/");

                $res = $this->gitHistory($git, $file);
                AJXP_XMLWriter::header();
                $ic = AJXP_Utils::mimetype($file, "image", false);
                $index = count($res);
                $mess = ConfService::getMessages();
                foreach ($res as &$commit) {
                    unset($commit["DETAILS"]);
                    $commit["icon"] = $ic;
                    $commit["index"] = $index;
                    $commit["EVENT"] = $mess["meta.git.".$commit["EVENT"]];
                    $index --;
                    AJXP_XMLWriter::renderNode("/".$commit["ID"], basename($commit["FILE"]), true, $commit);
                }
                AJXP_XMLWriter::close();
                break;
            break;

            case "git_revertfile":

                $originalFile = AJXP_Utils::decodeSecureMagic($httpVars["original_file"]);
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $commitId = $httpVars["commit_id"];

                $command = $git->getCommand("cat-file");
                $command->setOption("s", true);
                $command->addArgument($commitId.":".$file);
                $size = $command->execute();

                $command = $git->getCommand("show");
                $command->addArgument($commitId.":".$file);
                $commandLine = $command->createCommandString();
                $outputStream = fopen($this->repoBase.$originalFile, "w");
                $this->executeCommandInStreams($git, $commandLine, $outputStream);
                fclose($outputStream);
                $this->commitChanges();
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::reloadDataNode();
                AJXP_XMLWriter::close();


            break;

            case "git_getfile":

                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $commitId = $httpVars["commit_id"];
                $attach = $httpVars["attach"];

                $command = $git->getCommand("cat-file");
                $command->setOption("s", true);
                $command->addArgument($commitId.":".$file);
                $size = $command->execute();

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
                    HTMLWriter::generateInlineHeaders(basename($file), $size, $fileMime);
                } else {
                    HTMLWriter::generateAttachmentsHeader(basename($file), $size, false, false);
                }
                $outputStream = fopen("php://output", "a");
                $this->executeCommandInStreams($git, $commandLine, $outputStream);
                fclose($outputStream);
                break;

            break;

            default:
            break;
        }


    }

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

    protected function gitHistory($git, $file)
    {
        $command = $git->getCommand("log");
        $command->setOption("follow", true);
        $command->setOption("p", true);
        $command->addArgument($file);
        //var_dump($command->createCommandString());
        $res = $command->execute();
        $lines = explode(PHP_EOL, $res);
        $allCommits = array();
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
                if(count($currentCommit["DETAILS"]) >= 10) continue;
                $currentCommit["DETAILS"][] = $line;
            } else if (trim($line) == "") {
                $grabMessageLines = !$grabMessageLines;
            } else if ($grabMessageLines) {
                if(!isSet($currentCommit["MESSAGE"])) $currentCommit["MESSAGE"] = "";
                $currentCommit["MESSAGE"] .= trim($line);
            }
        }
        // $currentCommit
        if (count($currentCommit["DETAILS"]) && substr($currentCommit["DETAILS"][0], 0, strlen("new file")) == "new file") {
            $currentCommit["EVENT"] = "CREATION";
            unset($currentCommit["DETAILS"]);
        }
        $allCommits[] = $currentCommit;
        return $allCommits;
    }


    /**
     * @param AJXP_Node $fromNode
     * @param AJXP_Node$toNode
     * @param boolean $copy
     */
    public function changesHook($fromNode=null, $toNode=null, $copy=false)
    {
        $this->commitChanges();
        return;
        /*
        $refNode = $fromNode;
        if ($fromNode == null && $toNode != null) {
            $refNode = $toNode;
        }
        $this->commitChanges(dirname($refNode->getPath()));
        */
    }

    private function commitChanges($path = null)
    {
        $git = new VersionControl_Git($this->repoBase);
        $command = $git->getCommand("add");
        $command->addArgument(".");
        try {
            $cmd = $command->createCommandString();
            $this->logDebug("Git command ".$cmd);
            $res = $command->execute();
        } catch (Exception $e) {
            $this->logDebug("Error ".$e->getMessage());
        }
        $this->logDebug("GIT RESULT ADD : ".$res);

        $command = $git->getCommand("commit");
        $command->setOption("a", true);
        $userId = "no user";
        $mail = "mail@mail.com";
        if (AuthService::getLoggedUser()!=null) {
            $userId = AuthService::getLoggedUser()->getId();
            $mail = AuthService::getLoggedUser()->personalRole->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "mail@mail.com");
        }
        $command->setOption("m", $userId);
        $command->setOption("author", "$userId <$mail>");
        //$command->addArgument($path);

        try {
            $cmd = $command->createCommandString();
            $this->logDebug("Git command ".$cmd);
            $res = $command->execute();
        } catch (Exception $e) {
            $this->logDebug("Error ".$e->getMessage());
        }
        $this->logDebug("GIT RESULT COMMIT : ".$res);
    }

}
