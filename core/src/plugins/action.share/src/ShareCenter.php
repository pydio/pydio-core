<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Share;

use DOMNode;
use DOMXPath;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Filter\ContentFilter;
use Pydio\Access\Core\Model\NodesDiff;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Access\Meta\Watch\WatchRegister;
use Pydio\Core\Controller\CliRunner;
use Pydio\Core\Http\Base;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\PathUtils;
use Pydio\Core\Utils\Vars\XMLFilter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\OCS\Model\TargettedLink;
use Pydio\Share\Http\MinisiteServer;
use Pydio\Share\Legacy\LegacyPubliclet;
use Pydio\Share\Model\CompositeShare;
use Pydio\Share\Model\ShareLink;
use Pydio\Share\Store\ShareRightsManager;
use Pydio\Share\Store\ShareStore;
use Pydio\Share\View\PublicAccessManager;
use Pydio\OCS as OCS;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die( 'Access not allowed');
require_once(dirname(__FILE__)."/../vendor/autoload.php");

/**
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class ShareCenter extends Plugin
{
    /**
     * @var AbstractAccessDriver
     */
    private $accessDriver;
    /**
     * @var RepositoryInterface
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
     * @var WatchRegister
     */
    private $watcher = false;

    /**
     * @var ShareRightsManager
     */
    private $rightsManager;

    /**
     * @var ContextInterface
     */
    private $currentContext;

    /**************************/
    /* PLUGIN LIFECYCLE METHODS
    /**************************/
    /**
     * Plugin initializer
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        if(!$ctx->hasRepository()){
            return;
        }
        $this->repository = $ctx->getRepository();
        if (!($this->repository->getDriverInstance($ctx) instanceof \Pydio\Access\Core\IAjxpWrapperProvider)) {
            return;
        }
        $this->accessDriver = $this->repository->getDriverInstance($ctx);
        $this->urlBase = $ctx->getUrlBase();
        if (array_key_exists("meta.watch", PluginsService::getInstance($ctx)->getActivePlugins())) {
            $this->watcher = PluginsService::getInstance($ctx)->getPluginById("meta.watch");
        }
        $this->currentContext = $ctx;
    }

    /**
     * Extend parent
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if(!$ctx->getRepository()){
            return;
        }

        $disableSharing = false;
        $xpathesToRemove = array();
        $selectionContext = false;

        if( strpos($ctx->getRepository()->getAccessType(), "ajxp_") === 0){

            $disableSharing = true;

        }else if (UsersService::usersEnabled()) {

            $loggedUser = $ctx->getUser();
            if ($loggedUser != null && UsersService::isReservedUserId($loggedUser->getId())) {
                $disableSharing = true;
            }

        } else {

            $disableSharing = true;

        }
        if ($disableSharing) {
            // All share- actions
            $xpathesToRemove[] = 'action[@name="share-edit-shared"]';
            $xpathesToRemove[] = 'action[@name="share_react"]';

        }else{
            $folderSharingAllowed = $this->getAuthorization($ctx, "folder", "any");
            $fileSharingAllowed   = $this->getAuthorization($ctx, "file", "any");
            if($folderSharingAllowed && !$fileSharingAllowed){
                $selectionContext = "dir";
            }else if(!$folderSharingAllowed && $fileSharingAllowed){
                $selectionContext = "file";
            }else if(!$fileSharingAllowed && !$folderSharingAllowed){
                // All share- actions
                $xpathesToRemove[] = 'action[@name="share-edit-shared"]';
                $xpathesToRemove[] = 'action[@name="share_react"]';
            }
        }

        foreach($xpathesToRemove as $xpath){
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $nodeList = $actionXpath->query($xpath, $contribNode);
            foreach($nodeList as $shareActionNode){
                $contribNode->removeChild($shareActionNode);
            }
        }
        if(isSet($selectionContext)){
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $nodeList = $actionXpath->query('action[@name="share_react"]/gui/selectionContext', $contribNode);
            if(!$nodeList->length) return;
            /** @var \DOMElement $selectionContextNode */
            $selectionContextNode =  $nodeList->item(0);
            if($selectionContext == "dir") $selectionContextNode->setAttribute("file", "false");
            else if($selectionContext == "file") $selectionContextNode->setAttribute("dir", "false");
        }

    }


    /**************************/
    /* PUBLIC LINKS ROUTER
    /**************************/
    /**
     * @param $serverBase
     * @param $route
     * @param $params
     */
    public static function publicRoute($serverBase, $route, $params){

        if(isSet($_GET['minisite_session'])){

            $base = new Base();
            $h = $_GET['minisite_session'];
            SessionService::setSessionName("AjaXplorer_Shared".str_replace(".","_",$h));
            $base->handleRoute($serverBase, "/", ["minisite" => true]);

        }else{

            $hash = isSet($params["hash"])? $params["hash"] : "";
            if(strpos($hash, "--") !== false){
                list($hash, $lang) = explode("--", $hash);
            }
            if(strpos($hash, ".php") !== false){
                $hash = array_shift(explode(".php", $hash));
            }

            ConfService::init();
            ConfService::start();
            if(isSet($lang)){
                $_GET["lang"] = $lang;
            }
            if(isSet($params["optional"])){
                $_GET["dl"] = true;
                $_GET["file"] = "/".$params["optional"];
            }
            ConfService::getAuthDriverImpl();

            $minisiteServer = new MinisiteServer($serverBase, $hash, isSet($params["optional"]));
            $minisiteServer->registerCatchAll();
            $minisiteServer->listen();

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
        $base = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/");
        $id = pathinfo($_SERVER["SCRIPT_NAME"], PATHINFO_FILENAME);
        self::publicRoute($base, "/proxy", ["hash" => $id]);
    }

    /**
     * Loader for legacy publiclets
     * @static
     * @param array $data
     * @return void
     */
    public static function loadPubliclet($data)
    {
        $shareCenter = self::getShareCenter(Context::emptyContext());
        $options = $shareCenter->getConfigs();
        $shareStore = $shareCenter->getShareStore();
        LegacyPubliclet::render($data, $options, $shareStore);
    }



    /**************************/
    /* UTILS & ACCESSORS
    /**************************/
    /**
     * Compute right to create shares based on plugin options
     * @param ContextInterface $ctx
     * @param string $nodeType "file"|"folder"
     * @param string $shareType "any"|"minisite"|"workspace"
     * @return bool
     */
    protected function getAuthorization(ContextInterface $ctx, $nodeType, $shareType = "any"){

        $all             = $this->getContextualOption($ctx, "DISABLE_ALL_SHARING");
        if($all){
            return false;
        }
        if($ctx->getRepository()->hasParent()){
            $p = $ctx->getRepository()->getParentRepository();
            if(!empty($p) && !$p->isTemplate()){
                $pContext = new Context($ctx->getRepository()->getOwner(), $p->getId());
                $all = $this->getContextualOption($pContext, "DISABLE_RESHARING");
                if($all){
                    return false;
                }
            }
        }
        
        $filesMini       = $this->getContextualOption($ctx, "ENABLE_FILE_PUBLIC_LINK");
        $filesInternal   = $this->getContextualOption($ctx, "ENABLE_FILE_INTERNAL_SHARING");
        $foldersMini     = $this->getContextualOption($ctx, "ENABLE_FOLDER_PUBLIC_LINK");
        $foldersInternal = $this->getContextualOption($ctx, "ENABLE_FOLDER_INTERNAL_SHARING");

        if($shareType == "any"){
            return ($nodeType == "file" ? $filesInternal || $filesMini : $foldersInternal || $foldersMini);
        }else if($shareType == "minisite"){
            return ($nodeType == "file" ? $filesMini : $foldersMini);
        }else if($shareType == "workspace"){
            return ($nodeType == "file" ? $filesInternal : $foldersInternal);
        }
        return false;

    }

    /**
     * @return ShareCenter
     */
    public static function getShareCenter(ContextInterface $ctx = null){
        if($ctx === null){
            $ctx = Context::emptyContext();
        }
        /** @var ShareCenter $shareCenter */
        $shareCenter = PluginsService::getInstance($ctx)->getPluginById("action.share");
        if(empty($shareCenter->currentContext)){
            $shareCenter->currentContext = $ctx;
        }
        return $shareCenter;
    }

    /**
     * @return bool
     */
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
     * @param ContextInterface $ctx
     * @return ShareStore
     */
    public function getShareStore(ContextInterface $ctx = null){

        if(!isSet($this->shareStore) || $ctx !== null){
            $hMin = 32;
            $context = $ctx !== null ? $ctx : $this->currentContext;
            if(!empty($context)){
                $hMin = $this->getContextualOption($context, "HASH_MIN_LENGTH");
            }
            $this->shareStore = new ShareStore(
                $context,
                ConfService::getGlobalConf("PUBLIC_DOWNLOAD_FOLDER"),
                $hMin
            );
        }
        return $this->shareStore;
    }

    /**
     * @return View\PublicAccessManager
     */
    public function getPublicAccessManager(){

        if(!isSet($this->publicAccessManager)){
            $this->publicAccessManager = new PublicAccessManager([]);
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
            if(isSet($this->currentContext)){
                $options = array(
                    "SHARED_USERS_TMP_PREFIX" => $this->getContextualOption($this->currentContext, "SHARED_USERS_TMP_PREFIX"),
                    "SHARE_FORCE_PASSWORD" => $this->getContextualOption($this->currentContext, "SHARE_FORCE_PASSWORD")
                );
            }else{
                $options = array(
                    "SHARED_USERS_TMP_PREFIX" => "ext_",
                    "SHARE_FORCE_PASSWORD" => false
                );
            }
            $this->rightsManager = new ShareRightsManager(
                $this->currentContext,
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

        $maxvalue = abs(intval($this->getContextualOption($this->currentContext, $optionName)));
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
        if ( $this->getContextualOption($this->currentContext, "AVOID_SHARED_FOLDER_SAME_LABEL") == true) {
            $count = 0;
            $similarLabelRepos = RepositoryService::listRepositoriesWithCriteria(array("display" => $label), $count);
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

    /**
     * @param $childRepoId
     * @param $userId
     * @param bool $toggle
     * @param null $parentUserId
     */
    protected function toggleWatchOnSharedRepository($childRepoId, $userId, $toggle = true, $parentUserId = null){
        if ($this->watcher === false || !$this->currentContext->hasUser()) {
            return;
        }
        $rootNode = new AJXP_Node("pydio://".$userId."@".$childRepoId."/");
        // Register a watch on the current folder for shared user
        if($parentUserId !== null){
            if ($toggle) {
                $this->watcher->setWatchOnFolder(
                    $rootNode,
                    $userId,
                    WatchRegister::$META_WATCH_USERS_CHANGE,
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
                    WatchRegister::$META_WATCH_BOTH);

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
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @throws \Exception
     */
    public function preProcessDownload(ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface){

        if(!ApplicationState::hasMinisiteHash()) {
            return;
        }

        $hash = ApplicationState::getMinisiteHash();
        $share = $this->getShareStore()->loadShareObject($hash);
        if(!empty($share)){
            if($share->isExpired()){
                throw new \Exception('Link is expired');
            }
            if($share->hasDownloadLimit() || $share->hasTargetUsers()){
                $share->incrementDownloadCount($requestInterface->getAttribute('ctx'));
                $share->save();
            }
        }

    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function migrateLegacyShares(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        LegacyPubliclet::migrateLegacyMeta(
            $requestInterface->getAttribute("ctx"),
            $this,
            $this->getShareStore(),
            $this->getRightsManager(),
            $requestInterface->getParsedBody()["dry_run"] !== "run"
        );
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return null
     * @throws \Exception
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function switchAction(ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface)
    {
        $action = $requestInterface->getAttribute("action");
        $httpVars = $requestInterface->getParsedBody();
        /** @var ContextInterface $ctx */
        $this->currentContext = $ctx = $requestInterface->getAttribute("ctx");

        if (strpos($action, "sharelist") === false && !isSet($this->accessDriver)) {
            //throw new \Exception("Cannot find access driver!");
            $this->accessDriver = $ctx->getRepository()->getDriverInstance($ctx);
        }


        if (strpos($action, "sharelist") === false && $this->accessDriver->getId() == "access.demo") {
            $errorMessage = "This is a demo, all 'write' actions are disabled!";
            if ($httpVars["sub_action"] === "delegate_repo") {
                $responseInterface = $responseInterface->withBody(new SerializableResponseStream([new UserMessage($errorMessage, LOG_LEVEL_ERROR)]));
                return;
            } else {
                $responseInterface->getBody()->write($errorMessage);
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
                    }else if(!isSet($httpVars["simple_right_read"]) && isSet($httpVars["simple_right_download"]) && !isSet($httpVars["simple_right_write"])){
                        $httpVars["minisite_layout"] = "ajxp_unique_dl";
                    }
                    $httpVars["create_guest_user"] = "true";
                    if($httpVars["simple_share_type"] == "private" && !isSet($httpVars["guest_user_pass"])){
                        throw new \Exception("Please provide a guest_user_pass for private link");
                    }
                }
                $userSelection = UserSelection::fromContext($ctx, $httpVars);
                $ajxpNode = $userSelection->getUniqueNode();
                if (!file_exists($ajxpNode->getUrl())) {
                    throw new \Exception("Cannot share a non-existing file: ".$ajxpNode->getUrl());
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

                    $auth = $this->getAuthorization($ctx, "folder", "workspace");
                    if(!$auth){
                        $mess = LocaleService::getMessages();
                        throw new \Exception($mess["351"]);
                    }

                    $users = array(); $groups = array();
                    $this->getRightsManager()->createUsersFromParameters($httpVars, $users, $groups);

                    $result = $this->createSharedRepository($httpVars, $isUpdate, $users, $groups, $ajxpNode);

                    if (is_object($result) && $result instanceof Repository) {

                        if(!$isUpdate){
                            $this->getShareStore()->storeShare($this->repository->getId(), array(
                                "REPOSITORY" => $result->getUniqueId(),
                                "OWNER_ID" => $ctx->getUser()->getId()), "repository");
                        }

                        Controller::applyHook( ($isUpdate ? "node.share.update" : "node.share.create"), [$ajxpNode, array(
                            'type' => 'repository',
                            'repository' => &$this->repository,
                            'accessDriver' => &$this->accessDriver,
                            'new_repository' => &$result
                        )]);

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

                    $results = $this->shareNode($ctx, $ajxpNode, $httpVars, $isUpdate);
                    if(is_array($results) && $ajxpNode->hasMetaStore() && !$ajxpNode->isRoot()){
                        $ajxpNode->startUpdatingMetadata();
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
                            }else if($shareObject instanceof ShareLink){
                                $hash = $shareObject->getHash();
                                $this->getShareStore()->getMetaManager()->addShareInMeta(
                                    $ajxpNode,
                                    "minisite",
                                    $hash,
                                    ($shareScope == "public"),
                                    ($httpHash != null && $hash != $httpHash) ? $httpHash : null
                                );
                            }else if($shareObject instanceof Repository){
                                $this->getShareStore()->getMetaManager()->addShareInMeta(
                                    $ajxpNode,
                                    "repository",
                                    $shareObject->getUniqueId(),
                                    ($shareScope == "public"),
                                    null
                                );
                            }
                        }
                        $ajxpNode->finishedUpdatingMetadata();
                    }
                }


                Controller::applyHook("msg.instant", array( $ajxpNode->getContext(), "<reload_shared_elements/>"));
                /*
                 * Send IM to inform that node has been shared or unshared.
                 * Should be done only if share scope is public.
                 */
                if($shareScope == "public"){
                    $ajxpNode->loadNodeInfo();
                    $diff = new NodesDiff();
                    $diff->update($ajxpNode);
                    $content = $diff->toXML();
                    Controller::applyHook("msg.instant", array($ajxpNode->getContext(), $content, null, null, [$ajxpNode->getPath()]));
                }

                if(!isSet($httpVars["return_json"])){

                    $responseInterface = $responseInterface->withHeader("Content-type", "text/plain");
                    $responseInterface->getBody()->write($plainResult);

                }else{

                    $compositeShare = $this->getShareStore()->getMetaManager()->getCompositeShareForNode($ajxpNode);
                    if(!empty($compositeShare)){
                        $responseInterface = new JsonResponse($this->compositeShareToJson($ctx, $compositeShare));
                    }else{
                        $responseInterface = new JsonResponse([]);
                    }

                }

                break;

            case "toggle_link_watch":

                $userSelection = UserSelection::fromContext($ctx, $httpVars);
                $shareNode = $selectedNode = $userSelection->getUniqueNode();
                $watchValue = $httpVars["set_watch"] == "true" ? true : false;
                $folder = false;
                if (isSet($httpVars["element_type"]) && $httpVars["element_type"] == "folder") {
                    $folder = true;
                    $selectedNode = new AJXP_Node("pydio://". $ctx->getUser()->getId() ."@". InputFilter::sanitize($httpVars["repository_id"], InputFilter::SANITIZE_ALPHANUM) ."/");
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
                $ctxUser = $ctx->getUser();
                if ($this->watcher !== false) {
                    if (!$folder) {
                        if ($watchValue) {
                            $this->watcher->setWatchOnFolder(
                                $selectedNode,
                                $ctxUser->getId(),
                                WatchRegister::$META_WATCH_USERS_READ,
                                array($elementId)
                            );
                        } else {
                            $this->watcher->removeWatchFromFolder(
                                $selectedNode,
                                $ctxUser->getId(),
                                true,
                                $elementId
                            );
                        }
                    } else {
                        if ($watchValue) {
                            $this->watcher->setWatchOnFolder(
                                $selectedNode,
                                $ctxUser->getId(),
                                WatchRegister::$META_WATCH_BOTH
                            );
                        } else {
                            $this->watcher->removeWatchFromFolder(
                                $selectedNode,
                                $ctxUser->getId());
                        }
                    }
                }
                $mess = LocaleService::getMessages();
                $x = new SerializableResponseStream([new UserMessage($mess["share_center.47"])]);
                $responseInterface = $responseInterface->withBody($x);

            break;

            case "load_shared_element_data":

                SessionService::close();
                $node = null;
                if(isSet($httpVars["hash"]) && $httpVars["element_type"] == "file"){

                    // LEGACY LINKS
                    $parsedMeta = array($httpVars["hash"] => array("type" => "file"));
                    $jsonData = array();
                    foreach($parsedMeta as $shareId => $shareMeta){
                        $jsonData[] = $this->shareToJson($ctx, $shareId, $shareMeta, $node);
                    }
                    $responseInterface = new JsonResponse($jsonData);

                }else{

                    $file = InputFilter::decodeSecureMagic($httpVars["file"]);
                    $node = new AJXP_Node($ctx->getUrlBase().$file);
                    $loggedUser = $ctx->getUser();
                    if(isSet($httpVars["owner"]) && $loggedUser->isAdmin()
                        && $loggedUser->getGroupPath() == "/" && $loggedUser->getId() != InputFilter::sanitize($httpVars["owner"], InputFilter::SANITIZE_EMAILCHARS)
                    ){
                        // Impersonate the current user
                        $node->setUserId(InputFilter::sanitize($httpVars["owner"], InputFilter::SANITIZE_EMAILCHARS));
                    }
                    if(!file_exists($node->getUrl())){
                        $mess = LocaleService::getMessages();
                        throw new \Exception(str_replace('%s', "Cannot find file ".$file, $mess["share_center.219"]));
                    }
                    if(isSet($httpVars["tmp_repository_id"]) && $ctx->getUser()->isAdmin()){
                        $compositeShare = $this->getShareStore()->getMetaManager()->getCompositeShareForNode($node, true);
                    }else{
                        $compositeShare = $this->getShareStore()->getMetaManager()->getCompositeShareForNode($node);
                    }
                    if(empty($compositeShare)){
                        $mess = LocaleService::getMessages();
                        throw new \Exception(str_replace('%s', "Cannot find share for node ".$file, $mess["share_center.219"]));
                    }
                    $responseInterface = new JsonResponse($this->compositeShareToJson($ctx, $compositeShare));

                }


            break;

            case "unshare":

                $mess = LocaleService::getMessages();
                $userSelection = UserSelection::fromContext($ctx, $httpVars);
                if(isSet($httpVars["hash"])){
                    $sanitizedHash = InputFilter::sanitize($httpVars["hash"], InputFilter::SANITIZE_ALPHANUM);
                    $ajxpNode = ($userSelection->isEmpty() ? null : $userSelection->getUniqueNode());
                    $result = $this->getShareStore()->deleteShare($httpVars["element_type"], $sanitizedHash, false, false, $ajxpNode);
                    if($result !== false){
                        $x = new SerializableResponseStream([new UserMessage($mess["share_center.216"])]);
                        $responseInterface = $responseInterface->withBody($x);
                    }

                }else{

                    $userSelection = UserSelection::fromContext($ctx, $httpVars);
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
                        $ajxpNode->startUpdatingMetadata();
                        foreach($shares as $shareId =>  $share){
                            $t = isSet($share["type"]) ? $share["type"] : "file";
                            try{
                                $result = $this->getShareStore()->deleteShare($t, $shareId, false, true);
                            }catch(\Exception $e){
                                if($e->getMessage() == "repo-not-found"){
                                    $result = true;
                                }else{
                                    $ajxpNode->finishedUpdatingMetadata();
                                    throw $e;
                                }
                            }
                            $this->getShareStore()->getMetaManager()->removeShareFromMeta($ajxpNode, $shareId);
                            $res = $result && $res;
                        }
                        $ajxpNode->finishedUpdatingMetadata();
                        if($res !== false){

                            $x = new SerializableResponseStream([new UserMessage($mess["share_center.216"])]);
                            $responseInterface = $responseInterface->withBody($x);

                            Controller::applyHook("msg.instant", array($ajxpNode->getContext(), "<reload_shared_elements/>"));

                            if(isSet($httpVars["share_scope"]) &&  $httpVars["share_scope"] == "public"){
                                $ajxpNode->loadNodeInfo();
                                $diff = new NodesDiff();
                                $diff->update($ajxpNode);
                                $content = $diff->toXML();
                                Controller::applyHook("msg.instant", array($ajxpNode->getContext(), $content, null, null, [$ajxpNode->getPath()]));
                            }

                        }
                    }

                }
                break;

            case "reset_counter":

                if(isSet($httpVars["hash"])){

                    $userId = $ctx->getUser()->getId();
                    if(isSet($httpVars["owner_id"]) && $httpVars["owner_id"] != $userId){
                        if(!$ctx->getUser()->isAdmin()){
                            throw new \Exception("You are not allowed to access this resource");
                        }
                        $userId = $httpVars["owner_id"];
                    }
                    $this->getShareStore()->resetDownloadCounter($httpVars["hash"], $userId);

                }else{

                    $userSelection = UserSelection::fromContext($ctx, $httpVars);
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

            case "share_link_update_target_users":

                $hash = InputFilter::decodeSecureMagic($httpVars["hash"]);
                $shareLink = $this->getShareStore()->loadShareObject($hash);
                $repository = $shareLink->getRepository();
                $this->getShareStore()->testUserCanEditShare($repository->getOwner(), []);
                if(isSet($httpVars['json_users'])){
                    $values = json_decode($httpVars['json_users'], true);
                }
                if(!empty($values)){
                    $values = array_map( function($e){ return InputFilter::sanitize($e, InputFilter::SANITIZE_EMAILCHARS);}, $values );
                    $shareLink->addTargetUsers($values, (isSet($httpVars['restrict']) && $httpVars['restrict'] === 'true'));
                    $shareLink->save();
                    $responseInterface = new JsonResponse(['success' => true, 'users' => $values]);
                }else{
                    $responseInterface = new JsonResponse(['success' => false]);
                }

                break;

            case "sharelist-load":

                $itemsPerPage   = 50;
                $crtPage        = 1;
                $crtOffset      = 0;
                $parentRepoId   = isset($httpVars["parent_repository_id"]) ? $httpVars["parent_repository_id"] : "";
                $userContext    = $httpVars["user_context"];
                $shareType      = isSet($httpVars["share_type"])? InputFilter::sanitize($httpVars["share_type"], InputFilter::SANITIZE_ALPHANUM) : null;
                $currentUser    = $ctx->getUser()->getId();
                $clearBroken    = (isSet($httpVars["clear_broken_links"]) && $httpVars["clear_broken_links"] === "true") ? 0 : -1;
                if($userContext == "global" && $ctx->getUser()->isAdmin()){
                    $currentUser = false;
                }else if($userContext == "user" && $ctx->getUser()->isAdmin() && !empty($httpVars["user_id"])){
                    $currentUser = InputFilter::sanitize($httpVars["user_id"], InputFilter::SANITIZE_EMAILCHARS);
                }
                if (isSet($httpVars["dir"]) && strstr($httpVars["dir"], "%23")!==false) {
                    $parts = explode("%23", $httpVars["dir"]);
                    $crtPage = intval($parts[1]);
                    $crtOffset = ($crtPage - 1) * $itemsPerPage;
                }else if(isSet($httpVars["page"])){
                    $crtPage = intval($httpVars["page"]);
                    $crtOffset = ($crtPage - 1) * $itemsPerPage;
                }
                $cursor = [$crtOffset, $itemsPerPage];
                if($clearBroken > -1) {
                    $cursor = null;
                }
                if($httpVars['format'] === 'json'){
                    $data = $this->listSharesJson($ctx, $currentUser, $parentRepoId, $shareType, $cursor);
                    if($currentUser !== '__GROUP__' && $parentRepoId !== '__GROUP__' && $shareType !== '__GROUP__'){
                        foreach($data as $hash => $shareData){
                            $metadata = $this->buildMetadataForShare($ctx, $hash, $shareData, $parentRepoId);
                            if($metadata !== null){
                                $data[$hash]["metadata"] = $metadata;
                            }else{
                                unset($data[$hash]);
                            }
                        }
                    }
                    $responseInterface = new JsonResponse(["data" => $data, "cursor" => $cursor]);
                    break;
                }
                $nodes = $this->listSharesAsNodes($ctx, "/data/repositories/$parentRepoId/shares", $currentUser, $parentRepoId, $cursor, $clearBroken, $shareType);
                if($clearBroken > -1){
                    $responseInterface = new JsonResponse(["cleared_count" => $clearBroken]);
                    break;
                }
                $total = $cursor["total"];

                $nodesList = new NodesList();
                if($total > $itemsPerPage){
                    $nodesList->setPaginationData($total, $crtPage, round($total / $itemsPerPage));
                }
                if($userContext == "current"){
                    $nodesList->initColumnsData("", "", "ajxp_user.shares");
                    $nodesList->appendColumn("ajxp_conf.8", "ajxp_label");
                    $nodesList->appendColumn("share_center.132", "shared_element_parent_repository_label");
                    $nodesList->appendColumn("3", "share_type_readable");
                }else{
                    $nodesList->initColumnsData("filelist", "list", "ajxp_conf.repositories");
                    $nodesList->appendColumn("ajxp_conf.8", "ajxp_label");
                    $nodesList->appendColumn("share_center.159", "owner");
                    $nodesList->appendColumn("3", "share_type_readable");
                    $nodesList->appendColumn("share_center.52", "share_data");
                }
                foreach($nodes as $node){
                    $nodesList->addBranch($node);
                }
                $x = new SerializableResponseStream([$nodesList]);
                $responseInterface = $responseInterface->withBody($x);

            break;

            case "sharelist-clearExpired":

                $accessType = $ctx->getRepository()->getAccessType();
                $currentUser  = ($accessType != "ajxp_conf" && $accessType != "ajxp_admin");
                $count = $this->getShareStore()->clearExpiredFiles($currentUser);
                if($count){
                    $message = "Removed ".count($count)." expired links";
                }else{
                    $message = "Nothing to do";
                }
                $x = new SerializableResponseStream([new UserMessage($message)]);
                $responseInterface = $responseInterface->withBody($x);


            break;

            case "sharelist-migrate":

                $dryRun = true;
                if(isSet($httpVars['run']) && $httpVars['run'] === 'true') $dryRun = false;
                $toStore = $this->getShareStore($ctx)->migrateInternalSharesToStore('', $this->getRightsManager(), $dryRun);
                $responseInterface = new JsonResponse($toStore);

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
                //$this->getShareStore()->getMetaManager()->clearNodeMeta($ajxpNode);
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
            $recycle = $repo->getContextOption($newNode->getContext(), "RECYCLE_BIN");
            if(!empty($recycle) && strpos($newNode->getPath(), $recycle) === 1){
                $delete = true;
            }
        }
        $shareStore = $this->getShareStore();
        $modifiedNodes = $shareStore->moveSharesFromMetaRecursive($oldNode, $delete, $oldNode->getPath(), ($newNode != null ? $newNode->getPath() : null));
        // Force switching back to correct driver!
        if($modifiedNodes > 0){
            /*
            $oldNode->getRepository()->driverInstance = null;
            $oldNode->setDriver(null);
            $oldNode->getDriver();
            */
        }         
        return;

    }

    /**
     * Hook user.after_delete
     * make sure to clear orphan shares
     * @param ContextInterface $ctx
     * @param String $userId
     */
    public function cleanUserShares($ctx, $userId){
        $shares = $this->getShareStore($ctx)->listShares($userId);
        foreach($shares as $hash => $data){
            $this->getShareStore($ctx)->deleteShare($data['SHARE_TYPE'], $hash, false, true);
        }
    }

    /**
     * Hook workspace.delete
     * make sure to clear shares
     * @param ContextInterface $ctx
     * @param String $workspaceId
     */
    public function cleanWorkspaceShares($ctx, $workspaceId){
        $shares = $this->getShareStore($ctx)->listShares('', $workspaceId);
        foreach($shares as $hash => $data){
            $this->getShareStore($ctx)->deleteShare($data['SHARE_TYPE'], $hash, false, true);
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
        $crtContext = $node->getContext();
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
                        if($sharedNode === $node){
                            // This is a minisite on one file, using a content filter, send a node.change on root to force clear cache
                            $sharedPath = "/";
                        }else{
                            $sharedPath = substr($node->getPath(), strlen($sharedNode->getPath()));
                        }
                        $newContext = $crtContext->withRepositoryId($wsId);
                        $sharedNodeUrl = $newContext->getUrlBase().$sharedPath;
                        $newNode = new AJXP_Node($sharedNodeUrl);
                        if($sharedPath === '/') $newNode->setLeaf(false);
                        $result[$wsId] = array($newNode, "DOWN");
                        $this->logDebug('MIRROR NODES', 'Found shared in parent - register node '.$sharedNodeUrl);
                    }
                }
            }
        }
        if($direction !== "DOWN"){
            if($node->getRepository()->hasParent()){
                $parentRepoId = $node->getRepository()->getParentId();
                $parentRepository = RepositoryService::getRepositoryById($parentRepoId);
                if(!empty($parentRepository) && !$parentRepository->isTemplate()){
                    $newContext = $crtContext->withRepositoryId($parentRepoId);
                    $owner = $node->getRepository()->getOwner();
                    if($owner !== null){
                        $newContext = $newContext->withUserId($owner);
                    }
                    if($node->getRepository()->hasContentFilter()){
                        $cFilter = $node->getRepository()->getContentFilter();
                        $parentNodePath = array_keys($cFilter->filters)[0];
                        $parentNodeURL = $newContext->getUrlBase().$parentNodePath;
                    }else{
                        $currentRoot = $node->getRepository()->getContextOption($crtContext, "PATH");
                        $parentRoot = $parentRepository->getContextOption($newContext, "PATH");
                        $relative = substr($currentRoot, strlen($parentRoot));
                        $parentNodeURL = $newContext->getUrlBase().$relative.$node->getPath();
                    }
                    $this->logDebug("action.share", "Should trigger on ".$parentNodeURL);
                    $parentNode = new AJXP_Node($parentNodeURL);
                    $result[$parentRepoId] = array($parentNode, "UP");
                }
            }
        }
        return $result;
    }

    /**
     * @param null $fromMirrors
     * @param null $toMirrors
     * @param bool $copy
     * @param null $direction
     * @throws \Exception
     */
    private function applyForwardEvent($fromMirrors = null, $toMirrors = null, $copy = false, $direction = null){
        if($fromMirrors === null){
            // Create
            foreach($toMirrors as $mirror){
                list($node, $direction) = $mirror;
                Controller::applyHook("node.change", array(null, $node, false, $direction), true);
            }
        }else if($toMirrors === null){
            foreach($fromMirrors as $mirror){
                list($node, $direction) = $mirror;
                Controller::applyHook("node.change", array($node, null, false, $direction), true);
            }
        }else{
            foreach($fromMirrors as $repoId => $mirror){
                list($fNode, $fDirection) = $mirror;
                if(isSet($toMirrors[$repoId])){
                    list($tNode, $tDirection) = $toMirrors[$repoId];
                    unset($toMirrors[$repoId]);
                    try{
                        Controller::applyHook("node.change", array($fNode, $tNode, $copy, $fDirection), true);
                    }catch(\Exception $e){
                        $this->logError(__FUNCTION__, "Error while applying node.change hook (".$e->getMessage().")");
                    }
                }else{
                    try{
                    Controller::applyHook("node.change", array($fNode, null, $copy, $fDirection), true);
                    }catch(\Exception $e){
                        $this->logError(__FUNCTION__, "Error while applying node.change hook (".$e->getMessage().")");
                    }
                }
            }
            foreach($toMirrors as $mirror){
                list($tNode, $tDirection) = $mirror;
                try{
                Controller::applyHook("node.change", array(null, $tNode, $copy, $tDirection), true);
                }catch(\Exception $e){
                    $this->logError(__FUNCTION__, "Error while applying node.change hook (".$e->getMessage().")");
                }
            }
        }

    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $fromNode
     * @param AJXP_Node $toNode
     * @param bool $copy
     * @param String $direction
     */
    public function forwardEventToShares($fromNode=null, $toNode=null, $copy = false, $direction=null){

        $refNode = ($fromNode != null ? $fromNode : $toNode);// cannot be both null
        if(empty($direction) && $this->getContextualOption($refNode->getContext(), "FORK_EVENT_FORWARDING")){
            CliRunner::applyActionInBackground(
                $refNode->getContext(),
                "forward_change_event",
                array(
                    "from" => $fromNode === null ? "" : $fromNode->getUrl(),
                    "to" => $toNode === null ? "" : $toNode->getUrl(),
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

    /**
     * @param $actionName
     * @param $httpVars
     * @param $fileVars
     */
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
    /* CREATE / EDIT SHARES
    /**************************/

    /**
     * @param array $httpVars
     * @param UserSelection $userSelection
     * @return int
     * @throws \Exception
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
        if( ( $hasDir && !$this->getAuthorization($this->currentContext, "folder", "minisite") )
            || ($hasFile && !$this->getAuthorization($this->currentContext, "file"))){
            throw new \Exception(103);
        }
        if($setFilter){ // Either it's a file, or many nodes are shared
            $httpVars["filter_nodes"] = $nodes;
        }
        if(!isSet($httpVars["repo_label"])){
            $first = $userSelection->getUniqueNode();
            $httpVars["repo_label"] = $first->getLabel();
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
            $httpVars["repo_label"] = $ajxpNode->getLabel();
        }
    }

    /**
     * @param array $httpVars
     * @param bool $update
     * @return Repository
     * @throws \Exception
     */
    protected function createOrLoadSharedRepository($httpVars, &$update){

        if (!isSet($httpVars["repo_label"]) || $httpVars["repo_label"] == "") {
            $mess = LocaleService::getMessages();
            throw new \Exception($mess["349"]);
        }

        if (isSet($httpVars["repository_id"])) {
            $editingRepo = RepositoryService::getRepositoryById($httpVars["repository_id"]);
            $update = true;
        }

        // CHECK REPO DOES NOT ALREADY EXISTS WITH SAME LABEL
        $label = InputFilter::sanitize(InputFilter::securePath($httpVars["repo_label"]), InputFilter::SANITIZE_HTML);
        $description = InputFilter::sanitize(InputFilter::securePath($httpVars["repo_description"]), InputFilter::SANITIZE_HTML);
        $exists = $this->checkRepoWithSameLabel($label, isSet($editingRepo)?$editingRepo:null);
        if($exists){
            $mess = LocaleService::getMessages();
            throw new \Exception($mess["share_center.352"]);
        }

        $loggedUser = $this->currentContext->getUser();
        $currentRepo = $this->currentContext->getRepository();

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
            $oldScope = $editingRepo->getSafeOption("SHARE_ACCESS");
            $currentOwner = $editingRepo->getOwner();
            if($newScope != $oldScope && $currentOwner != $loggedUser->getId()){
                $mess = LocaleService::getMessages();
                throw new \Exception($mess["share_center.224"]);
            }
            if($newScope !== $oldScope){
                $editingRepo->addOption("SHARE_ACCESS", $newScope);
                $replace = true;
            }
            if(isSet($httpVars["transfer_owner"])){
                $newOwner = $httpVars["transfer_owner"];
                if($newOwner != $currentOwner && $currentOwner != $loggedUser->getId()){
                    $mess = LocaleService::getMessages();
                    throw new \Exception($mess["share_center.224"]);
                }
                $editingRepo->setOwnerData($editingRepo->getParentId(), $newOwner, $editingRepo->getUniqueUser());
                $replace = true;
            }

            if($replace) {
                RepositoryService::replaceRepository($newRepo->getId(), $newRepo);
            }

        } else {

            $options = $this->accessDriver->makeSharedRepositoryOptions($this->currentContext, $httpVars);
            // TMP TESTS
            $options["SHARE_ACCESS"] = $httpVars["share_scope"];
            $newRepo = $currentRepo->createSharedChild(
                $label,
                $options,
                $currentRepo->getId(),
                $loggedUser->getId(),
                null
            );
            $gPath = $loggedUser->getGroupPath();
            if (!empty($gPath) && !ConfService::getContextConf($this->currentContext, "CROSSUSERS_ALLGROUPS", "conf")) {
                $newRepo->setGroupPath($gPath);
            }
            $newRepo->setDescription($description);
            if(isSet($httpVars["filter_nodes"])){
                $newRepo->setContentFilter(new ContentFilter($httpVars["filter_nodes"]));
            }
            RepositoryService::addRepository($newRepo);
        }
        return $newRepo;

    }

    /**
     * @param array $httpVars
     * @param bool $update
     * @return mixed An array containing the hash (0) and the generated url (1)
     * @throws \Exception
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
        $userSelection = UserSelection::fromContext($this->currentContext, $httpVars);

        $this->filterHttpVarsForLeafPath($httpVars, $userSelection);

        $users = array(); $groups = array();
        $users[$hiddenUserEntry["ID"]] = $hiddenUserEntry;

        $newRepo = $this->createSharedRepository($httpVars, $repoUpdate, $users, $groups, $userSelection->getUniqueNode());

        $shareObject->setParentRepositoryId($this->repository->getId());
        $shareObject->attachToRepository($newRepo->getId());
        // STORE DATA & HASH IN SHARE STORE
        $hash = $shareObject->save();
        $url = $this->getPublicAccessManager()->buildPublicLink($hash);
        Controller::applyHook("url.shorten", array($this->currentContext, &$shareObject, $this->getPublicAccessManager()));

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
        Controller::applyHook("node.share.".($update?"update":"create"), [$userSelection->getUniqueNode(), array(
            'type' => 'minisite',
            'repository' => &$this->repository,
            'accessDriver' => &$this->accessDriver,
            'data' => &$data,
            'url' => $url,
            'new_repository' => &$newRepo
        )]);

        return array($hash, $url);
    }

    /**
     * @param array $httpVars
     * @param bool $update
     * @param array $users
     * @param array $groups
     * @param AJXP_Node $originalNode
     * @return Repository
     * @throws \Exception
     */
    public function createSharedRepository($httpVars, &$update, $users=array(), $groups=array(), $originalNode = null)
    {
        // ERRORS
        // 100 : missing args
        // 101 : repository label already exists
        // 102 : user already exists
        // 103 : current user is not allowed to share
        // SUCCESS
        // 200
        $loggedUser = $this->currentContext->getUser();
        $currentRepo = $this->currentContext->getRepository();
        $actRights = $loggedUser->getMergedRole()->listActionsStatesFor($currentRepo);
        if (isSet($actRights["share"]) && $actRights["share"] === false) {
            $mess = LocaleService::getMessages();
            throw new \Exception($mess["351"]);
        }

        $newRepo = $this->createOrLoadSharedRepository($httpVars, $update);

        $selection = UserSelection::fromContext($this->currentContext, $httpVars);
        $this->getRightsManager()->assignSharedRepositoryPermissions($currentRepo, $newRepo, $update, $users, $groups, $selection, $originalNode);

        // HANDLE WATCHES ON CHILDREN AND PARENT
        foreach($users as $userName => $userEntry){
            $this->toggleWatchOnSharedRepository(
                $newRepo->getId(),
                $userName,
                $userEntry["WATCH"],
                $loggedUser->getId()
            );
        }
        $this->toggleWatchOnSharedRepository(
            $newRepo->getId(),
            $loggedUser->getId(),
            ($httpVars["self_watch_folder"] == "true")
        );

        $this->logInfo(($update?"Update":"New")." Share", array(
            "file" => "'".$selection->getUniqueFile()."'",
            "files" => $selection->getFiles(),
            "repo_uuid" => $currentRepo->getId(),
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
     * @return Model\ShareLink
     * @throws \Exception
     */
    protected function shareObjectFromParameters($linkData, &$hiddenUserEntries, &$shareObjects,  $type = "public", $invitationLabel = ""){

        if(isSet($linkData["hash"])){
            $link = $this->getShareStore()->loadShareObject($linkData["hash"]);
        }else{
            if($type == "public"){
                $link = $this->getShareStore()->createEmptyShareObject();
            }else{
                $link = new TargettedLink($this->getShareStore());
                if(UsersService::usersEnabled()) {
                    $link->setOwnerId($this->currentContext->getUser()->getId());
                }
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
     * @throws \Exception
     */
    public function shareNode(ContextInterface $ctx, $ajxpNode, $httpVars, &$update){

        $hiddenUserEntries = array();
        $originalHttpVars = $httpVars;
        $ocsStore = new OCS\Model\SQLStore();
        $ocsClient = new OCS\Client\OCSClient();
        $userSelection = UserSelection::fromContext($ctx, $httpVars);
        $mess = LocaleService::getMessages();

        /**
         * @var ShareLink[] $shareObjects
         */
        $shareObjects = array();

        // PUBLIC LINK
        if(isSet($httpVars["enable_public_link"])){
            if(!$this->getAuthorization($ctx, $ajxpNode->isLeaf() ? "file":"folder", "minisite")){
                throw new \Exception($mess["share_center." . ($ajxpNode->isLeaf() ? "225" : "226")]);
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
        if((count($users) || count($groups)) && !$this->getAuthorization($ctx, $ajxpNode->isLeaf()?"file":"folder", "workspace")){
            $users = $groups = array();
        }
        foreach($hiddenUserEntries as $entry){
            $users[$entry["ID"]] = $entry;
        }

        if(!count($users) && !count($groups)){
            ob_start();
            unset($originalHttpVars["hash"]);
            $request = Controller::executableRequest($ctx, "unshare", $originalHttpVars);
            $this->switchAction($request, new Response());
            ob_end_clean();
            return null;
        }

        $newRepo = $this->createSharedRepository($httpVars, $update, $users, $groups, $ajxpNode);

        foreach($shareObjects as $shareObject){

            $shareObject->setParentRepositoryId($ctx->getRepositoryId());
            $shareObject->attachToRepository($newRepo->getId());
            $shareObject->save();
            if($shareObject instanceof \Pydio\OCS\Model\TargettedLink){
                $invitation = $shareObject->getPendingInvitation();
                if(!empty($invitation)){
                    $ocsStore->generateInvitationId($invitation);
                    try{
                        $ocsClient->sendInvitation($invitation);
                    }catch (\Exception $e){
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
                Controller::applyHook("url.shorten", array($this->currentContext, &$shareObject, $this->getPublicAccessManager()));
            }

        }
        if(count($groups) || (count($users) && count($users) > count($hiddenUserEntries) )){
            // Add an internal entry
            $this->getShareStore()->storeShare(
                $ctx->getRepositoryId(),
                [
                    'SHARE_TYPE'=>'repository',
                    'OWNER_ID'=>$ctx->getUser()->getId(),
                    'REPOSITORY'=>$newRepo->getId(),
                    'USERS_COUNT' => count($users) - count($hiddenUserEntries),
                    'GROUPS_COUNT' => count($groups)
                ],
                "repository",
                "repo-".$newRepo->getId()
            );
        }else{
            // Delete 'internal' if it exists
            $this->getShareStore()->deleteShareEntry("repo-" . $newRepo->getId());
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
     * @param null $shareType
     * @return array
     */
    public function listShares($currentUser, $parentRepositoryId="", &$cursor = null, $shareType = null){
        if($currentUser === false){
            $crtUser = "";
        }else {
            $crtUser = $currentUser;
        }
        return $this->getShareStore()->listShares($crtUser, $parentRepositoryId, $cursor, $shareType);
    }

    /**
     * @param ContextInterface $ctx
     * @param bool $currentUser
     * @param string $parentRepositoryId
     * @param null $shareType
     * @param null $cursor
     * @return array
     */
    public function listSharesJson(ContextInterface $ctx, $currentUser = true, $parentRepositoryId = '', $shareType = null, &$cursor = null){
        $shares =  $this->listShares($currentUser, $parentRepositoryId, $cursor, $shareType);
        return $shares;
    }

    /**
     * @param ContextInterface $ctx
     * @param $hash
     * @param $shareData
     * @param string $parentRepositoryId
     * @param int $clearBroken
     * @return mixed
     */
    private function buildMetadataForShare(ContextInterface $ctx, $hash, $shareData, $parentRepositoryId = '', &$clearBroken = -1){

        $parent = RepositoryService::getRepositoryById($parentRepositoryId);

        $shareType = $shareData["SHARE_TYPE"];
        $meta["share_type"] = $shareType;
        $meta["ajxp_shared"] = true;

        $repoId = $shareData["REPOSITORY"];
        $repoObject = RepositoryService::getRepositoryById($repoId);
        if($repoObject == null){
            $meta["text"] = "Invalid link";
            if($clearBroken > -1){
                $this->getShareStore($ctx)->deleteShare($shareType, $hash, false, true);
                $clearBroken ++;
            }
            return null;
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
            $ctx = new Context($meta["owner"], $parent->getId());
            $parentPath = $parent->getContextOption($ctx, "PATH");
            $meta["shared_element_parent_repository_label"] = $parent->getDisplay();
        }else{
            $crtParent = RepositoryService::getRepositoryById($repoObject->getParentId());
            if(!empty($crtParent)){
                $ctx = new Context($meta["owner"], $repoObject->getParentId());
                $parentPath = $crtParent->getContextOption($ctx, "PATH");
                $meta["shared_element_parent_repository_label"] = $crtParent->getDisplay();
            }else {
                $meta["shared_element_parent_repository_label"] = $repoObject->getParentId();
            }
        }
        if($repoObject->hasContentFilter()){
            $meta["ajxp_shared_minisite"] = "file";
            $meta["icon"] = "mime_empty.png";
            $meta["fonticon"] = "file";
            $meta["original_path"] = array_pop(array_keys($repoObject->getContentFilter()->filters));
        }else{
            $meta["ajxp_shared_minisite"] = "public";
            $meta["icon"] = "folder.png";
            $meta["fonticon"] = "folder";
            $ctx = $ctx->withRepositoryId($repoObject->getId());
            $meta["original_path"] = $repoObject->getContextOption($ctx, "PATH");
        }
        if(!empty($parentPath) &&  strpos($meta["original_path"], $parentPath) === 0){
            $meta["original_path"] = substr($meta["original_path"], strlen($parentPath));
        }
        try{
            // Test node really exists
            $originalNode = new AJXP_Node("pydio://".$meta["owner"]."@".$meta["shared_element_parent_repository"].$meta["original_path"]);
            $test = @file_exists($originalNode->getUrl());
            if(!$test){
                if($clearBroken > -1){
                    $this->getShareStore($ctx)->deleteShare($shareType, $hash);
                    $clearBroken ++;
                }else{
                    $meta["broken_link"] = true;
                    $meta["original_path"] .= " (BROKEN)";
                }
            }
        }catch(\Exception $e){}

        return $meta;

    }

    /**
     * @param ContextInterface $ctx
     * @param $rootPath
     * @param bool|string $currentUser if true, currently logged user. if false all users. If string, user ID.
     * @param string $parentRepositoryId
     * @param null $cursor
     * @param int $clearBroken
     * @param null $shareType
     * @return AJXP_Node[]
     */
    public function listSharesAsNodes(ContextInterface $ctx, $rootPath, $currentUser, $parentRepositoryId = "", &$cursor = null, &$clearBroken = -1, $shareType = null){

        $shares =  $this->listShares($currentUser, $parentRepositoryId, $cursor, $shareType);
        $nodes = array();

        foreach($shares as $hash => $shareData){

            $icon = "folder";

            if(!is_object($shareData["REPOSITORY"])){

                $meta = $this->buildMetadataForShare($ctx, $hash, $shareData, $parentRepositoryId, $clearBroken);
                $meta["icon"] = $meta["openicon"] = $icon;
                $meta["ajxp_mime"] = "repository_editable";

            }else if($shareData["REPOSITORY"] instanceof Repository && !empty($shareData["FILE_PATH"])){

                $meta = array(
                    "icon"			=> $icon,
                    "openicon"		=> $icon,
                    "ajxp_mime" 	=> "repository_editable"
                );

                $shareType = $shareData["SHARE_TYPE"];
                $meta["share_type"] = $shareType;
                $meta["ajxp_shared"] = true;
                $meta["owner"] = $shareData["OWNER_ID"];
                $meta["share_type_readable"] = "Publiclet (legacy)";
                $meta["text"] = basename($shareData["FILE_PATH"]);
                $meta["icon"] = "mime_empty.png";
                $meta["fonticon"] = "file";
                $meta["share_data"] = $meta["copy_url"] = $this->getPublicAccessManager()->buildPublicLink($hash);
                $meta["share_link"] = true;
                $meta["shared_element_hash"] = $hash;
                $meta["ajxp_shared_publiclet"] = $hash;

            }else{

                continue;

            }
            $nodes[] = new AJXP_Node($rootPath."/".$hash, $meta);

        }

        return $nodes;


    }

    /**
     * @param CompositeShare $compositeShare
     * @return array
     */
    public function compositeShareToJson(ContextInterface $ctx, $compositeShare){

        $repoId = $compositeShare->getRepositoryId();
        $repo = $compositeShare->getRepository();
        $messages = LocaleService::getMessages();

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
        }catch(\Exception $e){
            $notExistsData["label"] = $e->getMessage();
            return $notExistsData;
        }

        $jsonData = $compositeShare->toJson($ctx, $this->watcher, $this->getRightsManager(), $this->getPublicAccessManager(), $messages);
        if($jsonData === false){
            return $notExistsData;
        }
        return $jsonData;

    }

    /**
     * @param String $shareId
     * @param array $shareMeta
     * @param AJXP_Node $node
     * @throws \Exception
     * @return array|bool
     */
    public function shareToJson(ContextInterface $ctx, $shareId, $shareMeta, $node = null){

        $messages = LocaleService::getMessages();
        $jsonData = array();
        $elementWatch = false;
        if($shareMeta["type"] == "file"){

            $jsonData = LegacyPubliclet::publicletToJson(
                $ctx,
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

            $repo = RepositoryService::getRepositoryById($repoId);
            if($repoId == null || ($repo == null && $node != null)){
                if($minisite){
                    $this->getShareStore()->getMetaManager()->removeShareFromMeta($node, $shareId);
                }
                return $notExistsData;
            }
            try{
                $this->getShareStore()->testUserCanEditShare($repo->getOwner(), $repo->options);
            }catch(\Exception $e){
                $notExistsData["label"] = $e->getMessage();
                return $notExistsData;
            }
            $watchNode = new AJXP_Node($ctx->withRepositoryId($repoId)->getUrlBase()."/");
            if ($this->watcher != false && $node != null) {
                $elementWatch = $this->watcher->hasWatchOnNode(
                    $watchNode,
                    $ctx->hasUser()?$ctx->getUser()->getId():"shared",
                    WatchRegister::$META_WATCH_NAMESPACE
                );
            }
            if($node != null){
                $sharedEntries = $this->getRightsManager()->computeSharedRepositoryAccessRights($repoId, true, $watchNode);
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
                "users_number"  => UsersService::countUsersForRepository($ctx, $repoId),
                "label"         => $repo->getDisplay(),
                "description"   => $repo->getDescription(),
                "entries"       => $sharedEntries,
                "element_watch" => $elementWatch,
                "repository_url"=> ApplicationState::getWorkspaceShortcutURL($repo) ."/",
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
