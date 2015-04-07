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


// This is used to catch exception while downloading
if (!function_exists('download_exception_handler')) {
    function download_exception_handler($exception){}
}
/**
 * AJXP_Plugin to access a filesystem. Most "FS" like driver (even remote ones)
 * extend this one.
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class fsAccessDriver extends AbstractAccessDriver implements AjxpWrapperProvider
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    public function initRepository()
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }
        if ( $this->getFilteredOption("PROBE_REAL_SIZE", $this->repository->getId()) == true ) {
            // PASS IT TO THE WRAPPER
            ConfService::setConf("PROBE_REAL_SIZE", $this->getFilteredOption("PROBE_REAL_SIZE", $this->repository->getId()));
        }
        $create = $this->repository->getOption("CREATE");
        $path = SystemTextEncoding::toStorageEncoding($this->repository->getOption("PATH"));
        $recycle = $this->repository->getOption("RECYCLE_BIN");
        $chmod = $this->repository->getOption("CHMOD_VALUE");
        $wrapperData = $this->detectStreamWrapper(true);
        $this->wrapperClassName = $wrapperData["classname"];
        $this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();

        if ($create == true) {
            if(!is_dir($path)) @mkdir($path, 0755, true);
            if (!is_dir($path)) {
                throw new AJXP_Exception("Cannot create root path for repository (".$this->repository->getDisplay()."). Please check repository configuration or that your folder is writeable!");
            }
            if ($recycle!= "" && !is_dir($path."/".$recycle)) {
                @mkdir($path."/".$recycle);
                if (!is_dir($path."/".$recycle)) {
                    throw new AJXP_Exception("Cannot create recycle bin folder. Please check repository configuration or that your folder is writeable!");
                } else {
                    $this->setHiddenAttribute(new AJXP_Node($this->urlBase ."/".$recycle));
                }
            }
            $dataTemplate = $this->repository->getOption("DATA_TEMPLATE");
            if (!empty($dataTemplate) && is_dir($dataTemplate) && !is_file($path."/.ajxp_template")) {
                $errs = array();$succ = array();
                $repoData = array('base_url' => $this->urlBase, 'wrapper_name' => $this->wrapperClassName, 'chmod' => $chmod, 'recycle' => $recycle);
                $this->dircopy($dataTemplate, $path, $succ, $errs, false, false, $repoData, $repoData);
                touch($path."/.ajxp_template");
            }
        } else {
            if (!is_dir($path)) {
                throw new AJXP_Exception("Cannot find base path for your repository! Please check the configuration!");
            }
        }
        if ($recycle != "") {
            RecycleBinManager::init($this->urlBase, "/".$recycle);
        }
    }

    public function getResourceUrl($path)
    {
        return $this->urlBase.$path;
    }

    public function getWrapperClassName()
    {
        return $this->wrapperClassName;
    }

    /**
     * @param String $directoryPath
     * @param Repository $repositoryResolvedOptions
     * @return int
     */
    public function directoryUsage($directoryPath, $repositoryResolvedOptions){

        $dir = $repositoryResolvedOptions["PATH"].$directoryPath;
        $size = -1;
        if ( ( PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows") && class_exists("COM") ) {
            $obj = new COM ( 'scripting.filesystemobject' );
            if ( is_object ( $obj ) ) {
                $ref = $obj->getfolder ( $dir );
                $size = floatval($ref->size);
                $obj = null;
            }
        } else {
            if(PHP_OS == "Darwin") $option = "-sk";
            else $option = "-sb";
            $cmd = '/usr/bin/du '.$option.' ' . escapeshellarg($dir);
            $io = popen ( $cmd , 'r' );
            $size = fgets ( $io, 4096);
            $size = trim(str_replace($dir, "", $size));
            $size =  floatval($size);
            if(PHP_OS == "Darwin") $size = $size * 1024;
            pclose ( $io );
        }
        if($size != -1){
            return $size;
        }else{
            return $this->recursiveDirUsageByListing($directoryPath);
        }

    }

    protected function recursiveDirUsageByListing($path){
        $total_size = 0;
        $files = scandir($path);

        foreach ($files as $t) {
            if (is_dir(rtrim($path, '/') . '/' . $t)) {
                if ($t <> "." && $t <> "..") {
                    $size = $this->recursiveDirUsageByListing(rtrim($path, '/') . '/' . $t);
                    $total_size += $size;
                }
            } else {
                $size = sprintf("%u", filesize(rtrim($path, '/') . '/' . $t));
                $total_size += $size;
            }
        }
        return $total_size;
    }

    public function redirectActionsToMethod(&$contribNode, $arrayActions, $targetMethod)
    {
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        foreach ($arrayActions as $index => $value) {
            $arrayActions[$index] = 'action[@name="'.$value.'"]/processing/serverCallback';
        }
        $procList = $actionXpath->query(implode(" | ", $arrayActions), $contribNode);
        foreach ($procList as $node) {
            $node->setAttribute("methodName", $targetMethod);
        }
    }

    /**
     * @param DOMNode $contribNode
     */
    public function disableArchiveBrowsingContributions(&$contribNode)
    {
        // Cannot use zip features on FTP !
        // Remove "compress" action
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $compressNodeList = $actionXpath->query('action[@name="compress"]', $contribNode);
        if(!$compressNodeList->length) return ;
        unset($this->actions["compress"]);
        $compressNode = $compressNodeList->item(0);
        $contribNode->removeChild($compressNode);
        // Disable "download" if selection is multiple
        $nodeList = $actionXpath->query('action[@name="download"]/gui/selectionContext', $contribNode);
        $selectionNode = $nodeList->item(0);
        $values = array("dir" => "false", "unique" => "true");
        foreach ($selectionNode->attributes as $attribute) {
            if (isSet($values[$attribute->name])) {
                $attribute->value = $values[$attribute->name];
            }
        }
        $nodeList = $actionXpath->query('action[@name="download"]/processing/clientListener[@name="selectionChange"]', $contribNode);
        $listener = $nodeList->item(0);
        $listener->parentNode->removeChild($listener);
        // Disable "Explore" action on files
        $nodeList = $actionXpath->query('action[@name="ls"]/gui/selectionContext', $contribNode);
        $selectionNode = $nodeList->item(0);
        $values = array("file" => "false", "allowedMimes" => "");
        foreach ($selectionNode->attributes as $attribute) {
            if (isSet($values[$attribute->name])) {
                $attribute->value = $values[$attribute->name];
            }
        }
    }

    protected function getNodesDiffArray()
    {
        return array("REMOVE" => array(), "ADD" => array(), "UPDATE" => array());
    }

    public function addSlugToPath($selection)
    {
        if (is_array($selection))
            // As passed by Copy/Move
            $orig_files = $selection;
        elseif ((is_object($selection)) && (isset($selection->files)) && (is_array($selection->files)))
            // As passed by Download
            $orig_files = $selection->files;
        elseif (is_string($selection))
            // As passed by destination parameter
            return $this->repository->slug.$selection;
        else
            // Unrecognized
            return $selection;

        $files = array();
        foreach ($orig_files as $file)
            $files[] = $this->repository->slug.$file;
        return $files;
    }

    public function switchAction($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return;
        parent::accessPreprocess($action, $httpVars, $fileVars);
        $selection = new UserSelection($this->repository);
        $dir = $httpVars["dir"] OR "";
        if ($this->wrapperClassName == "fsAccessWrapper") {
            $dir = fsAccessWrapper::patchPathForBaseDir($dir);
        }
        $dir = AJXP_Utils::securePath($dir);
        if ($action != "upload") {
            $dir = AJXP_Utils::decodeSecureMagic($dir);
        }
        $selection->initFromHttpVars($httpVars);
        if (!$selection->isEmpty()) {
            $this->filterUserSelectionToHidden($selection->getFiles());
        }
        $mess = ConfService::getMessages();

        $newArgs = RecycleBinManager::filterActions($action, $selection, $dir, $httpVars);
        if(isSet($newArgs["action"])) $action = $newArgs["action"];
        if(isSet($newArgs["dest"])) $httpVars["dest"] = SystemTextEncoding::toUTF8($newArgs["dest"]);//Re-encode!
         // FILTER DIR PAGINATION ANCHOR
        $page = null;
        if (isSet($dir) && strstr($dir, "%23")!==false) {
            $parts = explode("%23", $dir);
            $dir = $parts[0];
            $page = $parts[1];
        }

        $pendingSelection = "";
        $logMessage = null;
        $reloadContextNode = false;

        switch ($action) {
            //------------------------------------
            //	DOWNLOAD
            //------------------------------------
            case "download":
                $this->logInfo("Download", array("files"=>$this->addSlugToPath($selection)));
                @set_error_handler(array("HTMLWriter", "javascriptErrorHandler"), E_ALL & ~ E_NOTICE);
                @register_shutdown_function("restore_error_handler");
                $zip = false;
                if ($selection->isUnique()) {
                    if (is_dir($this->urlBase.$selection->getUniqueFile())) {
                        $zip = true;
                        $base = basename($selection->getUniqueFile());
                        $uniqDir = dirname($selection->getUniqueFile());
                        if(!empty($uniqDir) && $uniqDir != "/"){
                            $dir = dirname($selection->getUniqueFile());
                        }
                    } else {
                        if (!file_exists($this->urlBase.$selection->getUniqueFile())) {
                            throw new Exception("Cannot find file!");
                        }
                    }
                    $node = $selection->getUniqueNode($this);
                } else {
                    $zip = true;
                }
                if ($zip) {
                    // Make a temp zip and send it as download
                    $loggedUser = AuthService::getLoggedUser();
                    $file = AJXP_Utils::getAjxpTmpDir()."/".($loggedUser?$loggedUser->getId():"shared")."_".time()."tmpDownload.zip";
                    $zipFile = $this->makeZip($selection->getFiles(), $file, empty($dir)?"/":$dir);
                    if(!$zipFile) throw new AJXP_Exception("Error while compressing");
                    if(!$this->getFilteredOption("USE_XSENDFILE", $this->repository->getId())
                        && !$this->getFilteredOption("USE_XACCELREDIRECT", $this->repository->getId())){
                        register_shutdown_function("unlink", $file);
                    }
                    $localName = ($base==""?"Files":$base).".zip";
                    if(isSet($httpVars["archive_name"])){
                        $localName = AJXP_Utils::decodeSecureMagic($httpVars["archive_name"]);
                    }
                    $this->readFile($file, "force-download", $localName, false, false, true);
                } else {
                    $localName = "";
                    AJXP_Controller::applyHook("dl.localname", array($this->urlBase.$selection->getUniqueFile(), &$localName, $this->wrapperClassName));
                    $this->readFile($this->urlBase.$selection->getUniqueFile(), "force-download", $localName);
                }
                if (isSet($node)) {
                    AJXP_Controller::applyHook("node.read", array(&$node));
                }


                break;

            case "prepare_chunk_dl" :

                $chunkCount = intval($httpVars["chunk_count"]);
                $fileId = $this->urlBase.$selection->getUniqueFile();
                $sessionKey = "chunk_file_".md5($fileId.time());
                $totalSize = $this->filesystemFileSize($fileId);
                $chunkSize = intval ( $totalSize / $chunkCount );
                $realFile  = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $fileId, true);
                $chunkData = array(
                    "localname"	  => basename($fileId),
                    "chunk_count" => $chunkCount,
                    "chunk_size"  => $chunkSize,
                    "total_size"  => $totalSize,
                    "file_id"	  => $sessionKey
                );

                $_SESSION[$sessionKey] = array_merge($chunkData, array("file"=>$realFile));
                HTMLWriter::charsetHeader("application/json");
                print(json_encode($chunkData));

                $node = $selection->getUniqueNode($this);
                AJXP_Controller::applyHook("node.read", array(&$node));

                break;

            case "download_chunk" :

                $chunkIndex = intval($httpVars["chunk_index"]);
                $chunkKey = $httpVars["file_id"];
                $sessData = $_SESSION[$chunkKey];
                $realFile = $sessData["file"];
                $chunkSize = $sessData["chunk_size"];
                $offset = $chunkSize * $chunkIndex;
                if ($chunkIndex == $sessData["chunk_count"]-1) {
                    // Compute the last chunk real length
                    $chunkSize = $sessData["total_size"] - ($chunkSize * ($sessData["chunk_count"]-1));
                    if (call_user_func(array($this->wrapperClassName, "isRemote"))) {
                        register_shutdown_function("unlink", $realFile);
                    }
                }
                $this->readFile($realFile, "force-download", $sessData["localname"].".".sprintf("%03d", $chunkIndex+1), false, false, true, $offset, $chunkSize);


            break;

            case "compress" :
                    // Make a temp zip and send it as download
                    $loggedUser = AuthService::getLoggedUser();
                    if (isSet($httpVars["archive_name"])) {
                        $localName = AJXP_Utils::decodeSecureMagic($httpVars["archive_name"]);
                        $this->filterUserSelectionToHidden(array($localName));
                    } else {
                        $localName = (basename($dir)==""?"Files":basename($dir)).".zip";
                    }
                    $file = AJXP_Utils::getAjxpTmpDir()."/".($loggedUser?$loggedUser->getId():"shared")."_".time()."tmpCompression.zip";
                    if(isSet($httpVars["compress_flat"])) $baseDir = "__AJXP_ZIP_FLAT__/";
                    else $baseDir = $dir;
                    $zipFile = $this->makeZip($selection->getFiles(), $file, $baseDir);
                    if(!$zipFile) throw new AJXP_Exception("Error while compressing file $localName");
                    register_shutdown_function("unlink", $file);
                    $tmpFNAME = $this->urlBase.$dir."/".str_replace(".zip", ".tmp", $localName);
                    copy($file, $tmpFNAME);
                    try {
                        AJXP_Controller::applyHook("node.before_create", array(new AJXP_Node($tmpFNAME), filesize($tmpFNAME)));
                    } catch (Exception $e) {
                        @unlink($tmpFNAME);
                        throw $e;
                    }
                    @rename($tmpFNAME, $this->urlBase.$dir."/".$localName);
                    AJXP_Controller::applyHook("node.change", array(null, new AJXP_Node($this->urlBase.$dir."/".$localName), false));
                    //$reloadContextNode = true;
                    //$pendingSelection = $localName;
                    $newNode = new AJXP_Node($this->urlBase.$dir."/".$localName);
                    if(!isset($nodesDiffs)) $nodesDiffs = $this->getNodesDiffArray();
                    $nodesDiffs["ADD"][] = $newNode;
            break;

            case "stat" :
                clearstatcache();
                header("Content-type:application/json");
                if($selection->isUnique()){
                    $stat = @stat($this->urlBase.$selection->getUniqueFile());
                    if (!$stat) {
                        print '{}';
                    } else {
                        print json_encode($stat);
                    }
                }else{
                    $files = $selection->getFiles();
                    print '{';
                    foreach($files as $index => $path){
                        $stat = @stat($this->urlBase.$path);
                        if(!$stat) $stat = '{}';
                        else $stat = json_encode($stat);
                        print json_encode($path).':'.$stat . (($index < count($files) -1) ? "," : "");
                    }
                    print '}';
                }

            break;


            //------------------------------------
            //	ONLINE EDIT
            //------------------------------------
            case "get_content":

                $dlFile = $this->urlBase.$selection->getUniqueFile();
                $this->logInfo("Get_content", array("files"=>$this->addSlugToPath($selection)));
                if (AJXP_Utils::getStreamingMimeType(basename($dlFile))!==false) {
                    $this->readFile($this->urlBase.$selection->getUniqueFile(), "stream_content");
                } else {
                    $this->readFile($this->urlBase.$selection->getUniqueFile(), "plain");
                }
                $node = $selection->getUniqueNode($this);
                AJXP_Controller::applyHook("node.read", array(&$node));

                break;

            case "put_content":
                if(!isset($httpVars["content"])) break;
                // Load "code" variable directly from POST array, do not "securePath" or "sanitize"...
                $code = $httpVars["content"];
                $file = $selection->getUniqueFile();
                $this->logInfo("Online Edition", array("file"=>$this->addSlugToPath($file)));
                if (isSet($httpVars["encode"]) && $httpVars["encode"] == "base64") {
                    $code = base64_decode($code);
                } else {
                    $code=str_replace("&lt;","<",SystemTextEncoding::magicDequote($code));
                }
                $fileName = $this->urlBase.$file;
                $currentNode = new AJXP_Node($fileName);
                try {
                    AJXP_Controller::applyHook("node.before_change", array(&$currentNode, strlen($code)));
                } catch (Exception $e) {
                    header("Content-Type:text/plain");
                    print $e->getMessage();
                    return;
                }
                if (!is_file($fileName) || !$this->isWriteable($fileName, "file")) {
                    header("Content-Type:text/plain");
                    print((!$this->isWriteable($fileName, "file")?"1001":"1002"));
                    return ;
                }
                $fp=fopen($fileName,"w");
                fputs ($fp,$code);
                fclose($fp);
                clearstatcache(true, $fileName);
                AJXP_Controller::applyHook("node.change", array($currentNode, $currentNode, false));
                header("Content-Type:text/plain");
                print($mess[115]);

            break;

            //------------------------------------
            //	COPY / MOVE
            //------------------------------------
            case "copy";
            case "move";

                if ($selection->isEmpty()) {
                    throw new AJXP_Exception("", 113);
                }
                $loggedUser = AuthService::getLoggedUser();
                if($loggedUser != null && !$loggedUser->canWrite(ConfService::getCurrentRepositoryId())){
                    throw new AJXP_Exception("You are not allowed to write", 207);
                }
                $success = $error = array();
                $dest = AJXP_Utils::decodeSecureMagic($httpVars["dest"]);
                $this->filterUserSelectionToHidden(array($httpVars["dest"]));
                if ($selection->inZip()) {
                    // Set action to copy anycase (cannot move from the zip).
                    $action = "copy";
                    $this->extractArchive($dest, $selection, $error, $success);
                } else {
                    $move = ($action == "move" ? true : false);
                    if ($move && isSet($httpVars["force_copy_delete"])) {
                        $move = false;
                    }
                    $this->copyOrMove($dest, $selection->getFiles(), $error, $success, $move);

                }

                if (count($error)) {
                    throw new AJXP_Exception(SystemTextEncoding::toUTF8(join("\n", $error)));
                } else {
                    if (isSet($httpVars["force_copy_delete"])) {
                        $errorMessage = $this->delete($selection->getFiles(), $logMessages);
                        if($errorMessage) throw new AJXP_Exception(SystemTextEncoding::toUTF8($errorMessage));
                        $this->logInfo("Copy/Delete", array("files"=>$this->addSlugToPath($selection), "destination" => $this->addSlugToPath($dest)));
                    } else {
                        $this->logInfo(($action=="move"?"Move":"Copy"), array("files"=>$this->addSlugToPath($selection), "destination"=>$this->addSlugToPath($dest)));
                    }
                    $logMessage = join("\n", $success);
                }
                if(!isSet($nodesDiffs)) $nodesDiffs = $this->getNodesDiffArray();
                // Assume new nodes are correctly created
                $selectedItems = $selection->getFiles();
                foreach ($selectedItems as $selectedPath) {
                    $newPath = $this->urlBase.$dest ."/". basename($selectedPath);
                    $newNode = new AJXP_Node($newPath);
                    $nodesDiffs["ADD"][] = $newNode;
                    if($action == "move") $nodesDiffs["REMOVE"][] = $selectedPath;
                }
                if (!(RecycleBinManager::getRelativeRecycle() ==$dest && $this->getFilteredOption("HIDE_RECYCLE", $this->repository->getId()) == true)) {
                    //$reloadDataNode = $dest;
                }

            break;

            //------------------------------------
            //	DELETE
            //------------------------------------
            case "delete";

                if ($selection->isEmpty()) {
                    throw new AJXP_Exception("", 113);
                }
                $logMessages = array();
                $errorMessage = $this->delete($selection->getFiles(), $logMessages);
                if (count($logMessages)) {
                    $logMessage = join("\n", $logMessages);
                }
                if($errorMessage) throw new AJXP_Exception(SystemTextEncoding::toUTF8($errorMessage));
                $this->logInfo("Delete", array("files"=>$this->addSlugToPath($selection)));
                if(!isSet($nodesDiffs)) $nodesDiffs = $this->getNodesDiffArray();
                $nodesDiffs["REMOVE"] = array_merge($nodesDiffs["REMOVE"], $selection->getFiles());

            break;


            case "purge" :


                $hardPurgeTime = intval($this->repository->getOption("PURGE_AFTER"))*3600*24;
                $softPurgeTime = intval($this->repository->getOption("PURGE_AFTER_SOFT"))*3600*24;
                $shareCenter = AJXP_PluginsService::findPluginById('action.share');
                if( !($shareCenter && $shareCenter->isEnabled()) ) {
                    //action.share is disabled, don't look at the softPurgeTime
                    $softPurgeTime = 0;
                }
                if ($hardPurgeTime > 0 || $softPurgeTime > 0) {
                    $this->recursivePurge($this->urlBase, $hardPurgeTime, $softPurgeTime);
                }

            break;

            //------------------------------------
            //	RENAME
            //------------------------------------
            case "rename";

                $file = $selection->getUniqueFile();
                $filename_new = AJXP_Utils::decodeSecureMagic($httpVars["filename_new"]);
                $dest = null;
                if (isSet($httpVars["dest"])) {
                    $dest = AJXP_Utils::decodeSecureMagic($httpVars["dest"]);
                    $filename_new = "";
                }
                $this->filterUserSelectionToHidden(array($filename_new));
                $this->rename($file, $filename_new, $dest);
                $logMessage= SystemTextEncoding::toUTF8($file)." $mess[41] ".SystemTextEncoding::toUTF8($filename_new);
                //$reloadContextNode = true;
                //$pendingSelection = $filename_new;
                if(!isSet($nodesDiffs)) $nodesDiffs = $this->getNodesDiffArray();
                if($dest == null) $dest = AJXP_Utils::safeDirname($file);
                $nodesDiffs["UPDATE"][$file] = new AJXP_Node($this->urlBase.$dest."/".$filename_new);
                $this->logInfo("Rename", array("original"=>$this->addSlugToPath($file), "new"=>$filename_new));

            break;

            //------------------------------------
            //	CREER UN REPERTOIRE / CREATE DIR
            //------------------------------------
            case "mkdir";

                $messtmp="";
                $files = $selection->getFiles();
                if(isSet($httpVars["dirname"])){
                    $files[] = $dir ."/". AJXP_Utils::decodeSecureMagic($httpVars["dirname"], AJXP_SANITIZE_FILENAME);
                }
                if(!isSet($nodesDiffs)) $nodesDiffs = $this->getNodesDiffArray();
                $messages = array();
                $errors = array();
                $max_length = ConfService::getCoreConf("NODENAME_MAX_LENGTH");
                foreach($files as $newDirPath){
                    $parentDir = AJXP_Utils::safeDirname($newDirPath);
                    $basename = AJXP_Utils::safeBasename($newDirPath);
                    $basename = substr($basename, 0, $max_length);
                    $this->filterUserSelectionToHidden(array($basename));
                    try{
                        AJXP_Controller::applyHook("node.before_create", array(new AJXP_Node($parentDir."/".$basename), -2));
                    }catch (AJXP_Exception $e){
                        $errors[] = $e->getMessage();
                        continue;
                    }
                    $error = $this->mkDir($parentDir, $basename, isSet($httpVars["ignore_exists"])?true:false);
                    if (isSet($error)) {
                        //throw new AJXP_Exception($error);
                        $errors[] = $error;
                        continue;
                    }
                    $messtmp.="$mess[38] ".SystemTextEncoding::toUTF8($basename)." $mess[39] ";
                    if ($parentDir=="") {$messtmp.="/";} else {$messtmp.= SystemTextEncoding::toUTF8($parentDir);}
                    $messages[] = $messtmp;
                    $newNode = new AJXP_Node($this->urlBase.$parentDir."/".$basename);
                    array_push($nodesDiffs["ADD"], $newNode);
                    $this->logInfo("Create Dir", array("dir"=>$this->addSlugToPath($parentDir)."/".$basename));
                }
                if(count($errors)){
                    if(!count($messages)){
                        throw new AJXP_Exception(implode('', $errors));
                    }else{
                        $errorMessage = implode("<br>", $errors);
                    }
                }
                $logMessage = implode("<br>", $messages);


            break;

            //------------------------------------
            //	CREER UN FICHIER / CREATE FILE
            //------------------------------------
            case "mkfile";

                $messtmp="";
                if(empty($httpVars["filename"]) && isSet($httpVars["node"])){
                    $filename=AJXP_Utils::decodeSecureMagic($httpVars["node"]);
                }else{
                    $filename=AJXP_Utils::decodeSecureMagic($httpVars["filename"], AJXP_SANITIZE_FILENAME);
                }
                $filename = substr($filename, 0, ConfService::getCoreConf("NODENAME_MAX_LENGTH"));
                $this->filterUserSelectionToHidden(array($filename));
                $content = "";
                if (isSet($httpVars["content"])) {
                    $content = $httpVars["content"];
                }
                $forceCreation = false;
                if (isSet($httpVars["force"]) && $httpVars["force"] == "true"){
                    $forceCreation = true;
                }
                $error = $this->createEmptyFile($dir, $filename, $content, $forceCreation);
                if (isSet($error)) {
                    throw new AJXP_Exception($error);
                }
                $messtmp.="$mess[34] ".SystemTextEncoding::toUTF8($filename)." $mess[39] ";
                if ($dir=="") {$messtmp.="/";} else {$messtmp.=SystemTextEncoding::toUTF8($dir);}
                $logMessage = $messtmp;
                //$reloadContextNode = true;
                //$pendingSelection = $dir."/".$filename;
                $this->logInfo("Create File", array("file"=>$this->addSlugToPath($dir)."/".$filename));
                $newNode = new AJXP_Node($this->urlBase.$dir."/".$filename);
                if(!isSet($nodesDiffs)) $nodesDiffs = $this->getNodesDiffArray();
                array_push($nodesDiffs["ADD"], $newNode);

            break;

            //------------------------------------
            //	CHANGE FILE PERMISSION
            //------------------------------------
            case "chmod";

                $files = $selection->getFiles();
                $changedFiles = array();
                $chmod_value = $httpVars["chmod_value"];
                $recursive = $httpVars["recursive"];
                $recur_apply_to = $httpVars["recur_apply_to"];
                foreach ($files as $fileName) {
                    $this->chmod($fileName, $chmod_value, ($recursive=="on"), ($recursive=="on"?$recur_apply_to:"both"), $changedFiles);
                }
                $logMessage="Successfully changed permission to ".$chmod_value." for ".count($changedFiles)." files or folders";
                $this->logInfo("Chmod", array("dir"=>$this->addSlugToPath($dir), "filesCount"=>count($changedFiles)));
                if(!isSet($nodesDiffs)) $nodesDiffs = $this->getNodesDiffArray();
                $nodesDiffs["UPDATE"] = array_merge($nodesDiffs["UPDATE"], $selection->buildNodes($this));

            break;

            //------------------------------------
            //	UPLOAD
            //------------------------------------
            case "upload":

                $repoData = array(
                    'base_url' => $this->urlBase,
                    'wrapper_name' => $this->wrapperClassName,
                    'chmod'     => $this->repository->getOption('CHMOD_VALUE'),
                    'recycle'     => $this->repository->getOption('RECYCLE_BIN')
                );
                $this->logDebug("Upload Files Data", $fileVars);
                $destination=$this->urlBase.AJXP_Utils::decodeSecureMagic($dir);
                $this->logDebug("Upload inside", array("destination"=>$this->addSlugToPath($destination)));
                if (!$this->isWriteable($destination)) {
                    $errorCode = 412;
                    $errorMessage = "$mess[38] ".SystemTextEncoding::toUTF8($dir)." $mess[99].";
                    $this->logDebug("Upload error 412", array("destination"=>$this->addSlugToPath($destination)));
                    return array("ERROR" => array("CODE" => $errorCode, "MESSAGE" => $errorMessage));
                }

                $partialUpload = false;
                $partialTargetSize = -1;
                $originalAppendTo = "";
                $createdNode = null;

                foreach ($fileVars as $boxName => $boxData) {
                    if(substr($boxName, 0, 9) != "userfile_") continue;

                    try{
                        // CHECK PHP UPLOAD ERRORS
                        AJXP_Utils::parseFileDataErrors($boxData, true);

                        // FIND PROPER FILE NAME
                        $userfile_name=AJXP_Utils::sanitize(SystemTextEncoding::fromPostedFileName($boxData["name"]), AJXP_SANITIZE_FILENAME);
                        if (isSet($httpVars["urlencoded_filename"])) {
                            $userfile_name = AJXP_Utils::sanitize(SystemTextEncoding::fromUTF8(urldecode($httpVars["urlencoded_filename"])), AJXP_SANITIZE_FILENAME);
                        }
                        $userfile_name = substr($userfile_name, 0, ConfService::getCoreConf("NODENAME_MAX_LENGTH"));
                        if (isSet($httpVars["auto_rename"])) {
                            $userfile_name = self::autoRenameForDest($destination, $userfile_name);
                        }
                        $this->logDebug("User filename ".$userfile_name);

                        // CHECK IF THIS IS A FORBIDDEN FILENAME
                        $this->filterUserSelectionToHidden(array($userfile_name));

                        // APPLY PRE-UPLOAD HOOKS
                        $already_existed = false;
                        try {
                            if (file_exists($destination."/".$userfile_name)) {
                                $already_existed = true;
                                AJXP_Controller::applyHook("node.before_change", array(new AJXP_Node($destination."/".$userfile_name), $boxData["size"]));
                            } else {
                                AJXP_Controller::applyHook("node.before_create", array(new AJXP_Node($destination."/".$userfile_name), $boxData["size"]));
                            }
                            AJXP_Controller::applyHook("node.before_change", array(new AJXP_Node($destination)));
                        } catch (Exception $e) {
                            throw new Exception($e->getMessage(), 507);
                        }

                        // PARTIAL UPLOAD CASE - PREPPEND .dlpart extension
                        if(isSet($httpVars["partial_upload"]) && $httpVars["partial_upload"] == 'true' && isSet($httpVars["partial_target_bytesize"])){
                            $partialUpload = true;
                            $partialTargetSize = intval($httpVars["partial_target_bytesize"]);
                            if(!isSet($httpVars["appendto_urlencoded_part"])){
                                $userfile_name .= ".dlpart";
                            }
                        }

                        // NOW DO THE ACTUAL COPY
                        $this->copyUploadedData($boxData, $destination, $userfile_name, $mess);

                        // PARTIAL UPLOAD - PART II: APPEND DATA TO EXISTING PART
                        if (isSet($httpVars["appendto_urlencoded_part"])) {
                            $appendTo = AJXP_Utils::sanitize(SystemTextEncoding::fromUTF8(urldecode($httpVars["appendto_urlencoded_part"])), AJXP_SANITIZE_FILENAME);
                            if(isSet($httpVars["partial_upload"]) && $httpVars["partial_upload"] == 'true'){
                                $originalAppendTo = $appendTo;
                                $appendTo .= ".dlpart";
                            }
                            $this->logDebug("AppendTo FILE".$appendTo);
                            $already_existed = $this->appendUploadedData($destination, $userfile_name, $appendTo);
                            $userfile_name = $appendTo;
                            if($partialUpload && $partialTargetSize == filesize($destination."/".$userfile_name)){
                                // This was the last part. We can now rename to the original name.
                                if(is_file($destination."/".$originalAppendTo)){
                                    unlink($destination."/".$originalAppendTo);
                                }
                                $result = @rename($destination."/".$userfile_name, $destination."/".$originalAppendTo);
                                if($result === false){
                                    throw new Exception("Error renaming ".$destination."/".$userfile_name." to ".$destination."/".$originalAppendTo);
                                }
                                $userfile_name = $originalAppendTo;
                                $partialUpload = false;
                                // Send a create event!
                                $already_existed = false;
                            }
                        }

                        // NOW PREPARE POST-UPLOAD EVENTS
                        $this->changeMode($destination."/".$userfile_name,$repoData);
                        $createdNode = new AJXP_Node($destination."/".$userfile_name);
                        clearstatcache(true, $createdNode->getUrl());
                        $createdNode->loadNodeInfo(true);
                        $logMessage.="$mess[34] ".SystemTextEncoding::toUTF8($userfile_name)." $mess[35] $dir";
                        $this->logInfo("Upload File", array("file"=>$this->addSlugToPath(SystemTextEncoding::fromUTF8($dir))."/".$userfile_name));

                        if($partialUpload){
                            $this->logDebug("Return Partial Upload: SUCESS but no event yet");
                            if(isSet($already_existed) && $already_existed === true){
                                return array("SUCCESS" => true, "PARTIAL_NODE" => $createdNode);
                            }
                        } else {
                            $this->logDebug("Return success");
                            if(isSet($already_existed) && $already_existed === true){
                                return array("SUCCESS" => true, "UPDATED_NODE" => $createdNode);
                            }else{
                                return array("SUCCESS" => true, "CREATED_NODE" => $createdNode);
                            }
                        }

                    }catch(Exception $e){
                        $errorCode = $e->getCode();
                        if(empty($errorCode)) $errorCode = 411;
                        return array("ERROR" => array("CODE" => $errorCode, "MESSAGE" => $e->getMessage()));
                    }

                }


            break;

            case "lsync" :

                if (!ConfService::currentContextIsCommandLine()) {
                    //die("This command must be accessed via CLI only.");
                }
                $fromNode = null;
                $toNode = null;
                $copyOrMove = false;
                if (isSet($httpVars["from"])) {
                    $fromNode = new AJXP_Node($this->urlBase.AJXP_Utils::decodeSecureMagic($httpVars["from"]));
                }
                if (isSet($httpVars["to"])) {
                    $toNode = new AJXP_Node($this->urlBase.AJXP_Utils::decodeSecureMagic($httpVars["to"]));
                }
                if (isSet($httpVars["copy"]) && $httpVars["copy"] == "true") {
                    $copyOrMove = true;
                }
                AJXP_Controller::applyHook("node.change", array($fromNode, $toNode, $copyOrMove));

            break;

            //------------------------------------
            //	XML LISTING
            //------------------------------------
            case "ls":

                if(!isSet($dir) || $dir == "/") $dir = "";
                $lsOptions = $this->parseLsOptions((isSet($httpVars["options"])?$httpVars["options"]:"a"));

                $startTime = microtime();
                $dir = AJXP_Utils::securePath($dir);
                $path = $this->urlBase.($dir!= ""?($dir[0]=="/"?"":"/").$dir:"");
                $nonPatchedPath = $path;
                if ($this->wrapperClassName == "fsAccessWrapper") {
                    $nonPatchedPath = fsAccessWrapper::unPatchPathForBaseDir($path);
                }
                // Backward compat
                if($selection->isUnique() && strpos($selection->getUniqueFile(), "/") !== 0){
                    $selection->setFiles(array($dir . "/" . $selection->getUniqueFile()));
                }
                if(!$selection->isEmpty()){
                    $uniqueNodes = $selection->buildNodes($this->repository->driverInstance);
                    $parentAjxpNode = new AJXP_Node($this->urlBase."/", array());
                    AJXP_Controller::applyHook("node.read", array(&$parentAjxpNode));
                    if (AJXP_XMLWriter::$headerSent == "tree") {
                        AJXP_XMLWriter::renderAjxpNode($parentAjxpNode, false);
                    } else {
                        AJXP_XMLWriter::renderAjxpHeaderNode($parentAjxpNode);
                    }
                    foreach($uniqueNodes as $node){
                        if(!file_exists($node->getUrl())) continue;
                        $nodeName = $node->getLabel();
                        if (!$this->filterNodeName($node->getPath(), $nodeName, $isLeaf, $lsOptions)) {
                            continue;
                        }
                        if (RecycleBinManager::recycleEnabled() && $node->getPath() == RecycleBinManager::getRecyclePath()) {
                            continue;
                        }
                        $node->loadNodeInfo(false, false, ($lsOptions["l"]?"all":"minimal"));
                        if (!empty($node->metaData["nodeName"]) && $node->metaData["nodeName"] != $nodeName) {
                            $node->setUrl(dirname($node->getUrl())."/".$node->metaData["nodeName"]);
                        }
                        if (!empty($node->metaData["hidden"]) && $node->metaData["hidden"] === true) {
                            continue;
                        }
                        if (!empty($node->metaData["mimestring_id"]) && array_key_exists($node->metaData["mimestring_id"], $mess)) {
                            $node->mergeMetadata(array("mimestring" =>  $mess[$node->metaData["mimestring_id"]]));
                        }
                        if($this->repository->hasContentFilter()){
                            $externalPath = $this->repository->getContentFilter()->externalPath($node);
                            $node->setUrl($this->urlBase.$externalPath);
                        }
                        AJXP_XMLWriter::renderAjxpNode($node);
                    }
                    AJXP_XMLWriter::close();
                    break;
                }/*else if (!$selection->isEmpty() && $selection->isUnique()){
                    $uniqueFile = $selection->getUniqueFile();
                }*/

                if ($this->getFilteredOption("REMOTE_SORTING")) {
                    $orderDirection = isSet($httpVars["order_direction"])?strtolower($httpVars["order_direction"]):"asc";
                    $orderField = isSet($httpVars["order_column"])?$httpVars["order_column"]:null;
                    if ($orderField != null && !in_array($orderField, array("ajxp_label", "filesize", "ajxp_modiftime", "mimestring"))) {
                        $orderField = "ajxp_label";
                    }
                }
                if(isSet($httpVars["recursive"]) && $httpVars["recursive"] == "true"){
                    $max_depth = (isSet($httpVars["max_depth"])?intval($httpVars["max_depth"]):0);
                    $max_nodes = (isSet($httpVars["max_nodes"])?intval($httpVars["max_nodes"]):0);
                    $crt_depth = (isSet($httpVars["crt_depth"])?intval($httpVars["crt_depth"])+1:1);
                    $crt_nodes = (isSet($httpVars["crt_nodes"])?intval($httpVars["crt_nodes"]):0);
                }else{
                    $threshold = $this->repository->getOption("PAGINATION_THRESHOLD");
                    if(!isSet($threshold) || intval($threshold) == 0) $threshold = 500;
                    $limitPerPage = $this->repository->getOption("PAGINATION_NUMBER");
                    if(!isset($limitPerPage) || intval($limitPerPage) == 0) $limitPerPage = 200;
                }

                $countFiles = $this->countFiles($path, !$lsOptions["f"]);
                if(isSet($crt_nodes)){
                    $crt_nodes += $countFiles;
                }
                if (isSet($threshold) && isSet($limitPerPage) && $countFiles > $threshold) {
                    if (isSet($uniqueFile)) {
                        $originalLimitPerPage = $limitPerPage;
                        $offset = $limitPerPage = 0;
                    } else {
                        $offset = 0;
                        $crtPage = 1;
                        if (isSet($page)) {
                            $offset = (intval($page)-1)*$limitPerPage;
                            $crtPage = $page;
                        }
                        $totalPages = floor($countFiles / $limitPerPage) + 1;
                    }
                } else {
                    $offset = $limitPerPage = 0;
                }

                $metaData = array();
                if (RecycleBinManager::recycleEnabled() && $dir == "") {
                    $metaData["repo_has_recycle"] = "true";
                }
                $parentAjxpNode = new AJXP_Node($nonPatchedPath, $metaData);
                $parentAjxpNode->loadNodeInfo(false, true, ($lsOptions["l"]?"all":"minimal"));
                AJXP_Controller::applyHook("node.read", array(&$parentAjxpNode));
                if (AJXP_XMLWriter::$headerSent == "tree") {
                    AJXP_XMLWriter::renderAjxpNode($parentAjxpNode, false);
                } else {
                    AJXP_XMLWriter::renderAjxpHeaderNode($parentAjxpNode);
                }
                if (isSet($totalPages) && isSet($crtPage)) {
                    $remoteOptions = null;
                    if ($this->getFilteredOption("REMOTE_SORTING")) {
                        $remoteOptions = array(
                            "remote_order" => "true",
                            "currentOrderCol" => isSet($orderField)?$orderField:"ajxp_label",
                            "currentOrderDir"=> isSet($orderDirection)?$orderDirection:"asc"
                        );
                    }
                    AJXP_XMLWriter::renderPaginationData(
                        $countFiles,
                        $crtPage,
                        $totalPages,
                        $this->countFiles($path, TRUE),
                        $remoteOptions
                    );
                    if (!$lsOptions["f"]) {
                        AJXP_XMLWriter::close();
                        exit(1);
                    }
                }

                $cursor = 0;
                $handle = opendir($path);
                if (!$handle) {
                    throw new AJXP_Exception("Cannot open dir ".$nonPatchedPath);
                }
                closedir($handle);
                $fullList = array("d" => array(), "z" => array(), "f" => array());

                $nodes = scandir($path);
                usort($nodes, "strcasecmp");
                if (isSet($orderField) && isSet($orderDirection) && $orderField == "ajxp_label" && $orderDirection == "desc") {
                    $nodes = array_reverse($nodes);
                }
                if (!empty($this->driverConf["SCANDIR_RESULT_SORTFONC"])) {
                    usort($nodes, $this->driverConf["SCANDIR_RESULT_SORTFONC"]);
                }
                if (isSet($orderField) && isSet($orderDirection) && $orderField != "ajxp_label") {
                    $toSort = array();
                    foreach ($nodes as $node) {
                        if($orderField == "filesize") $toSort[$node] = is_file($nonPatchedPath."/".$node) ? $this->filesystemFileSize($nonPatchedPath."/".$node) : 0;
                        else if($orderField == "ajxp_modiftime") $toSort[$node] = filemtime($nonPatchedPath."/".$node);
                        else if($orderField == "mimestring") $toSort[$node] = pathinfo($node, PATHINFO_EXTENSION);
                    }
                    if($orderDirection == "asc") asort($toSort);
                    else arsort($toSort);
                    $nodes = array_keys($toSort);
                }
                //while (strlen($nodeName = readdir($handle)) > 0) {
                foreach ($nodes as $nodeName) {
                    if($nodeName == "." || $nodeName == "..") continue;
                    if (isSet($uniqueFile) && $nodeName != $uniqueFile) {
                        $cursor ++;
                        continue;
                    }
                    if ($offset > 0 && $cursor < $offset) {
                        $cursor ++;
                        continue;
                    }
                    $isLeaf = "";
                    if (!$this->filterNodeName($path, $nodeName, $isLeaf, $lsOptions)) {
                        continue;
                    }
                    if (RecycleBinManager::recycleEnabled() && $dir == "" && "/".$nodeName == RecycleBinManager::getRecyclePath()) {
                        continue;
                    }

                    if ($limitPerPage > 0 && ($cursor - $offset) >= $limitPerPage) {
                        break;
                    }

                    $currentFile = $nonPatchedPath."/".$nodeName;
                    $meta = array();
                    if($isLeaf != "") $meta = array("is_file" => ($isLeaf?"1":"0"));
                    $node = new AJXP_Node($currentFile, $meta);
                    $node->setLabel($nodeName);
                    $node->loadNodeInfo(false, false, ($lsOptions["l"]?"all":"minimal"));
                    if (!empty($node->metaData["nodeName"]) && $node->metaData["nodeName"] != $nodeName) {
                        $node->setUrl($nonPatchedPath."/".$node->metaData["nodeName"]);
                    }
                    if (!empty($node->metaData["hidden"]) && $node->metaData["hidden"] === true) {
                           continue;
                       }
                    if (!empty($node->metaData["mimestring_id"]) && array_key_exists($node->metaData["mimestring_id"], $mess)) {
                        $node->mergeMetadata(array("mimestring" =>  $mess[$node->metaData["mimestring_id"]]));
                    }
                    if (isSet($originalLimitPerPage) && $cursor > $originalLimitPerPage) {
                        $node->mergeMetadata(array("page_position" => floor($cursor / $originalLimitPerPage) +1));
                    }

                    $nodeType = "d";
                    if ($node->isLeaf()) {
                        if (AJXP_Utils::isBrowsableArchive($nodeName)) {
                            if ($lsOptions["f"] && $lsOptions["z"]) {
                                $nodeType = "f";
                            } else {
                                $nodeType = "z";
                            }
                        } else $nodeType = "f";
                    }
                    // There is a special sorting, cancel the reordering of files & folders.
                    if(isSet($orderField) && $orderField != "ajxp_label") $nodeType = "f";

                    if($this->repository->hasContentFilter()){
                        $externalPath = $this->repository->getContentFilter()->externalPath($node);
                        $node->setUrl($this->urlBase.$externalPath);
                    }

                    $fullList[$nodeType][$nodeName] = $node;
                    $cursor ++;
                    if (isSet($uniqueFile) && $nodeName != $uniqueFile) {
                        break;
                    }
                }
                if (isSet($httpVars["recursive"]) && $httpVars["recursive"] == "true") {
                    $breakNow = false;
                    if(isSet($max_depth) && $max_depth > 0 && $crt_depth >= $max_depth) $breakNow = true;
                    if(isSet($max_nodes) && $max_nodes > 0 && $crt_nodes >= $max_nodes) $breakNow = true;
                    foreach ($fullList["d"] as &$nodeDir) {
                        if($breakNow){
                            $nodeDir->mergeMetadata(array("ajxp_has_children" => $this->countFiles($nodeDir->getUrl(), false, true)?"true":"false"));
                            AJXP_XMLWriter::renderAjxpNode($nodeDir, true);
                            continue;
                        }
                        $this->switchAction("ls", array(
                            "dir" => SystemTextEncoding::toUTF8($nodeDir->getPath()),
                            "options"=> $httpVars["options"],
                            "recursive" => "true",
                            "max_depth"=> $max_depth,
                            "max_nodes"=> $max_nodes,
                            "crt_depth"=> $crt_depth,
                            "crt_nodes"=> $crt_nodes,
                        ), array());
                    }
                } else {
                    array_map(array("AJXP_XMLWriter", "renderAjxpNode"), $fullList["d"]);
                }
                array_map(array("AJXP_XMLWriter", "renderAjxpNode"), $fullList["z"]);
                array_map(array("AJXP_XMLWriter", "renderAjxpNode"), $fullList["f"]);

                // ADD RECYCLE BIN TO THE LIST
                if ($dir == ""  && !$uniqueFile && RecycleBinManager::recycleEnabled() && $this->getFilteredOption("HIDE_RECYCLE", $this->repository->getId()) !== true) {
                    $recycleBinOption = RecycleBinManager::getRelativeRecycle();
                    if (file_exists($this->urlBase.$recycleBinOption)) {
                        $recycleNode = new AJXP_Node($this->urlBase.$recycleBinOption);
                        $recycleNode->loadNodeInfo();
                        AJXP_XMLWriter::renderAjxpNode($recycleNode);
                    }
                }

                $this->logDebug("LS Time : ".intval((microtime()-$startTime)*1000)."ms");

                AJXP_XMLWriter::close();

            break;
        }


        $xmlBuffer = "";
        if (isset($logMessage) || isset($errorMessage)) {
            $xmlBuffer .= AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null), false);
        }
        if ($reloadContextNode) {
            if(!isSet($pendingSelection)) $pendingSelection = "";
            $xmlBuffer .= AJXP_XMLWriter::reloadDataNode("", $pendingSelection, false);
        }
        if (isSet($reloadDataNode)) {
            $xmlBuffer .= AJXP_XMLWriter::reloadDataNode($reloadDataNode, "", false);
        }
        if (isSet($nodesDiffs)) {
            $xmlBuffer .= AJXP_XMLWriter::writeNodesDiff($nodesDiffs, false);
        }

        return $xmlBuffer;
    }

    public function parseLsOptions($optionString)
    {
        // LS OPTIONS : dz , a, d, z, all of these with or without l
        // d : directories
        // z : archives
        // f : files
        // => a : all, alias to dzf
        // l : list metadata
        $allowed = array("a", "d", "z", "f", "l");
        $lsOptions = array();
        foreach ($allowed as $key) {
            if (strchr($optionString, $key)!==false) {
                $lsOptions[$key] = true;
            } else {
                $lsOptions[$key] = false;
            }
        }
        if ($lsOptions["a"]) {
            $lsOptions["d"] = $lsOptions["z"] = $lsOptions["f"] = true;
        }
        return $lsOptions;
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @param bool $parentNode
     * @param bool $details
     * @return void
     */
    public function loadNodeInfo(&$ajxpNode, $parentNode = false, $details = false)
    {
        $mess = ConfService::getMessages();

        $nodeName = basename($ajxpNode->getPath());
        $metaData = $ajxpNode->metadata;
        if (!isSet($metaData["is_file"])) {
            $isLeaf = is_file($ajxpNode->getUrl()) || AJXP_Utils::isBrowsableArchive($nodeName);
            $metaData["is_file"] = ($isLeaf?"1":"0");
        } else {
            $isLeaf = $metaData["is_file"] == "1" ? true : false;
        }
        $metaData["filename"] = $ajxpNode->getPath();

        if (RecycleBinManager::recycleEnabled() && $ajxpNode->getPath() == RecycleBinManager::getRelativeRecycle()) {
            $recycleIcon = ($this->countFiles($ajxpNode->getUrl(), false, true)>0?"trashcan_full.png":"trashcan.png");
            $metaData["icon"] = $recycleIcon;
            $metaData["mimestring"] = $mess[122];
            $ajxpNode->setLabel($mess[122]);
            $metaData["ajxp_mime"] = "ajxp_recycle";
        } else {
            $mimeData = AJXP_Utils::mimeData($ajxpNode->getUrl(), !$isLeaf);
            $metaData["mimestring_id"] = $mimeData[0]; //AJXP_Utils::mimetype($ajxpNode->getUrl(), "type", !$isLeaf);
            $metaData["icon"] = $mimeData[1]; //AJXP_Utils::mimetype($nodeName, "image", !$isLeaf);
            if ($metaData["icon"] == "folder.png") {
                $metaData["openicon"] = "folder_open.png";
            }
            if (!$isLeaf) {
                $metaData["ajxp_mime"] = "ajxp_folder";
            }
        }
        //if ($lsOptions["l"]) {

        $metaData["file_group"] = @filegroup($ajxpNode->getUrl()) || "unknown";
        $metaData["file_owner"] = @fileowner($ajxpNode->getUrl()) || "unknown";
        $crtPath = $ajxpNode->getPath();
        $vRoots = $this->repository->listVirtualRoots();
        if (!empty($crtPath)) {
            if (!@$this->isWriteable($ajxpNode->getUrl())) {
               $metaData["ajxp_readonly"] = "true";
            }
            if (isSet($vRoots[ltrim($crtPath, "/")])) {
                $metaData["ajxp_readonly"] = $vRoots[ltrim($crtPath, "/")]["right"] == "r" ? "true" : "false";
            }
        } else {
            if (count($vRoots)) {
                $metaData["ajxp_readonly"] = "true";
            }
        }
        $fPerms = @fileperms($ajxpNode->getUrl());
        if ($fPerms !== false) {
            $fPerms = substr(decoct( $fPerms ), ($isLeaf?2:1));
        } else {
            $fPerms = '0000';
        }
        $metaData["file_perms"] = $fPerms;
        $datemodif = $this->date_modif($ajxpNode->getUrl());
        $metaData["ajxp_modiftime"] = ($datemodif ? $datemodif : "0");
        $metaData["ajxp_description"] =$metaData["ajxp_relativetime"] = $mess[4]." ".AJXP_Utils::relativeDate($datemodif, $mess);
        $metaData["bytesize"] = 0;
        if ($isLeaf) {
            $metaData["bytesize"] = $this->filesystemFileSize($ajxpNode->getUrl());
        }
        $metaData["filesize"] = AJXP_Utils::roundSize($metaData["bytesize"]);
        if (AJXP_Utils::isBrowsableArchive($nodeName)) {
            $metaData["ajxp_mime"] = "ajxp_browsable_archive";
        }

        if ($details == "minimal") {
            $miniMeta = array(
                "is_file" => $metaData["is_file"],
                "filename" => $metaData["filename"],
                "bytesize" => $metaData["bytesize"],
                "ajxp_modiftime" => $metaData["ajxp_modiftime"],
            );
            $ajxpNode->mergeMetadata($miniMeta);
        } else {
            $ajxpNode->mergeMetadata($metaData);
        }

    }

    /**
     * @param Array $uploadData Php-upload array
     * @param String $destination Destination folder, including stream data
     * @param String $filename Destination filename
     * @param Array $messages Application messages table
     * @return bool
     * @throws Exception
     */
    protected function copyUploadedData($uploadData, $destination, $filename, $messages){
        if (isSet($uploadData["input_upload"])) {
            try {
                $this->logDebug("Begining reading INPUT stream");
                $input = fopen("php://input", "r");
                $output = fopen("$destination/".$filename, "w");
                $sizeRead = 0;
                while ($sizeRead < intval($uploadData["size"])) {
                    $chunk = fread($input, 4096);
                    $sizeRead += strlen($chunk);
                    fwrite($output, $chunk, strlen($chunk));
                }
                fclose($input);
                fclose($output);
                $this->logDebug("End reading INPUT stream");
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), 411);
            }
        } else {
            $result = @move_uploaded_file($uploadData["tmp_name"], "$destination/".$filename);
            if (!$result) {
                $realPath = call_user_func(array($this->wrapperClassName, "getRealFSReference"),"$destination/".$filename);
                $result = move_uploaded_file($uploadData["tmp_name"], $realPath);
            }
            if (!$result) {
                $errorMessage="$messages[33] ".$filename;
                throw new Exception($errorMessage, 411);
            }
        }
        return true;
    }

    /**
     * @param String $folder Folder destination
     * @param String $target Existing part to append data
     * @param String $source Maybe updated by the function
     * @return bool If the target file already existed or not.
     */
    protected function appendUploadedData($folder, $source, $target){

        $already_existed = false;
        if($source == $target){
            throw new Exception("Something nasty happened: trying to copy $source into itself, it will create a loop!");
        }
        if (file_exists($folder ."/" . $target)) {
            $already_existed = true;
            $this->logDebug("Should copy stream from $source to $target");
            $partO = fopen($folder."/".$source, "r");
            $appendF = fopen($folder ."/". $target, "a+");
            while (!feof($partO)) {
                $buf = fread($partO, 1024);
                fwrite($appendF, $buf, strlen($buf));
            }
            fclose($partO);
            fclose($appendF);
            $this->logDebug("Done, closing streams!");
        }
        @unlink($folder."/".$source);
        return $already_existed;

    }

    public function readFile($filePathOrData, $headerType="plain", $localName="", $data=false, $gzip=null, $realfileSystem=false, $byteOffset=-1, $byteLength=-1)
    {
        if(!$data && !$gzip && !file_exists($filePathOrData)){
            throw new Exception("File $filePathOrData not found!");
        }
        if ($gzip === null) {
            $gzip = ConfService::getCoreConf("GZIP_COMPRESSION");
        }
        if (!$realfileSystem && $this->wrapperClassName == "fsAccessWrapper") {
            $originalFilePath = $filePathOrData;
            $filePathOrData = fsAccessWrapper::patchPathForBaseDir($filePathOrData);
        }
        session_write_close();

        restore_error_handler();
        restore_exception_handler();

        set_exception_handler('download_exception_handler');
        set_error_handler('download_exception_handler');
        // required for IE, otherwise Content-disposition is ignored
        if (ini_get('zlib.output_compression')) {
         AJXP_Utils::safeIniSet('zlib.output_compression', 'Off');
        }

        $isFile = !$data && !$gzip;
        if ($byteLength == -1) {
            if ($data) {
                $size = strlen($filePathOrData);
            } else if ($realfileSystem) {
                $size = sprintf("%u", filesize($filePathOrData));
            } else {
                $size = $this->filesystemFileSize($filePathOrData);
            }
        } else {
            $size = $byteLength;
        }
        if ($gzip && ($size > ConfService::getCoreConf("GZIP_LIMIT") || !function_exists("gzencode") || @strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') === FALSE)) {
            $gzip = false; // disable gzip
        }

        $localName = ($localName=="" ? basename((isSet($originalFilePath)?$originalFilePath:$filePathOrData)) : $localName);
        if ($headerType == "plain") {
            header("Content-type:text/plain");
        } else if ($headerType == "image") {
            header("Content-Type: ".AJXP_Utils::getImageMimeType(basename($filePathOrData))."; name=\"".$localName."\"");
            header("Content-Length: ".$size);
            header('Cache-Control: public');
        } else {
            if ($isFile) {
                header("Accept-Ranges: 0-$size");
                $this->logDebug("Sending accept range 0-$size");
            }

            // Check if we have a range header (we are resuming a transfer)
            if ( isset($_SERVER['HTTP_RANGE']) && $isFile && $size != 0 ) {
                if ($headerType == "stream_content") {
                    if (extension_loaded('fileinfo')  && $this->wrapperClassName == "fsAccessWrapper") {
                        $fInfo = new fInfo( FILEINFO_MIME );
                        $realfile = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $filePathOrData);
                        $mimeType = $fInfo->file( $realfile);
                        $splitChar = explode(";", $mimeType);
                        $mimeType = trim($splitChar[0]);
                        $this->logDebug("Detected mime $mimeType for $realfile");
                    } else {
                        $mimeType = AJXP_Utils::getStreamingMimeType(basename($filePathOrData));
                    }
                    header('Content-type: '.$mimeType);
                }
                // multiple ranges, which can become pretty complex, so ignore it for now
                $ranges = explode('=', $_SERVER['HTTP_RANGE']);
                $offsets = explode('-', $ranges[1]);
                $offset = floatval($offsets[0]);

                $length = floatval($offsets[1]) - $offset;
                if (!$length) $length = $size - $offset;
                if ($length + $offset > $size || $length < 0) $length = $size - $offset;
                $this->logDebug('Content-Range: bytes ' . $offset . '-' . $length . '/' . $size);
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: bytes ' . $offset . '-' . ($offset + $length) . '/' . $size);

                header("Content-Length: ". $length);
                $file = fopen($filePathOrData, 'rb');
                if(!is_resource($file)){
                    throw new Exception("Failed opening file ".$filePathOrData);
                }
                fseek($file, 0);
                $relOffset = $offset;
                while ($relOffset > 2.0E9) {
                    // seek to the requested offset, this is 0 if it's not a partial content request
                    fseek($file, 2000000000, SEEK_CUR);
                    $relOffset -= 2000000000;
                    // This works because we never overcome the PHP 32 bit limit
                }
                fseek($file, $relOffset, SEEK_CUR);

                while(ob_get_level()) ob_end_flush();
                $readSize = 0.0;
                $bufferSize = 1024 * 8;
                while (!feof($file) && $readSize < $length && connection_status() == 0) {
                    $this->logDebug("dl reading $readSize to $length", $_SERVER["HTTP_RANGE"]);
                    echo fread($file, $bufferSize);
                    $readSize += $bufferSize;
                    flush();
                }

                fclose($file);
                return;
            } else {
                if ($gzip) {
                    $gzippedData = ($data?gzencode($filePathOrData,9):gzencode(file_get_contents($filePathOrData), 9));
                    $size = strlen($gzippedData);
                }
                HTMLWriter::generateAttachmentsHeader($localName, $size, $isFile, $gzip);
                if ($gzip) {
                    print $gzippedData;
                    return;
                }
            }
        }

        if ($data) {
            print($filePathOrData);
        } else {
            if ($this->getFilteredOption("USE_XSENDFILE", $this->repository->getId()) && $this->wrapperClassName == "fsAccessWrapper") {
                if(!$realfileSystem) $filePathOrData = fsAccessWrapper::getRealFSReference($filePathOrData);
                $filePathOrData = str_replace("\\", "/", $filePathOrData);
                $server_name = $_SERVER["SERVER_SOFTWARE"];
                $regex = '/^(lighttpd\/1.4).([0-9]{2}$|[0-9]{3}$|[0-9]{4}$)+/';
                if(preg_match($regex, $server_name))
                    $header_sendfile = "X-LIGHTTPD-send-file";
                else
                    $header_sendfile = "X-Sendfile";
                header($header_sendfile.": ".SystemTextEncoding::toUTF8($filePathOrData));
                header("Content-type: application/octet-stream");
                header('Content-Disposition: attachment; filename="' . basename($filePathOrData) . '"');
                return;
            }
    if ($this->getFilteredOption("USE_XACCELREDIRECT", $this->repository->getId()) && $this->wrapperClassName == "fsAccessWrapper" && array_key_exists("X-Accel-Mapping",$_SERVER)) {
        if(!$realfileSystem) $filePathOrData = fsAccessWrapper::getRealFSReference($filePathOrData);
        $filePathOrData = str_replace("\\", "/", $filePathOrData);
        $filePathOrData = SystemTextEncoding::toUTF8($filePathOrData);
        $mapping = explode('=',$_SERVER['X-Accel-Mapping']);
        $replacecount = 0;
        $accelfile = str_replace($mapping[0],$mapping[1],$filePathOrData,$replacecount);
        if ($replacecount == 1) {
            header("X-Accel-Redirect: $accelfile");
            header("Content-type: application/octet-stream");
            header('Content-Disposition: attachment; filename="' . basename($accelfile) . '"');
            return;
        } else {
            $this->logError("X-Accel-Redirect","Problem with X-Accel-Mapping for file $filePathOrData");
        }
    }
            $stream = fopen("php://output", "a");
            if ($realfileSystem) {
                $this->logDebug("realFS!", array("file"=>$filePathOrData));
                $fp = fopen($filePathOrData, "rb");
                if(!is_resource($fp)){
                    throw new Exception("Failed opening file ".$filePathOrData);
                }
                if ($byteOffset != -1) {
                    fseek($fp, $byteOffset);
                }
                $sentSize = 0;
                $readChunk = 4096;
                while (!feof($fp)) {
                    if ( $byteLength != -1 &&  ($sentSize + $readChunk) >= $byteLength) {
                        // compute last chunk and break after
                        $readChunk = $byteLength - $sentSize;
                        $break = true;
                    }
                     $data = fread($fp, $readChunk);
                     $dataSize = strlen($data);
                     fwrite($stream, $data, $dataSize);
                     $sentSize += $dataSize;
                     if (isSet($break)) {
                         break;
                     }
                }
                fclose($fp);
            } else {
                call_user_func(array($this->wrapperClassName, "copyFileInStream"), $filePathOrData, $stream);
            }
            fflush($stream);
            fclose($stream);
        }
    }

    public function countFiles($dirName, $foldersOnly = false, $nonEmptyCheckOnly = false)
    {
        $handle=@opendir($dirName);
        if ($handle === false) {
            throw new Exception("Error while trying to open directory ".$dirName);
        }
        if ($foldersOnly && !call_user_func(array($this->wrapperClassName, "isRemote"))) {
            closedir($handle);
            $path = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $dirName);
            $dirs = glob($path."/*", GLOB_ONLYDIR|GLOB_NOSORT);
            if($dirs === false) return 0;
            return count($dirs);
        }
        $count = 0;
        $showHiddenFiles = $this->getFilteredOption("SHOW_HIDDEN_FILES", $this->repository->getId());
        while (strlen($file = readdir($handle)) > 0) {
            if($file != "." && $file !=".."
                && !(AJXP_Utils::isHidden($file) && !$showHiddenFiles)){
                if($foldersOnly && is_file($dirName."/".$file)) continue;
                $count++;
                if($nonEmptyCheckOnly) break;
            }
        }
        closedir($handle);
        return $count;
    }

    public function date_modif($file)
    {
        $tmp = @filemtime($file) or 0;
        return $tmp;// date("d,m L Y H:i:s",$tmp);
    }

    public function filesystemFileSize($filePath)
    {
        $bytesize = "-";
        $bytesize = @filesize($filePath);
        if (method_exists($this->wrapperClassName, "getLastRealSize")) {
            $last = call_user_func(array($this->wrapperClassName, "getLastRealSize"));
            if ($last !== false) {
                $bytesize = $last;
            }
        }
        if ($bytesize < 0) {
            $bytesize = sprintf("%u", $bytesize);
        }

        return $bytesize;
    }

    public static $currentZipOperationHandler;
    public function extractArchiveItemPreCallback($status, $data){
        $fullname = $data['filename'];
        $size = $data['size'];
        $realBase = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase);
        $repoName = $this->urlBase.str_replace($realBase, "", $fullname);

        $toNode = new AJXP_Node($repoName);
        $toNode->setLeaf($data['folder'] ? false:true);
        if(file_exists($toNode->getUrl())){
            AJXP_Controller::applyHook("node.before_change", array($toNode, $size));
        }else{
            AJXP_Controller::applyHook("node.before_create", array($toNode, $size));
        }
        return 1;
    }

    public function extractArchiveItemPostCallback($status, $data){
        $fullname = $data['filename'];
        $realBase = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase);
        $repoName = str_replace($realBase, "", $fullname);
        $toNode = new AJXP_Node($this->urlBase.$repoName);
        $toNode->setLeaf($data['folder'] ? false:true);
        AJXP_Controller::applyHook("node.change", array(null, $toNode, false));
        return 1;
    }

    /**
     * Extract an archive directly inside the dest directory.
     *
     * @param string $destDir
     * @param UserSelection $selection
     * @param array $error
     * @param array $success
     */
    public function extractArchive($destDir, $selection, &$error, &$success)
    {
        require_once(AJXP_BIN_FOLDER."/pclzip.lib.php");
        $zipPath = $selection->getZipPath(true);
        $zipLocalPath = $selection->getZipLocalPath(true);
        if(strlen($zipLocalPath)>1 && $zipLocalPath[0] == "/") $zipLocalPath = substr($zipLocalPath, 1)."/";
        $files = $selection->getFiles();

        $realZipFile = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase.$zipPath);
        $archive = new PclZip($realZipFile);
        $content = $archive->listContent();
        foreach ($files as $key => $item) {// Remove path
            $item = substr($item, strlen($zipPath));
            if($item[0] == "/") $item = substr($item, 1);
            foreach ($content as $zipItem) {
                if ($zipItem["stored_filename"] == $item || $zipItem["stored_filename"] == $item."/") {
                    $files[$key] = $zipItem["stored_filename"];
                    break;
                } else {
                    unset($files[$key]);
                }
            }
        }
        $this->logDebug("Archive", $this->addSlugToPath($files));
        $realDestination = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase.$destDir);
        $this->logDebug("Extract", array($realDestination, $realZipFile, $this->addSlugToPath($files), $zipLocalPath));
        self::$currentZipOperationHandler = &$this;
        $result = $archive->extract(PCLZIP_OPT_BY_NAME,     $files,
                                    PCLZIP_OPT_PATH,        $realDestination,
                                    PCLZIP_OPT_REMOVE_PATH, $zipLocalPath,
                                    PCLZIP_CB_PRE_EXTRACT,  "staticExtractArchiveItemPreCallback",
                                    PCLZIP_CB_POST_EXTRACT, "staticExtractArchiveItemPostCallback",
                                    PCLZIP_OPT_STOP_ON_ERROR
        );
        self::$currentZipOperationHandler = null;
        if ($result <= 0) {
            $error[] = $archive->errorInfo(true);
        } else {
            $mess = ConfService::getMessages();
            $success[] = sprintf($mess[368], basename($zipPath), $destDir);
        }
    }

    public function copyOrMove($destDir, $selectedFiles, &$error, &$success, $move = false)
    {
        $this->logDebug("CopyMove", array("dest"=>$this->addSlugToPath($destDir), "selection" => $this->addSlugToPath($selectedFiles)));
        $mess = ConfService::getMessages();
        if (!$this->isWriteable($this->urlBase.$destDir)) {
            $error[] = $mess[38]." ".$destDir." ".$mess[99];
            return ;
        }
        $repoData = array(
            'base_url' => $this->urlBase,
            'wrapper_name' => $this->wrapperClassName,
            'chmod'     => $this->repository->getOption('CHMOD_VALUE'),
            'recycle'     => $this->repository->getOption('RECYCLE_BIN')
        );

        foreach ($selectedFiles as $selectedFile) {
            if ($move && !$this->isWriteable(dirname($this->urlBase.$selectedFile))) {
                $error[] = "\n".$mess[38]." ".dirname($selectedFile)." ".$mess[99];
                continue;
            }
            $this->copyOrMoveFile($destDir, $selectedFile, $error, $success, $move, $repoData, $repoData);
        }
    }

    public function rename($filePath, $filename_new, $dest = null)
    {
        $nom_fic=basename($filePath);
        $mess = ConfService::getMessages();
        $filename_new=AJXP_Utils::sanitize(SystemTextEncoding::magicDequote($filename_new), AJXP_SANITIZE_FILENAME);
        $filename_new = substr($filename_new, 0, ConfService::getCoreConf("NODENAME_MAX_LENGTH"));
        $old=$this->urlBase."/$filePath";
        if (!$this->isWriteable($old)) {
            throw new AJXP_Exception($mess[34]." ".$nom_fic." ".$mess[99]);
        }
        if($dest == null) $new=dirname($old)."/".$filename_new;
        else $new = $this->urlBase.$dest;
        if ($filename_new=="" && $dest == null) {
            throw new AJXP_Exception("$mess[37]");
        }
        if (file_exists($new)) {
            throw new AJXP_Exception("$filename_new $mess[43]");
        }
        if (!file_exists($old)) {
            throw new AJXP_Exception($mess[100]." $nom_fic");
        }
        $oldNode = new AJXP_Node($old);
        AJXP_Controller::applyHook("node.before_path_change", array(&$oldNode));
        $test = @rename($old,$new);
        if($test === false){
            throw new Exception("Error while renaming ".$old." to ".$new);
        }
        AJXP_Controller::applyHook("node.change", array($oldNode, new AJXP_Node($new), false));
    }

    public static function autoRenameForDest($destination, $fileName)
    {
        if(!is_file($destination."/".$fileName)) return $fileName;
        $i = 1;
        $ext = "";
        $name = "";
        $split = explode(".", $fileName);
        if (count($split) > 1) {
            $ext = ".".$split[count($split)-1];
            array_pop($split);
            $name = join(".", $split);
        } else {
            $name = $fileName;
        }
        while (is_file($destination."/".$name."-$i".$ext)) {
            $i++; // increment i until finding a non existing file.
        }
        return $name."-$i".$ext;
    }

    public function mkDir($crtDir, $newDirName, $ignoreExists = false)
    {
        $currentNodeDir = new AJXP_Node($this->urlBase.$crtDir);
        AJXP_Controller::applyHook("node.before_change", array(&$currentNodeDir));

        $mess = ConfService::getMessages();
        if ($newDirName=="") {
            return "$mess[37]";
        }
        if (file_exists($this->urlBase."$crtDir/$newDirName")) {
            if($ignoreExists) return null;
            return "$mess[40]";
        }
        if (!$this->isWriteable($this->urlBase."$crtDir")) {
            return $mess[38]." $crtDir ".$mess[99];
        }

        $dirMode = 0775;
        $chmodValue = $this->repository->getOption("CHMOD_VALUE");
        if (isSet($chmodValue) && $chmodValue != "") {
            $dirMode = octdec(ltrim($chmodValue, "0"));
            if ($dirMode & 0400) $dirMode |= 0100; // User is allowed to read, allow to list the directory
            if ($dirMode & 0040) $dirMode |= 0010; // Group is allowed to read, allow to list the directory
            if ($dirMode & 0004) $dirMode |= 0001; // Other are allowed to read, allow to list the directory
        }
        $old = umask(0);
        mkdir($this->urlBase."$crtDir/$newDirName", $dirMode);
        umask($old);
        $newNode = new AJXP_Node($this->urlBase.$crtDir."/".$newDirName);
        $newNode->setLeaf(false);
        AJXP_Controller::applyHook("node.change", array(null, $newNode, false));
        return null;
    }

    public function createEmptyFile($crtDir, $newFileName, $content = "", $force = false)
    {
        AJXP_Controller::applyHook("node.before_change", array(new AJXP_Node($this->urlBase.$crtDir)));
        $mess = ConfService::getMessages();
        if ($newFileName=="") {
            return "$mess[37]";
        }
        if (!$force && file_exists($this->urlBase."$crtDir/$newFileName")) {
            return "$mess[71]";
        }
        if (!$this->isWriteable($this->urlBase."$crtDir")) {
            return "$mess[38] $crtDir $mess[99]";
        }
        $repoData = array(
            'base_url' => $this->urlBase,
            'wrapper_name' => $this->wrapperClassName,
            'chmod'     => $this->repository->getOption('CHMOD_VALUE'),
            'recycle'     => $this->repository->getOption('RECYCLE_BIN')
        );
        $fp=fopen($this->urlBase."$crtDir/$newFileName","w");
        if ($fp) {
            if ($content != "") {
                fputs($fp, $content);
            }
            $this->changeMode($this->urlBase."$crtDir/$newFileName", $repoData);
            fclose($fp);
            $newNode = new AJXP_Node($this->urlBase."$crtDir/$newFileName");
            AJXP_Controller::applyHook("node.change", array(null, $newNode, false));
            return null;
        } else {
            return "$mess[102] $crtDir/$newFileName (".$fp.")";
        }
    }


    public function delete($selectedFiles, &$logMessages)
    {
        $repoData = array(
            'base_url' => $this->urlBase,
            'wrapper_name' => $this->wrapperClassName,
            'chmod'     => $this->repository->getOption('CHMOD_VALUE'),
            'recycle'     => $this->repository->getOption('RECYCLE_BIN')
        );
        $mess = ConfService::getMessages();
        foreach ($selectedFiles as $selectedFile) {
            if ($selectedFile == "" || $selectedFile == DIRECTORY_SEPARATOR) {
                return $mess[120];
            }
            $fileToDelete=$this->urlBase.$selectedFile;
            if (!file_exists($fileToDelete)) {
                $logMessages[]=$mess[100]." ".SystemTextEncoding::toUTF8($selectedFile);
                continue;
            }
            $this->deldir($fileToDelete, $repoData);
            if (is_dir($fileToDelete)) {
                $logMessages[]="$mess[38] ".SystemTextEncoding::toUTF8($selectedFile)." $mess[44].";
            } else {
                $logMessages[]="$mess[34] ".SystemTextEncoding::toUTF8($selectedFile)." $mess[44].";
            }
            AJXP_Controller::applyHook("node.change", array(new AJXP_Node($fileToDelete)));
        }
        return null;
    }

    public function simpleCopy($origFile, $destFile)
    {
        return copy($origFile, $destFile);
    }

    public function isWriteable($dir, $type="dir")
    {
        if ( $this->getFilteredOption("USE_POSIX", $this->repository->getId()) == true && extension_loaded('posix')) {
            $real = call_user_func(array( $this->wrapperClassName, "getRealFSReference"), $dir);
            return posix_access($real, POSIX_W_OK);
        }
        return is_writable($dir);
    }

    /**
     * Change file permissions
     *
     * @param String $path
     * @param String $chmodValue
     * @param Boolean $recursive
     * @param String $nodeType "both", "file", "dir"
     * @param $changedFiles
     * @return void
     */
    public function chmod($path, $chmodValue, $recursive, $nodeType, &$changedFiles)
    {
        $realValue = octdec(ltrim($chmodValue, "0"));
        if (is_file($this->urlBase.$path)) {
            if ($nodeType=="both" || $nodeType=="file") {
                call_user_func(array($this->wrapperClassName, "changeMode"), $this->urlBase.$path, $realValue);
                $changedFiles[] = $path;
            }
        } else {
            if ($nodeType=="both" || $nodeType=="dir") {
                call_user_func(array($this->wrapperClassName, "changeMode"), $this->urlBase.$path, $realValue);
                $changedFiles[] = $path;
            }
            if ($recursive) {
                $handler = opendir($this->urlBase.$path);
                while ($child=readdir($handler)) {
                    if($child == "." || $child == "..") continue;
                    // do not pass realValue or it will be re-decoded.
                    $this->chmod($path."/".$child, $chmodValue, $recursive, $nodeType, $changedFiles);
                }
                closedir($handler);
            }
        }
    }

    /**
     * @param String $from
     * @param String $to
     * @param Boolean $copy
     */
    public function nodeChanged(&$from, &$to, $copy = false)
    {
        $fromNode = $toNode = null;
        if($from != null) $fromNode = new AJXP_Node($this->urlBase.$from);
        if($to != null) $toNode = new AJXP_Node($this->urlBase.$to);
        AJXP_Controller::applyHook("node.change", array($fromNode, $toNode, $copy));
    }

    /**
     * @param String $node
     * @param null $newSize
     */
    public function nodeWillChange($node, $newSize = null)
    {
        if ($newSize != null) {
            AJXP_Controller::applyHook("node.before_change", array(new AJXP_Node($this->urlBase.$node), $newSize));
        } else {
            AJXP_Controller::applyHook("node.before_path_change", array(new AJXP_Node($this->urlBase.$node)));
        }
    }


    /**
     * @var fsAccessDriver
     */
    public static $filteringDriverInstance;

    /**
     * @param $src
     * @param $dest
     * @param $basedir
     * @throws Exception
     * @return zipfile
     */
    public function makeZip ($src, $dest, $basedir)
    {
        $zipEncoding = ConfService::getCoreConf("ZIP_ENCODING");

        @set_time_limit(0);
        require_once(AJXP_BIN_FOLDER."/pclzip.lib.php");
        $filePaths = array();
        foreach ($src as $item) {
            $realFile = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase."/".$item);
            $realFile = AJXP_Utils::securePath($realFile);
            if (basename($item) == "") {
                $filePaths[] = array(PCLZIP_ATT_FILE_NAME => $realFile);
            } else {
                $shortName = basename($item);
                if(!empty($zipEncoding)){
                    $test = iconv(SystemTextEncoding::getEncoding(), $zipEncoding, $shortName);
                    if($test !== false) $shortName = $test;
                }
                $filePaths[] = array(PCLZIP_ATT_FILE_NAME => $realFile,
                                    PCLZIP_ATT_FILE_NEW_SHORT_NAME => $shortName);
            }
        }
        $this->logDebug("Pathes", $filePaths);
        self::$filteringDriverInstance = $this;
        $archive = new PclZip($dest);

        if($basedir == "__AJXP_ZIP_FLAT__/"){
            $vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_ALL_PATH, PCLZIP_OPT_NO_COMPRESSION, PCLZIP_OPT_ADD_TEMP_FILE_ON, PCLZIP_CB_PRE_ADD, 'zipPreAddCallback');
        }else{
            $basedir = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->urlBase).trim($basedir);
            $this->logDebug("Basedir", array($basedir));
            $vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_PATH, $basedir, PCLZIP_OPT_NO_COMPRESSION, PCLZIP_OPT_ADD_TEMP_FILE_ON, PCLZIP_CB_PRE_ADD, 'zipPreAddCallback');
        }

        if (!$vList) {
            throw new Exception("Zip creation error : ($dest) ".$archive->errorInfo(true));
        }
        self::$filteringDriverInstance = null;
        return $vList;
    }


    public function recursivePurge($dirName, $hardPurgeTime, $softPurgeTime = 0)
    {
        $handle=opendir($dirName);
        $shareCenter = false;
        if(class_exists("ShareCenter")){
            $shareCenter = ShareCenter::getShareCenter("action.share");
        }
        if($handle === false){
            $this->logError(__FUNCTION__, "Cannot open folder ".$dirName);
            return;
        }
        while (false !== ($entry = readdir($handle))) {
            if ($entry == "" || $entry == ".."  || AJXP_Utils::isHidden($entry) ) {
                continue;
            }
            $fileName = $dirName."/".$entry;
            if (is_file($fileName)) {
                $docAge = time() - filemtime($fileName);
                if ($hardPurgeTime > 0 && $docAge > $hardPurgeTime) {
                    $this->purge($fileName);
                } elseif ($softPurgeTime > 0 && $docAge > $softPurgeTime) {
                    if($shareCenter !== false && $shareCenter->isShared(new AJXP_Node($fileName))) {
                        $this->purge($fileName);
                    }
                }
            } else {
                $this->recursivePurge($fileName, $hardPurgeTime, $softPurgeTime);
            }
        }
        closedir($handle);
    }

    /**
     * Apply specific operation to set a node as hidden.
     * Can be overwritten, or will probably do nothing.
     * @param AJXP_Node $node
     */
    public function setHiddenAttribute($node){
        if($this->getWrapperClassName() == "fsAccessWrapper" && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
            $realPath =  call_user_func(array($this->wrapperClassName, "getRealFSReference"),$node->getUrl());
            @shell_exec("attrib +H " . escapeshellarg($realPath));
        }
    }

    private function purge($fileName)
    {
        $node = new AJXP_Node($fileName);
        AJXP_Controller::applyHook("node.before_path_change", array($node));
        unlink($fileName);
        AJXP_Controller::applyHook("node.change", array($node));
        $this->logInfo("Purge", array("file" => $fileName));
        print(" - Purging document : ".$fileName."\n");
    }

    /** The publiclet URL making */
    public function makePublicletOptions($filePath, $password, $expire, $downloadlimit, $repository)
    {
        $data = array(
            "DRIVER"=>$repository->getAccessType(),
            "OPTIONS"=>NULL,
            "FILE_PATH"=>$filePath,
            "ACTION"=>"download",
            "EXPIRE_TIME"=>$expire ? (time() + $expire * 86400) : 0,
            "DOWNLOAD_LIMIT"=>$downloadlimit ? $downloadlimit : 0,
            "PASSWORD"=>$password
        );
        return $data;
    }

    public function makeSharedRepositoryOptions($httpVars, $repository)
    {
        $newOptions = array(
            "PATH" => SystemTextEncoding::toStorageEncoding($repository->getOption("PATH")).AJXP_Utils::decodeSecureMagic($httpVars["file"]),
            "CREATE" => isSet($httpVars["inherit_recycle"])? $repository->getOption("CREATE") : false,
            "RECYCLE_BIN" => isSet($httpVars["inherit_recycle"])? $repository->getOption("RECYCLE_BIN") : "",
            "DEFAULT_RIGHTS" => "");
        if ($repository->getOption("USE_SESSION_CREDENTIALS")===true) {
            $newOptions["ENCODED_CREDENTIALS"] = AJXP_Safe::getEncodedCredentialString();
        }
        return $newOptions;
    }


}

function zipPreAddCallback($value, &$header)
{
    if(fsAccessDriver::$filteringDriverInstance == null) return true;
    $search = $header["filename"];
    $zipEncoding = ConfService::getCoreConf("ZIP_ENCODING");
    if(!empty($zipEncoding)){
        $test = iconv(SystemTextEncoding::getEncoding(), $zipEncoding, $header["stored_filename"]);
        if($test !== false){
            $header["stored_filename"] = $test;
        }
    }
    return !(fsAccessDriver::$filteringDriverInstance->filterFile($search, true)
        || fsAccessDriver::$filteringDriverInstance->filterFolder($search, "contains"));
}

if (!function_exists('staticExtractArchiveItemCallback')){
    function staticExtractArchiveItemPostCallback($status, $data){
        return fsAccessDriver::$currentZipOperationHandler->extractArchiveItemPostCallback($status, $data);
    }
    function staticExtractArchiveItemPreCallback($status, $data){
        return fsAccessDriver::$currentZipOperationHandler->extractArchiveItemPreCallback($status, $data);
    }
}