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
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * AJXP_Plugin to access a remote server using the File Transfer Protocol
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class ftpAccessDriver extends fsAccessDriver
{
    public function loadManifest()
    {
        parent::loadManifest();
        // BACKWARD COMPATIBILITY!
        $res = $this->xPath->query('//param[@name="USER"] | //param[@name="PASS"] | //user_param[@name="USER"] | //user_param[@name="PASS"]');
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
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if($contribNode->nodeName != "actions") return ;
        $this->disableArchiveBrowsingContributions($contribNode);
        $this->redirectActionsToMethod($contribNode, array("upload", "next_to_remote", "trigger_remote_copy"), "uploadActions");
    }

    public function initRepository()
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }
        $wrapperData = $this->detectStreamWrapper(true);
        $this->wrapperClassName = $wrapperData["classname"];
        $this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
        $recycle = $this->repository->getOption("RECYCLE_BIN");
        if ($recycle != "") {
            RecycleBinManager::init($this->urlBase, "/".$recycle);
        }
        //AJXP_PromptException::testOrPromptForCredentials("ftp_ws_credentials", $this->repository->getId());
    }

    public function uploadActions($action, $httpVars, $filesVars)
    {
        switch ($action) {
            case "trigger_remote_copy":
                if(!$this->hasFilesToCopy()) break;
                $toCopy = $this->getFileNameToCopy();
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".$toCopy." to ftp server");
                AJXP_XMLWriter::close();
                exit(1);
            break;
            case "next_to_remote":
                if(!$this->hasFilesToCopy()) break;
                $fData = $this->getNextFileToCopy();
                $nextFile = '';
                if ($this->hasFilesToCopy()) {
                    $nextFile = $this->getFileNameToCopy();
                }
                $this->logDebug("Base64 : ", array("from"=>$fData["destination"], "to"=>base64_decode($fData['destination'])));
                $destPath = $this->urlBase.base64_decode($fData['destination'])."/".$fData['name'];
                //$destPath = AJXP_Utils::decodeSecureMagic($destPath);
                // DO NOT "SANITIZE", THE URL IS ALREADY IN THE FORM ajxp.ftp://repoId/filename
                $destPath = SystemTextEncoding::fromPostedFileName($destPath);
                $node = new AJXP_Node($destPath);
                $this->logDebug("Copying file to server", array("from"=>$fData["tmp_name"], "to"=>$destPath, "name"=>$fData["name"]));
                try {
                    AJXP_Controller::applyHook("node.before_create", array(&$node));
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
                    AJXP_Controller::applyHook("node.change", array(null, &$node));

                } catch (Exception $e) {
                    $this->logDebug("Error during ftp copy", array($e->getMessage(), $e->getTrace()));
                }
                $this->logDebug("FTP Upload : shoud trigger next or reload nextFile=$nextFile");
                AJXP_XMLWriter::header();
                if ($nextFile!='') {
                    AJXP_XMLWriter::triggerBgAction("next_to_remote", array(), "Copying file ".SystemTextEncoding::toUTF8($nextFile)." to remote server");
                } else {
                    AJXP_XMLWriter::triggerBgAction("reload_node", array(), "Upload done, reloading client.");
                }
                AJXP_XMLWriter::close();
                exit(1);
            break;
            case "upload":
                $rep_source = AJXP_Utils::securePath("/".$httpVars['dir']);
                $this->logDebug("Upload : rep_source ", array($rep_source));
                $logMessage = "";
                foreach ($filesVars as $boxName => $boxData) {
                    if(substr($boxName, 0, 9) != "userfile_")     continue;
                    $this->logDebug("Upload : rep_source ", array($rep_source));
                    $err = AJXP_Utils::parseFileDataErrors($boxData);
                    if ($err != null) {
                        $errorCode = $err[0];
                        $errorMessage = $err[1];
                        break;
                    }
                    if (isSet($httpVars["auto_rename"])) {
                        $destination = $this->urlBase.$rep_source;
                        $boxData["name"] = fsAccessDriver::autoRenameForDest($destination, $boxData["name"]);
                    }
                    $boxData["destination"] = base64_encode($rep_source);
                    $destCopy = AJXP_XMLWriter::replaceAjxpXmlKeywords($this->repository->getOption("TMP_UPLOAD"));
                    $this->logDebug("Upload : tmp upload folder", array($destCopy));
                    if (!is_dir($destCopy)) {
                        if (! @mkdir($destCopy)) {
                            $this->logDebug("Upload error : cannot create temporary folder", array($destCopy));
                            $errorCode = 413;
                            $errorMessage = "Warning, cannot create folder for temporary copy.";
                            break;
                        }
                    }
                    if (!$this->isWriteable($destCopy)) {
                        $this->logDebug("Upload error: cannot write into temporary folder");
                        $errorCode = 414;
                        $errorMessage = "Warning, cannot write into temporary folder.";
                        break;
                    }
                    $this->logDebug("Upload : tmp upload folder", array($destCopy));
                    if (isSet($boxData["input_upload"])) {
                        try {
                            $destName = tempnam($destCopy, "");
                            $this->logDebug("Begining reading INPUT stream");
                            $input = fopen("php://input", "r");
                            $output = fopen($destName, "w");
                            $sizeRead = 0;
                            while ($sizeRead < intval($boxData["size"])) {
                                $chunk = fread($input, 4096);
                                $sizeRead += strlen($chunk);
                                fwrite($output, $chunk, strlen($chunk));
                            }
                            fclose($input);
                            fclose($output);
                            $boxData["tmp_name"] = $destName;
                            $this->storeFileToCopy($boxData);
                            $this->logDebug("End reading INPUT stream");
                        } catch (Exception $e) {
                            $errorCode=411;
                            $errorMessage = $e->getMessage();
                            break;
                        }
                    } else {
                        $destName = $destCopy."/".basename($boxData["tmp_name"]);
                        if ($destName == $boxData["tmp_name"]) $destName .= "1";
                        if (move_uploaded_file($boxData["tmp_name"], $destName)) {
                            $boxData["tmp_name"] = $destName;
                            $this->storeFileToCopy($boxData);
                        } else {
                            $mess = ConfService::getMessages();
                            $errorCode = 411;
                            $errorMessage="$mess[33] ".$boxData["name"];
                            break;
                        }
                    }
                }
                if (isSet($errorMessage)) {
                    $this->logDebug("Return error $errorCode $errorMessage");
                    return array("ERROR" => array("CODE" => $errorCode, "MESSAGE" => $errorMessage));
                } else {
                    $this->logDebug("Return success");
                    return array("SUCCESS" => true, "PREVENT_NOTIF" => true);
                }

            break;
            default:
            break;
        }
        session_write_close();
        exit;

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

    public function deldir($location)
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
                $this->deldir($recurse);
            }
            rmdir($location);
        } else {
            if (file_exists("$location")) {
                $test = @unlink("$location");
                if(!$test) throw new Exception("Cannot delete file ".$location);
            }
        }
        if (basename(dirname($location)) == $this->repository->getOption("RECYCLE_BIN")) {
            // DELETING FROM RECYCLE
            RecycleBinManager::deleteFromRecycle($location);
        }
    }


    public function storeFileToCopy($fileData)
    {
        $user = AuthService::getLoggedUser();
        $files = $user->getTemporaryData("tmp_upload");
        $this->logDebug("Saving user temporary data", array($fileData));
        $files[] = $fileData;
        $user->saveTemporaryData("tmp_upload", $files);
        if(AJXP_Utils::userAgentIsNativePydioApp()){
            $this->logInfo("Up from",$_SERVER["HTTP_USER_AGENT"]." - direct triger of next to remote");
            $this->uploadActions("next_to_remote", array(), array());
        }
    }

    public function getFileNameToCopy()
    {
            $user = AuthService::getLoggedUser();
            $files = $user->getTemporaryData("tmp_upload");
            return $files[0]["name"];
    }

    public function getNextFileToCopy()
    {
            if(!$this->hasFilesToCopy()) return "";
            $user = AuthService::getLoggedUser();
            $files = $user->getTemporaryData("tmp_upload");
            $fData = $files[0];
            array_shift($files);
            $user->saveTemporaryData("tmp_upload", $files);
            return $fData;
    }

    public function hasFilesToCopy()
    {
            $user = AuthService::getLoggedUser();
            $files = $user->getTemporaryData("tmp_upload");
            return (count($files)?true:false);
    }

    public function testParameters($params)
    {
        if (empty($params["FTP_USER"])) {
            throw new AJXP_Exception("Even if you intend to use the credentials stored in the session, temporarily set a user and password to perform the connexion test.");
        }
        if ($params["FTP_SECURE"]) {
            $link = @ftp_ssl_connect($params["FTP_HOST"], $params["FTP_PORT"]);
        } else {
            $link = @ftp_connect($params["FTP_HOST"], $params["FTP_PORT"]);
        }
        if (!$link) {
            throw new AJXP_Exception("Cannot connect to FTP server (".$params["FTP_HOST"].",". $params["FTP_PORT"].")");
        }
        @ftp_set_option($link, FTP_TIMEOUT_SEC, 10);
        if (!@ftp_login($link,$params["FTP_USER"],$params["FTP_PASS"])) {
            ftp_close($link);
            throw new AJXP_Exception("Cannot login to FTP server with user ".$params["FTP_USER"]);
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
