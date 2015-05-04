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
    private $baseProtocol;

    /**
     * @var ShareStore
     */
    private $shareStore;

    /**
     * @var MetaWatchRegister
     */
    private $watcher = false;

    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if (isSet($this->actions["share"])) {
            $disableSharing = false;
            $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
            if ($downloadFolder == "") {
                $disableSharing = true;
            } else if ((!is_dir($downloadFolder) || !is_writable($downloadFolder))) {
                $this->logDebug("Disabling Public links, $downloadFolder is not writeable!", array("folder" => $downloadFolder, "is_dir" => is_dir($downloadFolder),"is_writeable" => is_writable($downloadFolder)));
                $disableSharing = true;
            } else {
                if (AuthService::usersEnabled()) {
                    $loggedUser = AuthService::getLoggedUser();
                    if ($loggedUser != null && AuthService::isReservedUserId($loggedUser->getId())) {
                        $disableSharing = true;
                    }
                } else {
                    $disableSharing = true;
                }
            }
            $repo = ConfService::getRepository();
            // Hacky but necessary to edit roles...
            if(!is_a($repo->driverInstance, "AjxpWrapperProvider")
                && !(isset($_GET["get_action"]) && $_GET["get_action"]=="list_all_plugins_actions")){
                $disableSharing = true;
            }
            $xpathesToRemove = array();
            if ($disableSharing) {
                // All share- actions
                $xpathesToRemove[] = 'action[contains(@name, "share-")]';
            }else{
                $folderSharingMode = $this->pluginConf["ENABLE_FOLDER_SHARING"];
                $fileSharingAllowed = $this->pluginConf["ENABLE_FILE_PUBLIC_LINK"];
                if($fileSharingAllowed === false){
                    // Share file button
                    $xpathesToRemove[] = 'action[@name="share-file-minisite"]';
                }
                if($folderSharingMode == 'disable'){
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
    }

    public function init($options)
    {
        parent::init($options);
        $this->repository = ConfService::getRepository();
        if (!is_a($this->repository->driverInstance, "AjxpWrapperProvider")) {
            return;
        }
        $this->accessDriver = $this->repository->driverInstance;
        $this->urlBase = $this->repository->driverInstance->getResourceUrl("/");
        $this->baseProtocol = array_shift(explode("://", $this->urlBase));
        if (array_key_exists("meta.watch", AJXP_PluginsService::getInstance()->getActivePlugins())) {
            $this->watcher = AJXP_PluginsService::getInstance()->getPluginById("meta.watch");
        }
    }

    /**
     * @return ShareCenter
     */
    public static function getShareCenter(){
        return AJXP_PluginsService::findPluginById("action.share");
    }

    /**
     * @return ShareStore
     */
    private function getShareStore(){
        if(!isSet($this->shareStore)){
            require_once("class.ShareStore.php");
            $hMin = 32;
            if(isSet($this->repository)){
                $hMin = $this->getFilteredOption("HASH_MIN_LENGTH", $this->repository->getId());
            }
            $this->shareStore = new ShareStore(
                ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"),
                $hMin
            );
        }
        return $this->shareStore;
    }

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
                if(empty($subAction) && isSet($httpVars["simple_share_type"])){
                    $subAction = "create_minisite";
                    if(!isSet($httpVars["simple_right_read"]) && !isSet($httpVars["simple_right_download"])){
                        $httpVars["simple_right_read"] = $httpVars["simple_right_download"] = "true";
                    }
                }
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $ajxpNode = new AJXP_Node($this->urlBase.$file);
                if (!file_exists($ajxpNode->getUrl())) {
                    throw new Exception("Cannot share a non-existing file: ".$ajxpNode->getUrl());
                }
                //$metadata = null;
                $newMeta = null;
                $maxdownload = abs(intval($this->getFilteredOption("FILE_MAX_DOWNLOAD", $this->repository->getId())));
                $download = isset($httpVars["downloadlimit"]) ? abs(intval($httpVars["downloadlimit"])) : 0;
                if ($maxdownload == 0) {
                    $httpVars["downloadlimit"] = $download;
                } elseif ($maxdownload > 0 && $download == 0) {
                    $httpVars["downloadlimit"] = $maxdownload;
                } else {
                    $httpVars["downloadlimit"] = min($download,$maxdownload);
                }
                $maxexpiration = abs(intval($this->getFilteredOption("FILE_MAX_EXPIRATION", $this->repository->getId())));
                $expiration = isset($httpVars["expiration"]) ? abs(intval($httpVars["expiration"])) : 0;
                if ($maxexpiration == 0) {
                    $httpVars["expiration"] = $expiration;
                } elseif ($maxexpiration > 0 && $expiration == 0) {
                    $httpVars["expiration"] = $maxexpiration;
                } else {
                    $httpVars["expiration"] = min($expiration,$maxexpiration);
                }
                $forcePassword = $this->getFilteredOption("SHARE_FORCE_PASSWORD", $this->repository->getId());
                $httpHash = null;
                $originalHash = null;

                if ($subAction == "delegate_repo") {
                    header("Content-type:text/plain");
                    $result = $this->createSharedRepository($httpVars, $this->repository, $this->accessDriver);
                    if (is_a($result, "Repository")) {
                        $newMeta = array("id" => $result->getUniqueId(), "type" => "repository");
                        $numResult = 200;
                    } else {
                        $numResult = $result;
                    }
                    print($numResult);
                } else if ($subAction == "create_minisite") {
                    header("Content-type:text/plain");
                    if(isSet($httpVars["hash"]) && !empty($httpVars["hash"])) $httpHash = $httpVars["hash"];
                    if(isSet($httpVars["simple_share_type"])){
                        $httpVars["create_guest_user"] = "true";
                        if($httpVars["simple_share_type"] == "private" && !isSet($httpVars["guest_user_pass"])){
                            throw new Exception("Please provide a guest_user_pass for private link");
                        }
                    }
                    if($forcePassword && (
                        (isSet($httpVars["create_guest_user"]) && $httpVars["create_guest_user"] == "true" && empty($httpVars["guest_user_pass"]))
                        || (isSet($httpVars["guest_user_id"]) && isSet($httpVars["guest_user_pass"]) && $httpVars["guest_user_pass"] == "")
                        )){
                        $mess = ConfService::getMessages();
                        throw new Exception($mess["share_center.175"]);
                    }
                    $res = $this->createSharedMinisite($httpVars, $this->repository, $this->accessDriver);
                    if (!is_array($res)) {
                        $url = $res;
                    } else {
                        list($hash, $url) = $res;
                        $newMeta = array("id" => $hash, "type" => "minisite");
                        if($httpHash != null && $hash != $httpHash){
                            $originalHash = $httpHash;
                        }
                    }
                    print($url);
                } else {
                    $data = $this->accessDriver->makePublicletOptions($file, $httpVars["password"], $httpVars["expiration"], $httpVars["downloadlimit"], $this->repository);
                    $customData = array();
                    foreach ($httpVars as $key => $value) {
                        if (substr($key, 0, strlen("PLUGINS_DATA_")) == "PLUGINS_DATA_") {
                            $customData[substr($key, strlen("PLUGINS_DATA_"))] = $value;
                        }
                    }
                    if (count($customData)) {
                        $data["PLUGINS_DATA"] = $customData;
                    }
                    list($hash, $url) = $this->writePubliclet($data, $this->accessDriver, $this->repository);
                    $newMeta = array("id" => $hash, "type" => "file");
                    if (isSet($httpVars["format"]) && $httpVars["format"] == "json") {
                        header("Content-type:application/json");
                        echo json_encode(array("element_id" => $hash, "publiclet_link" => $url));
                    } else {
                        header("Content-type:text/plain");
                        echo $url;
                    }
                    flush();
                }
                if ($newMeta != null && $ajxpNode->hasMetaStore() && !$ajxpNode->isRoot()) {
                    $this->addShareInMeta($ajxpNode, $newMeta["type"], $newMeta["id"], $originalHash);
                }
                AJXP_Controller::applyHook("msg.instant", array("<reload_shared_elements/>", ConfService::getRepository()->getId()));
                // as the result can be quite small (e.g error code), make sure it's output in case of OB active.
                flush();

                break;

            case "toggle_link_watch":

                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $watchValue = $httpVars["set_watch"] == "true" ? true : false;
                $folder = false;
                $shNode = new AJXP_Node($this->urlBase.$file);
                if (isSet($httpVars["element_type"]) && $httpVars["element_type"] == "folder") {
                    $folder = true;
                    $node = new AJXP_Node($this->baseProtocol."://".$httpVars["repository_id"]."/");
                } else {
                    $node = new AJXP_Node($this->urlBase.$file);
                }

                $this->getSharesFromMeta($shNode, $shares, false);
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
                                $node,
                                AuthService::getLoggedUser()->getId(),
                                MetaWatchRegister::$META_WATCH_USERS_READ,
                                array($elementId)
                            );
                        } else {
                            $this->watcher->removeWatchFromFolder(
                                $node,
                                AuthService::getLoggedUser()->getId(),
                                true,
                                $elementId
                            );
                        }
                    } else {
                        if ($watchValue) {
                            $this->watcher->setWatchOnFolder(
                                $node,
                                AuthService::getLoggedUser()->getId(),
                                MetaWatchRegister::$META_WATCH_BOTH
                            );
                        } else {
                            $this->watcher->removeWatchFromFolder(
                                $node,
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
                    $this->getSharesFromMeta($node, $parsedMeta, true);
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
                if($flattenJson && count($jsonData)) $jsonData = $jsonData[0];
                echo json_encode($jsonData);

            break;

            case "unshare":

                if(isSet($httpVars["hash"])){

                    $res = $this->getShareStore()->deleteShare($httpVars["element_type"], $httpVars["hash"]);
                    if($res !== false){
                        AJXP_XMLWriter::header();
                        AJXP_XMLWriter::sendMessage("Successfully unshared element", null);
                        AJXP_XMLWriter::close();
                    }

                }else{

                    $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                    $ajxpNode = new AJXP_Node($this->urlBase.$file);
                    $this->getSharesFromMeta($ajxpNode, $shares, false);
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
                        $this->getShareStore()->deleteShare($t, $elementId);
                        $this->removeShareFromMeta($ajxpNode, $elementId);
                        AJXP_Controller::applyHook("msg.instant", array("<reload_shared_elements/>", ConfService::getRepository()->getId()));
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

                    $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                    $ajxpNode = new AJXP_Node($this->urlBase.$file);
                    $metadata = $ajxpNode->retrieveMetadata(
                        "ajxp_shared",
                        true,
                        AJXP_METADATA_SCOPE_REPOSITORY
                    );
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
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                if($this->getShareStore()->shareIsLegacy($hash)){
                    // Store in metadata
                    $ajxpNode = new AJXP_Node($this->urlBase.$file);
                    $metadata = $ajxpNode->retrieveMetadata(
                        "ajxp_shared",
                        true,
                        AJXP_METADATA_SCOPE_REPOSITORY
                    );
                    if (isSet($metadata["shares"][$httpVars["element_id"]])) {
                        if (!is_array($metadata["shares"][$httpVars["element_id"]])) {
                            $metadata["shares"][$httpVars["element_id"]] = array();
                        }
                        $metadata["shares"][$httpVars["element_id"]][$httpVars["p_name"]] = $httpVars["p_value"];
                        $ajxpNode->setMetadata(
                            "ajxp_shared",
                            $metadata,
                            true,
                            AJXP_METADATA_SCOPE_REPOSITORY);
                    }
                }else{
                    $this->getShareStore()->updateShareProperty($hash, $httpVars["p_name"], $httpVars["p_value"]);
                }


                break;

            case "sharelist-load":

                $parentRepoId = isset($httpVars["parent_repository_id"]) ? $httpVars["parent_repository_id"] : "";
                $userContext = $httpVars["user_context"];
                $currentUser = true;
                if($userContext == "global" && AuthService::getLoggedUser()->isAdmin()){
                    $currentUser = false;
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

                $currentUser  = (ConfService::getRepository()->getAccessType() != "ajxp_conf");
                $count = $this->clearExpiredFiles($currentUser);
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


    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    public function nodeSharedMetadata(&$ajxpNode)
    {
        if(empty($this->accessDriver) || $this->accessDriver->getId() == "access.imap") return;

        $this->getSharesFromMeta($ajxpNode, $shares, true);
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
     * @param AJXP_Node $ajxpNode
     * @return boolean
     */
    public function isShared($ajxpNode)
    {
        $this->getSharesFromMeta($ajxpNode, $shares, true);
        return count($shares) > 0;
    }

    /**
     * @param AJXP_Node $node
     * @param String|null $direction "UP", "DOWN"
     * @return array()
     */
    private function findMirrorNodesInShares($node, $direction){
        $result = array();
        if($direction !== "UP"){
            $upmetas = array();
            $node->collectMetadataInParents("ajxp_shared", AJXP_METADATA_ALLUSERS, AJXP_METADATA_SCOPE_REPOSITORY, false, $upmetas);
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
                $currentRoot = $node->getRepository()->getOption("PATH");
                $owner = $node->getRepository()->getOwner();
                $resolveUser = null;
                if($owner != null){
                    $resolveUser = ConfService::getConfStorageImpl()->createUserObject($owner);
                }
                $parentRoot = ConfService::getRepositoryById($parentRepoId)->getOption("PATH", false, $resolveUser);
                $relative = substr($currentRoot, strlen($parentRoot));
                $parentNodeURL = $node->getScheme()."://".$parentRepoId.$relative.$node->getPath();
                $this->logDebug("action.share", "Should trigger on ".$parentNodeURL);
                $parentNode = new AJXP_Node($parentNodeURL);
                if($owner != null) $parentNode->setUser($owner);
                $result[$parentRepoId] = array($parentNode, "UP");
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

    /**
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

            $this->getSharesFromMeta($oldNode, $shares, true);
            if(empty($shares)) {
                return;
            }

            $newShares = array();
            foreach($shares as $id => $data){
                $type = $data["type"];
                if($delete){
                    $this->getShareStore()->deleteShare($type, $id);
                    continue;
                }

                if($type == "minisite"){
                    $share = $this->getShareStore()->loadShare($id);
                    $repo = ConfService::getRepositoryById($share["REPOSITORY"]);
                }else if($type == "repository"){
                    $repo = ConfService::getRepositoryById($id);
                }else if($type == "file"){
                    $publicLink = $this->getShareStore()->loadShare($id);
                }
                if(isSet($repo)){

                    $cFilter = $repo->getContentFilter();
                    $path = $repo->getOption("PATH", true);
                    $save = false;
                    if(isSet($cFilter)){
                        $cFilter->movePath($oldNode->getPath(), $newNode->getPath());
                        $repo->setContentFilter($cFilter);
                        $save = true;
                    }else if(!empty($path)){
                        $path = str_replace($oldNode->getPath(), $newNode->getPath(), $path);
                        $repo->addOption("PATH", $path);
                        $save = true;
                    }
                    if($save){
                        ConfService::getConfStorageImpl()->saveRepository($repo, true);
                        $newShares[$id] = $data;
                    }

                } else {

                    if(isSet($publicLink["FILE_PATH"])){
                        $publicLink["FILE_PATH"] = str_replace($oldNode->getPath(), $newNode->getPath(), $publicLink["FILE_PATH"]);
                        $this->getShareStore()->deleteShare("file", $id);
                        $this->getShareStore()->storeShare($newNode->getRepositoryId(), $publicLink, "file", $id);
                        $newShares[$id] = $data;
                    }

                }
            }
            $oldNode->removeMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
            if($newNode != null && count($newShares)){
                $newNode->setMetadata("ajxp_shared", array("shares" => $newShares), true, AJXP_METADATA_SCOPE_REPOSITORY, true);
            }

        }
    }

    /**
     * @param Array $data
     * @param AbstractAccessDriver $accessDriver
     * @param Repository $repository
     */
    protected function storeSafeCredentialsIfNeeded(&$data, $accessDriver, $repository){
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

    /** Cypher the publiclet object data and write to disk.
     * @param Array $data The publiclet data array to write
                     The data array must have the following keys:
                     - DRIVER      The driver used to get the file's content
                     - OPTIONS     The driver options to be successfully constructed (usually, the user and password)
                     - FILE_PATH   The path to the file's content
                     - PASSWORD    If set, the written publiclet will ask for this password before sending the content
                     - ACTION      If set, action to perform
                     - USER        If set, the AJXP user
                     - EXPIRE_TIME If set, the publiclet will deny downloading after this time, and probably self destruct.
     *               - AUTHOR_WATCH If set, will post notifications for the publiclet author each time the file is loaded
     * @param AbstractAccessDriver $accessDriver
     * @param Repository $repository
     * @return array An array containing the hash (0) and the generated url (1)
    */
    public function writePubliclet(&$data, $accessDriver, $repository)
    {
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        if (!is_dir($downloadFolder)) {
            return "ERROR : Public URL folder does not exist!";
        }
        if (!function_exists("mcrypt_create_iv")) {
            return "ERROR : MCrypt must be installed to use publiclets!";
        }
        $this->initPublicFolder($downloadFolder);
        $data["PLUGIN_ID"] = $accessDriver->getId();
        $data["BASE_DIR"] = $accessDriver->getBaseDir();
        //$data["REPOSITORY"] = $repository;
        if (AuthService::usersEnabled()) {
            $data["OWNER_ID"] = AuthService::getLoggedUser()->getId();
        }
        $this->storeSafeCredentialsIfNeeded($data, $accessDriver, $repository);

        // Force expanded path in publiclet
        $copy = clone $repository;
        $copy->addOption("PATH", $repository->getOption("PATH"));
        $data["REPOSITORY"] = $copy;
        if ($data["ACTION"] == "") $data["ACTION"] = "download";

        try{
            $hash = $this->getShareStore()->storeShare($repository->getId(), $data, "publiclet");
        }catch(Exception $e){
            return $e->getMessage();
        }

        $this->getShareStore()->resetDownloadCounter($hash, AuthService::getLoggedUser()->getId());
        $url = $this->buildPublicletLink($hash);
        $this->logInfo("New Share", array(
            "file" => "'".$copy->display.":/".$data['FILE_PATH']."'",
            "url" => $url,
            "expiration" => $data['EXPIRE_TIME'],
            "limit" => $data['DOWNLOAD_LIMIT'],
            "repo_uuid" => $copy->uuid
        ));
        AJXP_Controller::applyHook("node.share.create", array(
            'type' => 'file',
            'repository' => &$copy,
            'accessDriver' => &$accessDriver,
            'data' => &$data,
            'url' => $url,
        ));
        return array($hash, $url);
    }

    public function buildPublicDlURL()
    {
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $dlURL = ConfService::getCoreConf("PUBLIC_DOWNLOAD_URL");
        if ($dlURL != "") {
            return rtrim($dlURL, "/");
        } else {
            $fullUrl = AJXP_Utils::detectServerURL(true);
            return str_replace("\\", "/", rtrim($fullUrl, "/").rtrim(str_replace(AJXP_INSTALL_PATH, "", $downloadFolder), "/"));
        }
    }

    public function computeMinisiteToServerURL()
    {
        $minisite = parse_url($this->buildPublicDlURL(), PHP_URL_PATH) ."/a.php";
        $server = rtrim(parse_url( AJXP_Utils::detectServerURL(true), PHP_URL_PATH), "/");
        return AJXP_Utils::getTravelPath($minisite, $server);
    }

    public function buildPublicletLink($hash)
    {
        $addLang = ConfService::getLanguage() != ConfService::getCoreConf("DEFAULT_LANGUAGE");
        if ($this->getFilteredOption("USE_REWRITE_RULE", $this->repository->getId()) == true) {
            if($addLang) return $this->buildPublicDlURL()."/".$hash."--".ConfService::getLanguage();
            else return $this->buildPublicDlURL()."/".$hash;
        } else {
            if($addLang) return $this->buildPublicDlURL()."/".$hash.".php?lang=".ConfService::getLanguage();
            else return $this->buildPublicDlURL()."/".$hash.".php";
        }
    }

    public function initPublicFolder($downloadFolder)
    {
        if (is_file($downloadFolder."/grid_t.png")) {
            return;
        }
        $language = ConfService::getLanguage();
        $pDir = dirname(__FILE__);
        $messages = array();
        if (is_file($pDir."/res/i18n/".$language.".php")) {
            include($pDir."/res/i18n/".$language.".php");
        } else {
            include($pDir."/res/i18n/en.php");
        }
        if (isSet($mess)) $messages = $mess;
        $sTitle = sprintf($messages[1], ConfService::getCoreConf("APPLICATION_TITLE"));
        $sLegend = $messages[20];

        @copy($pDir."/res/dl.png", $downloadFolder."/dl.png");
        @copy($pDir."/res/favi.png", $downloadFolder."/favi.png");
        @copy($pDir."/res/grid_t.png", $downloadFolder."/grid_t.png");
        @copy($pDir."/res/button_cancel.png", $downloadFolder."/button_cancel.png");
        @copy(AJXP_INSTALL_PATH."/server/index.html", $downloadFolder."/index.html");
        $dlUrl = $this->buildPublicDlURL();
        $htaccessContent = "Order Deny,Allow\nAllow from all\n";
        $htaccessContent .= "\n<Files \".ajxp_*\">\ndeny from all\n</Files>\n";
        $path = parse_url($dlUrl, PHP_URL_PATH);
        $htaccessContent .= '
        <IfModule mod_rewrite.c>
        RewriteEngine on
        RewriteBase '.$path.'
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^([a-zA-Z0-9_-]+)\.php$ share.php?hash=$1 [QSA]
        RewriteRule ^([a-zA-Z0-9_-]+)--([a-z]+)$ share.php?hash=$1&lang=$2 [QSA]
        RewriteRule ^([a-zA-Z0-9_-]+)$ share.php?hash=$1 [QSA]
        </IfModule>
        ';
        file_put_contents($downloadFolder."/.htaccess", $htaccessContent);
        $content404 = file_get_contents($pDir."/res/404.html");
        $content404 = str_replace(array("AJXP_MESSAGE_TITLE", "AJXP_MESSAGE_LEGEND"), array($sTitle, $sLegend), $content404);
        file_put_contents($downloadFolder."/404.html", $content404);

    }

    public static function loadMinisite($data, $hash = '', $error = null)
    {
        if(isset($data["SECURITY_MODIFIED"]) && $data["SECURITY_MODIFIED"] === true){
            $mess = ConfService::getMessages();
            $error = $mess['share_center.164'];
        }
        $repository = $data["REPOSITORY"];
        AJXP_PluginsService::getInstance()->initActivePlugins();
        $shareCenter = AJXP_PluginsService::findPlugin("action", "share");
        $confs = $shareCenter->getConfigs();
        $minisiteLogo = "plugins/gui.ajax/PydioLogo250.png";
        if(!empty($confs["CUSTOM_MINISITE_LOGO"])){
            $logoPath = $confs["CUSTOM_MINISITE_LOGO"];
            if (strpos($logoPath, "plugins/") === 0 && is_file(AJXP_INSTALL_PATH."/".$logoPath)) {
                $minisiteLogo = $logoPath;
            }else{
                $minisiteLogo = "index_shared.php?get_action=get_global_binary_param&binary_id=". $logoPath;
            }
        }
        // Default value
        if(isSet($data["AJXP_TEMPLATE_NAME"])){
            $templateName = $data["AJXP_TEMPLATE_NAME"];
            if($templateName == "ajxp_film_strip" && AJXP_Utils::userAgentIsMobile()){
                $templateName = "ajxp_shared_folder";
            }
        }
        if(!isSet($templateName)){
            $repoObject = ConfService::getRepositoryById($repository);
            if(!is_object($repoObject)){
                $mess = ConfService::getMessages();
                $error = $mess["share_center.166"];
                $templateName = "ajxp_unique_strip";
            }else{
                $filter = $repoObject->getContentFilter();
                if(!empty($filter) && count($filter->virtualPaths) == 1){
                    $templateName = "ajxp_unique_strip";
                }else{
                    $templateName = "ajxp_shared_folder";
                }
            }
        }
        // UPDATE TEMPLATE
        $html = file_get_contents(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/res/minisite.php");
        AJXP_Controller::applyHook("tpl.filter_html", array(&$html));
        $html = AJXP_XMLWriter::replaceAjxpXmlKeywords($html);
        $html = str_replace("AJXP_MINISITE_LOGO", $minisiteLogo, $html);
        $html = str_replace("AJXP_APPLICATION_TITLE", ConfService::getCoreConf("APPLICATION_TITLE"), $html);
        $html = str_replace("PYDIO_APP_TITLE", ConfService::getCoreConf("APPLICATION_TITLE"), $html);
        if(isSet($repository)){
            $html = str_replace("AJXP_START_REPOSITORY", $repository, $html);
            $html = str_replace("AJXP_REPOSITORY_LABEL", ConfService::getRepositoryById($repository)->getDisplay(), $html);
        }
        $html = str_replace('AJXP_HASH_LOAD_ERROR', isSet($error)?$error:'', $html);
        $html = str_replace("AJXP_TEMPLATE_NAME", $templateName, $html);
        $html = str_replace("AJXP_LINK_HASH", $hash, $html);
        $guiConfigs = AJXP_PluginsService::findPluginById("gui.ajax")->getConfigs();
        $html = str_replace("AJXP_THEME", $guiConfigs["GUI_THEME"] , $html);

        if(isSet($_GET["dl"]) && isSet($_GET["file"])){
            AuthService::$useSession = false;
        }else{
            session_name("AjaXplorer_Shared".$hash);
            session_start();
            AuthService::disconnect();
        }

        if (!empty($data["PRELOG_USER"])) {
            AuthService::logUser($data["PRELOG_USER"], "", true);
            $html = str_replace("AJXP_PRELOGED_USER", "ajxp_preloged_user", $html);
        } else if(isSet($data["PRESET_LOGIN"])) {
            $_SESSION["PENDING_REPOSITORY_ID"] = $repository;
            $_SESSION["PENDING_FOLDER"] = "/";
            $html = str_replace("AJXP_PRELOGED_USER", $data["PRESET_LOGIN"], $html);
        } else{
            $html = str_replace("AJXP_PRELOGED_USER", "ajxp_legacy_minisite", $html);
        }
        if(isSet($hash)){
            $_SESSION["CURRENT_MINISITE"] = $hash;
        }

        if(isSet($_GET["dl"]) && isSet($_GET["file"]) && (!isSet($data["DOWNLOAD_DISABLED"]) || $data["DOWNLOAD_DISABLED"] === false)){
            ConfService::switchRootDir($repository);
            ConfService::loadRepositoryDriver();
            AJXP_PluginsService::deferBuildingRegistry();
            AJXP_PluginsService::getInstance()->initActivePlugins();
            AJXP_PluginsService::flushDeferredRegistryBuilding();
            $errMessage = null;
            try {
                $params = $_GET;
                $ACTION = "download";
                if(isset($_GET["ct"])){
                    $mime = pathinfo($params["file"], PATHINFO_EXTENSION);
                    $editors = AJXP_PluginsService::searchAllManifests("//editor[contains(@mimes,'$mime') and @previewProvider='true']", "node", true, true, false);
                    if (count($editors)) {
                        foreach ($editors as $editor) {
                            $xPath = new DOMXPath($editor->ownerDocument);
                            $callbacks = $xPath->query("//action[@contentTypedProvider]", $editor);
                            if ($callbacks->length) {
                                $ACTION = $callbacks->item(0)->getAttribute("name");
                                if($ACTION == "audio_proxy") {
                                    $params["file"] = "base64encoded:".base64_encode($params["file"]);
                                }
                                break;
                            }
                        }
                    }
                }
                AJXP_Controller::registryReset();
                AJXP_Controller::findActionAndApply($ACTION, $params, null);
            } catch (Exception $e) {
                $errMessage = $e->getMessage();
            }
            if($errMessage == null) return;
            $html = str_replace('AJXP_HASH_LOAD_ERROR', $errMessage, $html);
        }

        if (isSet($_GET["lang"])) {
            $loggedUser = &AuthService::getLoggedUser();
            if ($loggedUser != null) {
                $loggedUser->setPref("lang", $_GET["lang"]);
            } else {
                setcookie("AJXP_lang", $_GET["lang"]);
            }
        }

        if (!empty($data["AJXP_APPLICATION_BASE"])) {
            $tPath = $data["AJXP_APPLICATION_BASE"];
        } else {
            $tPath = (!empty($data["TRAVEL_PATH_TO_ROOT"]) ? $data["TRAVEL_PATH_TO_ROOT"] : "../..");
        }
        // Update Host dynamically if it differ from registered one.
        $registeredHost = parse_url($tPath, PHP_URL_HOST);
        $currentHost = parse_url(AJXP_Utils::detectServerURL("SERVER_URL"), PHP_URL_HOST);
        if($registeredHost != $currentHost){
            $tPath = str_replace($registeredHost, $currentHost, $tPath);
        }
        $html = str_replace("AJXP_PATH_TO_ROOT", rtrim($tPath, "/")."/", $html);
        HTMLWriter::internetExplorerMainDocumentHeader();
        HTMLWriter::charsetHeader();
        echo($html);
    }

    public static function loadShareByHash($hash){
        AJXP_Logger::debug(__CLASS__, __FUNCTION__, "Do something");
        AJXP_PluginsService::getInstance()->initActivePlugins();
        if(isSet($_GET["lang"])){
            ConfService::setLanguage($_GET["lang"]);
        }
        $shareCenter = self::getShareCenter();
        $data = $shareCenter->loadPublicletData($hash);
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
            $ajxpNode = new AJXP_Node("ajxp.".$repoObject->getAccessType()."://".$repoObject->getId().$data["FILE_PATH"]);
        }
        $this->getShareStore()->deleteShare("file", $elementId);
        if(isSet($ajxpNode)){
            try{
                $this->removeShareFromMeta($ajxpNode, $elementId);
            }catch (Exception $e){

            }
            gc_collect_cycles();
        }

    }

    /**
     * @static
     * @param Array $data
     * @return void
     */
    public static function loadPubliclet($data)
    {
        if(isset($data["SECURITY_MODIFIED"]) && $data["SECURITY_MODIFIED"] === true){
            self::loadMinisite($data, "false");
            return;
        }
        // create driver from $data
        $className = $data["DRIVER"]."AccessDriver";
        $u = parse_url($_SERVER["REQUEST_URI"]);
        $shortHash = pathinfo(basename($u["path"]), PATHINFO_FILENAME);

        // Load language messages
        $language = ConfService::getLanguage();
        if (isSet($_GET["lang"])) {
            $language = basename($_GET["lang"]);
        }
        $messages = array();
        if (is_file(dirname(__FILE__)."/res/i18n/".$language.".php")) {
            include(dirname(__FILE__)."/res/i18n/".$language.".php");
        } else {
            include(dirname(__FILE__)."/res/i18n/en.php");
        }
        if(isSet($mess)) $messages = $mess;

        $AJXP_LINK_HAS_PASSWORD = false;
        $AJXP_LINK_BASENAME = SystemTextEncoding::toUTF8(basename($data["FILE_PATH"]));
        AJXP_PluginsService::getInstance()->initActivePlugins();

        $shareCenter = self::getShareCenter();

        ConfService::setLanguage($language);
        $mess = ConfService::getMessages();
        if ($shareCenter->getShareStore()->isShareExpired($shortHash, $data))
        {
            self::loadMinisite(array(), $shortHash, $mess["share_center.165"]);
            return;
        }


        $customs = array("title", "legend", "legend_pass", "background_attributes_1","text_color", "background_color", "textshadow_color");
        $images = array("button", "background_1");
        $shareCenter = AJXP_PluginsService::findPlugin("action", "share");
        $confs = $shareCenter->getConfigs();
        $confs["CUSTOM_SHAREPAGE_BACKGROUND_ATTRIBUTES_1"] = "background-repeat:repeat;background-position:50% 50%;";
        $confs["CUSTOM_SHAREPAGE_BACKGROUND_1"] = "plugins/action.share/res/hi-res/02.jpg";
        $confs["CUSTOM_SHAREPAGE_TEXT_COLOR"] = "#ffffff";
        $confs["CUSTOM_SHAREPAGE_TEXTSHADOW_COLOR"] = "rgba(0,0,0,5)";
        foreach ($customs as $custom) {
            $varName = "CUSTOM_SHAREPAGE_".strtoupper($custom);
            $$varName = $confs[$varName];
        }
        $dlFolder = realpath(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"));
        foreach ($images as $custom) {
            $varName = "CUSTOM_SHAREPAGE_".strtoupper($custom);
            if (!empty($confs[$varName])) {
                if (strpos($confs[$varName], "plugins/") === 0 && is_file(AJXP_INSTALL_PATH."/".$confs[$varName])) {
                    $realFile = AJXP_INSTALL_PATH."/".$confs[$varName];
                    copy($realFile, $dlFolder."/binary-".basename($realFile));
                    $$varName = "binary-".basename($realFile);
                } else {
                    $$varName = "binary-".$confs[$varName];
                    if(is_file($dlFolder."/binary-".$confs[$varName])) continue;
                    $copiedImageName = $dlFolder."/binary-".$confs[$varName];
                    $imgFile = fopen($copiedImageName, "wb");
                    ConfService::getConfStorageImpl()->loadBinary(array(), $confs[$varName], $imgFile);
                    fclose($imgFile);
                }

            }
        }

        HTMLWriter::charsetHeader();
        // Check password
        if (strlen($data["PASSWORD"])) {
            if (!isSet($_POST['password']) || ($_POST['password'] != $data["PASSWORD"])) {
                //AJXP_PluginsService::getInstance()->initActivePlugins();
                $AJXP_LINK_HAS_PASSWORD = true;
                $AJXP_LINK_WRONG_PASSWORD = (isSet($_POST['password']) && ($_POST['password'] != $data["PASSWORD"]));
                include (AJXP_INSTALL_PATH."/plugins/action.share/res/public_links.php");
                $res = ('<div style="position: absolute;z-index: 10000; bottom: 0; right: 0; color: #666;font-size: 13px;text-align: right;padding: 6px; line-height: 20px;text-shadow: 0px 1px 0px white;" class="no_select_bg"><br>Build your own box with Pydio : <a style="color: #000000;" target="_blank" href="https://pyd.io/">https://pyd.io/</a><br/>Community - Free non supported version © C. du Jeu 2008-2014 </div>');
                AJXP_Controller::applyHook("tpl.filter_html", array(&$res));
                echo($res);
                return;
            }
        } else {
            if (!isSet($_GET["dl"])) {
                //AJXP_PluginsService::getInstance()->initActivePlugins();
                include (AJXP_INSTALL_PATH."/plugins/action.share/res/public_links.php");
                $res = '<div style="position: absolute;z-index: 10000; bottom: 0; right: 0; color: #666;font-size: 13px;text-align: right;padding: 6px; line-height: 20px;text-shadow: 0px 1px 0px white;" class="no_select_bg"><br>Build your own box with Pydio : <a style="color: #000000;" target="_blank" href="https://pyd.io/">https://pyd.io/</a><br/>Community - Free non supported version © C. du Jeu 2008-2014 </div>';
                AJXP_Controller::applyHook("tpl.filter_html", array(&$res));
                echo($res);
                return;
            }
        }
        $filePath = AJXP_INSTALL_PATH."/plugins/access.".$data["DRIVER"]."/class.".$className.".php";
        if (!is_file($filePath)) {
            die("Warning, cannot find driver for conf storage! ($className, $filePath)");
        }
        require_once($filePath);
        $driver = new $className($data["PLUGIN_ID"], $data["BASE_DIR"]);
        $driver->loadManifest();

        //$hash = md5(serialize($data));
        $shareCenter->getShareStore()->incrementDownloadCounter($shortHash);

        //AuthService::logUser($data["OWNER_ID"], "", true);
        AuthService::logTemporaryUser($data["OWNER_ID"], $shortHash);
        if (isSet($data["SAFE_USER"]) && isSet($data["SAFE_PASS"])) {
            // FORCE SESSION MODE
            AJXP_Safe::getInstance()->forceSessionCredentialsUsage();
            AJXP_Safe::storeCredentials($data["SAFE_USER"], $data["SAFE_PASS"]);
        }

        $repoObject = $data["REPOSITORY"];
        ConfService::switchRootDir($repoObject->getId());
        ConfService::loadRepositoryDriver();
        AJXP_PluginsService::getInstance()->initActivePlugins();
        try {
            $params = array("file" => SystemTextEncoding::toUTF8($data["FILE_PATH"]));
            if (isSet($data["PLUGINS_DATA"])) {
                $params["PLUGINS_DATA"] = $data["PLUGINS_DATA"];
            }
            if (isset($_GET["ct"]) && $_GET["ct"] == "true") {
                $mime = pathinfo($params["file"], PATHINFO_EXTENSION);
                $editors = AJXP_PluginsService::searchAllManifests("//editor[contains(@mimes,'$mime') and @previewProvider='true']", "node", true, true, false);
                if (count($editors)) {
                    foreach ($editors as $editor) {
                        $xPath = new DOMXPath($editor->ownerDocument);
                        $callbacks = $xPath->query("//action[@contentTypedProvider]", $editor);
                        if ($callbacks->length) {
                            $data["ACTION"] = $callbacks->item(0)->getAttribute("name");
                            if($data["ACTION"] == "audio_proxy") $params["file"] = base64_encode($params["file"]);
                            break;
                        }
                    }
                }
            }
            AJXP_Controller::findActionAndApply($data["ACTION"], $params, null);
            register_shutdown_function(array("AuthService", "clearTemporaryUser"), $shortHash);
        } catch (Exception $e) {
            AuthService::clearTemporaryUser($shortHash);
            die($e->getMessage());
        }
    }

    /**
     * @param String $repoId
     * @param $mixUsersAndGroups
     * @param $currentFileUrl
     * @return array
     */
    public function computeSharedRepositoryAccessRights($repoId, $mixUsersAndGroups, $currentFileUrl = null)
    {
        $roles = AuthService::getRolesForRepository($repoId);
        $sharedEntries = $sharedGroups = $sharedRoles = array();
        $mess = ConfService::getMessages();
        foreach($roles as $rId){
            $role = AuthService::getRole($rId);
            if ($role == null) continue;

            $RIGHT = $role->getAcl($repoId);
            if (empty($RIGHT)) continue;
            $ID = $rId;
            $WATCH = false;
            if(strpos($rId, "AJXP_USR_/") === 0){
                $userId = substr($rId, strlen('AJXP_USR_/'));
                $role = AuthService::getRole($rId);
                $userObject = ConfService::getConfStorageImpl()->createUserObject($userId);
                $LABEL = $role->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
                if(empty($LABEL)) $LABEL = $userId;
                $TYPE = $userObject->hasParent()?"tmp_user":"user";
                if ($this->watcher !== false && $currentFileUrl != null) {
                    $WATCH = $this->watcher->hasWatchOnNode(
                        new AJXP_Node($currentFileUrl),
                        $userId,
                        MetaWatchRegister::$META_WATCH_USERS_NAMESPACE
                    );
                }
                $ID = $userId;
            }else if(strpos($rId, "AJXP_GRP_/") === 0){
                if(empty($loadedGroups)){
                    $displayAll = ConfService::getCoreConf("CROSSUSERS_ALLGROUPS_DISPLAY", "conf");
                    if($displayAll){
                        AuthService::setGroupFiltering(false);
                    }
                    $loadedGroups = AuthService::listChildrenGroups();
                    if($displayAll){
                        AuthService::setGroupFiltering(true);
                    }else{
                        $baseGroup = AuthService::filterBaseGroup("/");
                        foreach($loadedGroups as $loadedG => $loadedLabel){
                            unset($loadedGroups[$loadedG]);
                            $loadedGroups[rtrim($baseGroup, "/")."/".ltrim($loadedG, "/")] = $loadedLabel;
                        }
                    }
                }
                $groupId = substr($rId, strlen('AJXP_GRP_'));
                if(isSet($loadedGroups[$groupId])) {
                    $LABEL = $loadedGroups[$groupId];
                }
                if($groupId == "/"){
                    $LABEL = $mess["447"];
                }
                if(empty($LABEL)) $LABEL = $groupId;
                $TYPE = "group";
            }else if($rId == "ROOT_ROLE"){
                $rId = "AJXP_GRP_/";
                $TYPE = "group";
                $LABEL = $mess["447"];
            }else{
                $role = AuthService::getRole($rId);
                $LABEL = $role->getLabel();
                $TYPE = 'group';
            }

            if(empty($LABEL)) $LABEL = $rId;
            $entry = array(
                "ID"    => $ID,
                "TYPE"  => $TYPE,
                "LABEL" => $LABEL,
                "RIGHT" => $RIGHT
            );
            if($WATCH) $entry["WATCH"] = $WATCH;
            if($TYPE == "group"){
                $sharedGroups[$entry["ID"]] = $entry;
            } else {
                $sharedEntries[$entry["ID"]] = $entry;
            }
        }

        if (!$mixUsersAndGroups) {
            return array("USERS" => $sharedEntries, "GROUPS" => $sharedGroups);
        }else{
            return array_merge(array_values($sharedGroups), array_values($sharedEntries));

        }

        /*
        $users = AuthService::getUsersForRepository($repoId);
        //var_dump($roles);
        $baseGroup = "/";
        $groups = AuthService::listChildrenGroups($baseGroup);
        $mess = ConfService::getMessages();
        $groups[$baseGroup] = $mess["447"];
        $sharedEntries = array();
        if (!$mixUsersAndGroups) {
            $sharedGroups = array();
        }

        foreach ($groups as $gId => $gLabel) {
            $r = AuthService::getRole("AJXP_GRP_".AuthService::filterBaseGroup($gId));
            if ($r != null) {
                $right = $r->getAcl($repoId);
                if (!empty($right)) {
                    $entry = array(
                        "ID"    => "AJXP_GRP_".AuthService::filterBaseGroup($gId),
                        "TYPE"  => "group",
                        "LABEL" => $gLabel,
                        "RIGHT" => $right);
                    if (!$mixUsersAndGroups) {
                        $sharedGroups[$gId] = $entry;
                    } else {
                        $sharedEntries[] = $entry;
                    }
                }
            }
        }

        foreach ($roles as $rId){
            if(strpos($rId, "AJXP_GRP_") === 0 || strpos($rId, "AJXP_USR_") === 0) continue;
            $role = AuthService::getRole($rId);
            if ($role != null) {
                $right = $role->getAcl($repoId);
                if (!empty($right)) {
                    $label = $role->getLabel();
                    if(empty($label)) $label = $rId;
                    $entry = array(
                        "ID"    => $rId,
                        "TYPE"  => "group",
                        "LABEL" => $label,
                        "RIGHT" => $right);
                    if (!$mixUsersAndGroups) {
                        $sharedGroups[$rId] = $entry;
                    } else {
                        $sharedEntries[] = $entry;
                    }
                }
            }
        }

        foreach ($users as $userId => $userObject) {
            if($userObject->getId() == $loggedUser->getId() && !$loggedUser->isAdmin()) {
                continue;
            }
            $ri = $userObject->personalRole->getAcl($repoId);
            $uLabel = $userObject->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
            if(empty($uLabel)) $uLabel = $userId;
            if (!empty($ri)) {
                $entry =  array(
                    "ID"    => $userId,
                    "TYPE"  => $userObject->hasParent()?"tmp_user":"user",
                    "LABEL" => $uLabel,
                    "RIGHT" => $userObject->personalRole->getAcl($repoId)
                );
                if ($this->watcher !== false && $currentFileUrl != null) {
                    $entry["WATCH"] = $this->watcher->hasWatchOnNode(
                        new AJXP_Node($currentFileUrl),
                        $userId,
                        MetaWatchRegister::$META_WATCH_USERS_NAMESPACE
                    );
                }
                if (!$mixUsersAndGroups) {
                    $sharedEntries[$userId] = $entry;
                } else {
                    $sharedEntries[] = $entry;
                }
            }
        }

        if (!$mixUsersAndGroups) {
            return array("USERS" => $sharedEntries, "GROUPS" => $sharedGroups);
        }
        return $sharedEntries;
        */

    }

    /**
     * @param $httpVars
     * @param Repository $repository
     * @param AbstractAccessDriver $accessDriver
     * @return mixed An array containing the hash (0) and the generated url (1)
     */
    public function createSharedMinisite($httpVars, $repository, $accessDriver)
    {
        $uniqueUser = null;
        if(isSet($httpVars["repository_id"]) && isSet($httpVars["guest_user_id"])){
            $existingData = $this->getShareStore()->loadShare($httpVars["hash"]);
            $existingU = "";
            if(isSet($existingData["PRELOG_USER"])) $existingU = $existingData["PRELOG_USER"];
            else if(isSet($existingData["PRESET_LOGIN"])) $existingU = $existingData["PRESET_LOGIN"];
            $uniqueUser = $httpVars["guest_user_id"];
            if(isset($httpVars["guest_user_pass"]) && strlen($httpVars["guest_user_pass"]) && $uniqueUser == $existingU){
                //$userPass = $httpVars["guest_user_pass"];
                // UPDATE GUEST USER PASS HERE
                AuthService::updatePassword($uniqueUser, $httpVars["guest_user_pass"]);
            }else if(isSet($httpVars["guest_user_pass"]) && $httpVars["guest_user_pass"] == ""){

            }else if(isSet($existingData["PRESET_LOGIN"])){
                $httpVars["KEEP_PRESET_LOGIN"] = true;
            }

        }else if (isSet($httpVars["create_guest_user"])) {
            // Create a guest user
            $userId = substr(md5(time()), 0, 12);
            $pref = $this->getFilteredOption("SHARED_USERS_TMP_PREFIX", $this->repository->getId());
            if (!empty($pref)) {
                $userId = $pref.$userId;
            }
            if(!empty($httpVars["guest_user_pass"])){
                $userPass = $httpVars["guest_user_pass"];
            }else{
                $userPass = substr(md5(time()), 13, 24);
            }
            $uniqueUser = $userId;
        }
        if(isSet($uniqueUser)){
            if(isSet($userPass)) {
                $httpVars["user_pass_0"] = $httpVars["shared_pass"] = $userPass;
            }
            $httpVars["user_0"] = $uniqueUser;
            $httpVars["entry_type_0"] = "user";
            $httpVars["right_read_0"] = (isSet($httpVars["simple_right_read"]) ? "true" : "false");
            $httpVars["right_write_0"] = (isSet($httpVars["simple_right_write"]) ? "true" : "false");
            $httpVars["right_watch_0"] = "false";
            $httpVars["disable_download"] = (isSet($httpVars["simple_right_download"]) ? false : true);
            if ($httpVars["right_read_0"] == "false" && !$httpVars["disable_download"]) {
                $httpVars["right_read_0"] = "true";
            }
            if ($httpVars["right_write_0"] == "false" && $httpVars["right_read_0"] == "false") {
                return "share_center.58";
            }
        }

        $httpVars["minisite"] = true;
        $httpVars["selection"] = true;
        if(!isSet($userSelection)){
            $userSelection = new UserSelection($repository, $httpVars);
            $setFilter = false;
            if($userSelection->isUnique()){
                $node = $userSelection->getUniqueNode($this->accessDriver);
                $node->loadNodeInfo();
                if($node->isLeaf()){
                    $setFilter = true;
                    $httpVars["file"] = "/";
                }
            }else{
                $setFilter = true;
            }
            if($setFilter){
                $httpVars["filter_nodes"] = $userSelection->buildNodes($this->accessDriver);
            }
            if(!isSet($httpVars["repo_label"])){
                $first = $userSelection->getUniqueNode($this->accessDriver);
                $httpVars["repo_label"] = SystemTextEncoding::toUTF8($first->getLabel());
            }
        }
        $newRepo = $this->createSharedRepository($httpVars, $repository, $accessDriver, $uniqueUser);

        if(!is_a($newRepo, "Repository")) return $newRepo;

        $newId = $newRepo->getId();
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $this->initPublicFolder($downloadFolder);

        if(isset($existingData)){
            $data = $existingData;
        }else{
            $data = array(
                "REPOSITORY"=>$newId
            );
        }
        if(isSet($data["PRELOG_USER"]))unset($data["PRELOG_USER"]);
        if(isSet($data["PRESET_LOGIN"]))unset($data["PRESET_LOGIN"]);
        if((isSet($httpVars["create_guest_user"]) && isSet($userId)) || (isSet($httpVars["guest_user_id"]))){
            if(!isset($userId)) $userId = $httpVars["guest_user_id"];
            if(empty($httpVars["guest_user_pass"]) && !isSet($httpVars["KEEP_PRESET_LOGIN"])){
                $data["PRELOG_USER"] = $userId;
            }else{
                $data["PRESET_LOGIN"] = $userId;
            }
        }
        $data["DOWNLOAD_DISABLED"] = $httpVars["disable_download"];
        $data["AJXP_APPLICATION_BASE"] = AJXP_Utils::detectServerURL(true);
        if(isSet($httpVars["minisite_layout"])){
            $data["AJXP_TEMPLATE_NAME"] = $httpVars["minisite_layout"];
        }
        if(isSet($httpVars["expiration"]) && intval($httpVars["expiration"]) > 0){
            $data["EXPIRE_TIME"] = time() + intval($httpVars["expiration"]) * 86400;
        }
        if(isSet($httpVars["downloadlimit"]) && intval($httpVars["downloadlimit"]) > 0){
            $data["DOWNLOAD_LIMIT"] = intval($httpVars["downloadlimit"]);
        }
        if(AuthService::usersEnabled()){
            $data["OWNER_ID"] = AuthService::getLoggedUser()->getId();
        }

        if(!isSet($httpVars["repository_id"])){
            try{
                $forceHash = null;
                if(isSet($httpVars["custom_handle"]) && !empty($httpVars["custom_handle"])){
                    // Existing already
                    $value = AJXP_Utils::sanitize($httpVars["custom_handle"], AJXP_SANITIZE_ALPHANUM);
                    $value = strtolower($value);
                    $test = $this->getShareStore()->loadShare($value);
                    $mess = ConfService::getMessages();
                    if(!empty($test)) throw new Exception($mess["share_center.172"]);
                    $forceHash = $value;
                }
                $hash = $this->getShareStore()->storeShare($repository->getId(), $data, "minisite", $forceHash);
            }catch(Exception $e){
                return $e->getMessage();
            }
            $url = $this->buildPublicletLink($hash);
            $this->logInfo("New Share", array(
                "file" => "'".$httpVars['file']."'",
                "url" => $url,
                "expiration" => $data['EXPIRE_TIME'],
                "limit" => $data['DOWNLOAD_LIMIT'],
                "repo_uuid" => $repository->uuid
            ));
            AJXP_Controller::applyHook("node.share.create", array(
                'type' => 'minisite',
                'repository' => &$repository,
                'accessDriver' => &$accessDriver,
                'data' => &$data,
                'url' => $url,
                'new_repository' => &$newRepo
            ));
        }else{
            try{
                $hash = $httpVars["hash"];
                $updateHash = null;
                if(isSet($httpVars["custom_handle"]) && !empty($httpVars["custom_handle"]) && $httpVars["custom_handle"] != $httpVars["hash"]){
                    // Existing already
                    $value = AJXP_Utils::sanitize($httpVars["custom_handle"], AJXP_SANITIZE_ALPHANUM);
                    $value = strtolower($value);
                    $test = $this->getShareStore()->loadShare($value);
                    if(!empty($test)) throw new Exception("Sorry hash already exists");
                    $updateHash = $value;
                }
                $hash = $this->getShareStore()->storeShare($repository->getId(), $data, "minisite", $hash, $updateHash);
            }catch(Exception $e){
                return $e->getMessage();
            }
            $url = $this->buildPublicletLink($hash);
            $this->logInfo("Update Share", array(
                "file" => "'".$httpVars['file']."'",
                "url" => $url,
                "expiration" => $data['EXPIRE_TIME'],
                "limit" => $data['DOWNLOAD_LIMIT'],
                "repo_uuid" => $repository->uuid
            ));
            AJXP_Controller::applyHook("node.share.update", array(
                'type' => 'minisite',
                'repository' => &$repository,
                'accessDriver' => &$accessDriver,
                'data' => &$data,
                'url' => $url,
                'new_repository' => &$newRepo
            ));
        }

        return array($hash, $url);
    }

    /**
     * @param Array $httpVars
     * @param Repository $repository
     * @param AbstractAccessDriver $accessDriver
     * @param null $uniqueUser
     * @throws Exception
     * @return int|Repository
     */
    public function createSharedRepository($httpVars, $repository, $accessDriver, $uniqueUser = null)
    {
        // ERRORS
        // 100 : missing args
        // 101 : repository label already exists
        // 102 : user already exists
        // 103 : current user is not allowed to share
        // SUCCESS
        // 200

        if (!isSet($httpVars["repo_label"]) || $httpVars["repo_label"] == "") {
            return 100;
        }
        $foldersharing = $this->getFilteredOption("ENABLE_FOLDER_SHARING", $this->repository->getId());
        if (isset($foldersharing) && ($foldersharing === false || (is_string($foldersharing) && $foldersharing == "disable"))) {
            return 103;
        }
        $loggedUser = AuthService::getLoggedUser();
        $actRights = $loggedUser->mergedRole->listActionsStatesFor($repository);
        if (isSet($actRights["share"]) && $actRights["share"] === false) {
            return 103;
        }
        $users = array();
        $uRights = array();
        $uPasses = array();
        $groups = array();
        $uWatches = array();

        $index = 0;
        $prefix = $this->getFilteredOption("SHARED_USERS_TMP_PREFIX", $this->repository->getId());
        while (isSet($httpVars["user_".$index])) {
            $eType = $httpVars["entry_type_".$index];
            $uWatch = false;
            $rightString = ($httpVars["right_read_".$index]=="true"?"r":"").($httpVars["right_write_".$index]=="true"?"w":"");
            if($this->watcher !== false) $uWatch = $httpVars["right_watch_".$index] == "true" ? true : false;
            if (empty($rightString)) {
                $index++;
                continue;
            }
            if ($eType == "user") {
                $u = AJXP_Utils::decodeSecureMagic($httpVars["user_".$index], AJXP_SANITIZE_EMAILCHARS);
                if (!AuthService::userExists($u) && !isSet($httpVars["user_pass_".$index])) {
                    $index++;
                    continue;
                } else if (AuthService::userExists($u, "w") && isSet($httpVars["user_pass_".$index])) {
                    throw new Exception("User $u already exists, please choose another name.");
                }
                if(!AuthService::userExists($u, "r") && !empty($prefix)
                && strpos($u, $prefix)!==0 ){
                    $u = $prefix . $u;
                }
                $users[] = $u;
            } else {
                $u = AJXP_Utils::decodeSecureMagic($httpVars["user_".$index]);
                if (strpos($u, "/AJXP_TEAM/") === 0) {
                    $confDriver = ConfService::getConfStorageImpl();
                    if (method_exists($confDriver, "teamIdToUsers")) {
                        $teamUsers = $confDriver->teamIdToUsers(str_replace("/AJXP_TEAM/", "", $u));
                        foreach ($teamUsers as $userId) {
                            $users[] = $userId;
                            $uRights[$userId] = $rightString;
                            if ($this->watcher !== false) {
                                $uWatches[$userId] = $uWatch;
                            }
                        }
                    }
                    $index++;
                    continue;
                } else {
                    $groups[] = $u;
                }
            }
            $uRights[$u] = $rightString;
            $uPasses[$u] = isSet($httpVars["user_pass_".$index])?$httpVars["user_pass_".$index]:"";
            if ($this->watcher !== false) {
                $uWatches[$u] = $uWatch;
            }
            $index ++;
        }

        $label = AJXP_Utils::sanitize(AJXP_Utils::securePath($httpVars["repo_label"]), AJXP_SANITIZE_HTML);
        $description = AJXP_Utils::sanitize(AJXP_Utils::securePath($httpVars["repo_description"]), AJXP_SANITIZE_HTML);
        if (isSet($httpVars["repository_id"])) {
            $editingRepo = ConfService::getRepositoryById($httpVars["repository_id"]);
        }

        // CHECK USER & REPO DOES NOT ALREADY EXISTS
        if ( $this->getFilteredOption("AVOID_SHARED_FOLDER_SAME_LABEL", $this->repository->getId()) == true) {
            $count = 0;
            $similarLabelRepos = ConfService::listRepositoriesWithCriteria(array("display" => $label), $count);
            if($count && !isSet($editingRepo)){
                return 101;
            }
            if($count && isSet($editingRepo)){
                foreach($similarLabelRepos as $slr){
                    if($slr->getUniqueId() != $editingRepo->getUniqueId()){
                        return 101;
                    }
                }
            }
            /*
            $repos = ConfService::getRepositoriesList();
            foreach ($repos as $obj) {
                if ($obj->getDisplay() == $label && (!isSet($editingRepo) || $editingRepo != $obj)) {
                }
            }
            */
        }

        $confDriver = ConfService::getConfStorageImpl();
        foreach ($users as $userName) {
            if (AuthService::userExists($userName)) {
                // check that it's a child user
                $userObject = $confDriver->createUserObject($userName);
                if ( ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING", "conf") != true && ( !$userObject->hasParent() || $userObject->getParent() != $loggedUser->id ) ) {
                    return 102;
                }
            } else {
                if ( ($httpVars["create_guest_user"] != "true" && !ConfService::getCoreConf("USER_CREATE_USERS", "conf")) || AuthService::isReservedUserId($userName)) {
                    return 102;
                }
                if (!isSet($httpVars["shared_pass"]) || $httpVars["shared_pass"] == "") {
                    return 100;
                }
            }
        }

        // CREATE SHARED OPTIONS
        $options = $accessDriver->makeSharedRepositoryOptions($httpVars, $repository);
        $customData = array();
        foreach ($httpVars as $key => $value) {
            if (substr($key, 0, strlen("PLUGINS_DATA_")) == "PLUGINS_DATA_") {
                $customData[substr($key, strlen("PLUGINS_DATA_"))] = $value;
            }
        }
        if (count($customData)) {
            $options["PLUGINS_DATA"] = $customData;
        }
        if (isSet($editingRepo)) {
            $newRepo = $editingRepo;
            $replace = false;
            if ($editingRepo->getDisplay() != $label) {
                $newRepo->setDisplay($label);
                $replace= true;
            }
            if($editingRepo->getDescription() != $description){
                $newRepo->setDescription($description);
                $replace = true;
            }
            if($replace) ConfService::replaceRepository($httpVars["repository_id"], $newRepo);
        } else {
            if ($repository->getOption("META_SOURCES")) {
                $options["META_SOURCES"] = $repository->getOption("META_SOURCES");
                foreach ($options["META_SOURCES"] as $index => &$data) {
                    if (isSet($data["USE_SESSION_CREDENTIALS"]) && $data["USE_SESSION_CREDENTIALS"] === true) {
                        $options["META_SOURCES"][$index]["ENCODED_CREDENTIALS"] = AJXP_Safe::getEncodedCredentialString();
                    }
                    if($index == "meta.syncable" && $data["REPO_SYNCABLE"] === true ){
                        $data["REQUIRES_INDEXATION"] = true;
                    }
                }
            }
            $newRepo = $repository->createSharedChild(
                $label,
                $options,
                $repository->id,
                $loggedUser->id,
                null
            );
            $gPath = $loggedUser->getGroupPath();
            if (!empty($gPath) && !ConfService::getCoreConf("CROSSUSERS_ALLGROUPS", "conf")) {
                $newRepo->setGroupPath($gPath);
            }
            $newRepo->setDescription($description);
			$newRepo->options["PATH"] = SystemTextEncoding::fromStorageEncoding($newRepo->options["PATH"]);
            if(isSet($httpVars["filter_nodes"])){
                $newRepo->setContentFilter(new ContentFilter($httpVars["filter_nodes"]));
            }
            ConfService::addRepository($newRepo);
            if(!isSet($httpVars["minisite"])){
                $this->getShareStore()->storeShare($repository->getId(), array(
                    "REPOSITORY" => $newRepo->getUniqueId(),
                    "OWNER_ID" => $loggedUser->getId()), "repository");
            }
        }

        $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);

        if (isSet($editingRepo)) {

            $currentRights = $this->computeSharedRepositoryAccessRights($httpVars["repository_id"], false, $this->urlBase.$file);
            $originalUsers = array_keys($currentRights["USERS"]);
            $removeUsers = array_diff($originalUsers, $users);
            if (count($removeUsers)) {
                foreach ($removeUsers as $user) {
                    if (AuthService::userExists($user)) {
                        $userObject = $confDriver->createUserObject($user);
                        $userObject->personalRole->setAcl($newRepo->getUniqueId(), "");
                        $userObject->save("superuser");
                    }
                    $this->watcher->removeWatchFromFolder(
                        new AJXP_Node($this->urlBase.$file),
                        $user,
                        true
                    );
                }
            }
            $originalGroups = array_keys($currentRights["GROUPS"]);
            $removeGroups = array_diff($originalGroups, $groups);
            if (count($removeGroups)) {
                foreach ($removeGroups as $groupId) {
                    $role = AuthService::getRole($groupId);
                    if ($role !== false) {
                        $role->setAcl($newRepo->getUniqueId(), "");
                        AuthService::updateRole($role);
                    }
                }
            }
        }

        foreach ($users as $userName) {
            if (AuthService::userExists($userName, "r")) {
                // check that it's a child user
                $userObject = $confDriver->createUserObject($userName);
            } else {
                if (ConfService::getAuthDriverImpl()->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
                    $pass = $uPasses[$userName];
                } else {
                    $pass = md5($uPasses[$userName]);
                }
                if(!isSet($httpVars["minisite"])){
                    // This is an explicit user creation - check possible limits
                    AJXP_Controller::applyHook("user.before_create", array($userName));
                    $limit = $loggedUser->personalRole->filterParameterValue("core.conf", "USER_SHARED_USERS_LIMIT", AJXP_REPO_SCOPE_ALL, "");
                    if (!empty($limit) && intval($limit) > 0) {
                        $count = count(ConfService::getConfStorageImpl()->getUserChildren($loggedUser->getId()));
                        if ($count >= $limit) {
                            $mess = ConfService::getMessages();
                            throw new Exception($mess['483']);
                        }
                    }
                }
                AuthService::createUser($userName, $pass);
                $userObject = $confDriver->createUserObject($userName);
                $userObject->personalRole->clearAcls();
                $userObject->setParent($loggedUser->id);
                $userObject->setGroupPath($loggedUser->getGroupPath());
                $userObject->setProfile("shared");
                if(isSet($httpVars["minisite"])){
                    $mess = ConfService::getMessages();
                    $userObject->setHidden(true);
                    $userObject->personalRole->setParameterValue("core.conf", "USER_DISPLAY_NAME", "[".$mess["share_center.109"]."] ". AJXP_Utils::sanitize($newRepo->getDisplay(), AJXP_SANITIZE_EMAILCHARS));
                }
                AJXP_Controller::applyHook("user.after_create", array($userObject));
            }
            // CREATE USER WITH NEW REPO RIGHTS
            $userObject->personalRole->setAcl($newRepo->getUniqueId(), $uRights[$userName]);
            if (isSet($httpVars["minisite"])) {
                if(isset($editingRepo)){
                    try{
                        AuthService::deleteRole("AJXP_SHARED-".$newRepo->getUniqueId());
                    }catch (Exception $e){}
                }
                $newRole = new AJXP_Role("AJXP_SHARED-".$newRepo->getUniqueId());
                $r = AuthService::getRole("MINISITE");
                if (is_a($r, "AJXP_Role")) {
                    if ($httpVars["disable_download"]) {
                        $f = AuthService::getRole("MINISITE_NODOWNLOAD");
                        if (is_a($f, "AJXP_Role")) {
                            $r = $f->override($r);
                        }
                    }
                    $allData = $r->getDataArray();
                    $newData = $newRole->getDataArray();
                    if(isSet($allData["ACTIONS"][AJXP_REPO_SCOPE_SHARED])) $newData["ACTIONS"][$newRepo->getUniqueId()] = $allData["ACTIONS"][AJXP_REPO_SCOPE_SHARED];
                    if(isSet($allData["PARAMETERS"][AJXP_REPO_SCOPE_SHARED])) $newData["PARAMETERS"][$newRepo->getUniqueId()] = $allData["PARAMETERS"][AJXP_REPO_SCOPE_SHARED];
                    $newRole->bunchUpdate($newData);
                    AuthService::updateRole($newRole);
                    $userObject->addRole($newRole);
                }
            }
            $userObject->save("superuser");
            if ($this->watcher !== false) {
                // Register a watch on the current folder for shared user
                if ($uWatches[$userName] == "true") {
                    $this->watcher->setWatchOnFolder(
                        new AJXP_Node($this->urlBase.$file),
                        $userName,
                        MetaWatchRegister::$META_WATCH_USERS_CHANGE,
                        array(AuthService::getLoggedUser()->getId())
                    );
                } else {
                    $this->watcher->removeWatchFromFolder(
                        new AJXP_Node($this->urlBase.$file),
                        $userName,
                        true
                    );
                }
            }
        }

        if ($this->watcher !== false) {
            // Register a watch on the new repository root for current user
            if ($httpVars["self_watch_folder"] == "true") {
                $this->watcher->setWatchOnFolder(
                    new AJXP_Node($this->baseProtocol."://".$newRepo->getUniqueId()."/"),
                    AuthService::getLoggedUser()->getId(),
                    MetaWatchRegister::$META_WATCH_BOTH);
            } else {
                $this->watcher->removeWatchFromFolder(
                    new AJXP_Node($this->baseProtocol."://".$newRepo->getUniqueId()."/"),
                    AuthService::getLoggedUser()->getId());
            }
        }

        foreach ($groups as $group) {
            $r = $uRights[$group];
            if($group == "AJXP_GRP_/") {
                $group = "ROOT_ROLE";
            }
            $grRole = AuthService::getRole($group, true);
            $grRole->setAcl($newRepo->getUniqueId(), $r);
            AuthService::updateRole($grRole);
        }

        if (array_key_exists("minisite", $httpVars) && $httpVars["minisite"] != true) {
            AJXP_Controller::applyHook( (isSet($editingRepo) ? "node.share.update" : "node.share.create"), array(
                'type' => 'repository',
                'repository' => &$repository,
                'accessDriver' => &$accessDriver,
                'new_repository' => &$newRepo
            ));
        }

        return $newRepo;
    }


    /**
     * @param String $type
     * @param String $element
     * @param AbstractAjxpUser $loggedUser
     * @throws Exception
     */
    public function deleteSharedElement($type, $element, $loggedUser)
    {
        $this->getShareStore()->deleteShare($type, $element);
    }

    public function loadPublicletData($id)
    {
        return $this->getShareStore()->loadShare($id);
    }

    public function findSharesForRepo($repositoryId){
        return $this->getShareStore()->findSharesForRepo($repositoryId);
    }

    public function listShares($currentUser = true, $parentRepositoryId="", $cursor = null){
        if(AuthService::usersEnabled()){
            $crtUser = ($currentUser?AuthService::getLoggedUser()->getId():'');
        }else{
            $crtUser = ($currentUser?'shared':'');
        }
        return $this->getShareStore()->listShares($crtUser, $parentRepositoryId, $cursor);
    }

    /**
     * @param $rootPath
     * @param bool $currentUser
     * @param string $parentRepositoryId
     * @param null $cursor
     * @param bool $xmlPrint
     * @return AJXP_Node[]
     */
    public function listSharesAsNodes($rootPath, $currentUser = true, $parentRepositoryId = "", $cursor = null, $xmlPrint = false){

        $shares =  $this->listShares($currentUser, $parentRepositoryId, $cursor);
        $nodes = array();

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
                $meta["share_data"] = ($shareType == "repository" ? 'Shared as workspace: '.$repoObject->getDisplay() : $this->buildPublicletLink($hash));
                $meta["shared_element_hash"] = $hash;
                $meta["owner"] = $repoObject->getOwner();
                if($shareType != "repository") {
                    $meta["copy_url"]  = $this->buildPublicletLink($hash);
                }
                $meta["shared_element_parent_repository"] = $repoObject->getParentId();
                $parent = ConfService::getRepositoryById($repoObject->getParentId());
                if(!empty($parent)){
                    $meta["shared_element_parent_repository_label"] = $parent->getDisplay();
                }else{
                    $meta["shared_element_parent_repository_label"] = $repoObject->getParentId();
                }
                if($shareType != "repository"){
                    if($repoObject->hasContentFilter()){
                        $meta["ajxp_shared_minisite"] = "file";
                        $meta["icon"] = "mime_empty.png";
                    }else{
                        $meta["ajxp_shared_minisite"] = "public";
                        $meta["icon"] = "folder.png";
                    }
                    $meta["icon"] = $repoObject->hasContentFilter() ? "mime_empty.png" : "folder.png";
                }

            }else if(is_a($shareData["REPOSITORY"], "Repository") && !empty($shareData["FILE_PATH"])){

                $meta["owner"] = $shareData["OWNER_ID"];
                $meta["share_type_readable"] = "Publiclet (legacy)";
                $meta["text"] = basename($shareData["FILE_PATH"]);
                $meta["icon"] = "mime_empty.png";
                $meta["share_data"] = $meta["copy_url"] = $this->buildPublicletLink($hash);
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

    public function migrateOldSharedWorkspaces($parentRepositoryId){

        // Load only children of a given repository
        $repos = ConfService::listRepositoriesWithCriteria(
            array(
                "parent_uuid" => $parentRepositoryId
            ), $count
        );
        $count = 0;
        foreach($repos as $repoId => $repoObject){

            $shares = $this->getShareStore()->findSharesForRepo($repoId);
            if(! count($shares)){
                $count ++;
                $this->getShareStore()->storeShare($parentRepositoryId, array(
                    "REPOSITORY"    => $repoObject->getUniqueId(),
                    "OWNER_ID"      => $repoObject->getOwner()), "repository");
            }

        }
        return $count;

    }

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

        $publicLets = $this->getShareStore()->listShares($currentUser? $userId: '');
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
     * Hooked to user.after_delete event, make sure to clear orphan shares
     * @param String $userId
     */
    public function cleanUserShares($userId){
        $shares = $this->getShareStore()->listShares($userId);
        foreach($shares as $hash => $data){
            $this->getShareStore()->deleteShare($data['SHARE_TYPE'], $hash);
        }
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
     * @param AJXP_Node $node
     * @param $shareType
     * @param $shareId
     */
    public function addShareInMeta($node, $shareType, $shareId, $originalShareId=null){
        $this->getSharesFromMeta($node, $shares, true);
        if(empty($shares)){
            $shares = array();
        }
        if(!empty($shares) && $originalShareId != null && isSet($shares[$originalShareId])){
            unset($shares[$originalShareId]);
        }
        $shares[$shareId] = array("type" => $shareType);
        $node->setMetadata("ajxp_shared", array("shares" => $shares), true, AJXP_METADATA_SCOPE_REPOSITORY, true);
    }

    /**
     * @param AJXP_Node $node
     * @param $shareId
     * @param $type
     */
    public function getShareFromMeta($node, $shareId, &$type){
        $this->getSharesFromMeta($node, $shares, true);
        if(!empty($shares) && isSet($shares[$shareId])){
            $type = $shares[$shareId];
        }
    }

    /**
     * @param AJXP_Node $node
     * @param $shareId
     */
    public function removeShareFromMeta($node, $shareId){
        $this->getSharesFromMeta($node, $shares);
        if(!empty($shares) && isSet($shares[$shareId])){
            unset($shares[$shareId]);
            if(count($shares)){
                $node->setMetadata("ajxp_shared", array("shares" => $shares), true, AJXP_METADATA_SCOPE_REPOSITORY, true);
            }else{
                $node->removeMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
            }
        }

    }

    /**
     * @param AJXP_Node $node
     * @param Array $shares
     * @param bool $updateIfNotExists
     */
    public function getSharesFromMeta($node, &$shares, $updateIfNotExists = false){

        $meta = $node->retrieveMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);

        // NEW FORMAT
        if(isSet($meta["shares"])){
            $shares = array();
            $update = false;
            foreach($meta["shares"] as $hashOrRepoId => $shareData){
                if(!$updateIfNotExists || $this->getShareStore()->shareExists($shareData["type"],$hashOrRepoId)){
                    $shares[$hashOrRepoId] = $shareData;
                }else{
                    $update = true;
                }
            }
            if($update){
                if (count($shares) > 0) {
                    $meta["shares"] = $shares;
                    $node->setMetadata("ajxp_shared", $meta, true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                } else {
                    $node->removeMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                }

            }
            return;
        }

        // OLD FORMAT
        if(isSet($meta["minisite"])){
            $type = "minisite";
        }else{
            $type = "detect";
        }
        $els = array();
        if(is_string($meta["element"])) $els[] = $meta["element"];
        else if (is_array($meta["element"])) $els = $meta["element"];
        if($updateIfNotExists){
            $update = false;
            $shares = array();
            foreach($els as $hashOrRepoId => $additionalData){
                if(is_string($additionalData)) {
                    $hashOrRepoId = $additionalData;
                    $additionalData = array();
                }
                if($type == "detect"){
                    if(ConfService::getRepositoryById($hashOrRepoId) != null) $type = "repository";
                    else $type = "file";
                }
                if($this->getShareStore()->shareExists($type,$hashOrRepoId)){
                    $shares[$hashOrRepoId] = array_merge($additionalData, array("type" => $type));
                }else{
                    $update = true;
                }
            }
            if($update){
                if (count($shares) > 0) {
                    unset($meta["element"]);
                    if(isSet($meta["minisite"])) unset($meta["minisite"]);
                    $meta["shares"] = $shares;
                    $node->setMetadata("ajxp_shared", $meta, true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                } else {
                    $node->removeMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                }

            }
        }else{
            $shares = $els;
        }

    }

    /**
     * @param String $shareId
     * @param Array $shareData
     * @param AJXP_Node $node
     * @throws Exception
     * @return array|bool
     */
    public function shareToJson($shareId, $shareData, $node = null){

        $messages = ConfService::getMessages();
        $jsonData = array();
        $elementWatch = false;
        if($shareData["type"] == "file"){

            $pData = $this->getShareStore()->loadShare($shareId);
            if (!count($pData)) {
                return false;
            }
            foreach($this->getShareStore()->modifiableShareKeys as $key){
                if(isSet($pData[$key])) $shareData[$key] = $pData[$key];
            }
            if ($pData["OWNER_ID"] != AuthService::getLoggedUser()->getId() && !AuthService::getLoggedUser()->isAdmin()) {
                throw new Exception($messages["share_center.48"]);
            }
            if (isSet($shareData["short_form_url"])) {
                $link = $shareData["short_form_url"];
            } else {
                $link = $this->buildPublicletLink($shareId);
            }
            if ($this->watcher != false && $node != null) {
                $result = array();
                $elementWatch = $this->watcher->hasWatchOnNode(
                    $node,
                    AuthService::getLoggedUser()->getId(),
                    MetaWatchRegister::$META_WATCH_USERS_NAMESPACE,
                    $result
                );
                if ($elementWatch && !in_array($shareId, $result)) {
                    $elementWatch = false;
                }
            }
            $jsonData = array_merge(array(
                "element_id"       => $shareId,
                "publiclet_link"   => $link,
                "download_counter" => $this->getShareStore()->getCurrentDownloadCounter($shareId),
                "download_limit"   => $pData["DOWNLOAD_LIMIT"],
                "expire_time"      => ($pData["EXPIRE_TIME"]!=0?date($messages["date_format"], $pData["EXPIRE_TIME"]):0),
                "has_password"     => (!empty($pData["PASSWORD"])),
                "element_watch"    => $elementWatch,
                "is_expired"       => $this->shareStore->isShareExpired($shareId, $pData)
            ), $shareData);


        }else if($shareData["type"] == "minisite" || $shareData["type"] == "repository"){

            $repoId = $shareId;
            if(strpos($repoId, "repo-") === 0){
                // Legacy
                $repoId = str_replace("repo-", "", $repoId);
                $shareData["type"] = "repository";
            }
            $minisite = ($shareData["type"] == "minisite");
            $minisiteIsPublic = false;
            $dlDisabled = false;
            $minisiteLink = '';
            if ($minisite) {
                $minisiteData = $this->getShareStore()->loadShare($shareId);
                $repoId = $minisiteData["REPOSITORY"];
                $minisiteIsPublic = isSet($minisiteData["PRELOG_USER"]);
                $dlDisabled = isSet($minisiteData["DOWNLOAD_DISABLED"]) && $minisiteData["DOWNLOAD_DISABLED"] === true;
                if (isSet($shareData["short_form_url"])) {
                    $minisiteLink = $shareData["short_form_url"];
                } else {
                    $minisiteLink = $this->buildPublicletLink($shareId);
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
                    $this->removeShareFromMeta($node, $shareId);
                }
                return $notExistsData;
            } else if (!AuthService::getLoggedUser()->isAdmin() && $repo->getOwner() != AuthService::getLoggedUser()->getId()) {
                return $notExistsData;
            }
            if ($this->watcher != false && $node != null) {
                $elementWatch = $this->watcher->hasWatchOnNode(
                    new AJXP_Node($this->baseProtocol."://".$repoId."/"),
                    AuthService::getLoggedUser()->getId(),
                    MetaWatchRegister::$META_WATCH_NAMESPACE
                );
            }
            if($node != null){
                $sharedEntries = $this->computeSharedRepositoryAccessRights($repoId, true, $node->getUrl());
            }else{
                $sharedEntries = $this->computeSharedRepositoryAccessRights($repoId, true, null);
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
                "repository_url"=> AJXP_Utils::detectServerURL(true)."?goto=". $repo->getSlug() ."/",
                "content_filter"=> $cFilter
            );
            if (isSet($minisiteData)) {
                if(!empty($minisiteData["DOWNLOAD_LIMIT"]) && !$dlDisabled){
                    $jsonData["download_counter"] = $this->getShareStore()->getCurrentDownloadCounter($shareId);
                    $jsonData["download_limit"] = $minisiteData["DOWNLOAD_LIMIT"];
                }
                if(!empty($minisiteData["EXPIRE_TIME"])){
                    $delta = $minisiteData["EXPIRE_TIME"] - time();
                    $days = round($delta / (60*60*24));
                    $jsonData["expire_time"] = date($messages["date_format"], $minisiteData["EXPIRE_TIME"]);
                    $jsonData["expire_after"] = $days;
                }else{
                    $jsonData["expire_after"] = 0;
                }
                $jsonData["is_expired"] = $this->shareStore->isShareExpired($shareId, $minisiteData);
                if(isSet($minisiteData["AJXP_TEMPLATE_NAME"])){
                    $jsonData["minisite_layout"] = $minisiteData["AJXP_TEMPLATE_NAME"];
                }
                if(!$minisiteIsPublic){
                    $jsonData["has_password"] = true;
                }
                $jsonData["minisite"] = array(
                    "public"            => $minisiteIsPublic?"true":"false",
                    "public_link"       => $minisiteLink,
                    "disable_download"  => $dlDisabled,
                    "hash"              => $shareId,
                    "hash_is_shorten"   => isSet($shareData["short_form_url"])
                );
                foreach($this->getShareStore()->modifiableShareKeys as $key){
                    if(isSet($minisiteData[$key])) $jsonData[$key] = $minisiteData[$key];
                }

            }

        }


        return $jsonData;

    }

}
