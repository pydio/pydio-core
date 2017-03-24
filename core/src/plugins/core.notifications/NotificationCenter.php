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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Notification\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StatHelper;
use Pydio\Core\PluginFramework\Plugin;
use \DOMXPath as DOMXPath;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Notification dispatcher
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
class NotificationCenter extends Plugin
{
    /**
     * @var String
     */
    private $userId;

    /**
     * @var String
     */
    private $userTemporaryLabel = '';

    /**
     * @var IFeedStore|bool
     */
    private $eventStore = false;

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        $this->userId = "shared";
        if($ctx->hasUser()){
            $u = $ctx->getUser();
            $this->userId = $u->getId();
            $tempLabel = $u->getPersonalRole()->filterParameterValue("core.conf", "USER_TEMPORARY_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
            if(!empty($tempLabel)){
                $this->userTemporaryLabel = $tempLabel;
            }
        }
        $this->userId = $ctx->hasUser() ? $ctx->getUser()->getId() : "shared";
        $pService = PluginsService::getInstance($ctx);
        try {
            $this->eventStore = ConfService::instanciatePluginFromGlobalParams($this->pluginConf["UNIQUE_FEED_INSTANCE"], "Pydio\\Notification\\Core\\IFeedStore", $pService);
        } catch (\Exception $e) {

        }
        if ($this->eventStore === false) {
            $this->pluginConf["USER_EVENTS"] = false;
        }else{
            $pService->setPluginActive($this->eventStore->getType(), $this->eventStore->getName(), true, $this->eventStore);
        }
    }

    /**
     * @inheritdoc
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        $disableWsActivity = $this->getContextualOption($ctx, "SHOW_WORKSPACES_ACTIVITY") === false;
        $disableUserEvents = $this->getContextualOption($ctx, "USER_EVENTS") === false;
        if(!$disableUserEvents && !$disableWsActivity){
            return;
        }
        // DISABLE STUFF AS NEEDED
       if($contribNode->nodeName == "actions" && $disableUserEvents){
           $actionXpath=new DOMXPath($contribNode->ownerDocument);
           $publicUrlNodeList = $actionXpath->query('action[@name="get_my_feed"]', $contribNode);
           $publicUrlNode = $publicUrlNodeList->item(0);
           $contribNode->removeChild($publicUrlNode);
       }else if($contribNode->nodeName == "client_configs"){
           $actionXpath=new DOMXPath($contribNode->ownerDocument);
           $children = $actionXpath->query('component_config', $contribNode);
           /** @var \DOMElement $child */
           foreach($children as $child){
               if($child->getAttribute("className") !== "InfoPanel" && !$disableUserEvents){
                   continue;
               }
               $contribNode->removeChild($child);
           }
       }
    }

    /**
     * Hooked on msg.notifications
     * @param Notification $notification
     * @throws \Exception
     */
    public function persistNotificationToAlerts(Notification &$notification)
    {
        if ($this->eventStore) {
            $this->eventStore->persistAlert($notification);
            Controller::applyHook("msg.instant",array(
                $notification->getNode()->getContext(),
                "<reload_user_feed/>",
                $notification->getTarget()
            ));
            if($notification->getNode()->getRepository() != null && $notification->getNode()->getRepository()->hasParent()){
                $parentRepoId = $notification->getNode()->getRepository()->getParentId();
                Controller::applyHook("msg.instant",array(
                    $notification->getNode()->getContext()->withRepositoryId($parentRepoId),
                    "<reload_user_feed/>",
                    $notification->getTarget()
                ));
            }
        }
    }


    /**
     * @param AJXP_Node|null $oldNode
     * @param AJXP_Node|null $newNode
     * @param bool $copy
     * @param string $targetNotif
     * @throws \Exception
     */
    public function persistChangeHookToFeed(AJXP_Node $oldNode = null, AJXP_Node $newNode = null, $copy = false, $targetNotif = "new")
    {
        if(!$this->eventStore) return;

        $nodes = [];
        if($oldNode !== null && $newNode !== null && $oldNode->getRepositoryId() !== $newNode->getRepositoryId()){
            if(!$copy) $nodes[] = $oldNode;
            $nodes[] = $newNode;
        }else{
            $nodes[] = ($oldNode === null ? $newNode : $oldNode);
        }
        /** @var AJXP_Node $n */
        foreach($nodes as $n){
            $ctx = $n->getContext();
            if(!$ctx->hasUser() || !$ctx->hasRepository()){
                return;
            }
            $repository = $n->getContext()->getRepository();
            $userObject = $n->getContext()->getUser();
            $repositoryScope = $repository->securityScope();
            $repositoryScope = ($repositoryScope !== false ? $repositoryScope : "ALL");
            $repositoryOwner = $repository->hasOwner() ? $repository->getOwner() : null;
            Controller::applyHook("msg.instant",array(
                $ctx,
                "<reload_user_feed/>",
                $userObject->getId()
            ));
            $this->eventStore->persistEvent("node.change", func_get_args(), $repository->getId(), $repositoryScope, $repositoryOwner, $userObject->getId(), $userObject->getGroupPath());
        }

    }


    /**
     * @param ContextInterface $ctx
     * @param $data
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function loadRepositoryInfo(ContextInterface $ctx, &$data){
        $body = [
            'format' => 'array',
            'current_repository'=>'true',
            'feed_type'=>'notif',
            'limit' => 1,
            'path'=>'/',
            'merge_description'=>true,
            'description_as_label'=>false
        ];
        $req = Controller::executableRequest($ctx, "get_my_feed", $body);
        $this->loadUserFeed($req, new \Zend\Diactoros\Response\EmptyResponse(), $returnData);
        $data["core.notifications"] = $returnData;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @param array $returnData
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function loadUserFeed(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface, &$returnData = [])
    {
        SessionService::close();
        $httpVars = $requestInterface->getParsedBody();
        if(!$this->eventStore) {
            throw new \Pydio\Core\Exception\PydioException("Cannot find eventStore for notification plugin");
        }
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $u = $ctx->getUser();

        $crtPath = isSet($httpVars["path"])? InputFilter::sanitize($httpVars["path"], InputFilter::SANITIZE_DIRNAME) :"";
        $mess = LocaleService::getMessages();
        $nodesList = new \Pydio\Access\Core\Model\NodesList($crtPath);
        $format = "html";
        if (isSet($httpVars["format"])) {
            $format = $httpVars["format"];
        }
        if ($format == "html") {
            $responseInterface = $responseInterface->withHeader("Content-Type", "text/html");
            $responseInterface->getBody()->write("<h2>".$mess["notification_center.4"]."</h2>");
            $responseInterface->getBody()->write("<ul class='notification_list'>");
        } else {
            $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
            $responseInterface = $responseInterface->withBody($x);
            $x->addChunk($nodesList);
        }

        if ($u == null) {
            if($format == "html"){
                $responseInterface->getBody()->write("</ul>");
            }
            return;
        }
        $userId = $u->getId();
        $userGroup = $u->getGroupPath();
        $authRepos = array();
        $crtRepId = $ctx->getRepositoryId();
        if (isSet($httpVars["repository_id"]) && $u->getMergedRole()->canRead($httpVars["repository_id"])) {
            $authRepos[] = $httpVars["repository_id"];
        } else if (isSet($httpVars["current_repository"]) && $httpVars['current_repository'] === 'true'){
            $authRepos[] = $crtRepId;
        } else {
            $accessibleRepos =  \Pydio\Core\Services\UsersService::getRepositoriesForUser($u, false);
            $authRepos = array_keys($accessibleRepos);
        }
        $offset = isSet($httpVars["offset"]) ? intval($httpVars["offset"]): 0;
        $limit = isSet($httpVars["limit"]) ? intval($httpVars["limit"]): 15;
        if(!isSet($httpVars["feed_type"]) || $httpVars["feed_type"] == "notif" || $httpVars["feed_type"] == "all"){
            $res = $this->eventStore->loadEvents($authRepos, $crtPath, $userGroup, $offset, $limit, false, $userId, !empty($crtPath));
        }else{
            $res = array();
        }

        // APPEND USER ALERT IN THE SAME QUERY FOR NOW
        if(!isSet($httpVars["feed_type"]) || $httpVars["feed_type"] == "alert" || $httpVars["feed_type"] == "all"){
            $this->loadUserAlerts($requestInterface, $responseInterface, $nodesList);
        }
        restore_error_handler();
        $index = 1;
        foreach ($res as $n => $object) {
            $args = $object->arguments;
            $oldNode = (isSet($args[0]) ? $args[0] : null);
            $newNode = (isSet($args[1]) ? $args[1] : null);
            $copy = (isSet($args[2]) && $args[2] === true ? true : null);
            if( ($oldNode != null && !$oldNode instanceof AJXP_Node) || ($newNode != null && !$newNode instanceof AJXP_Node)){
                error_log("Skipping notification as nodes are not excepted class, probably a deserialization issue");
                continue;
            }
            // Backward compat : make sure there is a user ID in the node url.
            if( $oldNode !== null && !$oldNode->hasUser() ){
                $oldNode->setUserId($object->author);
            }
            if( $newNode !== null && !$newNode->hasUser() ){
                $newNode->setUserId($object->author);
            }
            $notif = $this->generateNotificationFromChangeHook($oldNode, $newNode, $copy, "unify");
            if ($notif !== false && $notif->getNode() !== false) {
                $notif->setAuthor($object->author);
                $notif->setDate(intval($object->date));
                if ($format == "html") {
                    $p = $notif->getNode()->getPath();
                    $responseInterface->getBody()->write("<li data-ajxpNode='$p'>".$notif->getDescriptionShort()."</li>");
                } else {
                    $node = $notif->getNode();
                    if ($node == null) {
                        $this->logInfo("Warning", "Empty node stored in notification ".$notif->getAuthor()."/ ".$notif->getAction());
                        continue;
                    }
                    //$node->setUserId($node->hasUser() ? $node->getUserId() : $notif->getAuthor());
                    try {
                        @$node->loadNodeInfo();
                    } catch (\Exception $e) {
                        continue;
                    }
                    $node->event_description = ucfirst($notif->getDescriptionBlock()) . " ".$mess["notification.tpl.block.user_link"] ." ". $notif->getAuthorLabel();
                    $node->event_description_long = strip_tags($notif->getDescriptionLong(true));
                    $node->event_date = StatHelper::relativeDate($notif->getDate(), $mess);
                    $node->short_date = StatHelper::relativeDate($notif->getDate(), $mess, true);
                    $node->event_time = $notif->getDate();
                    $node->event_type = "notification";
                    $node->event_action = $notif->getAction();
                    $node->event_id = $object->event_id;
                    if ($node->getRepository() != null) {
                        $node->repository_id = ''.$node->getRepository()->getId();
                        if ($node->repository_id != $crtRepId && $node->getRepository()->getDisplay() != null) {
                            $node->event_repository_label = "[".$node->getRepository()->getDisplay()."]";
                        }
                    }
                    $node->event_author = $notif->getAuthor();
                    // Replace PATH, to make sure they will be distinct children of the loader node
                    $node->real_path = $node->getPath();
                    $node->setLabel(basename($node->getPath()));
                    if(isSet($httpVars["merge_description"]) && $httpVars["merge_description"] == "true"){
                        if(isSet($httpVars["description_as_label"]) && $httpVars["description_as_label"] == "true"){
                            $node->setLabel($node->event_description." ".$node->event_date);
                        }else{
                            $node->setLabel(basename($node->getPath()));
                            $node->ajxp_description = $node->event_description .' ' . $node->event_date;
                            //$node->setLabel(basename($node->getPath())." ".$node->event_description." ".$node->event_date);
                        }
                    }
                    $url = parse_url($node->getUrl());
                    $node->setUrl($url["scheme"]."://".$url["host"]."/notification_".$index);
                    $index ++;
                    if($format == "array"){
                        $keys = $node->listMetaKeys();
                        $data = array();
                        foreach($keys as $k){
                            $data[$k] = $node->$k;
                        }
                        $returnData[] = $data;
                    }else{
                        $nodesList->addBranch($node);
                    }
                }
            }
        }
        if ($format == "html") {
            echo("</ul>");
            $responseInterface->getBody()->write("</ul>");
        }

    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @throws PydioException
     */
    public function clearUserFeed(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface){

        if(!$this->eventStore){
            return;
        }
        $data = $requestInterface->getParsedBody();
        $contextType = $data["context_type"];
        if(!in_array($contextType, ["all", "user", "repository"])){
            throw new PydioException("Invalid Arguments");
        }
        $contextValue = InputFilter::sanitize($data["context_value"], InputFilter::SANITIZE_ALPHANUM);
        $this->eventStore->deleteFeed('event', $contextType === 'user' ? $contextValue : null, $contextType === 'repository' ? $contextValue : null, $count);
        $responseInterface = new JsonResponse(["cleared_rows" => $count]);

    }


    /**
     * @param $actionName
     * @param $httpVars
     * @param $fileVars
     * @param ContextInterface $ctx
     */
    public function dismissUserAlert($actionName, $httpVars, $fileVars, ContextInterface $ctx)
    {
        if(!$this->eventStore) return;
        $alertId = $httpVars["alert_id"];
        $oc = 1;
        if(isSet($httpVars["occurrences"])) $oc = intval($httpVars["occurrences"]);
        $this->eventStore->dismissAlertById($ctx, $alertId, $oc);
    }

    /**
     * Position a date saying when the last read was done
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function updateAlertsLastRead(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface){
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute('ctx');
        $u = $ctx->getUser();
        $repositoryScope = $requestInterface->getParsedBody()['repository_scope'];
        if(empty($repositoryScope)){
            $repositoryScope = 'all';
        }
        $u->setPref('core.notifications.last_read.' . $repositoryScope, time());
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @param \Pydio\Access\Core\Model\NodesList|null $nodesList
     */
    public function loadUserAlerts(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface, \Pydio\Access\Core\Model\NodesList &$nodesList = null)
    {
        if(!$this->eventStore) return;
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $u = $ctx->getUser();
        $repositoryFilter = null;
        $httpVars = $requestInterface->getParsedBody();

        if (isSet($httpVars["repository_id"]) && $u->getMergedRole()->canRead($httpVars["repository_id"])) {
            $repositoryFilter = $httpVars["repository_id"];
        }
        if ($repositoryFilter === null && isSet($httpVars['current_repository']) && $httpVars['current_repository'] === 'true') {
            $repositoryFilter = $ctx->getRepositoryId();
        }
        $res = $this->eventStore->loadAlerts($u, $repositoryFilter);
        if(!count($res)) return;

        // Load last read status
        $lastReadForListing = intval($u->getPref('core.notifications.last_read.all'));
        if($repositoryFilter !== null){
            $lastReadForRepo = intval($u->getPref('core.notifications.last_read.' . $repositoryFilter));
            $lastReadForListing = max($lastReadForListing, $lastReadForRepo);
        }
        $unReadCount = 0;

        // Recompute children notifs


        $format = $httpVars["format"];
        $fillList = ($nodesList !== null);
        $mess = LocaleService::getMessages();
        if (!$fillList) {
            if ($format == "html") {
                $responseInterface = $responseInterface->withHeader("Content-Type", "text/html");
                $responseInterface->getBody()->write("<h2>".$mess["notification_center.3"]."</h2>");
                $responseInterface->getBody()->write("<ul class='notification_list'>");
            } else {
                $nodesList = new \Pydio\Access\Core\Model\NodesList();
                $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
                $responseInterface = $responseInterface->withBody($x);
                $x->addChunk($nodesList);
            }
        }

        $cumulated = array();
        foreach ($res as $notification) {
            if ($format == "html") {
                $responseInterface->getBody()->write("<li>".$notification->getDescriptionLong(true)."</li>");
            } else {
                $node = $notification->getNode();
                if(!$node instanceof AJXP_Node){
                    error_log("Skipping notification as nodes are not excepted class, probably a deserialization issue");
                    if($notification->alert_id){
                        $this->eventStore->dismissAlertById($ctx, $notification->alert_id);
                    }
                    continue;
                }
                $path = $node->getPath();
                $nodeRepo = $node->getRepository();

                if($notification->getAction() !== "share" && $nodeRepo != null
                    && $nodeRepo->hasParent()){

                    $parentId = $nodeRepo->getParentId();
                    $parentRepository = RepositoryService::getRepositoryById($parentId);
                    $parentRoot = $parentRepository->getContextOption($ctx, "PATH");

                    $newCtx = $node->getContext();
                    $currentRoot = $nodeRepo->getContextOption($newCtx, "PATH");
                    $contentFilter = $nodeRepo->getContentFilter();
                    if(isSet($contentFilter)){
                        $nodePath = $contentFilter->filterExternalPath($node->getPath());
                        if(empty($nodePath) || $nodePath == "/"){
                            $k = array_keys($contentFilter->filters);
                            $nodePath = $k[0];
                        }
                    }else{
                        $nodePath = $node->getPath();
                    }
                    $relative = rtrim( substr($currentRoot, strlen($parentRoot)), "/"). rtrim($nodePath, "/");
                    $parentNodeURL = $node->getScheme()."://".$node->getUser()->getId()."@".$parentId.$relative;
                    $this->logDebug("action.share", "Recompute alert to ".$parentNodeURL);
                    $node = new AJXP_Node($parentNodeURL);
                    $path = $node->getPath();
                }else if($notification->getAction() === 'share' && !$node->isLeaf()){
                    $path = $node->getPath()."#".$node->getRepositoryId();
                }

                if (isSet($cumulated[$path])) {
                    $cumulated[$path]->event_occurence ++;
                    if($notification->getDate() > $lastReadForListing){
                        $unReadCount ++;
                    }
                    continue;
                }
                try {
                    @$node->loadNodeInfo();
                } catch (\Exception $e) {
                    if($notification->alert_id){
                        $this->eventStore->dismissAlertById($ctx, $notification->alert_id);
                    }
                    continue;
                }

                if($notification->getDate() > $lastReadForListing){
                    $unReadCount ++;
                }
                $node->event_is_alert = true;
                $node->event_description = ucfirst($notification->getDescriptionBlock()) . " ".$mess["notification.tpl.block.user_link"] ." ". $notification->getAuthorLabel();
                $node->event_description_long = strip_tags($notification->getDescriptionLong(true));
                $node->event_date = StatHelper::relativeDate($notification->getDate(), $mess);
                $node->event_type = "alert";
                $node->alert_id = $notification->alert_id;
                $node->alert_action = $notification->getAction();
                if ($node->getRepository() != null) {
                    $node->repository_id = ''.$node->getRepository()->getId();
                    if ($node->repository_id !== $repositoryFilter && $node->getRepository()->getDisplay() != null) {
                        $node->event_repository_label = "[".$node->getRepository()->getDisplay()."]";
                    }
                } else {
                    $node->event_repository_label = "[N/A]";
                }
                $node->event_author = $notification->getAuthor();
                $node->event_occurence = 1;
                $cumulated[$path] = $node;
            }
        }
        if ($format == "html") {
            $responseInterface->getBody()->write("</ul>");
            return;
        }
        $index = 1;
        foreach ($cumulated as $path => $nodeToSend) {
            $nodeOcc = $nodeToSend->event_occurence > 1 ? " (".$nodeToSend->event_occurence.")" : "";
            if(isSet($httpVars["merge_description"]) && $httpVars["merge_description"] == "true"){
                if(isSet($httpVars["description_as_label"]) && $httpVars["description_as_label"] == "true"){
                    $nodeToSend->setLabel($nodeToSend->event_description." ". $nodeOcc." ".$nodeToSend->event_date);
                }else{
                    $baseName = basename($nodeToSend->getPath());
                    if(empty($baseName) && $nodeToSend->getRepository() != null){
                        $baseName = $nodeToSend->getRepository()->getDisplay();
                    }
                    $nodeToSend->setLabel($baseName." ". $nodeOcc." "." <small class='notif_desc'>".$nodeToSend->event_description." ".$nodeToSend->event_date."</small>");
                }
            }else{
                $baseName = basename($nodeToSend->getPath());
                if(empty($baseName) && $nodeToSend->getRepository() != null){
                    $baseName = $nodeToSend->getRepository()->getDisplay();
                }
                $nodeToSend->setLabel($baseName . $nodeOcc);
            }
            // Replace PATH
            $nodeToSend->real_path = $path;
            if($nodeToSend->alert_action === 'share' && !$nodeToSend->isLeaf()){
                $url = parse_url($nodeToSend->getUrl());
                $nodeToSend->setUrl($url["scheme"]."://".$url["host"]."/alert_".$index);
            }
            $index ++;
            $nodesList->addBranch($nodeToSend);

        }
        $nodesList->getParentNode()->mergeMetadata(['unread_notifications_count' => $unReadCount]);

    }
    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldNode
     * @param \Pydio\Access\Core\Model\AJXP_Node $newNode
     * @param bool $copy
     * @param string $targetNotif
     * @return Notification
     */
    public function generateNotificationFromChangeHook(AJXP_Node $oldNode = null, AJXP_Node $newNode = null, $copy = false, $targetNotif = "new")
    {
        $type = "";
        $primaryNode = null;
        $secondNode = null;
        $notif = new Notification();
        if ($oldNode == null) {
            if($targetNotif == "old") return false;
            $type = AJXP_NOTIF_NODE_ADD;
            $primaryNode = $newNode;
        } else if ($newNode == null) {
            if($targetNotif == "new") return false;
            $type = AJXP_NOTIF_NODE_DEL;
            $primaryNode = $oldNode;
        } else {
            if ($oldNode->getUrl() == $newNode->getUrl()) {
                $type = AJXP_NOTIF_NODE_CHANGE;
                $primaryNode = $newNode;
            } else if (dirname($oldNode->getPath()) == dirname($newNode->getPath())) {
                $type = AJXP_NOTIF_NODE_RENAME;
                $primaryNode = $newNode;
                $secondNode = $oldNode;
            } else if ($targetNotif == "new") {
                $type = $copy ? AJXP_NOTIF_NODE_COPY_FROM : AJXP_NOTIF_NODE_MOVE_FROM;
                $primaryNode = $newNode;
                $secondNode = $oldNode;
            } else if ($targetNotif == "old") {
                if($oldNode->getRepositoryId() !== $newNode->getRepositoryId() && $copy){
                    return false;
                }
                $type = $copy ? AJXP_NOTIF_NODE_COPY_TO : AJXP_NOTIF_NODE_MOVE_TO;
                $primaryNode = $oldNode;
                $secondNode = $newNode;
            } else if ($targetNotif == "unify") {
                $type = $copy ? AJXP_NOTIF_NODE_COPY : AJXP_NOTIF_NODE_MOVE;
                $primaryNode = $newNode;
                $secondNode = $oldNode;
            }
        }
        $notif->setNode($primaryNode);
        $notif->setAction($type);
        if ($secondNode != null) {
            $notif->setSecondaryNode($secondNode);
        }

        return $notif;
    }

    /**
     * @param ContextInterface $context
     * @param UserInterface|string $userOrRole
     * @param RepositoryInterface $sharedRepository
     * @param AJXP_Node $originalNode
     */
    public function shareAssignRight($context, $userOrRole, $sharedRepository, $originalNode){

        $notification = new Notification();
        $notification->setAction("share");
        $notification->setAuthor($context->getUser()->getId());

        if($userOrRole instanceof UserInterface && !$userOrRole->isHidden()){

            $targetIdentifier = $userOrRole->getId();
            $notification->setTarget($targetIdentifier);
            if($originalNode->isLeaf()){
                $node = new AJXP_Node("pydio://".$targetIdentifier."@inbox/".$originalNode->getLabel());
                $node->setLeaf(true);
            }else{
                $node = new AJXP_Node("pydio://".$targetIdentifier."@".$sharedRepository->getId()."/");
                $node->setLeaf(false);
            }
            $notification->setNode($node);
            $this->eventStore->persistAlert($notification, true);
            Controller::applyHook("msg.instant",array(
                $context->withRepositoryId(null),
                "<reload_user_feed/>",
                $targetIdentifier
            ));

        }else if(is_string($userOrRole) && strpos($userOrRole, "AJXP_GRP_/") === 0){

            $groupPath = substr($userOrRole, strlen("AJXP_GRP_"));
            $notification->setTarget("*");
            if($originalNode->isLeaf()){
                $node = new AJXP_Node("pydio://*@inbox/".$originalNode->getLabel());
                $node->setLeaf(true);
            }else{
                $node = new AJXP_Node("pydio://*@".$sharedRepository->getId()."/");
                $node->setLeaf(false);
            }
            $notification->setNode($node);
            $this->eventStore->persistAlert($notification, true, $groupPath);
            Controller::applyHook("msg.instant",array(
                $context->withRepositoryId(null),
                "<reload_user_feed/>",
                null,
                $groupPath
            ));


        }

    }
    

    /**
     * @param Notification $notif
     */
    public function prepareNotification(Notification &$notif)
    {
        $notif->setAuthor($this->userId);
        if(!empty($this->userTemporaryLabel)){
            $notif->setAuthorTemporaryLabel($this->userTemporaryLabel);
        }
        $notif->setDate(time());

    }
    /**
     * @param Notification $notif
     * @param string $targetId
     */
    public function postNotification(Notification $notif, $targetId)
    {
        $this->prepareNotification($notif);
        $notif->setTarget($targetId);
        //$this->sendToQueue($notif);
        Controller::applyHook("msg.queue_notification", array($notif));

    }


}
