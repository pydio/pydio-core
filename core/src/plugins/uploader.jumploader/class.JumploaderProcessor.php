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
    private static $remote = false;

    public function preProcess($action, &$httpVars, &$fileVars){
        if(isSet($httpVars["simple_uploader"]) || isSet($httpVars["xhr_uploader"])) return;
        $repository = ConfService::getRepository();
        $driver = ConfService::loadDriverForRepository($repository);

        if(method_exists($driver, "storeFileToCopy")){
            self::$remote = true;
        }

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

				/* if fileId is not set, request for cross-session resume (only if the protocol is not ftp)*/
		if(!isSet($httpVars["fileId"])) {
            AJXP_LOGGER::debug("Cross-Session Resume request");

            $plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
            $streamData = $plugin->detectStreamWrapper(true);
            $dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
            $destStreamURL = $streamData["protocol"]."://".$repository->getId().$dir;
            $fileHash = md5($httpVars["fileName"]);

            if(self::$remote){
                $resumeIndexes = array ();
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destStreamURL));
                $it->setMaxDepth(0);
                while ($it->valid()) {
                    if (!$it->isDot()) {
                        $subPathName = $it->getSubPathName();
                        AJXP_LOGGER :: debug("Iterator SubPathName: " . $it->getSubPathName());
                        if (strpos($subPathName, $fileHash) === 0) {
                            $explodedSubPathName = explode('.', $subPathName);
                            $resumeFileId = $explodedSubPathName[1];
                            $resumeIndexes[] = $explodedSubPathName[2];

                            AJXP_LOGGER :: debug("Current Index: " . $explodedSubPathName[2]);
                        }
                    }
                    $it->next();
                }

                /* no valid temp file found. return. */
                if (empty ($resumeIndexes))
                    return;

                AJXP_LOGGER :: debug("ResumeFileID: " . $resumeFileId);
                AJXP_LOGGER :: debug("Max Resume Index: " . max($resumeIndexes));
                $nextResumeIndex = max($resumeIndexes) + 1;
                AJXP_LOGGER :: debug("Next Resume Index: " . $nextResumeIndex);

                if(isSet($resumeFileId)) {
                    AJXP_LOGGER::debug("ResumeFileId is set. Returning values: fileId: " . $resumeFileId . ", partitionIndex: " . $nextResumeIndex);
                    $httpVars["resumeFileId"] = $resumeFileId;
                    $httpVars["resumePartitionIndex"] = $nextResumeIndex;
                }
            }
            return;
        }

        /* if the file has to be partitioned */
        if(isSet($httpVars["partitionCount"]) && intval($httpVars["partitionCount"]) > 1){
            AJXP_LOGGER::debug("Partitioned upload");
            $fileId = $httpVars["fileId"];
            $fileHash = md5($realName);
            
            /* In order to enable cross-session resume, temp files must not depend on session.
             * Now named after and md5() of the original file name.
             */
						AJXP_LOGGER::debug("Filename: " . $realName . ", File hash: " . $fileHash);
            $fileVars["userfile_0"]["name"] = "$fileHash.$fileId.$index";
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

				/* If set resumeFileId and resumePartitionIndex, cross-session resume is requested. */
    		if(isSet($httpVars["resumeFileId"]) && isSet($httpVars["resumePartitionIndex"])) {
				header("HTTP/1.1 200 OK");
							
        	    print("fileId: " . $httpVars["resumeFileId"] . "\n");
        	    print("partitionIndex: " . $httpVars["resumePartitionIndex"]);
    		
    			return;
    		}

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
                    $fileId = $httpVars["fileId"];
                    $fileHash = md5($httpVars["fileName"]);

                    /* deletion of all the partitions that have been uploaded */
                    for($i = 0; $i < $httpVars["partitionCount"]; $i++){
                        if(file_exists($destStreamURL."$fileHash.$fileId.$i")){
                            unlink($destStreamURL."$fileHash.$fileId.$i");
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
        $driver = ConfService::loadDriverForRepository($repository);

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
            $fileHash = md5($userfile_name);
            $partitionCount = $httpVars["partitionCount"];
            $fileLength = $_POST["fileLength"];

            if(self::$remote){
                $partitions = array();
                do{
                    $partitions[] = $driver->getNextFileToCopy();
                }while($driver->hasFilesToCopy());
            }

            if($partitionCount > 1){
                if(self::$remote){
                    for( $i = 0; $all_in_place && $i < $partitionCount; $i++){
                        $partition_file = "$fileHash.$fileId.$i";
                        if( strpos($partitions[$i]["name"], $partition_file) == 0 ){
                            $partitions_length += filesize( $partitions[$i]["tmp_name"] );
                        }
                        else{ $all_in_place = false; }
                    }
                }

                else{
                    for( $i = 0; $all_in_place && $i < $partitionCount; $i++ ) {
                        $partition_file = $destStreamURL."$fileHash.$fileId.$i";
                        if( file_exists( $partition_file ) ) {
                            $partitions_length += filesize( $partition_file );
                        } else { $all_in_place = false; }
                    }
                }
            }

            else{
                if(self::$remote){
                    if( strpos($partitions[0]["name"], $userfile_name) ){
                        $partitions_length += filesize( $partitions[0]["tmp_name"] );
                    }
                }

                else{
                    if(file_exists($destStreamURL.$userfile_name)){
                        $partitions_length += filesize($destStreamURL.$userfile_name);
                    }
                }
            }
                if( (!$all_in_place || $partitions_length != intval($fileLength))){
                    echo "Error: Upload validation error!";
                    /* we delete all the uploaded partitions */
                    if($httpVars["partitionCount"] > 1){
                        for($i = 0; $i < $partitionCount; $i++){
                            if(file_exists($destStreamURL."$fileHash.$fileId.$i")){
                                unlink($destStreamURL."$fileHash.$fileId.$i");
                            }
                        }
                    }
                    else{
                        $fileName = $httpVars["fileName"];
                        unlink($destStreamURL.$fileName);
                    }
                    return;
                }

                if(count($subs) > 0 && !self::$remote){
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

                if(!$folderForbidden){
                    $count = intval($httpVars["partitionCount"]);
                    $fileId = $httpVars["fileId"];
                    AJXP_Logger::debug("Should now rebuild file!", $httpVars);

                    if($count > 1){
                        AJXP_LOGGER::debug("PartitionRealName", $destStreamURL.$httpVars["partitionRealName"]);
                        $relPath = AJXP_Utils::decodeSecureMagic($httpVars["relativePath"]);

                        $target = $destStreamURL.$relPath;
                        if(self::$remote){
                            $current = "/tmp/".basename($relPath);
                            $newDest = fopen("/tmp/".$httpVars["partitionRealName"], "w");
                            $newFile = array();
                            $length = 0;
                            for($i = 0, $count = count($partitions); $i < $count; $i++){
                                $currentFile = $partitions[$i];
                                $currentFileName = $currentFile["tmp_name"];
                                $part = fopen($currentFileName, "r");
                                while(!feof($part)){
                                    $length += fwrite($newDest, fread($part, 4096));
                                }
                                fclose($part);
                                unlink($currentFileName);
                            }
                            $newFile["type"] = $partitions[0]["type"];
                            $newFile["name"] = $httpVars["partitionRealName"];
                            $newFile["error"] = 0;
                            $newFile["size"] = $length;
                            $newFile["tmp_name"] = "/tmp/".$httpVars["partitionRealName"];
                            $newFile["destination"] = $partitions[0]["destination"];
                            fclose($newDest);
                            $driver->storeFileToCopy($newFile);
                        }

                        else{
                            // Now move the final file to the right folder
                            // Currently the file is at the base of the current
                            $current = $destStreamURL.basename($relPath);

                            $newDest = fopen($destStreamURL.$httpVars["partitionRealName"], "w");
                            $fileHash = md5($httpVars["fileName"]);

                            for ($i = 0; $i < $count ; $i++){
                                $part = fopen($destStreamURL."$fileHash.$fileId.$i", "r");
                                while(!feof($part)){
                                    fwrite($newDest, fread($part, 4096));
                                }
                                fclose($part);
                                unlink($destStreamURL."$fileHash.$fileId.$i");
                            }

                        }
                        fclose($newDest);
                    }

                    if(!self::$remote) $err = copy($current, $target);

                    if($err !== false){
                        if(!self::$remote) unlink($current);
                        AJXP_Controller::applyHook("node.change", array(null, new AJXP_Node($target), false));
                    }

                    else if($current == $target){
                        AJXP_Controller::applyHook("node.change", array(null, new AJXP_Node($target), false));
                    }
                }

                else{
                    // Remove the file, as it should not have been uploaded!
                    if(!self::$remote) unlink($current);
                }
            }
        }
}
?>