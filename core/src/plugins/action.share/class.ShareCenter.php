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
require_once("class.CompositeShare.php");

/**
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class ShareCenter extends AJXP_Plugin
{
    /**
     * @var AbstractAccessDriver
     */
    private $accessDriver;
    /**
     * @var Repository
     */
    private $repository;
    private $urlBase;

    /**
     * @var ShareStore
     */
    private $shareStore;

    /**
     * @var PublicAccessManager
     */
    private $publicAccessManager;

    /**
     * @var MetaWatchRegister
     */
    private $watcher = false;

    /**
     * @var ShareRightsManager
     */
    private $rightsManager;

    /**************************/
    /* PLUGIN LIFECYCLE METHODS
    /**************************/
    /**
     * AJXP_Plugin initializer
     * @param array $options
     */
    public function init($options)
    {
        parent::init($options);
        $this->repository = ConfService::getRepository();
        if (!is_a($this->repository->driverInstance, "AjxpWrapperProvider")) {
            return;
        }
        $this->accessDriver = $this->repository->driverInstance;
        $this->urlBase = "pydio://". $this->repository->getId();
        if (array_key_exists("meta.watch", AJXP_PluginsService::getInstance()->getActivePlugins())) {
            $this->watcher = AJXP_PluginsService::getInstance()->getPluginById("meta.watch");
        }
    }

    /**
     * Extend parent
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        $disableSharing = false;
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        if ( empty($downloadFolder) || (!is_dir($downloadFolder) || !is_writable($downloadFolder))) {
            $this->logError("Warning on public links, $downloadFolder is not writeable!", array("folder" => $downloadFolder, "is_dir" => is_dir($downloadFolder),"is_writeable" => is_writable($downloadFolder)));
        }

        $xpathesToRemove = array();

        if( strpos(ConfService::getRepository()->getAccessType(), "ajxp_") === 0){

            $xpathesToRemove[] = 'action[@name="share-file-minisite"]';
            $xpathesToRemove[] = 'action[@name="share-folder-minisite-public"]';
            $xpathesToRemove[] = 'action[@name="share-edit-shared"]';

        }else if (AuthService::usersEnabled()) {

            $loggedUser = AuthService::getLoggedUser();
            if ($loggedUser != null && AuthService::isReservedUserId($loggedUser->getId())) {
                $disableSharing = true;
            }

        } else {

            $disableSharing = true;

        }
        if ($disableSharing) {
            // All share- actions
            $xpathesToRemove[] = 'action[contains(@name, "share-")]';
        }else{
            $folderSharingAllowed = $this->getAuthorization("folder", "any");
            $fileSharingAllowed = $this->getAuthorization("file", "any");
            if($fileSharingAllowed === false){
                // Share file button
                $xpathesToRemove[] = 'action[@name="share-file-minisite"]';
            }
            if(!$folderSharingAllowed){
                // Share folder button
                $xpathesToRemove[] = 'action[@name="share-folder-minisite-public"]';
            }
        }

        foreach($xpathesToRemove as $xpath){
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $nodeList = $actionXpath->query($xpath, $contribNode);
            foreach($nodeList as $shareActionNode){
                $contribNode->removeChild($shareActionNode);
            }
        }
    }

    /**************************/
    /* UTILS & ACCESSORS
    /**************************/
    /**
     * Compute right to create shares based on plugin options
     * @param string $nodeType "file"|"folder"
     * @param string $shareType "any"|"minisite"|"workspace"
     * @return bool
     */
    protected function getAuthorization($nodeType, $shareType = "any"){
        $filesMini = $this->getFilteredOption("ENABLE_FILE_PUBLIC_LINK");
        $filesInternal = $this->getFilteredOption("ENABLE_FILE_INTERNAL_SHARING");
        $foldersMini = $this->getFilteredOption("ENABLE_FOLDER_PUBLIC_LINK");
        $foldersInternal = $this->getFilteredOption("ENABLE_FOLDER_INTERNAL_SHARING");
        if($shareType == "any"){
            return ($nodeType == "file" ? $filesInternal || $filesMini : $foldersInternal || $foldersMini);
        }else if($shareType == "minisite"){
            return ($nodeType == "file" ? $filesMini : $foldersMini);
        }else if($shareType == "workspace"){
            return ($nodeType == "file" ? $filesInternal : $foldersInternal);
        }
        return false;
        /*
        if($nodeType == "file"){
            return $this->getFilteredOption("ENABLE_FILE_PUBLIC_LINK") !== false;
        }else{
            $opt = $this->getFilteredOption("ENABLE_FOLDER_SHARING");
            if($shareType == "minisite"){
                return ($opt == "minisite" || $opt == "both");
            }else if($shareType == "workspace"){
                return ($opt == "workspace" || $opt == "both");
            }else{
                return ($opt !== "disable");
            }
        }
        */
    }

    /**
     * @return ShareCenter
     */
    public static function getShareCenter(){
        return AJXP_PluginsService::findPluginById("action.share");
    }

    public static function currentContextIsLinkDownload(){
        return (isSet($_GET["dl"]) && isSet($_GET["dl"]) == "true");
    }

    /**
     * Check if the hash seems to correspond to the serialized data.
     * Kept there only for backward compatibility
     * @static
     * @param String $outputData serialized data
     * @param String $hash Id to check
     * @return bool
     */
    public static function checkHash($outputData, $hash)
    {
        // Never return false, otherwise it can break listing due to hardcore exit() call;
        // Rechecked later
        return true;

        //$full = md5($outputData);
        //return (!empty($hash) && strpos($full, $hash."") === 0);
    }


    /**
     * @return ShareStore
     */
    public function getShareStore(){
        if(!isSet($this->shareStore)){
            require_once("class.ShareStore.php");
            $hMin = 32;
            if(isSet($this->repository)){
                $hMin = $this->getFilteredOption("HASH_MIN_LENGTH", $this->repository);
            }
            $this->shareStore = new ShareStore(
                ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"),
                $hMin
            );
        }
        return $this->shareStore;
    }

    /**
     * @return PublicAccessManager
     */
    public function getPublicAccessManager(){

        if(!isSet($this->publicAccessManager)){
            require_once("class.PublicAccessManager.php");
            $options = array(
                "USE_REWRITE_RULE" => $this->getFilteredOption("USE_REWRITE_RULE", $this->repository) == true
            );
            $this->publicAccessManager = new PublicAccessManager($options);
        }
        return $this->publicAccessManager;

    }

    /**
     * @param AJXP_Node $ajxpNode
     * @return boolean
     */
    public function isShared($ajxpNode)
    {
        $shares = array();
        $this->getShareStore()->getMetaManager()->getSharesFromMeta($ajxpNode, $shares, true);
        return count($shares) > 0;
    }

    /**
     * @return ShareRightsManager
     */
    protected function getRightsManager(){
        if(!isSet($this->rightsManager)){
            require_once("class.ShareRightsManager.php");
            $options = array(
                "SHARED_USERS_TMP_PREFIX" => $this->getFilteredOption("SHARED_USERS_TMP_PREFIX", $this->repository),
                "SHARE_FORCE_PASSWORD" => $this->getFilteredOption("SHARE_FORCE_PASSWORD", $this->repository)
            );
            $this->rightsManager = new ShareRightsManager(
                $options,
                $this->getShareStore(),
                $this->watcher);
        }
        return $this->rightsManager;
    }

    /**
     * Update parameter value based on current max allowed option.
     * @param array $httpVars
     * @param string $parameterName
     * @param string $optionName
     */
    protected function updateToMaxAllowedValue(&$httpVars, $parameterName, $optionName){

        $maxvalue = abs(intval($this->getFilteredOption($optionName, $this->repository)));
        $value = isset($httpVars[$parameterName]) ? abs(intval($httpVars[$parameterName])) : 0;
        if ($maxvalue == 0) {
            $httpVars[$parameterName] = $value;
        } elseif ($maxvalue > 0 && $value == 0) {
            $httpVars[$parameterName] = $maxvalue;
        } else {
            $httpVars[$parameterName] = min($value,$maxvalue);
        }

    }

    /**
     * @param $label
     * @param Repository|null $editingRepo
     * @return bool
     */
    protected function checkRepoWithSameLabel($label, $editingRepo = null){
        if ( $this->getFilteredOption("AVOID_SHARED_FOLDER_SAME_LABEL", $this->repository) == true) {
            $count = 0;
            $similarLabelRepos = ConfService::listRepositoriesWithCriteria(array("display" => $label), $count);
            if($count && !isSet($editingRepo)){
                return true;
            }
            if($count && isSet($editingRepo)){
                foreach($similarLabelRepos as $slr){
                    if($slr->getUniqueId() != $editingRepo->getUniqueId()){
                        return true;
                    }
                }
            }
        }
        return false;
    }

    protected function toggleWatchOnSharedRepository($childRepoId, $userId, $toggle = true, $parentUserId = null){
        if ($this->watcher === false || AuthService::getLoggedUser() == null) {
            return;
        }
        $rootNode = new AJXP_Node("pydio://".$childRepoId."/");
        // Register a watch on the current folder for shared user
        if($parentUserId !== null){
            if ($toggle) {
                $this->watcher->setWatchOnFolder(
                    $rootNode,
                    $userId,
                    MetaWatchRegister::$META_WATCH_USERS_CHANGE,
                    array($parentUserId)
                );
            } else {
                $this->watcher->removeWatchFromFolder(
                    $rootNode,
                    $userId,
                    true
                );
            }
        }else{
            // Register a watch on the new repository root for current user
            if ($toggle) {

                $this->watcher->setWatchOnFolder(
                    $rootNode,
                    $userId,
                    MetaWatchRegister::$META_WATCH_BOTH);

            } else {

                $this->watcher->removeWatchFromFolder(
                    $rootNode,
                    $userId);
            }

        }

    }

    /**************************/
    /* CALLBACKS FOR ACTIONS
    /**************************/
    /**
     * Added as preprocessor on Download action to handle download Counter.
     * @param string $action
     * @param array $httpVars
     * @param array $fileVars
     * @throws Exception
     */
    public function preProcessDownload($action, &$httpVars, &$fileVars){
        if(isSet($_SESSION["CURRENT_MINISITE"])){
            $this->logDebug(__FUNCTION__, "Do something here!");
            $hash = $_SESSION["CURRENT_MINISITE"];
            $share = $this->getShareStore()->loadShare($hash);
            if(!empty($share)){
                if($this->getShareStore()->isShareExpired($hash, $share)){
                    throw new Exception('Link is expired');
                }
                if(!empty($share["DOWNLOAD_LIMIT"])){
                    $this->getShareStore()->incrementDownloadCounter($hash);
                }
            }
        }
    }

    /**
     * Main callback for all share- actions.
     * @param string $action
     * @param array $httpVars
     * @param array $fileVars
     * @return null
     * @throws Exception
     */
    public function switchAction($action, $httpVars, $fileVars)
    {
        if (strpos($action, "sharelist") === false && !isSet($this->accessDriver)) {
            throw new Exception("Cannot find access driver!");
        }


        if (strpos($action, "sharelist") === false && $this->accessDriver->getId() == "access.demo") {
            $errorMessage = "This is a demo, all 'write' actions are disabled!";
            if ($httpVars["sub_action"] == "delegate_repo") {
                return AJXP_XMLWriter::sendMessage(null, $errorMessage, false);
            } else {
                print($errorMessage);
            }
            return null;
        }


        switch ($action) {

            //------------------------------------
            // SHARING FILE OR FOLDER
            //------------------------------------
            case "share":

                $subAction = (isSet($httpVars["sub_action"])?$httpVars["sub_action"]:"");
                // REST API COMPATIBILITY
                if(empty($subAction) && isSet($httpVars["simple_share_type"])){
                    $subAction = "create_minisite";
                    if(!isSet($httpVars["simple_right_read"]) && !isSet($httpVars["simple_right_download"])){
                        $httpVars["simple_right_read"] = $httpVars["simple_right_download"] = "true";
                    }
                    $httpVars["create_guest_user"] = "true";
                    if($httpVars["simple_share_type"] == "private" && !isSet($httpVars["guest_user_pass"])){
                        throw new Exception("Please provide a guest_user_pass for private link");
                    }
                }
                $userSelection = new UserSelection(ConfService::getRepository(), $httpVars);
                $ajxpNode = $userSelection->getUniqueNode();
                if (!file_exists($ajxpNode->getUrl())) {
                    throw new Exception("Cannot share a non-existing file: ".$ajxpNode->getUrl());
                }

                $this->updateToMaxAllowedValue($httpVars, "downloadlimit", "FILE_MAX_DOWNLOAD");
                $this->updateToMaxAllowedValue($httpVars, "expiration", "FILE_MAX_EXPIRATION");

                $httpHash = null;
                $originalHash = null;

                if(!isSet($httpVars["share_scope"]) || !in_array($httpVars["share_scope"], array("public", "private"))){
                    $httpVars["share_scope"] = "private";
                }
                $shareScope = $httpVars["share_scope"];
                $plainResult = 'unknown sub_action';

                if ($subAction == "delegate_repo") {

                    $auth = $this->getAuthorization("folder", "workspace");
                    if(!$auth){
                        $mess = ConfService::getMessages();
                        throw new Exception($mess["351"]);
                    }

                    $users = array(); $groups = array();
                    $this->getRightsManager()->createUsersFromParameters($httpVars, $users, $groups);

                    $result = $this->createSharedRepository($httpVars, $isUpdate, $users, $groups);

                    if (is_a($result, "Repository")) {

                        if(!$isUpdate){
                            $this->getShareStore()->storeShare($this->repository->getId(), array(
                                "REPOSITORY" => $result->getUniqueId(),
                                "OWNER_ID" => AuthService::getLoggedUser()->getId()), "repository");
                        }

                        AJXP_Controller::applyHook( ($isUpdate ? "node.share.update" : "node.share.create"), array(
                            'type' => 'repository',
                            'repository' => &$this->repository,
                            'accessDriver' => &$this->accessDriver,
                            'new_repository' => &$result
                        ));

                        if ($ajxpNode->hasMetaStore() && !$ajxpNode->isRoot()) {
                            $this->getShareStore()->getMetaManager()->addShareInMeta(
                                $ajxpNode,
                                "repository",
                                $result->getUniqueId(),
                                ($shareScope == "public"),
                                $originalHash
                            );
                        }

                        $plainResult = 200;
                    } else {
                        $plainResult = $result;
                    }

                } else if ($subAction == "create_minisite") {

                    if(isSet($httpVars["hash"]) && !empty($httpVars["hash"])) $httpHash = $httpVars["hash"];

                    $result = $this->createSharedMinisite($httpVars, $isUpdate);

                    if (!is_array($result)) {
                        $url = $result;
                    } else {
                        list($hash, $url) = $result;
                        if ($ajxpNode->hasMetaStore() && !$ajxpNode->isRoot()) {
                            $this->getShareStore()->getMetaManager()->addShareInMeta(
                                $ajxpNode,
                                "minisite",
                                $hash,
                                ($shareScope == "public"),
                                ($httpHash != null && $hash != $httpHash) ? $httpHash : null
                            );
                        }

                    }
                    $plainResult = $url;

                } else if ($subAction == "share_node"){

                    $httpVars["return_json"] = true;
                    if(isSet($httpVars["hash"]) && !empty($httpVars["hash"])) $httpHash = $httpVars["hash"];
                    $ajxpNode->loadNodeInfo();

                    $results = $this->shareNode($ajxpNode, $httpVars, $isUpdate);
                    if(is_array($results) && $ajxpNode->hasMetaStore() && !$ajxpNode->isRoot()){
                        foreach($results as $shareObject){
                            if($shareObject instanceof \Pydio\OCS\Model\TargettedLink){
                                $hash = $shareObject->getHash();
                                $this->getShareStore()->getMetaManager()->addShareInMeta(
                                    $ajxpNode,
                                    "ocs_remote",
                                    $hash,
                                    ($shareScope == "public"),
                                    $hash
                                );
                            }else if(is_a($shareObject, "ShareLink")){
                                $hash = $shareObject->getHash();
                                $this->getShareStore()->getMetaManager()->addShareInMeta(
                                    $ajxpNode,
                                    "minisite",
                                    $hash,
                                    ($shareScope == "public"),
                                    ($httpHash != null && $hash != $httpHash) ? $httpHash : null
                                );
                            }else if(is_a($shareObject, "Repository")){
                                $this->getShareStore()->getMetaManager()->addShareInMeta(
                                    $ajxpNode,
                                    "repository",
                                    $shareObject->getUniqueId(),
                                    ($shareScope == "public"),
                                    null
                                );
                            }
                        }

                    }
                }


                AJXP_Controller::applyHook("msg.instant", array("<reload_shared_elements/>", ConfService::getRepository()->getId()));
                /*
                 * Send IM to inform that node has been shared or unshared.
                 * Should be done only if share scope is public.
                 */
                if($shareScope == "public"){
                    $ajxpNode->loadNodeInfo();
                    $content = AJXP_XMLWriter::writeNodesDiff(["UPDATE" => array($ajxpNode->getPath() => $ajxpNode)]);
                    AJXP_Controller::applyHook("msg.instant", array($content, $ajxpNode->getRepositoryId(), null, null, [$ajxpNode->getPath()]));
                }

                if(!isSet($httpVars["return_json"])){
                    header("Content-Type: text/plain");
                    print($plainResult);
                }else{
                    $compositeShare = $this->getShareStore()->getMetaManager()->getCompositeShareForNode($ajxpNode);
                    header("Content-type:application/json");
                    if(!empty($compositeShare)){
                        echo json_encode($this->compositeShareToJson($compositeShare));
                    }else{
                        echo json_encode(array());
                    }
                }
                // as the result can be quite small (e.g error code), make sure it's output in case of OB active.
                flush();

                break;

            case "toggle_link_watch":

                $userSelection = new UserSelection($this->repository, $httpVars);
                $shareNode = $selectedNode = $userSelection->getUniqueNode();
                $watchValue = $httpVars["set_watch"] == "true" ? true : false;
                $folder = false;
                if (isSet($httpVars["element_type"]) && $httpVars["element_type"] == "folder") {
                    $folder = true;
                    $selectedNode = new AJXP_Node("pydio://". AJXP_Utils::sanitize($httpVars["repository_id"], AJXP_SANITIZE_ALPHANUM)."/");
                }
                $shares = array();
                $this->getShareStore()->getMetaManager()->getSharesFromMeta($shareNode, $shares, false);
                if(!count($shares)){
                    break;
                }

                if(isSet($httpVars["element_id"]) && isSet($shares[$httpVars["element_id"]])){
                    $elementId = $httpVars["element_id"];
                }else{
                    $sKeys = array_keys($shares);
                    $elementId = $sKeys[0];
                }

                if ($this->watcher !== false) {
                    if (!$folder) {
                        if ($watchValue) {
                            $this->watcher->setWatchOnFolder(
                                $selectedNode,
                                AuthService::getLoggedUser()->getId(),
                                MetaWatchRegister::$META_WATCH_USERS_READ,
                                array($elementId)
                            );
                        } else {
                            $this->watcher->removeWatchFromFolder(
                                $selectedNode,
                                AuthService::getLoggedUser()->getId(),
                                true,
                                $elementId
                            );
                        }
                    } else {
                        if ($watchValue) {
                            $this->watcher->setWatchOnFolder(
                                $selectedNode,
                                AuthService::getLoggedUser()->getId(),
                                MetaWatchRegister::$META_WATCH_BOTH
                            );
                        } else {
                            $this->watcher->removeWatchFromFolder(
                                $selectedNode,
                                AuthService::getLoggedUser()->getId());
                        }
                    }
                }
                $mess = ConfService::getMessages();
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["share_center.47"], null);
                AJXP_XMLWriter::close();

            break;

            case "load_shared_element_data":

                $node = null;
                if(isSet($httpVars["hash"]) && $httpVars["element_type"] == "file"){

                    // LEGACY LINKS
                    $parsedMeta = array($httpVars["hash"] => array("type" => "file"));
                    $jsonData = array();
                    foreach($parsedMeta as $shareId => $shareMeta){
                        $jsonData[] = $this->shareToJson($shareId, $shareMeta, $node);
                    }
                    header("Content-type:application/json");
                    echo json_encode($jsonData);

                }else{

                    $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                    $node = new AJXP_Node($this->urlBase.$file);
                    $loggedUser = AuthService::getLoggedUser();
                    if(isSet($httpVars["owner"]) && $loggedUser->isAdmin()
                        && $loggedUser->getGroupPath() == "/" && $loggedUser->getId() != AJXP_Utils::sanitize($httpVars["owner"], AJXP_SANITIZE_EMAILCHARS)){
                        // Impersonate the current user
                        $node->setUser(AJXP_Utils::sanitize($httpVars["owner"], AJXP_SANITIZE_EMAILCHARS));
                    }
                    if(!file_exists($node->getUrl())){
                        $mess = ConfService::getMessages();
                        throw new Exception(str_replace('%s', "Cannot find file ".$file, $mess["share_center.219"]));
                    }
                    if(isSet($httpVars["tmp_repository_id"]) && AuthService::getLoggedUser()->isAdmin()){
                        $compositeShare = $this->getShareStore()->getMetaManager()->getCompositeShareForNode($node, true);
                    }else{
                        $compositeShare = $this->getShareStore()->getMetaManager()->getCompositeShareForNode($node);
                    }
                    if(empty($compositeShare)){
                        $mess = ConfService::getMessages();
                        throw new Exception(str_replace('%s', "Cannot find share for node ".$file, $mess["share_center.219"]));
                    }
                    header("Content-type:application/json");
                    $json = $this->compositeShareToJson($compositeShare);
                    echo json_encode($json);

                }


            break;

            case "unshare":

                $mess = ConfService::getMessages();
                $userSelection = new UserSelection($this->repository, $httpVars);
                if(isSet($httpVars["hash"])){
                    $sanitizedHash = AJXP_Utils::sanitize($httpVars["hash"], AJXP_SANITIZE_ALPHANUM);
                    $ajxpNode = ($userSelection->isEmpty() ? null : $userSelection->getUniqueNode());
                    $result = $this->getShareStore()->deleteShare($httpVars["element_type"], $sanitizedHash, false, false, $ajxpNode);
                    if($result !== false){
                        AJXP_XMLWriter::header();
                        AJXP_XMLWriter::sendMessage($mess["share_center.216"], null);
                        AJXP_XMLWriter::close();
                    }

                }else{

                    $userSelection = new UserSelection($this->repository, $httpVars);
                    $ajxpNode = $userSelection->getUniqueNode();
                    $shares = array();
                    $this->getShareStore()->getMetaManager()->getSharesFromMeta($ajxpNode, $shares, false);
                    if(isSet($httpVars["element_id"]) && isSet($shares[$httpVars["element_id"]])){
                        $elementId = $httpVars["element_id"];
                        if(isSet($shares[$elementId])){
                            $shares = array($elementId => $shares[$elementId]);
                        }
                    }
                    if(count($shares)){
                        $res = true;
                        foreach($shares as $shareId =>  $share){
                            $t = isSet($share["type"]) ? $share["type"] : "file";
                            try{
                                $result = $this->getShareStore()->deleteShare($t, $shareId, false, true);
                            }catch(Exception $e){
                                if($e->getMessage() == "repo-not-found"){
                                    $result = true;
                                }else{
                                    throw $e;
                                }
                            }
                            $this->getShareStore()->getMetaManager()->removeShareFromMeta($ajxpNode, $shareId);
                            $res = $result && $res;
                        }
                        if($res !== false){
                            AJXP_XMLWriter::header();
                            AJXP_XMLWriter::sendMessage($mess["share_center.216"], null);
                            AJXP_XMLWriter::close();
                            AJXP_Controller::applyHook("msg.instant", array("<reload_shared_elements/>", ConfService::getRepository()->getId()));

                            if(isSet($httpVars["share_scope"]) &&  $httpVars["share_scope"] == "public"){
                                $ajxpNode->loadNodeInfo();
                                $content = AJXP_XMLWriter::writeNodesDiff(["UPDATE" => [$ajxpNode->getPath() => $ajxpNode]]);
                                AJXP_Controller::applyHook("msg.instant", array($content, $ajxpNode->getRepositoryId(), null, null, [$ajxpNode->getPath()]));
                            }

                        }
                    }

                }
                break;

            case "reset_counter":

                if(isSet($httpVars["hash"])){

                    $userId = AuthService::getLoggedUser()->getId();
                    if(isSet($httpVars["owner_id"]) && $httpVars["owner_id"] != $userId){
                        if(!AuthService::getLoggedUser()->isAdmin()){
                            throw new Exception("You are not allowed to access this resource");
                        }
                        $userId = $httpVars["owner_id"];
                    }
                    $this->getShareStore()->resetDownloadCounter($httpVars["hash"], $userId);

                }else{

                    $userSelection = new UserSelection($this->repository, $httpVars);
                    $ajxpNode = $userSelection->getUniqueNode();
                    $metadata = $this->getShareStore()->getMetaManager()->getNodeMeta($ajxpNode);
                    if(!isSet($metadata["shares"]) || !is_array($metadata["shares"])){
                        return null;
                    }
                    if ( isSet($httpVars["element_id"]) && isSet($metadata["shares"][$httpVars["element_id"]])) {
                        $this->getShareStore()->resetDownloadCounter($httpVars["element_id"], $httpVars["owner_id"]);
                    }else{
                        $keys = array_keys($metadata["shares"]);
                        foreach($keys as $key){
                            $this->getShareStore()->resetDownloadCounter($key, null);
                        }
                    }

                }

            break;

            case "update_shared_element_data":

                if(!in_array($httpVars["p_name"], array("counter", "tags"))){
                    return null;
                }
                $hash = AJXP_Utils::decodeSecureMagic($httpVars["element_id"]);
                $userSelection = new UserSelection($this->repository, $httpVars);
                $ajxpNode = $userSelection->getUniqueNode();
                if($this->getShareStore()->shareIsLegacy($hash)){
                    // Store in metadata
                    $metadata = $this->getShareStore()->getMetaManager()->getNodeMeta($ajxpNode);
                    if (isSet($metadata["shares"][$httpVars["element_id"]])) {
                        if (!is_array($metadata["shares"][$httpVars["element_id"]])) {
                            $metadata["shares"][$httpVars["element_id"]] = array();
                        }
                        $metadata["shares"][$httpVars["element_id"]][$httpVars["p_name"]] = $httpVars["p_value"];
                        // Set Private=true by default.
                        $this->getShareStore()->getMetaManager()->setNodeMeta($ajxpNode, $metadata, true);
                    }
                }else{
                    // TODO: testUserCanEditShare ?
                    $this->getShareStore()->updateShareProperty($hash, $httpVars["p_name"], $httpVars["p_value"]);
                }


                break;

            case "sharelist-load":

                $parentRepoId = isset($httpVars["parent_repository_id"]) ? $httpVars["parent_repository_id"] : "";
                $userContext = $httpVars["user_context"];
                $currentUser = true;
                if($userContext == "global" && AuthService::getLoggedUser()->isAdmin()){
                    $currentUser = false;
                }else if($userContext == "user" && AuthService::getLoggedUser()->isAdmin() && !empty($httpVars["user_id"])){
                    $currentUser = AJXP_Utils::sanitize($httpVars["user_id"], AJXP_SANITIZE_EMAILCHARS);
                }
                $nodes = $this->listSharesAsNodes("/data/repositories/$parentRepoId/shares", $currentUser, $parentRepoId);

                AJXP_XMLWriter::header();
                if($userContext == "current"){
                    AJXP_XMLWriter::sendFilesListComponentConfig('<columns template_name="ajxp_user.shares">
                    <column messageId="ajxp_conf.8" attributeName="ajxp_label" sortType="String"/>
                    <column messageId="share_center.132" attributeName="shared_element_parent_repository_label" sortType="String"/>
                    <column messageId="3" attributeName="share_type_readable" sortType="String"/>
                    </columns>');
                }else{
                    AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchDisplayMode="list" switchGridMode="filelist" template_name="ajxp_conf.repositories">
                    <column messageId="ajxp_conf.8" attributeName="ajxp_label" sortType="String"/>
                    <column messageId="share_center.159" attributeName="owner" sortType="String"/>
                    <column messageId="3" attributeName="share_type_readable" sortType="String"/>
                    <column messageId="share_center.52" attributeName="share_data" sortType="String"/>
                    </columns>');
                }

                foreach($nodes as $node){
                    AJXP_XMLWriter::renderAjxpNode($node);
                }
                AJXP_XMLWriter::close();

            break;

            case "sharelist-clearExpired":

                $accessType = ConfService::getRepository()->getAccessType();
                $currentUser  = ($accessType != "ajxp_conf" && $accessType != "ajxp_admin");
                $count = $this->getShareStore()->clearExpiredFiles($currentUser);
                AJXP_XMLWriter::header();
                if($count){
                    AJXP_XMLWriter::sendMessage("Removed ".count($count)." expired links", null);
                }else{
                    AJXP_XMLWriter::sendMessage("Nothing to do", null);
                }
                AJXP_XMLWriter::close();

            break;

            default:
            break;
        }

        return null;

    }

    /**************************/
    /* CALLBACKS FOR HOOKS
    /**************************/
    /**
     * Hook node.info
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    public function nodeSharedMetadata(&$ajxpNode)
    {
        if(empty($this->accessDriver) || $this->accessDriver->getId() == "access.imap") return;
        $shares = array();
        $this->getShareStore()->getMetaManager()->getSharesFromMeta($ajxpNode, $shares, false);
        if(!empty($shares)){
            $compositeShare = $this->getShareStore()->getMetaManager()->getCompositeShareForNode($ajxpNode);
            if(empty($compositeShare) || $compositeShare->isInvalid()){
                $this->getShareStore()->getMetaManager()->clearNodeMeta($ajxpNode);
                return;
            }
        }
        if(!empty($shares) && count($shares)){
            $merge = array(
                "ajxp_shared"      => "true",
                "overlay_icon"     => "shared.png",
                "overlay_class"    => "mdi mdi-share-variant"
            );
            // Backward compat, until we rework client-side
            $sKeys = array_keys($shares);
            if($shares[$sKeys[0]]["type"] == "minisite"){
                if($ajxpNode->isLeaf()){
                    $merge["ajxp_shared_minisite"] = "file";
                }else{
                    $merge["ajxp_shared_minisite"] = "public";
                }
            }else if($shares[$sKeys[0]]["type"] == "file"){
                $merge["ajxp_shared_publiclet"] = "true";
            }
            $ajxpNode->mergeMetadata($merge, true);
        }
        return;
    }

    /**
     * Hook node.change
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param bool $copy
     */
    public function updateNodeSharedData($oldNode=null, $newNode=null, $copy = false){

        if($oldNode == null || $copy){
            // Create or copy, do nothing
            return;
        }
        if($oldNode != null && $newNode != null && $oldNode->getUrl() == $newNode->getUrl()){
            // Same path => must be a content update, do nothing
            return;
        }

        $this->logDebug("Should update node");
        $delete = false;
        if($newNode == null) {
            $delete = true;
        }else{
            $repo = $newNode->getRepository();
            $recycle = $repo->getOption("RECYCLE_BIN");
            if(!empty($recycle) && strpos($newNode->getPath(), $recycle) === 1){
                $delete = true;
            }
        }
        $shareStore = $this->getShareStore();
        $modifiedNodes = $shareStore->moveSharesFromMetaRecursive($oldNode, $delete, $oldNode->getPath(), ($newNode != null ? $newNode->getPath() : null));
        // Force switching back to correct driver!
        if($modifiedNodes > 0){
            $oldNode->getRepository()->driverInstance = null;
            $oldNode->setDriver(null);
            $oldNode->getDriver();
        }
        return;

    }

    /**
     * Hook user.after_delete
     * make sure to clear orphan shares
     * @param String $userId
     */
    public function cleanUserShares($userId){
        $shares = $this->getShareStore()->listShares($userId);
        foreach($shares as $hash => $data){
            $this->getShareStore()->deleteShare($data['SHARE_TYPE'], $hash, false, true);
        }
    }


    /************************************/
    /* EVENTS FORWARDING BETWEEN
    /* PARENTS AND CHILDREN WORKSPACES
    /************************************/
    /**
     * @param AJXP_Node $node
     * @param String|null $direction "UP", "DOWN"
     * @return array()
     */
    private function findMirrorNodesInShares($node, $direction){
        $result = array();
        if($direction !== "UP"){
            $upmetas = array();
            $this->getShareStore()->getMetaManager()->collectSharesInParent($node, $upmetas);
            foreach($upmetas as $metadata){
                if (is_array($metadata) && !empty($metadata["shares"])) {
                    foreach($metadata["shares"] as $sId => $sData){
                        $type = $sData["type"];
                        if($type == "file") continue;
                        $wsId = $sId;
                        if($type == "minisite"){
                            $minisiteData = $this->getShareStore()->loadShare($sId);
                            if(empty($minisiteData) || !isset($minisiteData["REPOSITORY"])) continue;
                            $wsId = $minisiteData["REPOSITORY"];
                        }else if($type == "ocs_remote"){
                            continue;
                        }
                        $sharedNode = $metadata["SOURCE_NODE"];
                        $sharedPath = substr($node->getPath(), strlen($sharedNode->getPath()));
                        $sharedNodeUrl = $node->getScheme() . "://".$wsId.$sharedPath;
                        $result[$wsId] = array(new AJXP_Node($sharedNodeUrl), "DOWN");
                        $this->logDebug('MIRROR NODES', 'Found shared in parent - register node '.$sharedNodeUrl);
                    }
                }
            }
        }
        if($direction !== "DOWN"){
            if($node->getRepository()->hasParent()){
                $parentRepoId = $node->getRepository()->getParentId();
                $parentRepository = ConfService::getRepositoryById($parentRepoId);
                if(!empty($parentRepository) && !$parentRepository->isTemplate){
                    $currentRoot = $node->getRepository()->getOption("PATH");
                    $owner = $node->getRepository()->getOwner();
                    $resolveUser = null;
                    if($owner != null){
                        $resolveUser = ConfService::getConfStorageImpl()->createUserObject($owner);
                    }
                    $parentRoot = $parentRepository->getOption("PATH", false, $resolveUser);
                    $relative = substr($currentRoot, strlen($parentRoot));
                    $relative = SystemTextEncoding::toStorageEncoding($relative);
                    $parentNodeURL = $node->getScheme()."://".$parentRepoId.$relative.$node->getPath();
                    $this->logDebug("action.share", "Should trigger on ".$parentNodeURL);
                    $parentNode = new AJXP_Node($parentNodeURL);
                    if($owner != null) $parentNode->setUser($owner);
                    $result[$parentRepoId] = array($parentNode, "UP");
                }
            }
        }
        return $result;
    }

    private function applyForwardEvent($fromMirrors = null, $toMirrors = null, $copy = false, $direction = null){
        if($fromMirrors === null){
            // Create
            foreach($toMirrors as $mirror){
                list($node, $direction) = $mirror;
                AJXP_Controller::applyHook("node.change", array(null, $node, false, $direction), true);
            }
        }else if($toMirrors === null){
            foreach($fromMirrors as $mirror){
                list($node, $direction) = $mirror;
                AJXP_Controller::applyHook("node.change", array($node, null, false, $direction), true);
            }
        }else{
            foreach($fromMirrors as $repoId => $mirror){
                list($fNode, $fDirection) = $mirror;
                if(isSet($toMirrors[$repoId])){
                    list($tNode, $tDirection) = $toMirrors[$repoId];
                    unset($toMirrors[$repoId]);
                    try{
                        AJXP_Controller::applyHook("node.change", array($fNode, $tNode, $copy, $fDirection), true);
                    }catch(Exception $e){
                        $this->logError(__FUNCTION__, "Error while applying node.change hook (".$e->getMessage().")");
                    }
                }else{
                    try{
                    AJXP_Controller::applyHook("node.change", array($fNode, null, $copy, $fDirection), true);
                    }catch(Exception $e){
                        $this->logError(__FUNCTION__, "Error while applying node.change hook (".$e->getMessage().")");
                    }
                }
            }
            foreach($toMirrors as $mirror){
                list($tNode, $tDirection) = $mirror;
                try{
                AJXP_Controller::applyHook("node.change", array(null, $tNode, $copy, $tDirection), true);
                }catch(Exception $e){
                    $this->logError(__FUNCTION__, "Error while applying node.change hook (".$e->getMessage().")");
                }
            }
        }

    }

    /**
     * @param AJXP_Node $fromNode
     * @param AJXP_Node $toNode
     * @param bool $copy
     * @param String $direction
     */
    public function forwardEventToShares($fromNode=null, $toNode=null, $copy = false, $direction=null){

        if(empty($direction) && $this->getFilteredOption("FORK_EVENT_FORWARDING")){
            AJXP_Controller::applyActionInBackground(
                ConfService::getRepository()->getId(),
                "forward_change_event",
                array(
                    "from" => $fromNode === null ? "" : $fromNode->getUrl(),
                    "to" =>   $toNode === null ? "" : $toNode->getUrl(),
                    "copy" => $copy ? "true" : "false",
                    "direction" => $direction
                ));
            return;
        }

        $fromMirrors = null;
        $toMirrors = null;
        if($fromNode != null){
            $fromMirrors = $this->findMirrorNodesInShares($fromNode, $direction);
        }
        if($toNode != null){
            $toMirrors = $this->findMirrorNodesInShares($toNode, $direction);
        }

        $this->applyForwardEvent($fromMirrors, $toMirrors, $copy, $direction);
        if(count($fromMirrors) || count($toMirrors)){
            // Make sure to switch back to correct repository in memory
            if($fromNode != null) {
                $fromNode->getRepository()->driverInstance = null;
                $fromNode->setDriver(null);
                $fromNode->getDriver();
            }else if($toNode != null){
                $toNode->getRepository()->driverInstance = null;
                $toNode->setDriver(null);
                $toNode->getDriver();
            }
        }
    }

    public function forwardEventToSharesAction($actionName, $httpVars, $fileVars){

        $fromMirrors = null;
        $toMirrors = null;
        $fromNode = $toNode = null;
        if(!empty($httpVars["from"])){
            $fromNode = new AJXP_Node($httpVars["from"]);
            $fromMirrors = $this->findMirrorNodesInShares($fromNode, $httpVars["direction"]);
        }
        if(!empty($httpVars["to"])){
            $toNode = new AJXP_Node($httpVars["to"]);
            $toMirrors = $this->findMirrorNodesInShares($toNode, $httpVars["direction"]);
        }
        $this->applyForwardEvent($fromMirrors, $toMirrors, ($httpVars["copy"] === "true"), $httpVars["direction"]);
        if(count($fromMirrors) || count($toMirrors)){
            // Make sure to switch back to correct repository in memory
            if($fromNode != null) {
                $fromNode->getRepository()->driverInstance = null;
                $fromNode->setDriver(null);
                $fromNode->getDriver();
            }else if($toNode != null){
                $toNode->getRepository()->driverInstance = null;
                $toNode->setDriver(null);
                $toNode->getDriver();
            }
        }
    }


    /**************************/
    /* BOOTLOADERS FOR LINKS
    /**************************/
    /**
     * Loader for minisites
     * @param array $data
     * @param string $hash
     * @param null $error
     */
    public static function loadMinisite($data, $hash = '', $error = null)
    {
        include_once("class.MinisiteRenderer.php");
        MinisiteRenderer::loadMinisite($data, $hash, $error);
    }

    /**
     * Loader used by the generic loader.
     * @param string $hash
     */
    public static function loadShareByHash($hash){
        AJXP_Logger::debug(__CLASS__, __FUNCTION__, "Do something");
        AJXP_PluginsService::getInstance()->initActivePlugins();
        if(isSet($_GET["lang"])){
            ConfService::setLanguage($_GET["lang"]);
        }
        $shareCenter = self::getShareCenter();
        $data = $shareCenter->getShareStore()->loadShare($hash);
        $mess = ConfService::getMessages();
        if($shareCenter->getShareStore()->isShareExpired($hash, $data)){
            AuthService::disconnect();
            self::loadMinisite($data, $hash, $mess["share_center.165"]);
            return;
        }
        if(!empty($data) && is_array($data)){
            if(isSet($data["SECURITY_MODIFIED"]) && $data["SECURITY_MODIFIED"] === true){
                header("HTTP/1.0 401 Not allowed, script was modified");
                exit();
            }
            if($data["SHARE_TYPE"] == "minisite"){
                self::loadMinisite($data, $hash);
            }else{
                self::loadPubliclet($data);
            }
        }else{
            $setUrl = ConfService::getCoreConf("SERVER_URL");
            $data = array();
            if (!empty($setUrl)) {
                $data["AJXP_APPLICATION_BASE"] = $setUrl;
            }
            self::loadMinisite($data, $hash, $mess["share_center.166"]);
        }

    }

    /**
     * Loader for legacy publiclets
     * @static
     * @param array $data
     * @return void
     */
    public static function loadPubliclet($data)
    {
        require_once("class.LegacyPubliclet.php");
        $shareCenter = self::getShareCenter();
        $options = $shareCenter->getConfigs();
        $shareStore = $shareCenter->getShareStore();
        LegacyPubliclet::render($data, $options, $shareStore);
    }


    /**************************/
    /* CREATE / EDIT SHARES
    /**************************/

    /**
     * @param array $httpVars
     * @param UserSelection $userSelection
     * @return int
     * @throws Exception
     */
    public function filterHttpVarsForLeafPath(&$httpVars, $userSelection){
        // ANALYSE SELECTION
        // TO CREATE PROPER FILTER / PATH FOR SHARED REPO
        $httpVars["minisite"] = true;
        $httpVars["selection"] = true;
        $setFilter = false;
        if($userSelection->isUnique()){
            $node = $userSelection->getUniqueNode();
            $node->loadNodeInfo();
            if($node->isLeaf()){
                $setFilter = true;
                $httpVars["file"] = "/";
                $httpVars["nodes"] = array("/");
            }
        }else{
            $setFilter = true;
        }
        $nodes = $userSelection->buildNodes();
        $hasDir = false; $hasFile = false;
        foreach($nodes as $n){
            $n->loadNodeInfo();
            if($n->isLeaf()) $hasFile = true;
            else $hasDir = true;
        }
        if( ( $hasDir && !$this->getAuthorization("folder", "minisite") ) || ($hasFile && !$this->getAuthorization("file"))){
            throw new Exception(103);
        }
        if($setFilter){ // Either it's a file, or many nodes are shared
            $httpVars["filter_nodes"] = $nodes;
        }
        if(!isSet($httpVars["repo_label"])){
            $first = $userSelection->getUniqueNode();
            $httpVars["repo_label"] = SystemTextEncoding::toUTF8($first->getLabel());
        }

    }

    /**
     * @param array $httpVars
     * @param AJXP_Node $ajxpNode
     */
    public function filterHttpVarsFromUniqueNode(&$httpVars, $ajxpNode){
        $httpVars["minisite"] = true;
        $httpVars["selection"] = true;
        if($ajxpNode->isLeaf()){
            $httpVars["filter_nodes"] = [$ajxpNode];
            $httpVars["file"] = "/";
            $httpVars["nodes"] = array("/");
        }
        if(!isSet($httpVars["repo_label"])){
            $httpVars["repo_label"] = SystemTextEncoding::toUTF8($ajxpNode->getLabel());
        }
    }

    /**
     * @param array $httpVars
     * @param bool $update
     * @return Repository
     * @throws Exception
     */
    protected function createOrLoadSharedRepository($httpVars, &$update){

        if (!isSet($httpVars["repo_label"]) || $httpVars["repo_label"] == "") {
            $mess = ConfService::getMessages();
            throw new Exception($mess["349"]);
        }

        if (isSet($httpVars["repository_id"])) {
            $editingRepo = ConfService::getRepositoryById($httpVars["repository_id"]);
            $update = true;
        }

        // CHECK REPO DOES NOT ALREADY EXISTS WITH SAME LABEL
        $label = AJXP_Utils::sanitize(AJXP_Utils::securePath($httpVars["repo_label"]), AJXP_SANITIZE_HTML);
        $description = AJXP_Utils::sanitize(AJXP_Utils::securePath($httpVars["repo_description"]), AJXP_SANITIZE_HTML);
        $exists = $this->checkRepoWithSameLabel($label, isSet($editingRepo)?$editingRepo:null);
        if($exists){
            $mess = ConfService::getMessages();
            throw new Exception($mess["share_center.352"]);
        }

        $loggedUser = AuthService::getLoggedUser();

        if (isSet($editingRepo)) {

            $this->getShareStore()->testUserCanEditShare($editingRepo->getOwner(), $editingRepo->options);
            $newRepo = $editingRepo;
            $replace = false;
            if ($editingRepo->getDisplay() != $label) {
                $newRepo->setDisplay($label);
                $replace = true;
            }
            if($editingRepo->getDescription() != $description){
                $newRepo->setDescription($description);
                $replace = true;
            }
            $newScope = ((isSet($httpVars["share_scope"]) && $httpVars["share_scope"] == "public") ? "public" : "private");
            $oldScope = $editingRepo->getOption("SHARE_ACCESS");
            $currentOwner = $editingRepo->getOwner();
            if($newScope != $oldScope && $currentOwner != AuthService::getLoggedUser()->getId()){
                $mess = ConfService::getMessages();
                throw new Exception($mess["share_center.224"]);
            }
            if($newScope !== $oldScope){
                $editingRepo->addOption("SHARE_ACCESS", $newScope);
                $replace = true;
            }
            if(isSet($httpVars["transfer_owner"])){
                $newOwner = $httpVars["transfer_owner"];
                if($newOwner != $currentOwner && $currentOwner != AuthService::getLoggedUser()->getId()){
                    $mess = ConfService::getMessages();
                    throw new Exception($mess["share_center.224"]);
                }
                $editingRepo->setOwnerData($editingRepo->getParentId(), $newOwner, $editingRepo->getUniqueUser());
                $replace = true;
            }

            if($replace) {
                ConfService::replaceRepository($newRepo->getId(), $newRepo);
            }

        } else {

            $options = $this->accessDriver->makeSharedRepositoryOptions($httpVars, $this->repository);
            // TMP TESTS
            $options["SHARE_ACCESS"] = $httpVars["share_scope"];
            $newRepo = $this->repository->createSharedChild(
                $label,
                $options,
                $this->repository->getId(),
                $loggedUser->getId(),
                null
            );
            $gPath = $loggedUser->getGroupPath();
            if (!empty($gPath) && !ConfService::getCoreConf("CROSSUSERS_ALLGROUPS", "conf")) {
                $newRepo->setGroupPath($gPath);
            }
            $newRepo->setDescription($description);
            // Smells like dirty hack!
            $newRepo->options["PATH"] = SystemTextEncoding::fromStorageEncoding($newRepo->options["PATH"]);

            if(isSet($httpVars["filter_nodes"])){
                $newRepo->setContentFilter(new ContentFilter($httpVars["filter_nodes"]));
            }
            ConfService::addRepository($newRepo);
        }
        return $newRepo;

    }

    /**
     * @param array $httpVars
     * @param bool $update
     * @return mixed An array containing the hash (0) and the generated url (1)
     * @throws Exception
     */
    public function createSharedMinisite($httpVars, &$update)
    {
        // PREPARE HIDDEN USER DATA
        if(isSet($httpVars["hash"])){
            $shareObject = $this->getShareStore()->loadShareObject($httpVars["hash"]);
        }else{
            $shareObject = $this->getShareStore()->createEmptyShareObject();
        }
        $shareObject->parseHttpVars($httpVars);
        $hiddenUserEntry = $this->getRightsManager()->prepareSharedUserEntry(
            $httpVars,
            $shareObject,
            isSet($httpVars["hash"]),
            (isSet($httpVars["guest_user_pass"])?$httpVars["guest_user_pass"]:null)
        );
        $userSelection = new UserSelection($this->repository, $httpVars);
        $this->filterHttpVarsForLeafPath($httpVars, $userSelection);

        $users = array(); $groups = array();
        $users[$hiddenUserEntry["ID"]] = $hiddenUserEntry;

        $newRepo = $this->createSharedRepository($httpVars, $repoUpdate, $users, $groups);

        $shareObject->setParentRepositoryId($this->repository->getId());
        $shareObject->attachToRepository($newRepo->getId());
        // STORE DATA & HASH IN SHARE STORE
        $this->getPublicAccessManager()->initFolder();
        $hash = $shareObject->save();
        $url = $this->getPublicAccessManager()->buildPublicLink($hash);

        // LOG AND PUBLISH EVENT
        $update = isSet($httpVars["repository_id"]);
        $data = $shareObject->getData();
        $this->logInfo(($update?"Update":"New")." Share", array(
            "file" => "'".$userSelection->getUniqueFile()."'",
            "files" => $userSelection->getFiles(),
            "url" => $url,
            "expiration" => $data["EXPIRATE_TIME"],
            "limit" => $data['DOWNLOAD_LIMIT'],
            "repo_uuid" => $this->repository->getId()
        ));
        AJXP_Controller::applyHook("node.share.".($update?"update":"create"), array(
            'type' => 'minisite',
            'repository' => &$this->repository,
            'accessDriver' => &$this->accessDriver,
            'data' => &$data,
            'url' => $url,
            'new_repository' => &$newRepo
        ));

        return array($hash, $url);
    }

    /**
     * @param array $httpVars
     * @param bool $update
     * @param array $users
     * @param array $groups
     * @return Repository
     * @throws Exception
     */
    public function createSharedRepository($httpVars, &$update, $users=array(), $groups=array())
    {
        // ERRORS
        // 100 : missing args
        // 101 : repository label already exists
        // 102 : user already exists
        // 103 : current user is not allowed to share
        // SUCCESS
        // 200
        $loggedUser = AuthService::getLoggedUser();
        $actRights = $loggedUser->mergedRole->listActionsStatesFor($this->repository);
        if (isSet($actRights["share"]) && $actRights["share"] === false) {
            $mess = ConfService::getMessages();
            throw new Exception($mess["351"]);
        }

        $newRepo = $this->createOrLoadSharedRepository($httpVars, $update);

        $selection = new UserSelection($this->repository, $httpVars);
        $this->getRightsManager()->assignSharedRepositoryPermissions($this->repository, $newRepo, $update, $users, $groups, $selection);

        // HANDLE WATCHES ON CHILDREN AND PARENT
        foreach($users as $userName => $userEntry){
            $this->toggleWatchOnSharedRepository(
                $newRepo->getId(),
                $userName,
                $userEntry["WATCH"],
                AuthService::getLoggedUser()->getId()
            );
        }
        $this->toggleWatchOnSharedRepository(
            $newRepo->getId(),
            AuthService::getLoggedUser()->getId(),
            ($httpVars["self_watch_folder"] == "true")
        );

        $this->logInfo(($update?"Update":"New")." Share", array(
            "file" => "'".$selection->getUniqueFile()."'",
            "files" => $selection->getFiles(),
            "repo_uuid" => $this->repository->getId(),
            "shared_repo_uuid" => $newRepo->getId()
        ));

        return $newRepo;
    }

    /**
     * @param array $linkData
     * @param array $hiddenUserEntries
     * @param array $shareObjects
     * @param string $type
     * @param string $invitationLabel
     * @return ShareLink
     * @throws Exception
     */
    protected function shareObjectFromParameters($linkData, &$hiddenUserEntries, &$shareObjects,  $type = "public", $invitationLabel = ""){
        if(isSet($linkData["hash"])){
            $link = $this->getShareStore()->loadShareObject($linkData["hash"]);
        }else{
            if($type == "public"){
                $link = $this->getShareStore()->createEmptyShareObject();
            }else{
                $link = new Pydio\OCS\Model\TargettedLink($this->getShareStore());
                if(AuthService::usersEnabled()) $link->setOwnerId(AuthService::getLoggedUser()->getId());
                $link->prepareInvitation($linkData["HOST"], $linkData["USER"], $invitationLabel);
            }
        }
        $link->parseHttpVars($linkData);
        $hiddenUserEntries[] = $this->getRightsManager()->prepareSharedUserEntry(
            $linkData,
            $link,
            isSet($linkData["hash"]),
            (isSet($linkData["guest_user_pass"])?$linkData["guest_user_pass"]:null)
        );
        $shareObjects[] = $link;
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @param array $httpVars
     * @param bool $update
     * @return Repository[]|ShareLink[]
     * @throws Exception
     */
    public function shareNode($ajxpNode, $httpVars, &$update){

        $hiddenUserEntries = array();
        $originalHttpVars = $httpVars;
        $ocsStore = new Pydio\OCS\Model\SQLStore();
        $ocsClient = new Pydio\OCS\Client\OCSClient();
        $userSelection = new UserSelection($this->repository, $httpVars);
        $mess = ConfService::getMessages();

        /**
         * @var ShareLink[] $shareObjects
         */
        $shareObjects = array();

        // PUBLIC LINK
        if(isSet($httpVars["enable_public_link"])){
            if(!$this->getAuthorization($ajxpNode->isLeaf() ? "file":"folder", "minisite")){
                throw new Exception($mess["share_center." . ($ajxpNode->isLeaf() ? "225" : "226")]);
            }
            $this->shareObjectFromParameters($httpVars, $hiddenUserEntries, $shareObjects, "public");
        }else if(isSet($httpVars["disable_public_link"])){
            $this->getShareStore()->deleteShare("minisite", $httpVars["disable_public_link"], true);
        }

        if(isSet($httpVars["ocs_data"])){
            $ocsData = json_decode($httpVars["ocs_data"], true);
            $removeLinks = $ocsData["REMOVE"];
            foreach($removeLinks as $linkHash){
                // Delete Link, delete invitation(s)
                $this->getShareStore()->deleteShare("minisite", $linkHash, true);
                $invitations = $ocsStore->invitationsForLink($linkHash);
                foreach($invitations as $invitation){
                    $ocsStore->deleteInvitation($invitation);
                    $ocsClient->cancelInvitation($invitation);
                }
            }
            $newLinks = $ocsData["LINKS"];
            foreach($newLinks as $linkData){
                $this->shareObjectFromParameters($linkData, $hiddenUserEntries, $shareObjects, "targetted", $userSelection->getUniqueNode()->getLabel());
            }
        }

        $this->filterHttpVarsFromUniqueNode($httpVars, $ajxpNode);

        $users = array(); $groups = array();
        $this->getRightsManager()->createUsersFromParameters($httpVars, $users, $groups);
        if((count($users) || count($groups)) && !$this->getAuthorization($ajxpNode->isLeaf()?"file":"folder", "workspace")){
            $users = $groups = array();
        }
        foreach($hiddenUserEntries as $entry){
            $users[$entry["ID"]] = $entry;
        }
        if(!count($users) && !count($groups)){
            ob_start();
            unset($originalHttpVars["hash"]);
            $this->switchAction("unshare", $originalHttpVars, array());
            ob_end_clean();
            return null;
        }

        $newRepo = $this->createSharedRepository($httpVars, $repoUpdate, $users, $groups);

        foreach($shareObjects as $shareObject){

            $shareObject->setParentRepositoryId($this->repository->getId());
            $shareObject->attachToRepository($newRepo->getId());
            $shareObject->save();
            if($shareObject instanceof \Pydio\OCS\Model\TargettedLink){
                $invitation = $shareObject->getPendingInvitation();
                if(!empty($invitation)){
                    try{
                        $ocsClient->sendInvitation($invitation);
                    }catch (Exception $e){
                        $this->getShareStore()->deleteShare("minisite", $shareObject->getHash(), true);
                        $shareUserId = $shareObject->getUniqueUser();
                        unset($users[$shareUserId]);
                        if(!count($users) && !count($groups)){
                            $this->getShareStore()->deleteShare("repository", $newRepo->getId());
                        }
                        throw $e;
                    }
                    $ocsStore->storeInvitation($invitation);
                }
            }else{
                $this->getPublicAccessManager()->initFolder();
            }

        }
        $shareObjects[] = $newRepo;
        return $shareObjects;

    }

    /**************************/
    /* LISTING FUNCTIONS
    /**************************/
    /**
     * @param bool|string $currentUser if true, currently logged user. if false all users. If string, user ID.
     * @param string $parentRepositoryId
     * @param null $cursor
     * @return array
     */
    public function listShares($currentUser = true, $parentRepositoryId="", $cursor = null){
        if($currentUser === false){
            $crtUser = "";
        }else if(AuthService::usersEnabled()){
            if($currentUser === true){
                $crtUser = AuthService::getLoggedUser()->getId();
            }else{
                $crtUser = $currentUser;
            }
        }else{
            $crtUser = "shared";
        }
        return $this->getShareStore()->listShares($crtUser, $parentRepositoryId, $cursor);
    }

    /**
     * @param $rootPath
     * @param bool|string $currentUser if true, currently logged user. if false all users. If string, user ID.
     * @param string $parentRepositoryId
     * @param null $cursor
     * @param bool $xmlPrint
     * @return AJXP_Node[]
     */
    public function listSharesAsNodes($rootPath, $currentUser = true, $parentRepositoryId = "", $cursor = null, $xmlPrint = false){

        $shares =  $this->listShares($currentUser, $parentRepositoryId, $cursor);
        $nodes = array();
        $parent = ConfService::getRepositoryById($parentRepositoryId);

        foreach($shares as $hash => $shareData){

            $icon = "folder";
            $meta = array(
                "icon"			=> $icon,
                "openicon"		=> $icon,
                "ajxp_mime" 	=> "repository_editable"
            );

            $shareType = $shareData["SHARE_TYPE"];
            $meta["share_type"] = $shareType;
            $meta["ajxp_shared"] = true;

            if(!is_object($shareData["REPOSITORY"])){

                $repoId = $shareData["REPOSITORY"];
                $repoObject = ConfService::getRepositoryById($repoId);
                if($repoObject == null){
                    $meta["text"] = "Invalid link";
                    continue;
                }
                $meta["text"] = $repoObject->getDisplay();
                $permissions = $this->getRightsManager()->computeSharedRepositoryAccessRights($repoId, true, null);
                $regularUsers = count(array_filter($permissions, function($a){
                    return (!isSet($a["HIDDEN"]) || $a["HIDDEN"] == false);
                })) > 0;
                $hiddenUsers = count(array_filter($permissions, function($a){
                    return (isSet($a["HIDDEN"]) && $a["HIDDEN"] == true);
                })) > 0;
                if($regularUsers && $hiddenUsers){
                    $meta["share_type_readable"] = "Public Link & Internal Users";
                }elseif($regularUsers){
                    $meta["share_type_readable"] = "Internal Users";
                }else if($hiddenUsers){
                    $meta["share_type_readable"] = "Public Link";
                }else{
                    $meta["share_type_readable"] =  $repoObject->hasContentFilter() ? "Public Link" : ($shareType == "repository"? "Internal Users": "Public Link");
                    if(isSet($shareData["LEGACY_REPO_OR_MINI"])){
                        $meta["share_type_readable"] = "Internal Only";
                    }
                }
                $meta["share_data"] = ($shareType == "repository" ? 'Shared as workspace: '.$repoObject->getDisplay() : $this->getPublicAccessManager()->buildPublicLink($hash));
                $meta["shared_element_hash"] = $hash;
                $meta["owner"] = $repoObject->getOwner();
                $meta["shared_element_parent_repository"] = $repoObject->getParentId();
                if(!empty($parent)) {
                    $parentPath = $parent->getOption("PATH", false, $meta["owner"]);
                    $meta["shared_element_parent_repository_label"] = $parent->getDisplay();
                }else{
                    $crtParent = ConfService::getRepositoryById($repoObject->getParentId());
                    if(!empty($crtParent)){
                        $parentPath = $crtParent->getOption("PATH", false, $meta["owner"]);
                        $meta["shared_element_parent_repository_label"] = $crtParent->getDisplay();
                    }else {
                        $meta["shared_element_parent_repository_label"] = $repoObject->getParentId();
                    }
                }
                if($repoObject->hasContentFilter()){
                    $meta["ajxp_shared_minisite"] = "file";
                    $meta["icon"] = "mime_empty.png";
                    $meta["original_path"] = array_pop(array_keys($repoObject->getContentFilter()->filters));
                }else{
                    $meta["ajxp_shared_minisite"] = "public";
                    $meta["icon"] = "folder.png";
                    $meta["original_path"] = $repoObject->getOption("PATH");
                }
                if(!empty($parentPath) &&  strpos($meta["original_path"], $parentPath) === 0){
                    $meta["original_path"] = substr($meta["original_path"], strlen($parentPath));
                }

            }else if(is_a($shareData["REPOSITORY"], "Repository") && !empty($shareData["FILE_PATH"])){

                $meta["owner"] = $shareData["OWNER_ID"];
                $meta["share_type_readable"] = "Publiclet (legacy)";
                $meta["text"] = basename($shareData["FILE_PATH"]);
                $meta["icon"] = "mime_empty.png";
                $meta["share_data"] = $meta["copy_url"] = $this->getPublicAccessManager()->buildPublicLink($hash);
                $meta["share_link"] = true;
                $meta["shared_element_hash"] = $hash;
                $meta["ajxp_shared_publiclet"] = $hash;

            }else{

                continue;

            }

            if($xmlPrint){
                AJXP_XMLWriter::renderAjxpNode(new AJXP_Node($rootPath."/".$hash, $meta));
            }else{
                $nodes[] = new AJXP_Node($rootPath."/".$hash, $meta);
            }
        }

        return $nodes;


    }

    /**
     * @param CompositeShare $compositeShare
     * @return array
     */
    public function compositeShareToJson($compositeShare){

        $repoId = $compositeShare->getRepositoryId();
        $repo = $compositeShare->getRepository();
        $messages = ConfService::getMessages();

        $notExistsData = array(
            "error"         => true,
            "repositoryId"  => $repoId,
            "users_number"  => 0,
            "label"         => "Error - Cannot find shared data",
            "description"   => "Cannot find repository",
            "entries"       => array(),
            "element_watch" => false,
            "repository_url"=> ""
        );

        if($repoId == null || $repo == null){
            //CLEAR AND ASSOCIATED LINKS HERE ?
            //$this->getShareStore()->getMetaManager()->removeShareFromMeta($node, $shareId);
            return $notExistsData;
        }
        try{
            $this->getShareStore()->testUserCanEditShare($compositeShare->getOwner(), array("SHARE_ACCESS" => $compositeShare->getVisibilityScope()));
        }catch(Exception $e){
            $notExistsData["label"] = $e->getMessage();
            return $notExistsData;
        }

        $jsonData = $compositeShare->toJson($this->watcher, $this->getRightsManager(), $this->getPublicAccessManager(), $messages);
        if($jsonData === false){
            return $notExistsData;
        }
        return $jsonData;

    }

    /**
     * @param String $shareId
     * @param array $shareMeta
     * @param AJXP_Node $node
     * @throws Exception
     * @return array|bool
     */
    public function shareToJson($shareId, $shareMeta, $node = null){

        $messages = ConfService::getMessages();
        $jsonData = array();
        $elementWatch = false;
        if($shareMeta["type"] == "file"){

            require_once("class.LegacyPubliclet.php");
            $jsonData = LegacyPubliclet::publicletToJson(
                $shareId,
                $shareMeta,
                $this->getShareStore(),
                $this->getPublicAccessManager(),
                $this->watcher,
                $node
            );

        }else if($shareMeta["type"] == "minisite" || $shareMeta["type"] == "repository"){

            $repoId = $shareId;
            if(strpos($repoId, "repo-") === 0){
                // Legacy
                $repoId = str_replace("repo-", "", $repoId);
                $shareMeta["type"] = "repository";
            }

            $minisite = ($shareMeta["type"] == "minisite");
            if ($minisite) {
                $shareLink = $this->getShareStore()->loadShareObject($shareId);
                $repoId = $shareLink->getRepositoryId();
            }
            $notExistsData = array(
                "error"         => true,
                "repositoryId"  => $repoId,
                "users_number"  => 0,
                "label"         => "Error - Cannot find shared data",
                "description"   => "Cannot find repository",
                "entries"       => array(),
                "element_watch" => false,
                "repository_url"=> ""
            );

            $repo = ConfService::getRepositoryById($repoId);
            if($repoId == null || ($repo == null && $node != null)){
                if($minisite){
                    $this->getShareStore()->getMetaManager()->removeShareFromMeta($node, $shareId);
                }
                return $notExistsData;
            }
            try{
                $this->getShareStore()->testUserCanEditShare($repo->getOwner(), $repo->options);
            }catch(Exception $e){
                $notExistsData["label"] = $e->getMessage();
                return $notExistsData;
            }

            if ($this->watcher != false && $node != null) {
                $elementWatch = $this->watcher->hasWatchOnNode(
                    new AJXP_Node("pydio://".$repoId."/"),
                    AuthService::getLoggedUser()->getId(),
                    MetaWatchRegister::$META_WATCH_NAMESPACE
                );
            }
            if($node != null){
                $sharedEntries = $this->getRightsManager()->computeSharedRepositoryAccessRights($repoId, true, new AJXP_Node("pydio://".$repoId."/"));
            }else{
                $sharedEntries = $this->getRightsManager()->computeSharedRepositoryAccessRights($repoId, true, null);
            }
            if(empty($sharedEntries) && $minisite){
                $this->getShareStore()->getMetaManager()->removeShareFromMeta($node, $shareId);
                return $notExistsData;
            }
            $cFilter = $repo->getContentFilter();
            if(!empty($cFilter)){
                $cFilter = $cFilter->toArray();
            }
            $jsonData = array(
                "repositoryId"  => $repoId,
                "users_number"  => AuthService::countUsersForRepository($repoId),
                "label"         => $repo->getDisplay(),
                "description"   => $repo->getDescription(),
                "entries"       => $sharedEntries,
                "element_watch" => $elementWatch,
                "repository_url"=> AJXP_Utils::getWorkspaceShortcutURL($repo)."/",
                "content_filter"=> $cFilter,
                "share_owner"   => $repo->getOwner(),
                "share_scope"    => (isSet($repo->options["SHARE_ACCESS"]) ? $repo->options["SHARE_ACCESS"] : "private")
            );
            if($minisite && isSet($shareLink)){
                $shareLink->setAdditionalMeta($shareMeta);
                $jsonData["minisite"] = $shareLink->getJsonData($this->getPublicAccessManager(), $messages);
            }

        }


        return $jsonData;

    }


}
