<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Charles
 * Date: 13/10/11
 * Time: 22:25
 * To change this template use File | Settings | File Templates.
 */
 
class AjaXplorerUpgrader {

    private $archive;
    private $archiveURL;
    private $workingFolder;
    private $steps;
    public $step = 0;

    public $error = null;
    public $result = "";
    public $currentStepTitle;

    public function __construct($archiveURL){
        $this->archiveURL = $archiveURL;
        $this->workingFolder = AJXP_DATA_PATH."/tmp/update";
        $this->steps = array(
            "checkDownloadFolder"  => "Checking download permissions",
            "downloadArchive"       => "Downloading upgrade archive",
            "checkArchiveIntegrity" => "Checking archive integrity",
            "checkTargetFolder"  => "Checking folders permissions",
            "extractArchive" => "Extracting Archive",
            "copyCodeFiles" => "Copying core source files",
            "duplicateConfFiles" => "Copying configuration files",
            "cleanUnusedFiles"   => "Deleting unused files",
            "specificTask"      => "Running specific upgrade task"
        );
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
            $this->result = call_user_func(array($this, $stepKeys[$this->step]));
        }catch(Exception $e){
            $this->error = $e;
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
        $content = file_get_contents($this->archiveURL);
        if($content === false || strlen($content) == 0){
            throw new Exception("Error while downloading");
        }
        file_put_contents($this->archive, $content);
        return "File saved in ".$this->archive;
    }

    function extractArchive(){
        require_once(AJXP_BIN_FOLDER . "/pclzip.lib.php");
        /*
        $zipPath = $selection->getZipPath(true);
        $zipLocalPath = $selection->getZipLocalPath(true);
        if(strlen($zipLocalPath)>1 && $zipLocalPath[0] == "/") $zipLocalPath = substr($zipLocalPath, 1)."/";
        $files = $selection->getFiles();

        $realZipFile = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase.$zipPath);
        $archive = new PclZip($realZipFile);
        $content = $archive->listContent();
        foreach ($files as $key => $item){// Remove path
            $item = substr($item, strlen($zipPath));
            if($item[0] == "/") $item = substr($item, 1);
            foreach ($content as $zipItem){
                if($zipItem["stored_filename"] == $item || $zipItem["stored_filename"] == $item."/"){
                    $files[$key] = $zipItem["stored_filename"];
                    break;
                }else{
                    unset($files[$key]);
                }
            }
        }
        AJXP_Logger::debug("Archive", $files);
        $realDestination = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase.$destDir);
        AJXP_Logger::debug("Extract", array($realDestination, $realZipFile, $files, $zipLocalPath));
        $result = $archive->extract(PCLZIP_OPT_BY_NAME, $files,
                                    PCLZIP_OPT_PATH, $realDestination,
                                    PCLZIP_OPT_REMOVE_PATH, $zipLocalPath);
        */
        $archive = new PclZip($this->archive);
        $result = $archive->extract(PCLZIP_OPT_PATH, $this->workingFolder);
        if($result <= 0){
            throw new Exception($archive->errorInfo(true));
        }else{
            return "OK";
        }

    }

    function checkTargetFolders(){

    }

    function checkArchiveIntegrity(){

    }

    function copyCodeFiles(){

    }

    function duplicateConfFiles(){

    }


}
