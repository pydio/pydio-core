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
 
class AjaXplorerUpgrader {

    private $archiveURL;
    private $archiveHash;
    private $archiveHashMethod;
    private $markedFiles;

    private $debugMode = FALSE;
    private $cleanFile = "UPGRADE/CLEAN-FILES";
    private $additionalScript = "UPGRADE/PHP-SCRIPT";
    private $releaseNote = "UPGRADE/NOTE";
    private $installPath;

    private $archive;
    private $workingFolder;
    private $steps;
    public $step = 0;

    public $error = null;
    public $result = "";
    public $currentStepTitle;

    public function __construct($archiveURL, $hash, $method, $backupFiles = array()){
        $this->archiveURL = $archiveURL;
        $this->archiveHash = $hash;
        $this->archiveHashMethod = $method;
        $this->markedFiles = $backupFiles;

        $this->installPath = AJXP_INSTALL_PATH;
        if($this->debugMode){
            @mkdir(AJXP_INSTALL_PATH."/upgrade_test");
            $this->installPath = AJXP_INSTALL_PATH."/upgrade_test";
        }

        $this->workingFolder = AJXP_DATA_PATH."/tmp/update";
        $this->steps = array(
            "checkDownloadFolder"   => "Checking download permissions",
            "downloadArchive"       => "Downloading upgrade archive",
            "checkArchiveIntegrity" => "Checking archive integrity",
            "checkTargetFolder"     => "Checking folders permissions",
            "extractArchive"        => "Extracting Archive",
            "backupMarkedFiles"     => "Backuping your modified files",
            "copyCodeFiles"         => "Copying core source files",
            "restoreMarkedFiles"     => "Restoring your modified files",
            "duplicateConfFiles"    => "Copying configuration files",
            "cleanUnusedFiles"      => "Deleting unused files",
            "specificTask"          => "Running specific upgrade task",
            "updateVersion"         => "Everything went ok, upgrading version!"
        );
    }

    static function getUpgradePath($url, $format = "php"){
        $json = file_get_contents($url."?version=".AJXP_VERSION);
        if($format == "php") return json_decode($json);
        else return $json;
    }

    function hasNextStep(){
        if($this->step < count($this->steps) && $this->error == NULL){
            $stepValues = array_values($this->steps);
            $this->currentStepTitle = $stepValues[$this->step];
            return true;
        }
        return false;
    }

    function execute(){
        $stepKeys = array_keys($this->steps);
        try{
            if(method_exists($this, $stepKeys[$this->step])){
                $this->result = call_user_func(array($this, $stepKeys[$this->step]));
            }else{
                $this->result = "Skipping step, method not found";
            }
        }catch(Exception $e){
            $this->error = $e->getMessage();
        }
        $this->step ++;
    }

    function checkDownloadFolder(){
        if(!is_dir($this->workingFolder)){
            $t = @mkdir($this->workingFolder, 0666, true);
            if($t === false) throw new Exception("Cannot create target folder for downloading upgrade archive!");
        }
        return "OK";
    }

    function checkTargetFolder(){
        if(!is_writable(AJXP_INSTALL_PATH)){
            throw new Exception("The root install path is not writeable, no file will be copied!
            The archive is available on your server, you can copy its manually to override the current installation.");
        }
        return "OK";
    }

    function downloadArchive(){
        $this->archive = $this->workingFolder."/".basename($this->archiveURL);
        if($this->debugMode && is_file($this->archive)) {
            return "Already downloaded";
        }
        $content = file_get_contents($this->archiveURL);
        if($content === false || strlen($content) == 0){
            throw new Exception("Error while downloading");
        }
        file_put_contents($this->archive, $content);
        return "File saved in ".$this->archive;
    }

    function extractArchive(){
        require_once(AJXP_BIN_FOLDER . "/pclzip.lib.php");
        $archive = new PclZip($this->archive);
        $result = $archive->extract(PCLZIP_OPT_PATH, $this->workingFolder);
        if($result <= 0){
            throw new Exception($archive->errorInfo(true));
        }else{
            // Check that there is a new folder without zip extension
            if(is_dir($this->workingFolder."/".substr(basename($this->archive), 0, -4)) ){
                $this->workingFolder = $this->workingFolder."/".substr(basename($this->archive), 0, -4);
            }
            return "Extracted folder ".$this->workingFolder;
        }

    }

    function checkArchiveIntegrity(){
        if(!is_file($this->archive)){
            throw new Exception("Cannot find archive file!");
        }
        $result = hash_file($this->archiveHashMethod, $this->archive);
        if($result != $this->archiveHash){
            throw new Exception("Warning the archive seems corrupted, you should re-download it!");
        }
        return "Hash is ok ($this->archiveHash)";
    }

    function backupMarkedFiles(){

        $targetFolder = $this->installPath;
        foreach($this->markedFiles as $index => $file){
            $file = trim($file);
            if(!empty($file) && is_file($targetFolder."/".$file)){
                $newName = $file.".orig-".date("Ymd");
                copy($targetFolder."/".$file, $targetFolder."/".$newName);
            }else{
                unset($this->markedFiles[$index]);
            }
        }
        if(!count($this->markedFiles)){
            return "Nothing to do";
        }
        return "Backup of ".count($this->markedFiles)." file(s) marked as preserved.";
    }

    function copyCodeFiles(){
        // CORE & PLUGINS
        $targetFolder = $this->installPath;
        $this->copy_r($this->workingFolder."/core", $targetFolder."/core");
        $this->copy_r($this->workingFolder."/plugins", $targetFolder."/plugins");
        $rootFiles = glob($this->workingFolder."/*.php");
        foreach($rootFiles as $file){
            copy($file, $targetFolder."/".basename($file));
        }
        return "Upgraded core, plugins and base access points.";
    }

    function restoreMarkedFiles(){

        if(!count($this->markedFiles)){
            return "Nothing to do";
        }
        $targetFolder = $this->installPath;
        foreach($this->markedFiles as $file){
            $bakupName = $file.".orig-".date("Ymd");
            $newName = $file.".new-".date("Ymd");
            if(is_file($targetFolder."/".$file) && is_file($targetFolder."/".$bakupName)){
                copy($targetFolder."/".$file, $targetFolder."/".$newName);
                copy($targetFolder."/".$bakupName, $targetFolder."/".$file);
                unlink($targetFolder."/".$bakupName);
            }
        }
        return "Restoration of ".count($this->markedFiles)." file(s) marked as preserved.";
    }


    function duplicateConfFiles(){
        $confFiles = glob($this->workingFolder."/conf/*.php");
        foreach($confFiles as $file){
            $newFileName = $this->installPath."/conf/".basename($file).".new-".date("Ymd");
            copy($file, $newFileName);
        }
        return "Successfully copied ".count($confFiles)." files inside config folder (not overriden, please review them)";
    }


    function cleanUnusedFiles(){

        if(!is_file($this->workingFolder."/".$this->cleanFile)) return "Nothing to do.";
        $deleted = array();
        foreach(file($this->workingFolder."/".$this->cleanFile) as $file){
            $file = trim($file);
            if(is_file($this->installPath."/".$file)){
                if(in_array($file, $this->markedFiles)){
                    rename($this->installPath."/".$file, $this->installPath."/".$file.".unused");
                }else{
                    unlink($this->installPath."/".$file);
                }
                $deleted[] = $file;
            }
        }
        return "Deleted (or backedup) following files : ".implode(", ",$deleted);

    }

    function specificTask(){

        if(!is_file($this->workingFolder."/".$this->additionalScript)) return "Nothing to do.";
        include($this->workingFolder."/".$this->additionalScript);
        return "Executed specific upgrade task.";

    }

    function updateVersion(){
        // Finally copy VERSION file
        copy($this->workingFolder."/conf/VERSION", $this->installPath."/conf/VERSION");
        $vCont = file_get_contents($this->installPath."/conf/VERSION");
        list($v, $date) = explode("__", $vCont);
        return "<b>Version upgraded to ".$v." ($date)</b>";
    }


    function copy_r( $path, $dest )
    {
        if( is_dir($path) )
        {
            @mkdir( $dest );
            $objects = scandir($path);
            if( sizeof($objects) > 0 )
            {
                foreach( $objects as $file )
                {
                    if( $file == "." || $file == ".." )
                        continue;
                    // go on
                    if( is_dir( $path.DIRECTORY_SEPARATOR.$file ) )
                    {
                        $this->copy_r( $path.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file );
                    }
                    else
                    {
                        copy( $path.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file );
                    }
                }
            }
            return true;
        }
        elseif( is_file($path) )
        {
            return copy($path, $dest);
        }
        else
        {
            return false;
        }
    }

}
