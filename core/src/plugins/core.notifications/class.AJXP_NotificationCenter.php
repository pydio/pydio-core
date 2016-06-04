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
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Utils\Utils;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Utils\TextEncoder;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Notification dispatcher
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
class AJXP_NotificationCenter extends Plugin
{
    /**
     * @var String
     */
    private $userId;
    /**
     * @var AJXP_FeedStore|bool
     */
    private $eventStore = false;

    public function init($options)
    {
        parent::init($options);
        $this->userId = AuthService::getLoggedUser() !== null ? AuthService::getLoggedUser()->getId() : "shared";
        try {
            $this->eventStore = ConfService::instanciatePluginFromGlobalParams($this->pluginConf["UNIQUE_FEED_INSTANCE"], "AJXP_FeedStore");
        } catch (Exception $e) {

        }
        if ($this->eventStore === false) {
            $this->pluginConf["USER_EVENTS"] = false;
        }else{
            \Pydio\Core\PluginFramework\PluginsService::getInstance()->setPluginActive($this->eventStore->getType(), $this->eventStore->getName(), true, $this->eventStore);
        }
    }

    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
           parent::parseSpecificContributions($ctx, $contribNode);

           // DISABLE STUFF
           if (empty($this->pluginConf["USER_EVENTS"])) {
               if($contribNode->nodeName == "actions"){
                   $actionXpath=new DOMXPath($contribNode->ownerDocument);
                   $publicUrlNodeList = $actionXpath->query('action[@name="get_my_feed"]', $contribNode);
                   $publicUrlNode = $publicUrlNodeList->item(0);
                   $contribNode->removeChild($publicUrlNode);
               }else if($contribNode->nodeName == "client_configs"){
                   $actionXpath=new DOMXPath($contribNode->ownerDocument);
                   $children = $actionXpath->query('component_config', $contribNode);
                   foreach($children as $child){
                       $contribNode->removeChild($child);
                   }
               }
           }
    }

    public function persistNotificationToAlerts(AJXP_Notification &$notification)
    {
        if ($this->eventStore) {
            $this->eventStore->persistAlert($notification);
            Controller::applyHook("msg.instant",array(
                "<reload_user_feed/>",
                $notification->getNode()->getRepositoryId(),
                $notification->getTarget()
            ));
            if($notification->getNode()->getRepository() != null && $notification->getNode()->getRepository()->hasParent()){
                Controller::applyHook("msg.instant",array(
                    "<reload_user_feed/>",
                    $notification->getNode()->getRepository()->getParentId(),
                    $notification->getTarget()
                ));
            }
        }
    }


    public function persistChangeHookToFeed(AJXP_Node $oldNode = null, AJXP_Node $newNode = null, $copy = false, $targetNotif = "new")
    {
        if(!$this->eventStore) return;

        $n = ($oldNode == null ? $newNode : $oldNode);
        $repoId = $n->getRepositoryId();
        if($n->getUser()){
            $userId = $n->getUser();
            $obj = ConfService::getConfStorageImpl()->createUserObject($userId);
            if($obj) $userGroup = $obj->getGroupPath();
        }else{
            $userId = AuthService::getLoggedUser()->getId();
            $userGroup = AuthService::getLoggedUser()->getGroupPath();
        }
        $repository = ConfService::getRepositoryById($repoId);
        $repositoryScope = $repository->securityScope();
        $repositoryScope = ($repositoryScope !== false ? $repositoryScope : "ALL");
        $repositoryOwner = $repository->hasOwner() ? $repository->getOwner() : null;
        Controller::applyHook("msg.instant",array(
            "<reload_user_feed/>",
            $repoId,
            $userId
        ));
        $this->eventStore->persistEvent("node.change", func_get_args(), $repoId, $repositoryScope, $repositoryOwner, $userId, $userGroup);

    }

    public function loadRepositoryInfo(&$data){
        $body = [
            'format' => 'array',
            'current_repository'=>true,
            'feed_type'=>'notif',
            'limit' => 1,
            'path'=>'/',
            'merge_description'=>true,
            'description_as_label'=>false
        ];
        $req = Controller::executableRequest(Context::fromGlobalServices(), "get_my_feed", $body);
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
        $httpVars = $requestInterface->getParsedBody();
        if(!$this->eventStore) {
            throw new \Pydio\Core\Exception\PydioException("Cannot find eventStore for notification plugin");
        }
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $u = $ctx->getUser();

        $mess = ConfService::getMessages();
        $nodesList = new \Pydio\Access\Core\Model\NodesList();
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
        } else if (isSet($httpVars["current_repository"])){
            $authRepos[] = $crtRepId;
        } else {
            $accessibleRepos = ConfService::getAccessibleRepositories($u, false, true, false);
            $authRepos = array_keys($accessibleRepos);
        }
        $offset = isSet($httpVars["offset"]) ? intval($httpVars["offset"]): 0;
        $limit = isSet($httpVars["limit"]) ? intval($httpVars["limit"]): 15;
        if(!isSet($httpVars["feed_type"]) || $httpVars["feed_type"] == "notif" || $httpVars["feed_type"] == "all"){
            $res = $this->eventStore->loadEvents($authRepos, isSet($httpVars["path"])?$httpVars["path"]:"", $userGroup, $offset, $limit, false, $userId);
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
                    try {
                        @$node->loadNodeInfo();
                    } catch (Exception $e) {
                        continue;
                    }
                    $node->event_description = ucfirst($notif->getDescriptionBlock()) . " ".$mess["notification.tpl.block.user_link"] ." ". $notif->getAuthorLabel();
                    $node->event_description = TextEncoder::fromUTF8($node->event_description);
                    $node->event_description_long = $notif->getDescriptionLong(true);
                    $node->event_date = TextEncoder::fromUTF8(Utils::relativeDate($notif->getDate(), $mess));
                    $node->short_date = Utils::relativeDate($notif->getDate(), $mess, true);
                    $node->event_time = $notif->getDate();
                    $node->event_type = "notification";
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
                            $node->setLabel(basename($node->getPath())." <small class='notif_desc'>".$node->event_description." ".$node->event_date."</small>");
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


    public function loadUserAlerts(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface, \Pydio\Access\Core\Model\NodesList &$nodesList = null)
    {
        if(!$this->eventStore) return;
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $u = $ctx->getUser();
        $userId = $u->getId();
        $repositoryFilter = null;
        $httpVars = $requestInterface->getParsedBody();

        if (isSet($httpVars["repository_id"]) && $u->getMergedRole()->canRead($httpVars["repository_id"])) {
            $repositoryFilter = $httpVars["repository_id"];
        }
        if ($repositoryFilter == null) {
            $repositoryFilter = $ctx->getRepositoryId();
        }
        $res = $this->eventStore->loadAlerts($userId, $repositoryFilter);
        if(!count($res)) return;

        // Recompute children notifs


        $format = $httpVars["format"];
        $fillList = ($nodesList !== null);
        $mess = ConfService::getMessages();
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
        $parentRepository = ConfService::getRepositoryById($repositoryFilter);
        $parentRoot = $parentRepository->getOption("PATH");
        $cumulated = array();
        foreach ($res as $notification) {
            if ($format == "html") {
                $responseInterface->getBody()->write("<li>".$notification->getDescriptionLong(true)."</li>");
            } else {
                $node = $notification->getNode();
                if(!$node instanceof AJXP_Node){
                    error_log("Skipping notification as nodes are not excepted class, probably a deserialization issue");
                    continue;
                }
                $path = $node->getPath();
                $nodeRepo = $node->getRepository();

                if($nodeRepo != null && $nodeRepo->hasParent() && $nodeRepo->getParentId() == $repositoryFilter){
                    $currentRoot = $nodeRepo->getOption("PATH");
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
                    $parentNodeURL = $node->getScheme()."://".$repositoryFilter.$relative;
                    $this->logDebug("action.share", "Recompute alert to ".$parentNodeURL);
                    $node = new AJXP_Node($parentNodeURL);
                    $path = $node->getPath();
                }


                if (isSet($cumulated[$path])) {
                    $cumulated[$path]->event_occurence ++;
                    continue;
                }
                try {
                    @$node->loadNodeInfo();
                } catch (Exception $e) {
                    if($notification->alert_id){
                        $this->eventStore->dismissAlertById($ctx, $notification->alert_id);
                    }
                    continue;
                }
                $node->event_is_alert = true;
                $node->event_description = ucfirst($notification->getDescriptionBlock()) . " ".$mess["notification.tpl.block.user_link"] ." ". $notification->getAuthorLabel();
                $node->event_description = TextEncoder::fromUTF8($node->event_description);
                $node->event_description_long = $notification->getDescriptionLong(true);
                $node->event_date = TextEncoder::fromUTF8(Utils::relativeDate($notification->getDate(), $mess));
                $node->event_type = "alert";
                $node->alert_id = $notification->alert_id;
                if ($node->getRepository() != null) {
                    $node->repository_id = ''.$node->getRepository()->getId();
                    if ($node->repository_id != $repositoryFilter && $node->getRepository()->getDisplay() != null) {
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
            //$url = parse_url($nodeToSend->getUrl());
            //$nodeToSend->setUrl($url["scheme"]."://".$url["host"]."/alert_".$index);
            $index ++;
            $nodesList->addBranch($nodeToSend);

        }


    }
    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldNode
     * @param \Pydio\Access\Core\Model\AJXP_Node $newNode
     * @param bool $copy
     * @param string $targetNotif
     * @return AJXP_Notification
     */
    public function generateNotificationFromChangeHook(AJXP_Node $oldNode = null, AJXP_Node $newNode = null, $copy = false, $targetNotif = "new")
    {
        $type = "";
        $primaryNode = null;
        $secondNode = null;
        $notif = new AJXP_Notification();
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


    public function prepareNotification(AJXP_Notification &$notif)
    {
        $notif->setAuthor($this->userId);
        $notif->setDate(time());

    }
    /**
     * @param AJXP_Notification $notif
     * @param string $targetId
     */
    public function postNotification(AJXP_Notification $notif, $targetId)
    {
        $this->prepareNotification($notif);
        $notif->setTarget($targetId);
        //$this->sendToQueue($notif);
        Controller::applyHook("msg.queue_notification", array($notif));

    }


}
