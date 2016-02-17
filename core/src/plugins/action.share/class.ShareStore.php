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
require_once("class.ShareLink.php");

class ShareStore {

    var $sqlSupported = false;
    var $downloadFolder;
    var $hashMinLength;
    public $modifiableShareKeys = array("counter", "tags", "short_form_url");
    /**
     * @var sqlConfDriver
     */
    var $confStorage;

    var $shareMetaManager;

    public function __construct($downloadFolder, $hashMinLength = 32){
        $this->downloadFolder = $downloadFolder;
        $this->hashMinLength = $hashMinLength;
        $storage = ConfService::getConfStorageImpl();
        if($storage->getId() == "conf.sql") {
            $this->sqlSupported = true;
            $this->confStorage = $storage;
        }
    }

    /**
     * @return ShareMetaManager
     */
    public function getMetaManager(){
        if(!isSet($this->shareMetaManager)){
            require_once("class.ShareMetaManager.php");
            $this->shareMetaManager = new ShareMetaManager($this);
        }
        return $this->shareMetaManager;
    }

    /**
     * Create a share.php file in the download folder.
     * @throws Exception
     */
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
     * @param array $shareData
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

    /**
     * Initialize an empty ShareLink object.
     * @return ShareLink
     */
    public function createEmptyShareObject(){
        $shareObject = new ShareLink($this);
        if(AuthService::usersEnabled()){
            $shareObject->setOwnerId(AuthService::getLoggedUser()->getId());
        }
        return $shareObject;
    }

    /**
     * Initialize a ShareLink from persisted data.
     * @param string $hash
     * @return ShareLink
     * @throws Exception
     */
    public function loadShareObject($hash){
        $data = $this->loadShare($hash);
        if($data === false){
            throw new Exception("Cannot find share with hash ".$hash);
        }
        $shareObject = new ShareLink($this, $data);
        $shareObject->setHash($hash);
        return $shareObject;
    }

    /**
     * Load data persisted on DB or on publiclet files.
     * @param $hash
     * @return array|bool|mixed
     */
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

    /**
     * Test if hash.php is a real file.
     * @param string $hash
     * @return bool
     */
    public function shareIsLegacy($hash){
        $dlFolder = $this->downloadFolder;
        $file = $dlFolder."/".$hash.".php";
        return is_file($file);
    }

    /**
     * Update a single share property
     * @param string $hash
     * @param string $pName
     * @param string $pValue
     * @return bool
     * @throws Exception
     */
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

    /**
     * List shares based on child repository ID;
     * @param $repositoryId
     * @return array
     */
    public function findSharesForRepo($repositoryId){
        if(!$this->sqlSupported) return array();
        return $this->confStorage->simpleStoreList("share", null, "", "serial", '%"REPOSITORY";s:32:"'.$repositoryId.'"%');
    }

    /**
     * Update share type from legacy values to new ones.
     * @param $shareData
     */
    protected function updateShareType(&$shareData){
        if ( isSet($shareData["SHARE_TYPE"]) && $shareData["SHARE_TYPE"] == "publiclet" ) {
            $shareData["SHARE_TYPE"] = "file";
        } else if ( isset($shareData["REPOSITORY"]) && is_a($shareData["REPOSITORY"], "Repository") ){
            $shareData["SHARE_TYPE"] = "file";
        } else if ( isSet($shareData["PUBLICLET_PATH"]) ){
            $shareData["SHARE_TYPE"] = "minisite";
        }
    }

    /**
     * List all shares persisted in DB and on file.
     * @param string $limitToUser
     * @param string $parentRepository
     * @param null $cursor
     * @param null $shareType
     * @return array
     */
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
            $criteria["parent_uuid"] = ($parentRepository == "" ? AJXP_FILTER_NOT_EMPTY : $parentRepository);
            $criteria["owner_user_id"] = ($limitToUser == "" ? AJXP_FILTER_NOT_EMPTY : $limitToUser);
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

    /**
     * @param string $userId Share OWNER user ID / Will be compared to the currently logged user ID
     * @param array|null $shareData Share Data
     * @return bool Wether currently logged user can view/edit this share or not.
     * @throws Exception
     */
    public function testUserCanEditShare($userId, $shareData){

        if($shareData !== null && isSet($shareData["SHARE_ACCESS"]) && $shareData["SHARE_ACCESS"] == "public"){
            return true;
        }
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
     * @return bool
     */
    public function deleteShare($type, $element, $keepRepository = false)
    {
        $mess = ConfService::getMessages();
        AJXP_Logger::debug(__CLASS__, __FILE__, "Deleting shared element ".$type."-".$element);

        if ($type == "repository") {
            if(strpos($element, "repo-") === 0) $element = str_replace("repo-", "", $element);
            $repo = ConfService::getRepositoryById($element);
            $share = $this->loadShare($element);
            if($repo == null) {
                // Maybe a share has
                if(is_array($share) && isSet($share["REPOSITORY"])){
                    $repo = ConfService::getRepositoryById($share["REPOSITORY"]);
                }
                if($repo == null){
                    throw new Exception("repo-not-found");
                }
                $element = $share["REPOSITORY"];
            }
            $this->testUserCanEditShare($repo->getOwner(), $repo->options);
            $res = ConfService::deleteRepository($element);
            if ($res == -1) {
                throw new Exception($mess[427]);
            }
            if($this->sqlSupported){
                if(isSet($share)){
                    $this->confStorage->simpleStoreClear("share", $element);
                }else{
                    $shares = $this->findSharesForRepo($element);
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
                throw new Exception('repo-not-found');
            }
            $this->testUserCanEditShare($repo->getOwner(), $repo->options);
            if(!$keepRepository){
                $res = ConfService::deleteRepository($repoId);
                if ($res == -1) {
                    throw new Exception($mess[427]);
                }
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
            $this->testUserCanEditShare($element, array());
            AuthService::deleteUser($element);
        } else if ($type == "file") {
            $publicletData = $this->loadShare($element);
            if (isSet($publicletData["OWNER_ID"]) && $this->testUserCanEditShare($publicletData["OWNER_ID"], $publicletData)) {
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
     * @param AJXP_Node $baseNode
     * @param bool $delete
     * @param string $oldPath
     * @param string $newPath
     * @param string|null $parentRepositoryPath
     */
    public function moveSharesFromMetaRecursive($baseNode, $delete = false, $oldPath, $newPath, $parentRepositoryPath = null){

        // Find shares in children
        try{
            $result = $this->getMetaManager()->collectSharesIncludingChildren($baseNode);
        }catch(Exception $e){
            // Error while loading node, ignore
            return;
        }
        $basePath = $baseNode->getPath();
        foreach($result as $relativePath => $metadata){
            if($relativePath == "/") {
                $relativePath = "";
            }
            $changeOldNode = new AJXP_Node("pydio://".$baseNode->getRepositoryId().$oldPath.$relativePath);

            foreach($metadata as $ownerId => $meta){
                if(!isSet($meta["shares"])){
                    continue;
                }
                $changeOldNode->setUser($ownerId);
                /// do something
                $changeNewNode = null;
                if(!$delete){
                    //$newPath = preg_replace('#^'.preg_quote($oldPath, '#').'#', $newPath, $path);
                    $changeNewNode = new AJXP_Node("pydio://".$baseNode->getRepositoryId().$newPath.$relativePath);
                    $changeNewNode->setUser($ownerId);
                }
                $collectedRepositories = array();
                list($privateShares, $publicShares) = $this->moveSharesFromMeta($meta["shares"], $delete?"delete":"move", $changeOldNode, $changeNewNode, $collectedRepositories, $parentRepositoryPath);

                if($basePath == "/"){
                    // Just update target node!
                    $changeMetaNode = new AJXP_Node("pydio://".$baseNode->getRepositoryId().$relativePath);
                    $changeMetaNode->setUser($ownerId);
                    $this->getMetaManager()->clearNodeMeta($changeMetaNode);
                    if(count($privateShares)){
                        $this->getMetaManager()->setNodeMeta($changeMetaNode, array("shares" => $privateShares), true);
                    }
                    if(count($publicShares)){
                        $this->getMetaManager()->setNodeMeta($changeMetaNode, array("shares" => $privateShares), false);
                    }
                }else{
                    $this->getMetaManager()->clearNodeMeta($changeOldNode);
                    if(!$delete){
                        if(count($privateShares)){
                            $this->getMetaManager()->setNodeMeta($changeNewNode, array("shares" => $privateShares), true);
                        }
                        if(count($publicShares)){
                            $this->getMetaManager()->setNodeMeta($changeNewNode, array("shares" => $privateShares), false);
                        }
                    }
                }

                foreach($collectedRepositories as $sharedRepoId => $parentRepositoryPath){
                    $this->moveSharesFromMetaRecursive(new AJXP_Node("pydio://".$sharedRepoId."/"), $delete, $changeOldNode->getPath(), $changeNewNode->getPath(), $parentRepositoryPath);
                }

            }
        }


    }

    /**
     * @param array $shares
     * @param String $operation
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param array $collectRepositories
     * @param string|null $parentRepositoryPath
     * @return array
     * @throws Exception
     */
    public function moveSharesFromMeta($shares, $operation="move", $oldNode, $newNode=null, &$collectRepositories = array(), $parentRepositoryPath = null){

        $privateShares = array();
        $publicShares = array();
        foreach($shares as $id => $data){
            $type = $data["type"];
            if($operation == "delete"){
                $this->deleteShare($type, $id);
                continue;
            }

            if($type == "minisite"){
                $share = $this->loadShare($id);
                $repo = ConfService::getRepositoryById($share["REPOSITORY"]);
            }else if($type == "repository"){
                $repo = ConfService::getRepositoryById($id);
            }else if($type == "file"){
                $publicLink = $this->loadShare($id);
            }

            if(isSet($repo)){
                $cFilter = $repo->getContentFilter();
                $path = $repo->getOption("PATH", true);
                $save = false;
                if(isSet($cFilter)){
                    if($parentRepositoryPath !== null){
                        $repo->addOption("PATH", $parentRepositoryPath);
                    }else{
                        $cFilter->movePath($oldNode->getPath(), $newNode->getPath());
                        $repo->setContentFilter($cFilter);
                    }
                    $save = true;
                }else if(!empty($path)){
                    $path = preg_replace("#".preg_quote($oldNode->getPath(), "#")."$#", $newNode->getPath(), $path);
                    $repo->addOption("PATH", $path);
                    $save = true;
                    $collectRepositories[$repo->getId()] = $path;
                }
                if($save){
                    ConfService::getConfStorageImpl()->saveRepository($repo, true);
                }
                $access = $repo->getOption("SHARE_ACCESS");
                if(!empty($access) && $access == "PUBLIC"){
                    $publicShares[$id] = $data;
                }else{
                    $privateShares[$id] = $data;
                }

            } else {

                if(isset($publicLink) && is_array($publicLink) && isSet($publicLink["FILE_PATH"])){
                    $publicLink["FILE_PATH"] = str_replace($oldNode->getPath(), $newNode->getPath(), $publicLink["FILE_PATH"]);
                    $this->deleteShare("file", $id);
                    $this->storeShare($newNode->getRepositoryId(), $publicLink, "file", $id);
                    $privateShares[$id] = $data;
                }
            }
        }
        return array($privateShares, $publicShares);
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
        }
        return false;
    }


    /**
     * Get download counter
     * @param string $hash
     * @return int
     */
    public function getCurrentDownloadCounter($hash){
        return PublicletCounter::getCount($hash);
    }

    /**
     * Add a unit to the current download counter value.
     * @param $hash
     */
    public function incrementDownloadCounter($hash){
        PublicletCounter::increment($hash);
    }

    /**
     * Set the counter value to 0.
     * @param string $hash
     * @param string $userId
     * @throws Exception
     */
    public function resetDownloadCounter($hash, $userId){
        $data = $this->loadShare($hash);
        $repoId = $data["REPOSITORY"];
        $repo = ConfService::getRepositoryById($repoId);
        if ($repo == null) {
            throw new Exception("Cannot find associated share");
        }
        $this->testUserCanEditShare($repo->getOwner(), $repo->options);
        PublicletCounter::reset($hash);
    }

    /**
     * Check if share is expired
     * @param string $hash
     * @param array $data
     * @return bool
     */
    public function isShareExpired($hash, $data){
        return ($data["EXPIRE_TIME"] && time() > $data["EXPIRE_TIME"]) ||
        ($data["DOWNLOAD_LIMIT"] && $data["DOWNLOAD_LIMIT"]> 0 && $data["DOWNLOAD_LIMIT"] <= $this->getCurrentDownloadCounter($hash));
    }

    /**
     * Find all expired shares and remove them.
     * @param bool|true $currentUser
     * @return array
     */
    public function clearExpiredFiles($currentUser = true)
    {
        if($currentUser){
            $loggedUser = AuthService::getLoggedUser();
            $userId = $loggedUser->getId();
            $originalUser = null;
        }else{
            $originalUser = AuthService::getLoggedUser()->getId();
            $userId = null;
        }
        $deleted = array();
        $switchBackToOriginal = false;

        $publicLets = $this->listShares($currentUser? $userId: '');
        foreach ($publicLets as $hash => $publicletData) {
            if($publicletData === false) continue;
            if ($currentUser && ( !isSet($publicletData["OWNER_ID"]) || $publicletData["OWNER_ID"] != $userId )) {
                continue;
            }
            if( (isSet($publicletData["EXPIRE_TIME"]) && is_numeric($publicletData["EXPIRE_TIME"]) && $publicletData["EXPIRE_TIME"] > 0 && $publicletData["EXPIRE_TIME"] < time()) ||
                (isSet($publicletData["DOWNLOAD_LIMIT"]) && $publicletData["DOWNLOAD_LIMIT"] > 0 && $publicletData["DOWNLOAD_LIMIT"] <= $publicletData["DOWNLOAD_COUNT"]) ) {
                if(!$currentUser) $switchBackToOriginal = true;
                $this->deleteExpiredPubliclet($hash, $publicletData);
                $deleted[] = $publicletData["FILE_PATH"];

            }
        }
        if($switchBackToOriginal){
            AuthService::logUser($originalUser, "", true);
        }
        return $deleted;
    }

    /**
     * Find all expired legacy publiclets and remove them.
     * @param $elementId
     * @param $data
     * @throws Exception
     */
    private function deleteExpiredPubliclet($elementId, $data){

        if(AuthService::getLoggedUser() == null ||  AuthService::getLoggedUser()->getId() != $data["OWNER_ID"]){
            AuthService::logUser($data["OWNER_ID"], "", true);
        }
        $repoObject = $data["REPOSITORY"];
        if(!is_a($repoObject, "Repository")) {
            $repoObject = ConfService::getRepositoryById($data["REPOSITORY"]);
        }
        $repoLoaded = false;

        if(!empty($repoObject)){
            try{
                ConfService::loadDriverForRepository($repoObject)->detectStreamWrapper(true);
                $repoLoaded = true;
            }catch (Exception $e){
                // Cannot load this repository anymore.
            }
        }
        if($repoLoaded){
            AJXP_Controller::registryReset();
            $ajxpNode = new AJXP_Node("pydio://".$repoObject->getId().$data["FILE_PATH"]);
        }
        $this->deleteShare("file", $elementId);
        if(isSet($ajxpNode)){
            try{
                $this->getMetaManager()->removeShareFromMeta($ajxpNode, $elementId);
            }catch (Exception $e){

            }
            gc_collect_cycles();
        }

    }



    /**
     * @param array $data
     * @param AbstractAccessDriver $accessDriver
     * @param Repository $repository
     */
    public function storeSafeCredentialsIfNeeded(&$data, $accessDriver, $repository){
        $storeCreds = false;
        if ($repository->getOption("META_SOURCES")) {
            $options["META_SOURCES"] = $repository->getOption("META_SOURCES");
            foreach ($options["META_SOURCES"] as $metaSource) {
                if (isSet($metaSource["USE_SESSION_CREDENTIALS"]) && $metaSource["USE_SESSION_CREDENTIALS"] === true) {
                    $storeCreds = true;
                    break;
                }
            }
        }
        if ($storeCreds || $accessDriver->hasMixin("credentials_consumer")) {
            $cred = AJXP_Safe::tryLoadingCredentialsFromSources(array(), $repository);
            if (isSet($cred["user"]) && isset($cred["password"])) {
                $data["SAFE_USER"] = $cred["user"];
                $data["SAFE_PASS"] = $cred["password"];
            }
        }
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