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
    private $useQueue = true ;

    public function init($options){
        parent::init($options);
        $this->userId = AuthService::getLoggedUser() !== null ? AuthService::getLoggedUser()->getId() : "shared";
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
        if($oldNode == null){
            if($targetNotif == "old") return false;
            $type = AJXP_NOTIF_NODE_ADD;
            $primaryNode = $newNode;
        }else if($newNode == null){
            if($targetNotif == "new") return false;
            $type = AJXP_NOTIF_NODE_DEL;
            $primaryNode = $oldNode;
        }else{
            if($targetNotif == "new"){
                $type = $copy ? AJXP_NOTIF_NODE_COPY_FROM : AJXP_NOTIF_NODE_MOVE_FROM;
                $primaryNode = $newNode;
                $secondNode = $oldNode;
            }else if($targetNotif == "old"){
                $type = $copy ? AJXP_NOTIF_NODE_COPY_TO : AJXP_NOTIF_NODE_MOVE_TO;
                $primaryNode = $oldNode;
                $secondNode = $newNode;
            }
        }
        $notif = new AJXP_Notification();
        $notif->setNode($primaryNode);
        $notif->setAction($type);
        if($secondNode != null){
            $notif->setSecondaryNode($secondNode);
        }

        return $notif;
    }

    public function consumeQueue($action, $httpVars, $fileVars){
        if($action != "consume_notification_queue") return;
        $queueObjects = ConfService::getConfStorageImpl()->consumeQueue("user_notifications");
        AJXP_Logger::debug("Processing notification queue, ".count($queueObjects)." notifs to handle");
        if(is_array($queueObjects)){
            foreach($queueObjects as $notification){
                $this->dispatch($notification);
            }
        }
    }

    /**
     * @param AJXP_Notification $notif
     * @param string $targetId
     */
    public function postNotification(AJXP_Notification $notif, $targetId){

        if($this->userId == $targetId) {
            // Do not auto-notify my self :-)
            // return;
        }
        $notif->setAuthor($this->userId);
        $notif->setDate(time());
        $notif->setTarget($targetId);

        $this->sendToQueue($notif);

    }

    protected function sendToQueue(AJXP_Notification $notification){
        if(!$this->useQueue){
            AJXP_Logger::debug("SHOULD DISPATCH NOTIFICATION ON ".$notification->getNode()->getUrl()." ACTION ".$notification->getAction());
            $this->dispatch($notification);
        }else{
            ConfService::getConfStorageImpl()->storeObjectToQueue("user_notifications", $notification);
        }
    }

    public function dispatch(AJXP_Notification $notification){
        $mailers = AJXP_PluginsService::getInstance()->getPluginsByType("mailer");
        $mailer = new AjxpMailer("id", "basedir");
        if(count($mailers)){
            $mailer = array_pop($mailers);
            try{
                $mailer->sendMail(
                    array($notification->getTarget()),
                    $notification->getDescriptionShort(),
                    $notification->getDescriptionLong(),
                    $notification->getAuthor()
                );
            }catch (Exception $e){
                AJXP_Logger::logAction("ERROR : ".$e->getMessage());
            }
        }
    }

}
