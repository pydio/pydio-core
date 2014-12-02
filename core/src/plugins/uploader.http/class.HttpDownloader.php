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

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Remote files downloader
 * @package AjaXplorer_Plugins
 * @subpackage Downloader
 */
class HttpDownloader extends AJXP_Plugin
{
    public function switchAction($action, $httpVars, $fileVars)
    {
        //$this->logInfo("DL file", $httpVars);

        $repository = ConfService::getRepository();
        if (!$repository->detectStreamWrapper(false)) {
            return false;
        }
        $plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
        $streamData = $plugin->detectStreamWrapper(true);
        $dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
        $destStreamURL = $streamData["protocol"]."://".$repository->getId().$dir."/";
        $dlURL = null;
        if (isSet($httpVars["file"])) {
            $parts = parse_url($httpVars["file"]);
            $getPath = $parts["path"];
            $basename = basename($getPath);
            $dlURL = $httpVars["file"];
        }
        if (isSet($httpVars["dlfile"])) {
            $dlFile = $streamData["protocol"]."://".$repository->getId().AJXP_Utils::decodeSecureMagic($httpVars["dlfile"]);
            $realFile = file_get_contents($dlFile);
            if(empty($realFile)) throw new Exception("cannot find file $dlFile for download");
            $parts = parse_url($realFile);
            $getPath = $parts["path"];
            $basename = basename($getPath);
            $dlURL = $realFile;
        }

        switch ($action) {
            case "external_download":
                if (!ConfService::currentContextIsCommandLine() && ConfService::backgroundActionsSupported()) {

                    $unixProcess = AJXP_Controller::applyActionInBackground($repository->getId(), "external_download", $httpVars);
                    if ($unixProcess !== null) {
                        @file_put_contents($destStreamURL.".".$basename.".pid", $unixProcess->getPid());
                    }
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::triggerBgAction("reload_node", array(), "Triggering DL ", true, 2);
                    AJXP_XMLWriter::close();
                    session_write_close();
                    exit();
                }

                require_once(AJXP_BIN_FOLDER."/http_class/http_class.php");
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

                if (empty($err)) {
                    $err = $httpClient->SendRequest($arguments);
                    $httpClient->follow_redirect = true;

                    $pidHiddenFileName = $destStreamURL.".".$basename.".pid";
                    if (is_file($pidHiddenFileName)) {
                        $pid = file_get_contents($pidHiddenFileName);
                        @unlink($pidHiddenFileName);
                    }
                    if (empty($err)) {

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
                            $node = new AJXP_Node($destStreamURL.$basename);
                            AJXP_Controller::applyHook("node.before_create", array($node, $totalSize));
                        }

                        $tmpFilename = $destStreamURL.$basename.".dlpart";
                        $hiddenFilename = $destStreamURL."__".$basename.".ser";
                        $filename = $destStreamURL.$basename;

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
                        $destStream = fopen($tmpFilename, "w");
                        while (true) {
                            $body = "";
                            $error = $httpClient->ReadReplyBody($body, 1000);
                            if($error != "" || strlen($body) == 0) break;
                            fwrite($destStream, $body, strlen($body));
                        }
                        fclose($destStream);
                        rename($tmpFilename, $filename);
                        unlink($hiddenFilename);
                    }
                    $httpClient->Close();

                    if (isset($dlFile) && isSet($httpVars["delete_dlfile"]) && is_file($dlFile)) {
                        AJXP_Controller::applyHook("node.before_path_change", array(new AJXP_Node($dlFile)));
                        unlink($dlFile);
                        AJXP_Controller::applyHook("node.change", array(new AJXP_Node($dlFile), null, false));
                    }
                    $mess = ConfService::getMessages();
                    AJXP_Controller::applyHook("node.change", array(null, new AJXP_Node($filename), false));
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::triggerBgAction("reload_node", array(), $mess["httpdownloader.8"]);
                    AJXP_XMLWriter::close();

                }

            break;
            case "update_dl_data":
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                header("text/plain");
                if (is_file($destStreamURL.$file)) {
                    echo filesize($destStreamURL.$file);
                } else {
                    echo "stop";
                }

            break;
            case "stop_dl":
                $newName = "__".str_replace(".dlpart", ".ser", $basename);
                $hiddenFilename = $destStreamURL.$newName;
                $data = @unserialize(@file_get_contents($hiddenFilename));
                header("text/plain");
                $this->logDebug("Getting $hiddenFilename",$data);
                if (isSet($data["pid"])) {
                    $process = new UnixProcess();
                    $process->setPid($data["pid"]);
                    $process->stop();
                    unlink($hiddenFilename);
                    unlink($destStreamURL.$basename);
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
                $ajxpNode->target_filesize = AJXP_Utils::roundSize($data["totalSize"]);
                $ajxpNode->process_stoppable = (isSet($data["pid"])?"true":"false");
            }
        }
    }
}
