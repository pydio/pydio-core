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

require_once("class.PublicletCounter.php");

class ShareStore {

    var $sqlSupported = false;
    var $downloadFolder;
    var $hashMinLength;
    public $modifiableShareKeys = array("counter", "tags", "short_form_url");
    /**
     * @var sqlConfDriver
     */
    var $confStorage;

    public function __construct($downloadFolder, $hashMinLength = 32){
        $this->downloadFolder = $downloadFolder;
        $this->hashMinLength = $hashMinLength;
        $storage = ConfService::getConfStorageImpl();
        if($storage->getId() == "conf.sql") {
            $this->sqlSupported = true;
            $this->confStorage = $storage;
        }
    }

    private function createGenericLoader(){
        if(!is_file($this->downloadFolder."/share.php")){
            $loader_content = '<'.'?'.'php
                    define("AJXP_EXEC", true);
                    require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/core/classes/class.AJXP_Utils.php");
                    $hash = AJXP_Utils::securePath(AJXP_Utils::sanitize($_GET["hash"], AJXP_SANITIZE_ALPHANUM));
                    if(file_exists($hash.".php")){
                        require_once($hash.".php");
                    }else{
                        require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/publicLet.inc.php");
                        ShareCenter::loadShareByHash($hash);
                    }
                ';
            if (@file_put_contents($this->downloadFolder."/share.php", $loader_content) === FALSE) {
                throw new Exception("Can't write to PUBLIC URL");
            }
        }
    }

    /**
     * @param String $parentRepositoryId
     * @param Array $shareData
     * @param string $type
     * @param String $existingHash
     * @param null $updateHash
     * @throws Exception
     * @return string $hash
     */
    public function storeShare($parentRepositoryId, $shareData, $type="minisite", $existingHash = null, $updateHash = null){

        $data = serialize($shareData);
        if($existingHash){
            $hash = $existingHash;
        }else{
            $hash = $this->computeHash($data, $this->downloadFolder);
        }
        if($this->sqlSupported){
            $this->createGenericLoader();
            $shareData["SHARE_TYPE"] = $type;
            if($updateHash != null){
                $this->confStorage->simpleStoreClear("share", $existingHash);
                $hash = $updateHash;
            }
            $this->confStorage->simpleStoreSet("share", $hash, $shareData, "serial", $parentRepositoryId);
            return $hash;
        }
        if(!empty($existingHash)){
            throw new Exception("Current storage method does not support parameters edition!");
        }

        $loader = 'ShareCenter::loadMinisite($data);';
        if($type == "publiclet"){
            $loader = 'ShareCenter::loadPubliclet($data);';
        }

        $outputData = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, str_pad($hash, 16, "\0"), $data, MCRYPT_MODE_ECB));
        $fileData = "<"."?"."php \n".
            '   require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/publicLet.inc.php"); '."\n".
            '   $id = str_replace(".php", "", basename(__FILE__)); '."\n". // Not using "" as php would replace $ inside
            '   $cypheredData = base64_decode("'.$outputData.'"); '."\n".
            '   $inputData = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, str_pad($id, 16, "\0"), $cypheredData, MCRYPT_MODE_ECB), "\0");  '."\n".
            '   // if (!ShareCenter::checkHash($inputData, $id)) { header("HTTP/1.0 401 Not allowed, script was modified"); exit(); } '."\n".
            '   // Ok extract the data '."\n".
            '   $data = unserialize($inputData); '.$loader;
        if (@file_put_contents($this->downloadFolder."/".$hash.".php", $fileData) === FALSE) {
            throw new Exception("Can't write to PUBLIC URL");
        }
        @chmod($this->downloadFolder."/".$hash.".php", 0755);
        return $hash;

    }

    public function loadShare($hash){

        $dlFolder = $this->downloadFolder;
        $file = $dlFolder."/".$hash.".php";
        if(!is_file($file)) {
            if($this->sqlSupported){
                $this->confStorage->simpleStoreGet("share", $hash, "serial", $data);
                if(!empty($data)){
                    $data["DOWNLOAD_COUNT"] = PublicletCounter::getCount($hash);
                    $data["SECURITY_MODIFIED"] = false;
                    return $data;
                }
            }
            return array();
        }
        $lines = file($file);
        $inputData = '';
        // Necessary for the eval
        $id = $hash;
        // UPDATE LINK FOR PHP5.6
        if(trim($lines[4]) == '$inputData = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $id, $cypheredData, MCRYPT_MODE_ECB), "\0");' && is_writable($file)){
            // Upgrade line
            $lines[4] = '   $inputData = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, str_pad($id, 16, "\0"), $cypheredData, MCRYPT_MODE_ECB), "\0");'."\n";
            $res = file_put_contents($file, implode('', $lines));
        }
        $code = $lines[3] . $lines[4] . $lines[5];
        eval($code);
        if(empty($inputData)) return false;
        $dataModified = !$this->checkHash($inputData, $hash); //(md5($inputData) != $id);
        $publicletData = unserialize($inputData);
        $publicletData["SECURITY_MODIFIED"] = $dataModified;
        if (!isSet($publicletData["REPOSITORY"])) {
            $publicletData["DOWNLOAD_COUNT"] = PublicletCounter::getCount($hash);
        }
        $publicletData["PUBLICLET_PATH"] = $file;
        /*
        if($this->sqlSupported){
            // Move old file to DB-storage
            $type = (isset($publicletData["REPOSITORY"]) ? "minisite" : "publiclet");
            $this->createGenericLoader();
            $shareData["SHARE_TYPE"] = $type;
            $this->confStorage->simpleStoreSet("share", $hash, $publicletData, "serial");
            unlink($file);
        }
        */

        return $publicletData;

    }

    public function shareIsLegacy($hash){
        $dlFolder = $this->downloadFolder;
        $file = $dlFolder."/".$hash.".php";
        return is_file($file);
    }

    public function updateShareProperty($hash, $pName, $pValue){
        if(!$this->sqlSupported) return false;
        $relatedObjectId = $this->confStorage->simpleStoreGet("share", $hash, "serial", $data);
        if(is_array($data)){
            $data[$pName] = $pValue;
            $this->confStorage->simpleStoreSet("share", $hash, $data, "serial", $relatedObjectId);
            return true;
        }
        return false;
    }

    public function findSharesForRepo($repositoryId){
        if(!$this->sqlSupported) return array();
        return $this->confStorage->simpleStoreList("share", null, "", "serial", '%"REPOSITORY";s:32:"'.$repositoryId.'"%');
    }

    protected function updateShareType(&$shareData){
        if ( isSet($shareData["SHARE_TYPE"]) && $shareData["SHARE_TYPE"] == "publiclet" ) {
            $shareData["SHARE_TYPE"] = "file";
        } else if ( isset($shareData["REPOSITORY"]) && is_a($shareData["REPOSITORY"], "Repository") ){
            $shareData["SHARE_TYPE"] = "file";
        } else if ( isSet($shareData["PUBLICLET_PATH"]) ){
            $shareData["SHARE_TYPE"] = "minisite";
        }
    }

    public function listShares($limitToUser = '', $parentRepository = '', $cursor = null, $shareType = null){

        $dbLets = array();
        if($this->sqlSupported){
            // Get DB files
            $dbLets = $this->confStorage->simpleStoreList(
                "share",
                $cursor,
                "",
                "serial",
                (!empty($limitToUser)?'%"OWNER_ID";s:'.strlen($limitToUser).':"'.$limitToUser.'"%':''),
                $parentRepository);
        }

        // Get hardcoded files
        $files = glob(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")."/*.php");
        if($files === false) return $dbLets;
        foreach ($files as $file) {
            if(basename($file) == "share.php") continue;
            $ar = explode(".", basename($file));
            $id = array_shift($ar);
            $publicletData = $this->loadShare($id);
            if($publicletData === false) continue;
            if (!empty($limitToUser) && ( !isSet($publicletData["OWNER_ID"]) || $publicletData["OWNER_ID"] != $limitToUser )) {
                continue;
            }
            if(!empty($parentRepository) && ( (is_string($publicletData["REPOSITORY"]) && $publicletData["REPOSITORY"] != $parentRepository)
                    || (is_object($publicletData["REPOSITORY"]) && $publicletData["REPOSITORY"]->getUniqueId() != $parentRepository ) )){
                continue;
            }
            $publicletData["SHARE_TYPE"] = "file";
            $dbLets[$id] = $publicletData;
        }

        // Update share_type and filter if necessary
        foreach($dbLets as $id => &$shareData){
            if($shareData === false){
                unset($dbLets[$id]);
                continue;
            }
            $this->updateShareType($shareData);
            if(!empty($shareType) && $shareData["SHARE_TYPE"] != $shareType){
                unset($dbLets[$id]);
            }
        }

        if(empty($shareType) || $shareType == "repository"){
            // BACKWARD COMPATIBILITY: collect old-school shared repositories that are not yet stored in simpleStore
            $storedIds = array();
            foreach($dbLets as $share){
                if(empty($limitToUser) || $limitToUser == $share["OWNER_ID"]) {
                    if(is_string($share["REPOSITORY"])) $storedIds[] = $share["REPOSITORY"];
                    else if (is_object($share["REPOSITORY"])) $storedIds[] = $share["REPOSITORY"]->getUniqueId();
                }
            }
            // Find repositories that would have a parent
            $criteria = array();
            $criteria["parent_uuid"] = (empty($parentRepository) ? AJXP_FILTER_NOT_EMPTY : $parentRepository);
            $criteria["owner_user_id"] = (empty($limitToUser) ? AJXP_FILTER_NOT_EMPTY : $limitToUser);
            if(count($storedIds)){
                $criteria["!uuid"] = $storedIds;
            }
            $oldRepos = ConfService::listRepositoriesWithCriteria($criteria, $count);
            foreach($oldRepos as $sharedWorkspace){
                if(!$sharedWorkspace->hasContentFilter()){
                    $dbLets['repo-'.$sharedWorkspace->getId()] = array(
                        "SHARE_TYPE"    => "repository",
                        "OWNER_ID"      => $sharedWorkspace->getOwner(),
                        "REPOSITORY"    => $sharedWorkspace->getUniqueId(),
                        "LEGACY_REPO_OR_MINI"   => true
                    );
                    //Auto Migrate? boaf.
                    //$this->storeShare($sharedWorkspace->getParentId(), $data, "repository");
                }
            }
        }

        return $dbLets;
    }

    protected function testUserCanEditShare($userId){

        if(empty($userId)){
            $mess = ConfService::getMessages();
            throw new Exception($mess["share_center.160"]);
        }
        $crtUser = AuthService::getLoggedUser();
        if($crtUser->getId() == $userId) return true;
        if($crtUser->isAdmin()) return true;
        $user = ConfService::getConfStorageImpl()->createUserObject($userId);
        if($user->hasParent() && $user->getParent() == $crtUser->getId()){
            return true;
        }
        $mess = ConfService::getMessages();
        throw new Exception($mess["share_center.160"]);
    }

    /**
     * @param String $type
     * @param String $element
     * @throws Exception
     * @internal param String $ownerId
     * @return bool
     */
    public function deleteShare($type, $element)
    {
        $mess = ConfService::getMessages();
        AJXP_Logger::debug(__CLASS__, __FILE__, "Deleting shared element ".$type."-".$element);

        if ($type == "repository") {
            if(strpos($element, "repo-") === 0) $element = str_replace("repo-", "", $element);
            $repo = ConfService::getRepositoryById($element);
            if($repo == null) {
                // Maybe a share has
                $share = $this->loadShare($element);
                if(is_array($share) && isSet($share["REPOSITORY"])){
                    $repo = ConfService::getRepositoryById($share["REPOSITORY"]);
                }
                if($repo == null){
                    throw new Exception("Cannot find associated share");
                }
            }
            $this->testUserCanEditShare($repo->getOwner());
            $res = ConfService::deleteRepository($element);
            if ($res == -1) {
                throw new Exception($mess[427]);
            }
            if($this->sqlSupported){
                if(isSet($share)){
                    $this->confStorage->simpleStoreClear("share", $element);
                }else{
                    $shares = self::findSharesForRepo($element);
                    if(count($shares)){
                        $keys = array_keys($shares);
                        $this->confStorage->simpleStoreClear("share", $keys[0]);
                    }
                }
            }
        } else if ($type == "minisite") {
            $minisiteData = $this->loadShare($element);
            $repoId = $minisiteData["REPOSITORY"];
            $repo = ConfService::getRepositoryById($repoId);
            if ($repo == null) {
                return false;
            }
            $this->testUserCanEditShare($repo->getOwner());
            $res = ConfService::deleteRepository($repoId);
            if ($res == -1) {
                throw new Exception($mess[427]);
            }
            // Silently delete corresponding role if it exists
            AuthService::deleteRole("AJXP_SHARED-".$repoId);
            // If guest user created, remove it now.
            if (isSet($minisiteData["PRELOG_USER"]) && AuthService::userExists($minisiteData["PRELOG_USER"])) {
                AuthService::deleteUser($minisiteData["PRELOG_USER"]);
            }
            // If guest user created, remove it now.
            if (isSet($minisiteData["PRESET_LOGIN"]) && AuthService::userExists($minisiteData["PRESET_LOGIN"])) {
                AuthService::deleteUser($minisiteData["PRESET_LOGIN"]);
            }
            if(isSet($minisiteData["PUBLICLET_PATH"]) && is_file($minisiteData["PUBLICLET_PATH"])){
                unlink($minisiteData["PUBLICLET_PATH"]);
            }else if($this->sqlSupported){
                $this->confStorage->simpleStoreClear("share", $element);
            }
        } else if ($type == "user") {
            $this->testUserCanEditShare($element);
            AuthService::deleteUser($element);
        } else if ($type == "file") {
            $publicletData = $this->loadShare($element);
            if (isSet($publicletData["OWNER_ID"]) && $this->testUserCanEditShare($publicletData["OWNER_ID"])) {
                PublicletCounter::delete($element);
                if(isSet($publicletData["PUBLICLET_PATH"]) && is_file($publicletData["PUBLICLET_PATH"])){
                    unlink($publicletData["PUBLICLET_PATH"]);
                }else if($this->sqlSupported){
                    $this->confStorage->simpleStoreClear("share", $element);
                }
            } else {
                throw new Exception($mess["share_center.160"]);
            }
        }
    }

    /**
     * @param String $type
     * @param String $element
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @return bool
     */
    public function moveShareIfPossible($type, $element, $oldNode, $newNode){
        if(!$this->sqlSupported) return false;
        $this->confStorage->simpleStoreGet("share", $element, "serial", $data);
        if($oldNode->isLeaf() && $type == "minisite" && is_array($data)){
            $repo = ConfService::getRepositoryById($data["REPOSITORY"]);
            $cFilter = $repo->getContentFilter();
            if(isSet($cFilter)){
                $cFilter->movePath($oldNode->getPath(), $newNode->getPath());
            }
        }
    }

    /**
     * @param String $type
     * @param String $element
     * @return bool
     */
    public function shareExists($type, $element)
    {
        if ($type == "repository") {
            return (ConfService::getRepositoryById($element) != null);
        } else if ($type == "file" || $type == "minisite") {
            $fileExists = is_file($this->downloadFolder."/".$element.".php");
            if($fileExists) {
                return true;
            }
            if($this->sqlSupported) {
                $this->confStorage->simpleStoreGet("share", $element, "serial", $data);
                if(is_array($data)) return true;
            }
            return false;
        }
    }


    public function getCurrentDownloadCounter($hash){
        return PublicletCounter::getCount($hash);
    }

    public function incrementDownloadCounter($hash){
        PublicletCounter::increment($hash);
    }

    public function resetDownloadCounter($hash, $userId){
        $data = $this->loadShare($hash);
        // TODO We must check that the user has the right to do that!
        PublicletCounter::reset($hash);
    }

    public function isShareExpired($hash, $data){
        return ($data["EXPIRE_TIME"] && time() > $data["EXPIRE_TIME"]) ||
        ($data["DOWNLOAD_LIMIT"] && $data["DOWNLOAD_LIMIT"]> 0 && $data["DOWNLOAD_LIMIT"] <= $this->getCurrentDownloadCounter($hash));
    }



    /**
     * Computes a short form of the hash, checking if it already exists in the folder,
     * in which case it increases the hashlength until there is no collision.
     * @static
     * @param String $outputData Serialized data
     * @param String|null $checkInFolder Path to folder
     * @return string
     */
    private function computeHash($outputData, $checkInFolder = null)
    {
        $length = $this->hashMinLength;
        $full =  md5($outputData);
        $starter = substr($full, 0, $length);
        if ($checkInFolder != null) {
            while (file_exists($checkInFolder.DIRECTORY_SEPARATOR.$starter.".php")) {
                $length ++;
                $starter = substr($full, 0, $length);
            }
        }
        return $starter;
    }

    /**
     * Check if the hash seems to correspond to the serialized data.
     * @static
     * @param String $outputData serialized data
     * @param String $hash Id to check
     * @return bool
     */
    private function checkHash($outputData, $hash)
    {
        $full = md5($outputData);
        return (!empty($hash) && strpos($full, $hash."") === 0);
    }


}