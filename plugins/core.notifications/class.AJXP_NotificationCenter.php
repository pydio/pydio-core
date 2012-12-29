<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Notification dispatcher
 */
class AJXP_NotificationCenter extends AJXP_Plugin
{
    /**
     * @var AJXP_NotificationCenter
     */
    static private $instance;
    private $userId;
    private $useQueue = false ;
    /**
     * @var AJXP_FeedStore|bool
     */
    private $eventStore = false;
    /**
     * @var bool|AJXP_MessageExchanger
     */
    private $msgExchanger = false;

    public function init($options){
        parent::init($options);
        $this->userId = AuthService::getLoggedUser() !== null ? AuthService::getLoggedUser()->getId() : "shared";
        $this->useQueue = $this->pluginConf["USE_QUEUE"];
        $eStore = ConfService::getInstance()->getUniquePluginImplInst("FEED_DRIVER", "feed");
        if(is_a($eStore, "AJXP_FeedStore")){
            $this->eventStore = $eStore;
        }
        $msgBroker = ConfService::getInstance()->getUniquePluginImplInst("MQ_DRIVER", "mq");
        if(is_a($msgBroker, "AJXP_MessageExchanger")){
            $this->msgExchanger = $msgBroker;
        }

    }

    public function persistChangeHookToFeed(AJXP_Node $oldNode = null, AJXP_Node $newNode = null, $copy = false, $targetNotif = "new"){
        if(!$this->eventStore) return;

        $n = ($oldNode == null ? $newNode : $oldNode);
        $repoId = $n->getRepositoryId();
        $userId = AuthService::getLoggedUser()->getId();
        $userGroup = AuthService::getLoggedUser()->getGroupPath();
        $repository = ConfService::getRepositoryById($repoId);
        $repositoryScope = $repository->securityScope();
        $repositoryScope = ($repositoryScope !== false ? $repositoryScope : "ALL");

        $this->eventStore->persistEvent("node.change", func_get_args(), $repoId, $repositoryScope, $userId, $userGroup);

    }

    public function loadUserFeed($actionName, $httpVars, $fileVars){

        if(!$this->eventStore) return;
        $u = AuthService::getLoggedUser();
        $userId = $u->getId();
        $userGroup = $u->getGroupPath();
        $authRepos = array();
        if(isSet($httpVars["repository_id"]) && $u->mergedRole->canRead($httpVars["repository_id"])){
            $authRepos[] = $httpVars["repository_id"];
        }else{
            $acls = AuthService::getLoggedUser()->mergedRole->listAcls();
            foreach($acls as $repoId => $rightString){
                if($rightString == "r" | $rightString == "rw") $authRepos[] = $repoId;
            }
        }
        $res = $this->eventStore->loadEvents($authRepos, $userId, $userGroup, 0, 10);

        echo("<ul>");
        foreach($res as $n => $object){
            $args = $object->arguments;
            $oldNode = (isSet($args[0]) ? $args[0] : null);
            $newNode = (isSet($args[1]) ? $args[1] : null);
            $copy = (isSet($args[2]) && $args[2] === true ? true : null);
            $notif = $this->generateNotificationFromChangeHook($oldNode, $newNode, $copy, "unify");
            if($notif !== false && $notif->getNode() !== false){
                $notif->setAuthor($object->author);
                $notif->setDate(intval($object->date));
                echo("<li>");
                echo($notif->getDescriptionLong(true));
                echo("</li>");
            }
        }
        echo("</ul>");

    }

    /**
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param bool $copy
     * @param string $targetNotif
     * @return AJXP_Notification
     */
    public function generateNotificationFromChangeHook(AJXP_Node $oldNode = null, AJXP_Node $newNode = null, $copy = false, $targetNotif = "new"){
        $type = "";
        $primaryNode = null;
        $secondNode = null;
        $notif = new AJXP_Notification();
        if($oldNode == null){
            if($targetNotif == "old") return false;
            $type = AJXP_NOTIF_NODE_ADD;
            $primaryNode = $newNode;
        }else if($newNode == null){
            if($targetNotif == "new") return false;
            $type = AJXP_NOTIF_NODE_DEL;
            $primaryNode = $oldNode;
        }else{
            if($oldNode->getUrl() == $newNode->getUrl()){
                $type = AJXP_NOTIF_NODE_CHANGE;
                $primaryNode = $newNode;
            }else if(dirname($oldNode->getPath()) == dirname($newNode->getPath())){
                $type = AJXP_NOTIF_NODE_RENAME;
                $primaryNode = $newNode;
                $secondNode = $oldNode;
            }else if($targetNotif == "new"){
                $type = $copy ? AJXP_NOTIF_NODE_COPY_FROM : AJXP_NOTIF_NODE_MOVE_FROM;
                $primaryNode = $newNode;
                $secondNode = $oldNode;
            }else if($targetNotif == "old"){
                $type = $copy ? AJXP_NOTIF_NODE_COPY_TO : AJXP_NOTIF_NODE_MOVE_TO;
                $primaryNode = $oldNode;
                $secondNode = $newNode;
            }else if($targetNotif == "unify"){
                $type = $copy ? AJXP_NOTIF_NODE_COPY : AJXP_NOTIF_NODE_MOVE;
                $primaryNode = $newNode;
                $secondNode = $oldNode;
            }
        }
        $notif->setNode($primaryNode);
        $notif->setAction($type);
        if($secondNode != null){
            $notif->setSecondaryNode($secondNode);
        }

        return $notif;
    }

    public function consumeQueue($action, $httpVars, $fileVars){
        if($action != "consume_notification_queue" || $this->msgExchanger === false) return;
        $queueObjects = $this->msgExchanger->consumeWorkerChannel("user_notifications");
        if(is_array($queueObjects)){
            AJXP_Logger::debug("Processing notification queue, ".count($queueObjects)." notifs to handle");
            foreach($queueObjects as $notification){
                $this->dispatch($notification);
            }
        }
    }


    public function prepareNotification(AJXP_Notification &$notif){

        $notif->setAuthor($this->userId);
        $notif->setDate(time());

    }
    /**
     * @param AJXP_Notification $notif
     * @param string $targetId
     */
    public function postNotification(AJXP_Notification $notif, $targetId){

        $this->prepareNotification($notif);
        $notif->setTarget($targetId);
        $this->sendToQueue($notif);

    }

    protected function sendToQueue(AJXP_Notification $notification){
        if(!$this->useQueue && $this->msgExchanger){
            AJXP_Logger::debug("SHOULD DISPATCH NOTIFICATION ON ".$notification->getNode()->getUrl()." ACTION ".$notification->getAction());
            $this->dispatch($notification);
        }else{
            $this->msgExchanger->publishWorkerMessage("user_notifications", $notification);
        }
    }

    public function dispatch(AJXP_Notification $notification){
        AJXP_Controller::applyHook("msg.notification", array(&$notification));
        return;
    }

}
