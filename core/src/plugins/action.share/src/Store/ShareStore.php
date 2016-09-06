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
namespace Pydio\Share\Store;


use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\Repository;

use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Conf\Sql\SqlConfDriver;

use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Log\Core\Logger;
use Pydio\OCS\Model\TargettedLink;
use Pydio\Share\Model\ShareLink;
use Pydio\Share\ShareCenter;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class ShareStore
 * @package Pydio\Share\Store
 */
class ShareStore {

    var $sqlSupported = false;
    var $legacyPublicFolder;
    /**
     * @var int
     */
    private $hashMinLength;

    public $modifiableShareKeys = ["counter", "tags", "short_form_url"];
    /**
     * @var SqlConfDriver
     */
    var $confStorage;

    var $shareMetaManager;

    /** @var  ContextInterface */
    private $context;

    /**
     * ShareStore constructor.
     * @param ContextInterface $context
     * @param string $downloadFolder
     * @param int $hashMinLength
     */
    public function __construct(ContextInterface $context, $downloadFolder, $hashMinLength = 32){
        $this->context = $context;
        $this->legacyPublicFolder = $downloadFolder;
        $this->hashMinLength = $hashMinLength;
        $storage = ConfService::getConfStorageImpl();
        if($storage->getId() == "conf.sql") {
            $this->sqlSupported = true;
            $this->confStorage = $storage;
        }
    }

    /**
     * @return int
     */
    public function getHashMinLength(){
        return $this->hashMinLength;
    }

    /**
     * @return ShareMetaManager
     */
    public function getMetaManager(){
        if(!isSet($this->shareMetaManager)){
            $this->shareMetaManager = new ShareMetaManager($this);
        }
        return $this->shareMetaManager;
    }

    /**
     * @param String $parentRepositoryId
     * @param array $shareData
     * @param string $type
     * @param String $existingHash
     * @param null $updateHash
     * @throws \Exception
     * @return string $hash
     */
    public function storeShare($parentRepositoryId, $shareData, $type="minisite", $existingHash = null, $updateHash = null){

        if(!$this->sqlSupported){
            throw new \Pydio\Core\Exception\PydioException("Please setup an SQL connexion to use sharing features");
        }
        $data = serialize($shareData);
        if($existingHash){
            $hash = $existingHash;
        }else{
            $hash = $this->computeHash($data, $this->legacyPublicFolder);
        }
        
        $shareData["SHARE_TYPE"] = $type;
        if($updateHash != null){
            $this->confStorage->simpleStoreClear("share", $existingHash);
            $hash = $updateHash;
        }
        $this->confStorage->simpleStoreSet("share", $hash, $shareData, "serial", $parentRepositoryId);
        return $hash;

    }

    /**
     * Initialize an empty ShareLink object.
     * @return ShareLink
     */
    public function createEmptyShareObject(){
        $shareObject = new ShareLink($this);
        if(UsersService::usersEnabled()){
            $shareObject->setOwnerId($this->context->getUser()->getId());
        }
        return $shareObject;
    }

    /**
     * Initialize a ShareLink from persisted data.
     * @param string $hash
     * @return ShareLink
     * @throws \Exception
     */
    public function loadShareObject($hash){
        $data = $this->loadShare($hash);
        if($data === false){
            $mess = LocaleService::getMessages();
            throw new \Exception(str_replace('%s', 'Cannot find share with hash '.$hash, $mess["share_center.219"]));
        }
        if(isSet($data["TARGET"]) && $data["TARGET"] == "remote"){
            $shareObject = new TargettedLink($this, $data);
        }else{
            $shareObject = new ShareLink($this, $data);
        }
        $shareObject->setHash($hash);
        return $shareObject;
    }

    /**
     * Load data persisted on DB or on publiclet files.
     * @param $hash
     * @return array|bool|mixed
     */
    public function loadShare($hash){

        $dlFolder = $this->legacyPublicFolder;
        $file = $dlFolder."/".$hash.".php";
        if(!is_file($file)) {
            if($this->sqlSupported){
                $this->confStorage->simpleStoreGet("share", $hash, "serial", $data);
                if(!empty($data)){
                    return $data;
                }
            }
            return [];
        }
        class_alias("Pydio\\Share\\ShareCenter", "ShareCenter");
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
        $publicletData = @unserialize($inputData);
        $publicletData["PUBLICLET_PATH"] = $file;

        return $publicletData;

    }

    /**
     * Test if hash.php is a real file.
     * @param string $hash
     * @return bool
     */
    public function shareIsLegacy($hash){
        $dlFolder = $this->legacyPublicFolder;
        $file = $dlFolder."/".$hash.".php";
        return is_file($file);
    }

    /**
     * Update a single share property
     * @param string $hash
     * @param string $pName
     * @param string $pValue
     * @return bool
     * @throws \Exception
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
        if(!$this->sqlSupported) return [];
        $cursor = null;
        return $this->confStorage->simpleStoreList("share", $cursor, "", "serial", '%"REPOSITORY";s:32:"'.$repositoryId.'"%');
    }

    /**
     * Update share type from legacy values to new ones.
     * @param $shareData
     */
    protected function updateShareType(&$shareData){
        if ( isSet($shareData["SHARE_TYPE"]) && $shareData["SHARE_TYPE"] == "publiclet" ) {
            $shareData["SHARE_TYPE"] = "file";
        } else if ( isset($shareData["REPOSITORY"]) && $shareData["REPOSITORY"] instanceof Repository ){
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
    public function listShares($limitToUser = '', $parentRepository = '', &$cursor = null, $shareType = null){

        $dbLets = [];
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
            $storedIds = [];
            foreach($dbLets as $share){
                if(empty($limitToUser) || $limitToUser == $share["OWNER_ID"]) {
                    if(is_string($share["REPOSITORY"])) $storedIds[] = $share["REPOSITORY"];
                    else if (is_object($share["REPOSITORY"])) $storedIds[] = $share["REPOSITORY"]->getUniqueId();
                }
            }
            // Find repositories that would have a parent
            $criteria = [];
            $criteria["parent_uuid"] = ($parentRepository == "" ? AJXP_FILTER_NOT_EMPTY : $parentRepository);
            $criteria["owner_user_id"] = ($limitToUser == "" ? AJXP_FILTER_NOT_EMPTY : $limitToUser);
            if(count($storedIds)){
                $criteria["!uuid"] = $storedIds;
            }
            $otherCountOnly = false;
            if(isSet($cursor)){
                $offset = $cursor[0];
                $limit = $cursor[1];
                $loadedDbLets = count($dbLets);
                $totalDbLets = $cursor["total"];
                $newPosition = max(0, $offset - $totalDbLets);
                if($loadedDbLets >= $limit){
                    //return $dbLets;
                    $criteria["CURSOR"] = ["OFFSET" => 0, "LIMIT" => 1];
                    $otherCountOnly = true;
                }else{
                    if($loadedDbLets > 0) $limit = $limit - $loadedDbLets;
                    $criteria["CURSOR"] = ["OFFSET" => $newPosition, "LIMIT" => $limit];
                }
            }
            $oldRepos = RepositoryService::listRepositoriesWithCriteria($criteria, $count);
            if(!$otherCountOnly){
                foreach($oldRepos as $sharedWorkspace){
                    $dbLets['repo-'.$sharedWorkspace->getId()] = [
                        "SHARE_TYPE"    => "repository",
                        "OWNER_ID"      => $sharedWorkspace->getOwner(),
                        "REPOSITORY"    => $sharedWorkspace->getUniqueId(),
                        "LEGACY_REPO_OR_MINI"   => true
                    ];
                }
            }
            if(isSet($cursor)){
                $cursor["total"] += $count;
            }
        }

        return $dbLets;
    }

    /**
     * @param string $userId Share OWNER user ID / Will be compared to the currently logged user ID
     * @param array|null $shareData Share Data
     * @return bool Wether currently logged user can view/edit this share or not.
     * @throws \Exception
     */
    public function testUserCanEditShare($userId, $shareData){

        if($shareData !== null && isSet($shareData["SHARE_ACCESS"]) && $shareData["SHARE_ACCESS"] == "public"){
            return true;
        }
        if(empty($userId)){
            $mess = LocaleService::getMessages();
            throw new \Exception($mess["share_center.160"]);
        }
        $crtUser = $this->context->getUser();
        if($crtUser->getId() == $userId) return true;
        $user = UsersService::getUserById($userId);
        if($crtUser->isAdmin() && $crtUser->canAdministrate($user)) {
            return true;
        }
        if($user->hasParent() && $user->getParent() == $crtUser->getId()){
            return true;
        }
        $mess = LocaleService::getMessages();
        throw new \Exception($mess["share_center.160"]);
    }

    /**
     * @param String $type
     * @param String $element
     * @param bool $keepRepository
     * @param bool $ignoreRepoNotFound
     * @param AJXP_Node $ajxpNode
     * @return bool
     * @throws \Exception
     */
    public function deleteShare($type, $element, $keepRepository = false, $ignoreRepoNotFound = false, $ajxpNode = null)
    {
        $mess = LocaleService::getMessages();
        Logger::debug(__CLASS__, __FILE__, "Deleting shared element ".$type."-".$element);

        if ($type == "repository") {
            if(strpos($element, "repo-") === 0) $element = str_replace("repo-", "", $element);
            $repo = RepositoryService::getRepositoryById($element);
            $share = $this->loadShare($element);
            if($repo == null) {
                // Maybe a share has
                if(is_array($share) && isSet($share["REPOSITORY"])){
                    $repo = RepositoryService::getRepositoryById($share["REPOSITORY"]);
                }
                if(isSet($share["OWNER_ID"])) {
                    $owner = $share["OWNER_ID"];
                }
                if($repo == null && !$ignoreRepoNotFound){
                    throw new \Exception(str_replace('%s', 'Cannot find associated repository', $mess["share_center.219"]));
                }
            }
            if($repo != null){
                $owner = $repo->getOwner();
                $this->testUserCanEditShare($repo->getOwner(), $repo->options);
                $res = RepositoryService::deleteRepository($element);
                if ($res == -1) {
                    throw new \Exception($mess[427]);
                }
            }
            if($ajxpNode != null){
                if(isSet($owner) && $owner !== $this->context->getUser()->getId()){
                    $ajxpNode->setUserId($owner);
                }
                $this->getMetaManager()->removeShareFromMeta($ajxpNode, $element);
            }
            if($this->sqlSupported){
                if(isSet($share) && count($share)){
                    $this->confStorage->simpleStoreClear("share", $element);
                }else{
                    $shares = $this->findSharesForRepo($element);
                    if(count($shares)){
                        $keys = array_keys($shares);
                        $this->confStorage->simpleStoreClear("share", $keys[0]);
                        if($ajxpNode != null){
                            $this->getMetaManager()->removeShareFromMeta($ajxpNode, $keys[0]);
                        }
                    }
                }
            }
        } else if ($type == "minisite") {
            $minisiteData = $this->loadShare($element);
            $repoId = $minisiteData["REPOSITORY"];
            $repo = RepositoryService::getRepositoryById($repoId);
            if ($repo == null) {
                if(!$ignoreRepoNotFound) {
                    throw new \Exception(str_replace('%s', 'Cannot find associated repository', $mess["share_center.219"]));
                }
            }else{
                $owner = $repo->getOwner();
                $this->testUserCanEditShare($repo->getOwner(), $repo->options);
            }
            if(!$keepRepository){
                $res = RepositoryService::deleteRepository($repoId);
                if ($res == -1) {
                    throw new \Exception($mess[427]);
                }
            }
            // Silently delete corresponding role if it exists
            RolesService::deleteRole("AJXP_SHARED-" . $repoId);
            // If guest user created, remove it now.
            if (isSet($minisiteData["PRELOG_USER"]) && UsersService::userExists($minisiteData["PRELOG_USER"])) {
                UsersService::deleteUser($minisiteData["PRELOG_USER"]);
            }
            // If guest user created, remove it now.
            if (isSet($minisiteData["PRESET_LOGIN"]) && UsersService::userExists($minisiteData["PRESET_LOGIN"])) {
                UsersService::deleteUser($minisiteData["PRESET_LOGIN"]);
            }
            if(isSet($minisiteData["PUBLICLET_PATH"]) && is_file($minisiteData["PUBLICLET_PATH"])){
                unlink($minisiteData["PUBLICLET_PATH"]);
            }else if($this->sqlSupported){
                $this->confStorage->simpleStoreClear("share", $element);
            }
            if($ajxpNode !== null){
                if(isSet($owner) && $owner !== $this->context->getUser()->getId()){
                    $ajxpNode->setUserId($owner);
                }
                $this->getMetaManager()->removeShareFromMeta($ajxpNode, $element);
                if(!$keepRepository){
                    $this->getMetaManager()->removeShareFromMeta($ajxpNode, $repoId);
                }
            }
        } else if ($type == "user") {
            $this->testUserCanEditShare($element, []);
            UsersService::deleteUser($element);
        } else if ($type == "file") {
            $publicletData = $this->loadShare($element);
            if ($publicletData!== false && isSet($publicletData["OWNER_ID"]) && $this->testUserCanEditShare($publicletData["OWNER_ID"], $publicletData)) {
                if(isSet($publicletData["PUBLICLET_PATH"]) && is_file($publicletData["PUBLICLET_PATH"])){
                    unlink($publicletData["PUBLICLET_PATH"]);
                }else if($this->sqlSupported){
                    $this->confStorage->simpleStoreClear("share", $element);
                }
            } else {
                throw new \Exception($mess["share_center.160"]);
            }
        }
        return true;
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $baseNode
     * @param bool $delete
     * @param string $oldPath
     * @param string $newPath
     * @param string|null $parentRepositoryPath
     * @return int Number of nodes modified in different repositories
     */
    public function moveSharesFromMetaRecursive($baseNode, $delete = false, $oldPath, $newPath, $parentRepositoryPath = null){

        $modifiedDifferentNodes = 0;
        // Find shares in children
        try{
            $result = $this->getMetaManager()->collectSharesIncludingChildren($baseNode);
        }catch(\Exception $e){
            // Error while loading node, ignore
            return $modifiedDifferentNodes;
        }
        $basePath = $baseNode->getPath();
        foreach($result as $relativePath => $metadata){
            if($relativePath == "/") {
                $relativePath = "";
            }
            $modifiedDifferentNodes ++;
            $changeOldNode = new AJXP_Node($baseNode->getContext()->getUrlBase().$oldPath.$relativePath);

            foreach($metadata as $ownerId => $meta){
                if(!isSet($meta["shares"])){
                    continue;
                }
                $changeOldNode->setUserId($ownerId);
                /// do something
                $changeNewNode = null;
                if(!$delete){
                    //$newPath = preg_replace('#^'.preg_quote($oldPath, '#').'#', $newPath, $path);
                    $changeNewNode = new AJXP_Node($baseNode->getContext()->getUrlBase().$newPath.$relativePath);
                    $changeNewNode->setUserId($ownerId);
                }
                $collectedRepositories = [];
                list($privateShares, $publicShares) = $this->moveSharesFromMeta($meta["shares"], $delete?"delete":"move", $changeOldNode, $changeNewNode, $collectedRepositories, $parentRepositoryPath);

                if($basePath == "/"){
                    // Just update target node!
                    $changeMetaNode = new AJXP_Node($baseNode->getContext()->getUrlBase().$relativePath);
                    $changeMetaNode->setUserId($ownerId);
                    $this->getMetaManager()->clearNodeMeta($changeMetaNode);
                    if(count($privateShares)){
                        $this->getMetaManager()->setNodeMeta($changeMetaNode, ["shares" => $privateShares], true);
                    }
                    if(count($publicShares)){
                        $this->getMetaManager()->setNodeMeta($changeMetaNode, ["shares" => $privateShares], false);
                    }
                }else{
                    $this->getMetaManager()->clearNodeMeta($changeOldNode);
                    if(!$delete){
                        if(count($privateShares)){
                            $this->getMetaManager()->setNodeMeta($changeNewNode, ["shares" => $privateShares], true);
                        }
                        if(count($publicShares)){
                            $this->getMetaManager()->setNodeMeta($changeNewNode, ["shares" => $privateShares], false);
                        }
                    }
                }

                foreach($collectedRepositories as $sharedRepoId => $parentRepositoryPath){
                    $ctx = new Context($ownerId, $sharedRepoId);
                    $modifiedDifferentNodes += $this->moveSharesFromMetaRecursive(new AJXP_Node($ctx->getUrlBase()), $delete, $changeOldNode->getPath(), $changeNewNode->getPath(), $parentRepositoryPath);
                }

            }
        }

        return $modifiedDifferentNodes;

    }

    /**
     * @param array $shares
     * @param String $operation
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldNode
     * @param \Pydio\Access\Core\Model\AJXP_Node $newNode
     * @param array $collectRepositories
     * @param string|null $parentRepositoryPath
     * @return array
     * @throws \Exception
     */
    public function moveSharesFromMeta($shares, $operation="move", $oldNode, $newNode=null, &$collectRepositories = [], $parentRepositoryPath = null){

        $privateShares = [];
        $publicShares = [];
        foreach($shares as $id => $data){
            $type = $data["type"];
            if($operation == "delete"){
                $this->deleteShare($type, $id, false, true);
                continue;
            }

            if($type == "minisite"){
                $share = $this->loadShare($id);
                $repo = RepositoryService::getRepositoryById($share["REPOSITORY"]);
            }else if($type == "repository"){
                $repo = RepositoryService::getRepositoryById($id);
            }else if($type == "file"){
                $publicLink = $this->loadShare($id);
            }

            if(isSet($repo)){
                $oldNodeLabel = $oldNode->getLabel();
                $newNodeLabel = $newNode->getLabel();
                if($newNode != null && $newNodeLabel != $oldNodeLabel && $repo->getDisplay() == $oldNodeLabel){
                    $repo->setDisplay($newNodeLabel);
                }
                $cFilter = $repo->getContentFilter();
                $path = $repo->getSafeOption("PATH");
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

                    $oldNodePath = $oldNode->getPath();
                    $newNodePath = $newNode->getPath();

                    $path = preg_replace("#".preg_quote($oldNodePath, "#")."$#", $newNodePath, $path);
                    $repo->addOption("PATH", $path);
                    $save = true;
                    $collectRepositories[$repo->getId()] = $path;
                }
                if($save){
                    //ConfService::getConfStorageImpl()->saveRepository($repo, true);
                    RepositoryService::replaceRepository($repo->getId(), $repo);
                }
                $access = $repo->getSafeOption("SHARE_ACCESS");
                if(!empty($access) && $access == "PUBLIC"){
                    $publicShares[$id] = $data;
                }else{
                    $privateShares[$id] = $data;
                }

            } else {

                if(isset($publicLink) && is_array($publicLink) && isSet($publicLink["FILE_PATH"])){
                    $oldNodePath = $oldNode->getPath();
                    $newNodePath = $newNode->getPath();
                    $publicLink["FILE_PATH"] = str_replace($oldNodePath, $newNodePath, $publicLink["FILE_PATH"]);
                    $this->deleteShare("file", $id);
                    $this->storeShare($newNode->getRepositoryId(), $publicLink, "file", $id);
                    $privateShares[$id] = $data;
                }
            }
        }
        return [$privateShares, $publicShares];
    }

    /**
     * @param String $type
     * @param String $element
     * @return bool
     */
    public function shareExists($type, $element)
    {
        if ($type == "repository") {
            return (RepositoryService::getRepositoryById($element) != null);
        } else if($type == "ocs_remote"){
            return true;
        } else if ($type == "file" || $type == "minisite") {
            $fileExists = is_file($this->legacyPublicFolder."/".$element.".php");
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
     * Set the counter value to 0.
     * @param string $hash
     * @param string $userId
     * @throws \Exception
     */
    public function resetDownloadCounter($hash, $userId){
        $share = $this->loadShareObject($hash);
        $repoId = $share->getRepositoryId();
        $repo = RepositoryService::getRepositoryById($repoId);
        if ($repo == null) {
            $mess = LocaleService::getMessages();
            throw new \Exception(str_replace('%s', 'Cannot find associated repository', $mess["share_center.219"]));
        }
        $this->testUserCanEditShare($repo->getOwner(), $repo->options);
        $share->resetDownloadCount();
        $share->save();
    }
    
    /**
     * Find all expired shares and remove them.
     * @param bool|true $currentUser
     * @return array
     */
    public function clearExpiredFiles($currentUser = true)
    {
        if($currentUser){
            $loggedUser = $this->context->getUser();
            $userId = $loggedUser->getId();
            $originalUser = null;
        }else{
            $originalUser = $this->context->getUser()->getId();
            $userId = null;
        }
        $deleted = [];
        $switchBackToOriginal = false;

        $publicLets = $this->listShares($currentUser? $userId: '');
        foreach ($publicLets as $hash => $publicletData) {
            if($publicletData === false) continue;
            if ($currentUser && ( !isSet($publicletData["OWNER_ID"]) || $publicletData["OWNER_ID"] != $userId )) {
                continue;
            }
            if (ShareLink::isShareExpired($publicletData)){
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
     * Delete an expired publiclets.
     * @param $elementId
     * @param $data
     * @throws \Exception
     */
    private function deleteExpiredPubliclet($elementId, $data){

        // TODO: REWRITE THIS FUNCTION

        if($this->context->hasUser() ||  $this->context->getUser()->getId() != $data["OWNER_ID"]){

            AuthService::logUser($data["OWNER_ID"], "", true);
        }
        $repoObject = $data["REPOSITORY"];
        if(!($repoObject instanceof Repository)) {
            $repoObject = RepositoryService::getRepositoryById($data["REPOSITORY"]);
        }
        $repoLoaded = false;

        $node = new AJXP_Node("pydio://".$data["OWNER_ID"]."@".$repoObject->getId());
        if(!empty($repoObject)){
            try{
                $node->getDriver()->detectStreamWrapper(true);
                $repoLoaded = true;
            }catch (\Exception $e){
                // Cannot load this repository anymore.
            }
        }
        if($repoLoaded && isSet($data["FILE_PATH"])){
            // @TODO : ADD USER IN URL
            $ajxpNode = new AJXP_Node("pydio://".$repoObject->getId().$data["FILE_PATH"]);
        }
        $this->deleteShare($data['SHARE_TYPE'], $elementId, false, true);
        if(isSet($ajxpNode)){
            try{
                $this->getMetaManager()->removeShareFromMeta($ajxpNode, $elementId);
            }catch (\Exception $e){

            }
            gc_collect_cycles();
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