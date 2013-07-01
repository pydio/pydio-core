<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Encapsulation of the Jumploader Java applet (must be downloaded separately).
 * @package AjaXplorer_Plugins
 * @subpackage Uploader
 */
class JumploaderProcessor extends AJXP_Plugin {

	/**
	 * Handle UTF8 Decoding
	 *
	 * @var unknown_type
	 */
	private static $skipDecoding = false;

    public function preProcess($action, &$httpVars, &$fileVars){
        if(isSet($httpVars["simple_uploader"]) || isSet($httpVars["xhr_uploader"])) return;
        $repository = ConfService::getRepository();
        if($repository->detectStreamWrapper(false)){
            $plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
            $streamData = $plugin->detectStreamWrapper(true);
            if($streamData["protocol"] == "ajxp.ftp" || $streamData["protocol"]=="ajxp.remotefs"){
                AJXP_Logger::debug("Skip decoding");
                self::$skipDecoding = true;
            }
        }
        AJXP_Logger::debug("Jumploader HttpVars", $httpVars);
        AJXP_Logger::debug("Jumploader FileVars", $fileVars);

        $httpVars["dir"] = base64_decode(str_replace(" ","+",$httpVars["dir"]));
        $index = $httpVars["partitionIndex"];
        $realName = $fileVars["userfile_0"]["name"];

        /* if the file has to be partitioned */
        if(isSet($httpVars["partitionCount"]) && intval($httpVars["partitionCount"]) > 1){
            AJXP_LOGGER::debug("Partitioned upload");
            $fileId = $httpVars["fileId"];
            $clientId = $httpVars["ajxp_sessid"];
            $fileVars["userfile_0"]["name"] = "$clientId.$fileId.$index";
            $httpVars["lastPartition"] = false;
        }

        /* if we received the last partition */
        if(intval($index) == intval($httpVars["partitionCount"])-1){
            $httpVars["lastPartition"] = true;
            $httpVars["partitionRealName"] = $realName;
        }
    }

    public function postProcess($action, $httpVars, $postProcessData){
        if(isSet($httpVars["simple_uploader"]) || isSet($httpVars["xhr_uploader"])) return;
        /*if(self::$skipDecoding){

        }*/

        if(isset($postProcessData["processor_result"]["ERROR"])){
            if(isset($httpVars["lastPartition"]) && isset($httpVars["partitionCount"])){
                /* we get the stream url (where all the partitions have been uploaded so far) */
                $repository = ConfService::getRepository();
                $dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
                $plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
                $streamData = $plugin->detectStreamWrapper(true);
                $destStreamURL = $streamData["protocol"]."://".$repository->getId().$dir."/";

                if($httpVars["partitionCount"] > 1){
                    /* we fetch the information that help us to construct the temp files name */
                    $index = intval($httpVars["partitionIndex"]);
                    $fileId = $httpVars["fileId"];
                    $clientId = $httpVars["ajxp_sessid"];

                    /* deletion of all the partitions that have been uploaded */
                    for($i = 0; $i < $httpVars["partitionCount"]; $i++){
                        if(file_exists($destStreamURL."$clientId.$fileId.$i")){
                            unlink($destStreamURL."$clientId.$fileId.$i");
                        }
                    }
                }
                else{
                    $fileName = $httpVars["fileName"];
                    unlink($destStreamURL.$fileName);
                }
            }
            echo "Error: ".$postProcessData["processor_result"]["ERROR"]["MESSAGE"];
            return;
        }

        if(!isSet($httpVars["partitionRealName"]) && !isSet($httpVars["lastPartition"])) {
            return ;
        }

        $repository = ConfService::getRepository();

        if(!$repository->detectStreamWrapper(false)){
            return false;
        }

        if($httpVars["lastPartition"]){
            $plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
            $streamData = $plugin->detectStreamWrapper(true);
            $dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
            $destStreamURL = $streamData["protocol"]."://".$repository->getId().$dir."/";

            /* we check if the current file has a relative path (aka we want to upload an entire directory) */
            AJXP_LOGGER::debug("Now dispatching relativePath dest:", $httpVars["relativePath"]);
            $subs = explode("/", $httpVars["relativePath"]);
            $userfile_name = array_pop($subs);
            $folderForbidden = false;
            $all_in_place = true;
            $partitions_length = 0;
            $fileId = $httpVars["fileId"];
            $clientId = $httpVars["ajxp_sessid"];
            $partitionCount = $httpVars["partitionCount"];
            $partitionIndex = $httpVars["partitionIndex"];
            $fileLength = $_POST["fileLength"];

            /* check if all the partitions have been uploaded or not */
            for( $i = 0; $all_in_place && $i < $partitionCount; $i++ ) {
                $partition_file = $destStreamURL."$clientId.$fileId.$i";
                if( file_exists( $partition_file ) ) {
                    $partitions_length += filesize( $partition_file );
                } else {
                    $all_in_place = false;
                }
            }

            if(!$all_in_place || $partitions_length != intval($fileLength)){
                echo "Error: Upload validation error!";
                /* we delete all the uploaded partitions */
                if($httpVars["partitionCount"] > 1){
                    for($i = 0; $i < $partitionCount; $i++){
                        if(file_exists($destStreamURL."$clientId.$fileId.$i")){
                            unlink($destStreamURL."$clientId.$fileId.$i");
                        }
                    }
                }
                else{
                    $fileName = $httpVars["fileName"];
                    unlink($destStreamURL.$fileName);
                }
                return;
            }

            if(count($subs) > 0){
                $curDir = "";

                if(substr($curDir, -1) == "/"){
                    $curDir = substr($curDir, 0, -1);
                }

                // Create the folder tree as necessary
                foreach ($subs as $key => $spath) {
                    $messtmp="";
                    $dirname=AJXP_Utils::decodeSecureMagic($spath, AJXP_SANITIZE_HTML_STRICT);
                    $dirname = substr($dirname, 0, ConfService::getCoreConf("NODENAME_MAX_LENGTH"));
                    //$this->filterUserSelectionToHidden(array($dirname));
                    if(AJXP_Utils::isHidden($dirname)){
                        $folderForbidden = true;
                        break;
                    }

                    if(file_exists($destStreamURL."$curDir/$dirname")) {
                        // if the folder exists, traverse
                        AJXP_Logger::debug("$curDir/$dirname existing, traversing for $userfile_name out of", $httpVars["relativePath"]);
                        $curDir .= "/".$dirname;
                        continue;
                    }

                    AJXP_Logger::debug($destStreamURL.$curDir);
                    $dirMode = 0775;
                    $chmodValue = $repository->getOption("CHMOD_VALUE");
                    if(isSet($chmodValue) && $chmodValue != "")
                    {
                        $dirMode = octdec(ltrim($chmodValue, "0"));
                        if ($dirMode & 0400) $dirMode |= 0100; // User is allowed to read, allow to list the directory
                        if ($dirMode & 0040) $dirMode |= 0010; // Group is allowed to read, allow to list the directory
                        if ($dirMode & 0004) $dirMode |= 0001; // Other are allowed to read, allow to list the directory
                    }
                    $old = umask(0);
                    mkdir($destStreamURL.$curDir."/".$dirname, $dirMode);
                    umask($old);
                    $curDir .= "/".$dirname;
                }
            }

            // Now move the final file to the right folder
            // Currently the file is at the base of the current
            $relPath = AJXP_Utils::decodeSecureMagic($httpVars["relativePath"]);
            $current = $destStreamURL.basename($relPath);
            $target = $destStreamURL.$relPath;

            if(!$folderForbidden){
                $count = intval($httpVars["partitionCount"]);
                $fileId = $httpVars["fileId"];
                $clientId = $httpVars["ajxp_sessid"];
                AJXP_Logger::debug("Should now rebuild file!", $httpVars);

                if($count > 1){
                    $newDest = fopen($destStreamURL.$httpVars["partitionRealName"], "w");
                    AJXP_LOGGER::debug("PartitionRealName", $destStreamURL.$httpVars["partitionRealName"]);
                    for ($i = 0; $i < $count ; $i++){
                        $part = fopen($destStreamURL."$clientId.$fileId.$i", "r");
                        while(!feof($part)){
                            fwrite($newDest, fread($part, 4096));
                        }
                        fclose($part);
                        unlink($destStreamURL."$clientId.$fileId.$i");
                    }
                    fclose($newDest);
                }

                $err = copy($current, $target);
                if($err !== false){
                    unlink($current);
                    AJXP_Controller::applyHook("node.change", array(null, new AJXP_Node($target), false));
                }

                else if($current == $target){
                    AJXP_Controller::applyHook("node.change", array(null, new AJXP_Node($target), false));
                }
            }

            else{
                // Remove the file, as it should not have been uploaded!
                unlink($current);
            }
        }
    }
}
?>