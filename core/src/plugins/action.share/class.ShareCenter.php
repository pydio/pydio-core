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
            $fileSharingAllowed = $this->getAuthorization("file");
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
     * @param string $shareType
     * @return bool
     */
    protected function getAuthorization($nodeType, $shareType = "any"){
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
            $this->rightsManager = new ShareRightsManager($this->getFilteredOption("SHARED_USERS_TMP_PREFIX", $this->repository), $this->watcher);
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

                $this->updateToMaxAllowedValue($httpVars, "FILE_MAX_DOWNLOAD", "downloadlimit");
                $this->updateToMaxAllowedValue($httpVars, "FILE_MAX_EXPIRATION", "expiration");

                $newMeta = null;
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
                        throw new Exception(103);
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

                        $newMeta = array("id" => $result->getUniqueId(), "type" => "repository");
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
                        $newMeta = array("id" => $hash, "type" => "minisite");
                        if($httpHash != null && $hash != $httpHash){
                            $originalHash = $httpHash;
                        }
                    }
                    $plainResult = $url;

                } else if ($subAction == "share_node"){

                    $httpVars["return_json"] = true;
                    if(isSet($httpVars["hash"]) && !empty($httpVars["hash"])) $httpHash = $httpVars["hash"];

                    $shareOrRepo = $this->shareNode($httpVars, $isUpdate);

                    if(is_a($shareOrRepo, "ShareLink")){
                        $hash = $shareOrRepo->getHash();
                        $newMeta = array("id" => $hash, "type" => "minisite");
                        if($httpHash != null && $hash != $httpHash){
                            $originalHash = $httpHash;
                        }
                    }else if(is_a($shareOrRepo, "Repository")){
                        $newMeta = array("id"  => $shareOrRepo->getUniqueId(), "type" => "repository");
                    }else{
                        // Share has been removed
                    }

                }

                if ($newMeta != null && $ajxpNode->hasMetaStore() && !$ajxpNode->isRoot()) {

                    $this->getShareStore()->getMetaManager()->addShareInMeta(
                        $ajxpNode,
                        $newMeta["type"],
                        $newMeta["id"],
                        ($shareScope == "public"),
                        $originalHash
                    );

                }

                AJXP_Controller::applyHook("msg.instant", array("<reload_shared_elements/>", ConfService::getRepository()->getId()));
                if(!isSet($httpVars["return_json"])){
                    header("Content-Type: text/plain");
                    print($plainResult);
                }else{
                    $this->switchAction(
                        "load_shared_element_data",
                        array("file" => $ajxpNode->getPath(), "merged" => "true"),
                        array()
                    );
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
                if(isSet($httpVars["hash"])){
                    $t = "minisite";
                    if(isSet($httpVars["element_type"]) && $httpVars["element_type"] == "file") $t = "file";
                    $parsedMeta = array($httpVars["hash"] => array("type" => $t));
                }else{
                    $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                    $node = new AJXP_Node($this->urlBase.$file);
                    $parsedMeta = array();
                    $this->getShareStore()->getMetaManager()->getSharesFromMeta($node, $parsedMeta, true);
                }

                $flattenJson = false;
                $jsonData = array();
                foreach($parsedMeta as $shareId => $shareMeta){

                    $jsonData[] = $this->shareToJson($shareId, $shareMeta, $node);
                    if($shareMeta["type"] != "file"){
                        $flattenJson = true;
                    }

                }
                header("Content-type:application/json");
                if(isSet($httpVars['merged']) && count($jsonData)){
                    // Send minisite ( = more complete ) if any, or send repository
                    foreach($jsonData as $data){
                        if(isSet($data['minisite'])){
                            $minisiteData = $data;
                            break;
                        }
                    }
                    if(isSet($minisiteData)) $jsonData = $minisiteData;
                    else $jsonData = $jsonData[0];

                }else if($flattenJson && count($jsonData)) {
                    $jsonData = $jsonData[0];
                }
                echo json_encode($jsonData);

            break;

            case "unshare":

                if(isSet($httpVars["hash"])){

                    $result = $this->getShareStore()->deleteShare($httpVars["element_type"], $httpVars["hash"]);
                    if($result !== false){
                        AJXP_XMLWriter::header();
                        AJXP_XMLWriter::sendMessage("Successfully unshared element", null);
                        AJXP_XMLWriter::close();
                    }

                }else{

                    $userSelection = new UserSelection($this->repository, $httpVars);
                    $ajxpNode = $userSelection->getUniqueNode();
                    $shares = array();
                    $this->getShareStore()->getMetaManager()->getSharesFromMeta($ajxpNode, $shares, false);
                    if(count($shares)){
                        if(isSet($httpVars["element_id"]) && isSet($shares[$httpVars["element_id"]])){
                            $elementId = $httpVars["element_id"];
                        }else{
                            $sKeys = array_keys($shares);
                            $elementId = $sKeys[0];
                        }
                        if(isSet($shares[$elementId]) && isSet($shares[$elementId]["type"])){
                            $t = $shares[$elementId]["type"];
                        }else{
                            $t = "file";
                        }
                        try{
                            $result = $this->getShareStore()->deleteShare($t, $elementId);
                        }catch(Exception $e){
                            if($e->getMessage() == "repo-not-found"){
                                $result = true;
                            }else{
                                throw $e;
                            }
                        }
                        if($result !== false){
                            $this->getShareStore()->getMetaManager()->removeShareFromMeta($ajxpNode, $elementId);
                            AJXP_XMLWriter::header();
                            AJXP_XMLWriter::sendMessage("Successfully unshared element", null);
                            AJXP_XMLWriter::close();
                            AJXP_Controller::applyHook("msg.instant", array("<reload_shared_elements/>", ConfService::getRepository()->getId()));
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
        if(!empty($shares) && count($shares)){
            $merge = array(
                "ajxp_shared"      => "true",
                "overlay_icon"     => "shared.png",
                "overlay_class"    => "icon-share-sign"
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

        if($oldNode != null && !$copy){
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
            $shareStore->moveSharesFromMetaRecursive($oldNode, $delete, $oldNode->getPath(), ($newNode != null ? $newNode->getPath() : null));
            // Force switching back to correct driver!
            $oldNode->getRepository()->driverInstance = null;
            $oldNode->setDriver(null);
            $oldNode->getDriver();
            return;
        }

    }

    /**
     * Hook user.after_delete
     * make sure to clear orphan shares
     * @param String $userId
     */
    public function cleanUserShares($userId){
        $shares = $this->getShareStore()->listShares($userId);
        foreach($shares as $hash => $data){
            $this->getShareStore()->deleteShare($data['SHARE_TYPE'], $hash);
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
                            $wsId = $minisiteData["REPOSITORY"];
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
            self::loadMinisite(array(), $hash, $mess["share_center.165"]);
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
            self::loadMinisite(array(), $hash, $mess["share_center.166"]);
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
     * @param bool $update
     * @return Repository
     * @throws Exception
     */
    protected function createOrLoadSharedRepository($httpVars, &$update){

        if (!isSet($httpVars["repo_label"]) || $httpVars["repo_label"] == "") {
            throw new Exception(100);
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
            throw new Exception(101);
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
                throw new Exception("You are not allowed to handle this share. Please ask the owner of the share.");
            }
            if($newScope !== $oldScope){
                $editingRepo->addOption("SHARE_ACCESS", $newScope);
                $replace = true;
            }
            if(isSet($httpVars["transfer_owner"])){
                $newOwner = $httpVars["transfer_owner"];
                if($newOwner != $currentOwner && $currentOwner != AuthService::getLoggedUser()->getId()){
                    throw new Exception("You are not allowed to handle this share. Please ask the owner");
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
     * @param ShareLink $shareObject
     * @param bool $update
     * @param null $guestUserPass
     * @return array
     * @throws Exception
     */
    protected function prepareSharedUserEntry($httpVars, &$shareObject, $update, $guestUserPass = null){
        $userPass = null;

        $forcePassword = $this->getFilteredOption("SHARE_FORCE_PASSWORD", $this->repository);
        if($forcePassword && (
                (isSet($httpVars["create_guest_user"]) && $httpVars["create_guest_user"] == "true" && empty($guestUserPass))
                || (isSet($httpVars["guest_user_id"]) && isSet($guestUserPass) && strlen($guestUserPass) == 0)
            )){
            $mess = ConfService::getMessages();
            throw new Exception($mess["share_center.175"]);
        }

        if($update){

            // THIS IS AN EXISTING SHARE
            // FIND SHARE AND EXISTING HIDDEN USER ID
            if($shareObject->isAttachedToRepository()){
                $existingRepo = $shareObject->getRepository();
                $this->getShareStore()->testUserCanEditShare($existingRepo->getOwner(), $existingRepo->options);
            }
            $uniqueUser = $shareObject->getUniqueUser();

            if($guestUserPass !== null && strlen($guestUserPass)) {
                $userPass = $guestUserPass;
                $shareObject->setUniqueUser($uniqueUser, true);
            }else if(!$shareObject->shouldRequirePassword() || ($guestUserPass !== null && $guestUserPass == "")){
                $shareObject->setUniqueUser($uniqueUser, false);
            }

        } else {

            $update = false;
            $shareObject->createHiddenUserId(
                $this->getFilteredOption("SHARED_USERS_TMP_PREFIX", $this->repository),
                !empty($guestUserPass)
            );
            if(!empty($guestUserPass)){
                $userPass = $guestUserPass;
            }else{
                $userPass = $shareObject->createHiddenUserPassword();
            }
            $uniqueUser = $shareObject->getUniqueUser();
        }

        $hiddenUserEntry = $this->getRightsManager()->createHiddenUserEntry($httpVars, $uniqueUser, $userPass, $update);
        if(empty($hiddenUserEntry["RIGHT"])){
            throw new Exception("share_center.58");
        }
        return $hiddenUserEntry;
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
        $hiddenUserEntry = $this->prepareSharedUserEntry(
            $httpVars,
            $shareObject,
            isSet($httpVars["hash"]),
            (isSet($httpVars["guest_user_pass"])?$httpVars["guest_user_pass"]:null)
        );
        $shareObject->parseHttpVars($httpVars);
        $userSelection = new UserSelection($this->repository, $httpVars);
        $this->filterHttpVarsForLeafPath($httpVars, $userSelection);

        $users = array(); $groups = array();
        $users[$hiddenUserEntry["ID"]] = $hiddenUserEntry;

        $newRepo = $this->createSharedRepository($httpVars, $repoUpdate, $users, $groups, $shareObject->disableDownload());

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
     * @param array|null $hiddenUserEntry
     * @param bool $disableDownload
     * @return Repository
     * @throws Exception
     */
    public function createSharedRepository($httpVars, &$update, $users=array(), $groups=array(), $disableDownload = false)
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
            throw new Exception(103);
        }

        $newRepo = $this->createOrLoadSharedRepository($httpVars, $update);

        $selection = new UserSelection($this->repository, $httpVars);
        $this->getRightsManager()->assignSharedRepositoryPermissions($this->repository, $newRepo, $update, $users, $groups, $selection, $disableDownload);

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


        return $newRepo;
    }

    /**
     * @param array $httpVars
     * @param bool $update
     * @return Repository|ShareLink
     * @throws Exception
     */
    public function shareNode($httpVars, &$update){

        $hiddenUserEntry = null;
        $downloadDisabled = false;
        if(isSet($httpVars["enable_public_link"])){
            // PREPARE HIDDEN USER DATA
            if(isSet($httpVars["hash"])){
                $shareObject = $this->getShareStore()->loadShareObject($httpVars["hash"]);
            }else{
                $shareObject = $this->getShareStore()->createEmptyShareObject();
            }
            $hiddenUserEntry = $this->prepareSharedUserEntry(
                $httpVars,
                $shareObject,
                isSet($httpVars["hash"]),
                (isSet($httpVars["guest_user_pass"])?$httpVars["guest_user_pass"]:null)
            );
            $shareObject->parseHttpVars($httpVars);
            $downloadDisabled = $shareObject->disableDownload();
        }else if(isSet($httpVars["disable_public_link"])){
            // TODO: Check if we need to keep the repository
            // or not depending on other users
            // $hasOtherUsers = false;
            $this->getShareStore()->deleteShare("minisite", $httpVars["disable_public_link"], true);
            // Todo : seems like metadata is not deleted
        }
        $userSelection = new UserSelection($this->repository, $httpVars);
        $this->filterHttpVarsForLeafPath($httpVars, $userSelection);

        $users = array(); $groups = array();
        $this->getRightsManager()->createUsersFromParameters($httpVars, $users, $groups);
        if(isSet($hiddenUserEntry)){
            $users[$hiddenUserEntry["ID"]] = $hiddenUserEntry;
        }
        if(!count($users) && !count($groups)){
            ob_start();
            $this->switchAction("unshare", $httpVars, array());
            ob_end_clean();
            return null;
        }

        $newRepo = $this->createSharedRepository($httpVars, $repoUpdate, $users, $groups, $downloadDisabled);

        if(isSet($shareObject)){

            $shareObject->setParentRepositoryId($this->repository->getId());
            $shareObject->attachToRepository($newRepo->getId());
            // STORE DATA & HASH IN SHARE STORE
            $this->getPublicAccessManager()->initFolder();
            $hash = $shareObject->save();
            return $shareObject;

        }else{

            return $newRepo;

        }


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

            $icon = "hdd_external_mount.png";
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
                $meta["share_type_readable"] =  $repoObject->hasContentFilter() ? "Publiclet" : ($shareType == "repository"? "Workspace": "Minisite");
                if(isSet($shareData["LEGACY_REPO_OR_MINI"])){
                    $meta["share_type_readable"] = "Repository or Minisite (legacy)";
                }
                $meta["share_data"] = ($shareType == "repository" ? 'Shared as workspace: '.$repoObject->getDisplay() : $this->getPublicAccessManager()->buildPublicLink($hash));
                $meta["shared_element_hash"] = $hash;
                $meta["owner"] = $repoObject->getOwner();
                if($shareType != "repository") {
                    $meta["copy_url"]  = $this->getPublicAccessManager()->buildPublicLink($hash);
                }
                $meta["shared_element_parent_repository"] = $repoObject->getParentId();
                if(!empty($parent)) {
                    $parentPath = $parent->getOption("PATH", false, $meta["owner"]);
                    $meta["shared_element_parent_repository_label"] = $parent->getDisplay();
                }else{
                    $crtParent = ConfService::getRepositoryById($repoObject->getParentId());
                    if(!empty($crtParent)){
                        $meta["shared_element_parent_repository_label"] = $crtParent->getDisplay();
                    }else {
                        $meta["shared_element_parent_repository_label"] = $repoObject->getParentId();
                    }
                }
                if($shareType != "repository"){
                    if($repoObject->hasContentFilter()){
                        $meta["ajxp_shared_minisite"] = "file";
                        $meta["icon"] = "mime_empty.png";
                        $meta["original_path"] = array_pop(array_keys($repoObject->getContentFilter()->filters));
                    }else{
                        $meta["ajxp_shared_minisite"] = "public";
                        $meta["icon"] = "folder.png";
                        $meta["original_path"] = $repoObject->getOption("PATH");
                    }
                    $meta["icon"] = $repoObject->hasContentFilter() ? "mime_empty.png" : "folder.png";
                }else{
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
            $minisiteIsPublic = false;
            $dlDisabled = false;
            $minisiteLink = '';
            if ($minisite) {
                $storedData = $this->getShareStore()->loadShare($shareId);
                $repoId = $storedData["REPOSITORY"];
                $minisiteIsPublic = isSet($storedData["PRELOG_USER"]);
                $dlDisabled = isSet($storedData["DOWNLOAD_DISABLED"]) && $storedData["DOWNLOAD_DISABLED"] === true;
                if (isSet($shareMeta["short_form_url"])) {
                    $minisiteLink = $shareMeta["short_form_url"];
                } else {
                    $minisiteLink = $this->getPublicAccessManager()->buildPublicLink($shareId);
                }
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
            if ($minisite && isSet($storedData)) {
                if(!empty($storedData["DOWNLOAD_LIMIT"]) && !$dlDisabled){
                    $jsonData["download_counter"] = $this->getShareStore()->getCurrentDownloadCounter($shareId);
                    $jsonData["download_limit"] = $storedData["DOWNLOAD_LIMIT"];
                }
                if(!empty($storedData["EXPIRE_TIME"])){
                    $delta = $storedData["EXPIRE_TIME"] - time();
                    $days = round($delta / (60*60*24));
                    $jsonData["expire_time"] = date($messages["date_format"], $storedData["EXPIRE_TIME"]);
                    $jsonData["expire_after"] = $days;
                }else{
                    $jsonData["expire_after"] = 0;
                }
                $jsonData["is_expired"] = $this->getShareStore()->isShareExpired($shareId, $storedData);
                if(isSet($storedData["AJXP_TEMPLATE_NAME"])){
                    $jsonData["minisite_layout"] = $storedData["AJXP_TEMPLATE_NAME"];
                }
                if(!$minisiteIsPublic){
                    $jsonData["has_password"] = true;
                }
                $jsonData["minisite"] = array(
                    "public"            => $minisiteIsPublic?"true":"false",
                    "public_link"       => $minisiteLink,
                    "disable_download"  => $dlDisabled,
                    "hash"              => $shareId,
                    "hash_is_shorten"   => isSet($shareMeta["short_form_url"])
                );
                foreach($this->getShareStore()->modifiableShareKeys as $key){
                    if(isSet($storedData[$key])) $jsonData[$key] = $storedData[$key];
                }

            }

        }


        return $jsonData;

    }


}
