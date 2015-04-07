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
 * @package AjaXplorer_Plugins
 * @subpackage Core
 * @class AbstractAccessDriver
 * Abstract representation of an action driver. Must be implemented.
 */
class AbstractAccessDriver extends AJXP_Plugin
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverType = "access";

    public function init($repository, $options = array())
    {
        //$this->loadActionsFromManifest();
        parent::init($options);
        $this->repository = $repository;
    }

    public function initRepository()
    {
        // To be implemented by subclasses
    }


    public function accessPreprocess($actionName, &$httpVars, &$filesVar)
    {
        if ($actionName == "apply_check_hook") {
            if (!in_array($httpVars["hook_name"], array("before_create", "before_path_change", "before_change"))) {
                return;
            }
            $selection = new UserSelection();
            $selection->initFromHttpVars($httpVars);
            $node = $selection->getUniqueNode($this);
            AJXP_Controller::applyHook("node.".$httpVars["hook_name"], array($node, $httpVars["hook_arg"]));
        }
        if ($actionName == "ls") {
            // UPWARD COMPATIBILTY
            if (isSet($httpVars["options"])) {
                if($httpVars["options"] == "al") $httpVars["mode"] = "file_list";
                else if($httpVars["options"] == "a") $httpVars["mode"] = "search";
                else if($httpVars["options"] == "d") $httpVars["skipZip"] = "true";
                // skip "complete" mode that was in fact quite the same as standard tree listing (dz)
            }
        }
    }

    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
    }


    /**
     * Backward compatibility, now moved to SharedCenter::loadPubliclet();
     * @param $data
     * @return void
     */
    public function loadPubliclet($data)
    {
        require_once(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/action.share/class.ShareCenter.php");
        ShareCenter::loadPubliclet($data);
    }

    /**
     * Populate publiclet options
     * @param String $filePath The path to the file to share
     * @param String $password optionnal password
     * @param String $downloadlimit optional limit for downloads
     * @param String $expires optional expiration date
     * @param Repository $repository
     * @return Array
     */
    public function makePublicletOptions($filePath, $password, $expires, $downloadlimit, $repository) {}

    /**
     * Populate shared repository options
     * @param Array $httpVars
     * @param Repository $repository
     * @return Array
     */
    public function makeSharedRepositoryOptions($httpVars, $repository){}

    /**
     * @param $directoryPath string
     * @param $repositoryResolvedOptions array
     * @return integer
     * @throw Exception
     */
    public function directoryUsage($directoryPath, $repositoryResolvedOptions){
        throw new Exception("Current driver does not support recursive directory usage!");
    }

    public function crossRepositoryCopy($httpVars)
    {
        ConfService::detectRepositoryStreams(true);
        $mess = ConfService::getMessages();
        $selection = new UserSelection();
        $selection->initFromHttpVars($httpVars);
        $files = $selection->getFiles();

        $accessType = $this->repository->getAccessType();
        $repositoryId = $this->repository->getId();
        $plugin = AJXP_PluginsService::findPlugin("access", $accessType);
        $origWrapperData = $plugin->detectStreamWrapper(true);
        $origStreamURL = $origWrapperData["protocol"]."://$repositoryId";

        $destRepoId = $httpVars["dest_repository_id"];
        $destRepoObject = ConfService::getRepositoryById($destRepoId);
        $destRepoAccess = $destRepoObject->getAccessType();
        $plugin = AJXP_PluginsService::findPlugin("access", $destRepoAccess);
        $plugin->repository = $destRepoObject;
        $destWrapperData = $plugin->detectStreamWrapper(true);
        $destStreamURL = $destWrapperData["protocol"]."://$destRepoId";
        // Check rights
        if (AuthService::usersEnabled()) {
            $loggedUser = AuthService::getLoggedUser();
            if(!$loggedUser->canRead($repositoryId) || !$loggedUser->canWrite($destRepoId)
                || (isSet($httpVars["moving_files"]) && !$loggedUser->canWrite($repositoryId))
            ){
                throw new Exception($mess[364]);
            }
        }
        $srcRepoData= array(
            'base_url' => $origStreamURL,
            'wrapper_name' => $origWrapperData['classname'],
            'recycle'   => $this->repository->getOption("RECYCLE_BIN")
        );
        $destRepoData=array(
            'base_url' => $destStreamURL,
            'wrapper_name' => $destWrapperData['classname'],
            'chmod'         => $this->repository->getOption('CHMOD')
        );

        $messages = array();
        $errorMessages = array();
        foreach ($files as $file) {

            $this->copyOrMoveFile(
                AJXP_Utils::decodeSecureMagic($httpVars["dest"]),
                $file, $errorMessages, $messages, isSet($httpVars["moving_files"]) ? true: false,
                $srcRepoData, $destRepoData);

        }
        AJXP_XMLWriter::header();
        if (count($errorMessages)) {
            AJXP_XMLWriter::sendMessage(null, join("\n", $errorMessages), true);
        }
        AJXP_XMLWriter::sendMessage(join("\n", $messages), null, true);
        AJXP_XMLWriter::close();
    }

    /**
     * @param String $destDir url of destination dir
     * @param String $srcFile url of source file
     * @param Array $error accumulator for error messages
     * @param Array $success accumulator for success messages
     * @param bool $move Whether to copy or move
     * @param array $srcRepoData Set of data concerning source repository: base_url, wrapper_name and recycle option
     * @param array $destRepoData Set of data concerning destination repository: base_url, wrapper_name and chmod option
     */
    protected function copyOrMoveFile($destDir, $srcFile, &$error, &$success, $move = false, $srcRepoData = array(), $destRepoData = array())
    {
        $srcUrlBase = $srcRepoData['base_url'];
        $srcWrapperName = $srcRepoData['wrapper_name'];
        $srcRecycle = $srcRepoData['recycle'];
        $destUrlBase = $destRepoData['base_url'];
        $destWrapperName = $destRepoData['wrapper_name'];

        $mess = ConfService::getMessages();
        $bName = basename($srcFile);
        $localName = '';
        AJXP_Controller::applyHook("dl.localname", array($srcFile, &$localName, $srcWrapperName));
        if(!empty($localName)) $bName = $localName;
        $destFile = $destUrlBase.$destDir."/".$bName;
        $realSrcFile = $srcUrlBase.$srcFile;

        if (is_dir(dirname($realSrcFile)) && (strpos($destFile, rtrim($realSrcFile, "/") . "/") === 0)) {
            $error[] = $mess[101];
            return;
        }

        if (!file_exists($realSrcFile)) {
            $error[] = $mess[100].$srcFile;
            return ;
        }
        if (!$move) {
            AJXP_Controller::applyHook("node.before_create", array(new AJXP_Node($destFile), filesize($realSrcFile)));
        }
        if (dirname($realSrcFile)==dirname($destFile)) {
            if ($move) {
                $error[] = $mess[101];
                return ;
            } else {
                $base = basename($srcFile);
                $i = 1;
                if (is_file($realSrcFile)) {
                    $dotPos = strrpos($base, ".");
                    if ($dotPos>-1) {
                        $radic = substr($base, 0, $dotPos);
                        $ext = substr($base, $dotPos);
                    }
                }
                // auto rename file
                $i = 1;
                $newName = $base;
                while (file_exists($destUrlBase.$destDir."/".$newName)) {
                    $suffix = "-$i";
                    if(isSet($radic)) $newName = $radic . $suffix . $ext;
                    else $newName = $base.$suffix;
                    $i++;
                }
                $destFile = $destUrlBase.$destDir."/".$newName;
            }
        }
        $srcNode = new AJXP_Node($realSrcFile);
        $destNode = new AJXP_Node($destFile);
        if (!is_file($realSrcFile)) {
            $errors = array();
            $succFiles = array();
            $srcNode->setLeaf(false);
            if ($move) {
                AJXP_Controller::applyHook("node.before_path_change", array($srcNode));
                if(file_exists($destFile)) $this->deldir($destFile, $destRepoData);
                $res = rename($realSrcFile, $destFile);
            } else {
                $dirRes = $this->dircopy($realSrcFile, $destFile, $errors, $succFiles, false, true, $srcRepoData, $destRepoData);
            }
            if (count($errors) || (isSet($res) && $res!==true)) {
                $error[] = $mess[114];
                return ;
            } else {
                $destNode->setLeaf(false);
                AJXP_Controller::applyHook("node.change", array($srcNode, $destNode, !$move));
            }
        } else {
            if ($move) {
                AJXP_Controller::applyHook("node.before_path_change", array($srcNode));
                if(file_exists($destFile)) unlink($destFile);
                if(strcmp($srcWrapperName,$destWrapperName) === 0){
                    $res = rename($realSrcFile, $destFile);
                }else{
                    $res = copy($realSrcFile, $destFile);
                }
                AJXP_Controller::applyHook("node.change", array($srcNode, $destNode, false));
            } else {
                try {
                    $this->filecopy($realSrcFile, $destFile, $srcWrapperName, $destWrapperName);
                    $this->changeMode($destFile, $destRepoData);
                    AJXP_Controller::applyHook("node.change", array($srcNode, $destNode, true));
                } catch (Exception $e) {
                    $error[] = $e->getMessage();
                    return ;
                }
            }
        }

        if ($move) {
            // Now delete original
            // $this->deldir($realSrcFile); // both file and dir
            $messagePart = $mess[74]." ".SystemTextEncoding::toUTF8($destDir);
            if (RecycleBinManager::recycleEnabled() && $destDir == RecycleBinManager::getRelativeRecycle()) {
                RecycleBinManager::fileToRecycle($srcFile);
                $messagePart = $mess[123]." ".$mess[122];
            }
            if (isset($dirRes)) {
                $success[] = $mess[117]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$messagePart." (".SystemTextEncoding::toUTF8($dirRes)." ".$mess[116].") ";
            } else {
                $success[] = $mess[34]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$messagePart;
            }
        } else {
            if (RecycleBinManager::recycleEnabled() && $destDir == "/".$srcRecycle) {
                RecycleBinManager::fileToRecycle($srcFile);
            }
            if (isSet($dirRes)) {
                $success[] = $mess[117]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$mess[73]." ".SystemTextEncoding::toUTF8($destDir)." (".SystemTextEncoding::toUTF8($dirRes)." ".$mess[116].")";
            } else {
                $success[] = $mess[34]." ".SystemTextEncoding::toUTF8(basename($srcFile))." ".$mess[73]." ".SystemTextEncoding::toUTF8($destDir);
            }
        }

    }

    /**
     * @param String $srcFile url of source file
     * @param String $destFile url of destination file
     * @param String $srcWrapperName Wrapper name
     * @param String $destWrapperName Wrapper name
     */
    protected function filecopy($srcFile, $destFile, $srcWrapperName, $destWrapperName)
    {
        if (call_user_func(array($srcWrapperName, "isRemote")) || call_user_func(array($destWrapperName, "isRemote")) || $srcWrapperName != $destWrapperName) {
            $src = fopen($srcFile, "r");
            $dest = fopen($destFile, "w");
            if (is_resource($src) && is_resource($dest)) {
                while (!feof($src)) {
                    //stream_copy_to_stream($src, $dest, 4096);
                    $count = stream_copy_to_stream($src, $dest, 4096);
                    if ($count == 0) break;
                }
            }
            if(is_resource($dest)) fclose($dest);
            if(is_resource($src)) fclose($src);
        } else {
            copy($srcFile, $destFile);
        }
    }

    /**
     * @param String $srcdir Url of source file
     * @param String $dstdir Url of dest file
     * @param Array $errors Array of errors
     * @param Array $success Array of success
     * @param bool $verbose Boolean
     * @param bool $convertSrcFile Boolean
     * @param array $srcRepoData Set of data concerning source repository: base_url, wrapper_name and recycle option
     * @param array $destRepoData Set of data concerning destination repository: base_url, wrapper_name and chmod option
     * @return int
     */
    protected function dircopy($srcdir, $dstdir, &$errors, &$success, $verbose = false, $convertSrcFile = true, $srcRepoData = array(), $destRepoData = array())
    {
        $num = 0;
        //$verbose = true;
        $recurse = array();
        if (!is_dir($dstdir)) {
            $dirMode = 0755;
            $chmodValue = $destRepoData["chmod"]; //$this->repository->getOption("CHMOD_VALUE");
            if (isSet($chmodValue) && $chmodValue != "") {
                $dirMode = octdec(ltrim($chmodValue, "0"));
                if ($dirMode & 0400) $dirMode |= 0100; // User is allowed to read, allow to list the directory
                if ($dirMode & 0040) $dirMode |= 0010; // Group is allowed to read, allow to list the directory
                if ($dirMode & 0004) $dirMode |= 0001; // Other are allowed to read, allow to list the directory
            }
            $old = umask(0);
            mkdir($dstdir, $dirMode);
            umask($old);
        }
        if ($curdir = opendir($srcdir)) {
            while ($file = readdir($curdir)) {
                if ($file != '.' && $file != '..') {
                    $srcfile = $srcdir . "/" . $file;
                    $dstfile = $dstdir . "/" . $file;
                    if (is_file($srcfile)) {
                        if(is_file($dstfile)) $ow = filemtime($srcfile) - filemtime($dstfile); else $ow = 1;
                        if ($ow > 0) {
                            try {
                                if($convertSrcFile) $tmpPath = call_user_func(array($srcRepoData["wrapper_name"], "getRealFSReference"), $srcfile);
                                else $tmpPath = $srcfile;
                                if($verbose) echo "Copying '$tmpPath' to '$dstfile'...";
                                copy($tmpPath, $dstfile);
                                $success[] = $srcfile;
                                $num ++;
                                $this->changeMode($dstfile, $destRepoData);
                            } catch (Exception $e) {
                                $errors[] = $srcfile;
                            }
                        }
                    } else {
                        $recurse[] = array("src" => $srcfile, "dest"=> $dstfile);
                    }
                }
            }
            closedir($curdir);
            foreach ($recurse as $rec) {
                if($verbose) echo "Dircopy $srcfile";
                $num += $this->dircopy($rec["src"], $rec["dest"], $errors, $success, $verbose, $convertSrcFile, $srcRepoData, $destRepoData);
            }
        }
        return $num;
    }

    /**
     * @param $filePath
     * @param $repoData
     */
    protected function changeMode($filePath, $repoData)
    {
        $chmodValue = $repoData["chmod"]; //$this->repository->getOption("CHMOD_VALUE");
        if (isSet($chmodValue) && $chmodValue != "") {
            $chmodValue = octdec(ltrim($chmodValue, "0"));
            call_user_func(array($repoData["wrapper_name"], "changeMode"), $filePath, $chmodValue);
        }
    }

    /**
     * @param $location
     * @param $repoData
     * @throws Exception
     */
    protected function deldir($location, $repoData)
    {
        if (is_dir($location)) {
            AJXP_Controller::applyHook("node.before_path_change", array(new AJXP_Node($location)));
            $all=opendir($location);
            while ($file=readdir($all)) {
                if (is_dir("$location/$file") && $file !=".." && $file!=".") {
                    $this->deldir("$location/$file", $repoData);
                    if (file_exists("$location/$file")) {
                        rmdir("$location/$file");
                    }
                    unset($file);
                } elseif (!is_dir("$location/$file")) {
                    if (file_exists("$location/$file")) {
                        unlink("$location/$file");
                    }
                    unset($file);
                }
            }
            closedir($all);
            rmdir($location);
        } else {
            if (file_exists("$location")) {
                AJXP_Controller::applyHook("node.before_path_change", array(new AJXP_Node($location)));
                $test = @unlink("$location");
                if(!$test) throw new Exception("Cannot delete file ".$location);
            }
        }
        if (isSet($repoData["recycle"]) && basename(dirname($location)) == $repoData["recycle"]) {
            // DELETING FROM RECYCLE
            RecycleBinManager::deleteFromRecycle($location);
        }
    }


    /**
     *
     * Try to reapply correct permissions
     * @param array $stat
     * @param Repository $repoObject
     * @param callable $remoteDetectionCallback
     * @internal param \oct $mode
     */
    public static function fixPermissions(&$stat, $repoObject, $remoteDetectionCallback = null)
    {
        $fixPermPolicy = $repoObject->getOption("FIX_PERMISSIONS");
        $loggedUser = AuthService::getLoggedUser();
        if ($loggedUser == null) {
            return;
        }
        $sessionKey = md5($repoObject->getId()."-".$loggedUser->getId()."-fixPermData");


        if (!isSet($_SESSION[$sessionKey])) {
            if ($fixPermPolicy == "detect_remote_user_id" && $remoteDetectionCallback != null) {
                list($uid, $gid) = call_user_func($remoteDetectionCallback, $repoObject);
                if ($uid != null && $gid != null) {
                    $_SESSION[$sessionKey] = array("uid" => $uid, "gid" => $gid);
                }

            } else if (substr($fixPermPolicy, 0, strlen("file:")) == "file:") {
                $filePath = AJXP_VarsFilter::filter(substr($fixPermPolicy, strlen("file:")));
                if (file_exists($filePath)) {
                    // GET A GID/UID FROM FILE
                    $lines = file($filePath);
                    foreach ($lines as $line) {
                        $res = explode(":", $line);
                        if ($res[0] == $loggedUser->getId()) {
                            $uid = $res[1];
                            $gid = $res[2];
                            $_SESSION[$sessionKey] = array("uid" => $uid, "gid" => $gid);
                            break;
                        }
                    }
                }
            }
            // If not set, set an empty anyway
            if (!isSet($_SESSION[$sessionKey])) {
                $_SESSION[$sessionKey] = array(null, null);
            }

        } else {
            $data = $_SESSION[$sessionKey];
            if (!empty($data)) {
                if(isSet($data["uid"])) $uid = $data["uid"];
                if(isSet($data["gid"])) $gid = $data["gid"];
            }
        }

        $p = $stat["mode"];
        //$st = sprintf("%07o", ($p & 7777770));
        //AJXP_Logger::debug("FIX PERM DATA ($fixPermPolicy, $st)".$p,sprintf("%o", ($p & 000777)));
        if ($p != NULL) {
            /*
                decoct returns a string, it's more convenient to manipulate as we know the structure
                of the octal form of stat["mode"]
                    - first two or three chars => file type (dir: 40, file: 100, symlink: 120)
                    - three remaining characters => file permissions (1st char: user, 2nd char: group, 3rd char: others)
            */

            $p = decoct($p);
            $lastInd = (intval($p[0]) == 4)? 4 : 5;
            $otherPerms = decbin(intval($p[$lastInd]));
            $actualPerms = $otherPerms;

            if ( ( isSet($uid) && $stat["uid"] == $uid ) || $fixPermPolicy == "user"  ) {
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"upgrading abit to ubit");
                $userPerms = decbin(intval($p[$lastInd - 2]));
                $actualPerms |= $userPerms;
            } else if ( ( isSet($gid) && $stat["gid"] == $gid ) || $fixPermPolicy == "group"  ) {
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"upgrading abit to gbit");
                $groupPerms = decbin(intval($p[$lastInd - 1]));
                $actualPerms |= $groupPerms;
            }
            $test = bindec($actualPerms);
            $p[$lastInd] = $test;

            $stat["mode"] = $stat[2] = octdec($p);
            //AJXP_Logger::debug(__CLASS__,__FUNCTION__,"FIXED PERM DATA ($fixPermPolicy)",sprintf("%o", ($p & 000777)));
        }
    }

    protected function resetAllPermission($value)
    {
    }

    /**
     * Test if userSelection is containing a hidden file, which should not be the case!
     * @param Array $files
     * @throws Exception
     */
    public function filterUserSelectionToHidden($files)
    {
        $showHiddenFiles = $this->getFilteredOption("SHOW_HIDDEN_FILES", $this->repository->getId());
        foreach ($files as $file) {
            $file = basename($file);
            if (AJXP_Utils::isHidden($file) && !$showHiddenFiles) {
                throw new Exception("$file Forbidden", 411);
            }
            if ($this->filterFile($file) || $this->filterFolder($file)) {
                throw new Exception("$file Forbidden", 411);
            }
        }
    }

    public function filterNodeName($nodePath, $nodeName, &$isLeaf, $lsOptions)
    {
        $showHiddenFiles = $this->getFilteredOption("SHOW_HIDDEN_FILES", $this->repository->getId());
        $isLeaf = (is_file($nodePath."/".$nodeName) || AJXP_Utils::isBrowsableArchive($nodeName));
        if (AJXP_Utils::isHidden($nodeName) && !$showHiddenFiles) {
            return false;
        }
        $nodeType = "d";
        if ($isLeaf) {
            if(AJXP_Utils::isBrowsableArchive($nodeName)) $nodeType = "z";
            else $nodeType = "f";
        }
        if(!$lsOptions[$nodeType]) return false;
        if ($nodeType == "d") {
            if(RecycleBinManager::recycleEnabled()
                && $nodePath."/".$nodeName == RecycleBinManager::getRecyclePath()){
                return false;
            }
            return !$this->filterFolder($nodeName);
        } else {
            if($nodeName == "." || $nodeName == "..") return false;
            if(RecycleBinManager::recycleEnabled()
                && $nodePath == RecycleBinManager::getRecyclePath()
                && $nodeName == RecycleBinManager::getCacheFileName()){
                return false;
            }
            return !$this->filterFile($nodeName);
        }
    }

    public function filterFile($fileName, $hiddenTest = false)
    {
        $pathParts = pathinfo($fileName);
        if($hiddenTest){
            $showHiddenFiles = $this->getFilteredOption("SHOW_HIDDEN_FILES", $this->repository->getId());
            if (AJXP_Utils::isHidden($pathParts["basename"]) && !$showHiddenFiles) return true;
        }
        $hiddenFileNames = $this->getFilteredOption("HIDE_FILENAMES", $this->repository->getId());
        $hiddenExtensions = $this->getFilteredOption("HIDE_EXTENSIONS", $this->repository->getId());
        if (!empty($hiddenFileNames)) {
            if (!is_array($hiddenFileNames)) {
                $hiddenFileNames = explode(",",$hiddenFileNames);
            }
            foreach ($hiddenFileNames as $search) {
                if(strcasecmp($search, $pathParts["basename"]) == 0) return true;
            }
        }
        if (!empty($hiddenExtensions)) {
            if (!is_array($hiddenExtensions)) {
                $hiddenExtensions = explode(",",$hiddenExtensions);
            }
            foreach ($hiddenExtensions as $search) {
                if(strcasecmp($search, $pathParts["extension"]) == 0) return true;
            }
        }
        return false;
    }

    public function filterFolder($folderName, $compare = "equals")
    {
        $hiddenFolders = $this->getFilteredOption("HIDE_FOLDERS", $this->repository->getId());
        if (!empty($hiddenFolders)) {
            if (!is_array($hiddenFolders)) {
                $hiddenFolders = explode(",",$hiddenFolders);
            }
            foreach ($hiddenFolders as $search) {
                if($compare == "equals" && strcasecmp($search, $folderName) == 0) return true;
                if($compare == "contains" && strpos($folderName, "/".$search) !== false) return true;
            }
        }
        return false;
    }


}
