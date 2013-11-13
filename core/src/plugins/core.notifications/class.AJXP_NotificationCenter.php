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
 * Notification dispatcher
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
class AJXP_NotificationCenter extends AJXP_Plugin
{
    /**
     * @var AJXP_NotificationCenter
     */
    private static $instance;
    private $userId;
    /**
     * @var AJXP_FeedStore|bool
     */
    private $eventStore = false;
    /**
     * @var bool|AJXP_MessageExchanger
     */
    private $msgExchanger = false;

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
        }
    }

    protected function parseSpecificContributions(&$contribNode)
    {
           parent::parseSpecificContributions($contribNode);
           if($contribNode->nodeName != "actions") return;

           // WEBDAV ACTION
           if (empty($this->pluginConf["USER_EVENTS"])) {
               unset($this->actions["get_my_feed"]);
               $actionXpath=new DOMXPath($contribNode->ownerDocument);
               $publicUrlNodeList = $actionXpath->query('action[@name="get_my_feed"]', $contribNode);
               $publicUrlNode = $publicUrlNodeList->item(0);
               $contribNode->removeChild($publicUrlNode);
           }
    }

    public function persistNotificationToAlerts(AJXP_Notification &$notification)
    {
        if ($this->eventStore) {
            AJXP_Controller::applyHook("msg.instant",array(
                "<reload_user_feed/>",
                AJXP_REPO_SCOPE_ALL,
                $notification->getTarget()
            ));
            $this->eventStore->persistAlert($notification);
        }
    }


    public function persistChangeHookToFeed(AJXP_Node $oldNode = null, AJXP_Node $newNode = null, $copy = false, $targetNotif = "new")
    {
        if(!$this->eventStore) return;

        $n = ($oldNode == null ? $newNode : $oldNode);
        $repoId = $n->getRepositoryId();
        $userId = AuthService::getLoggedUser()->getId();
        $userGroup = AuthService::getLoggedUser()->getGroupPath();
        $repository = ConfService::getRepositoryById($repoId);
        $repositoryScope = $repository->securityScope();
        $repositoryScope = ($repositoryScope !== false ? $repositoryScope : "ALL");
        $repositoryOwner = $repository->hasOwner() ? $repository->getOwner() : null;

        $this->eventStore->persistEvent("node.change", func_get_args(), $repoId, $repositoryScope, $repositoryOwner, $userId, $userGroup);

    }

    public function loadUserFeed($actionName, $httpVars, $fileVars)
    {
        if(!$this->eventStore) return;
        $u = AuthService::getLoggedUser();
        if ($u == null) {
            if($httpVars["format"] == "html") return;
            AJXP_XMLWriter::header();
            AJXP_XMLWriter::close();
            return;
        }
        $userId = $u->getId();
        $userGroup = $u->getGroupPath();
        $authRepos = array();
        $crtRepId = ConfService::getCurrentRepositoryId();
        if (isSet($httpVars["repository_id"]) && $u->mergedRole->canRead($httpVars["repository_id"])) {
            $authRepos[] = $httpVars["repository_id"];
        } else {
            $acls = AuthService::getLoggedUser()->mergedRole->listAcls();
            foreach ($acls as $repoId => $rightString) {
                if($rightString == "r" | $rightString == "rw") $authRepos[] = $repoId;
            }
        }
        $offset = isSet($httpVars["offset"]) ? intval($httpVars["offset"]): 0;
        $limit = isSet($httpVars["limit"]) ? intval($httpVars["limit"]): 15;
        $res = $this->eventStore->loadEvents($authRepos, $userId, $userGroup, $offset, $limit, (isSet($httpVars["repository_id"])?false:true));
        $mess = ConfService::getMessages();
        $format = "html";
        if (isSet($httpVars["format"])) {
            $format = $httpVars["format"];
        }
        if ($format == "html") {
            echo("<h2>".$mess["notification_center.4"]."</h2>");
            echo("<ul class='notification_list'>");
        } else {
            AJXP_XMLWriter::header();
        }

        // APPEND USER ALERT IN THE SAME QUERY FOR NOW
        $this->loadUserAlerts("", array_merge($httpVars, array("skip_container_tags" => "true")), $fileVars);
        restore_error_handler();
        $index = 1;
        foreach ($res as $n => $object) {
            $args = $object->arguments;
            $oldNode = (isSet($args[0]) ? $args[0] : null);
            $newNode = (isSet($args[1]) ? $args[1] : null);
            $copy = (isSet($args[2]) && $args[2] === true ? true : null);
            $notif = $this->generateNotificationFromChangeHook($oldNode, $newNode, $copy, "unify");
            if ($notif !== false && $notif->getNode() !== false) {
                $notif->setAuthor($object->author);
                $notif->setDate(intval($object->date));
                if ($format == "html") {
                    $p = $notif->getNode()->getPath();
                    echo("<li data-ajxpNode='$p'>");
                    echo($notif->getDescriptionShort(true));
                    echo("</li>");
                } else {
                    $node = $notif->getNode();
                    if ($node == null) {
                        $this->logInfo("Warning", "Empty node stored in notification ".$notif->getAuthor()."/ ".$notif->getAction());
                        continue;
                    }
                    try {
                        $node->loadNodeInfo();
                    } catch (Exception $e) {
                        continue;
                    }
                    $node->event_description = ucfirst($notif->getDescriptionBlock()) . " ".$mess["notification.tpl.block.user_link"] ." ". $notif->getAuthor();
                    $node->event_description_long = $notif->getDescriptionLong(true);
                    $node->event_date = AJXP_Utils::relativeDate($notif->getDate(), $mess);
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
                    $url = parse_url($node->getUrl());
                    $node->setUrl($url["scheme"]."://".$url["host"]."/notification_".$index);
                    $index ++;

                    AJXP_XMLWriter::renderAjxpNode($node);
                }
            }
        }
        if ($format == "html") {
            echo("</ul>");
        } else {
            AJXP_XMLWriter::close();
        }

    }


    public function dismissUserAlert($actionName, $httpVars, $fileVars)
    {
        if(!$this->eventStore) return;
        $alertId = $httpVars["alert_id"];
        $oc = 1;
        if(isSet($httpVars["occurrences"])) $oc = intval($httpVars["occurrences"]);
        $this->eventStore->dismissAlertById($alertId, $oc);
    }


    public function loadUserAlerts($actionName, $httpVars, $fileVars)
    {
        if(!$this->eventStore) return;
        $u = AuthService::getLoggedUser();
        $userId = $u->getId();
        $repositoryFilter = null;
        if (isSet($httpVars["repository_id"]) && $u->mergedRole->canRead($httpVars["repository_id"])) {
            $repositoryFilter = $httpVars["repository_id"];
        }
        $res = $this->eventStore->loadAlerts($userId, $repositoryFilter);
        if ($repositoryFilter == null) {
            $repositoryFilter = ConfService::getRepository()->getId();
        }
        if(!count($res)) return;

        $format = $httpVars["format"];
        $skipContainingTags = (isSet($httpVars["skip_container_tags"]));
        $mess = ConfService::getMessages();
        if (!$skipContainingTags) {
            if ($format == "html") {
                echo("<h2>".$mess["notification_center.3"]."</h2>");
                echo("<ul class='notification_list'>");
            } else {
                AJXP_XMLWriter::header();
            }
        }
        $cumulated = array();
        foreach ($res as $notification) {
            if ($format == "html") {
                echo("<li>");
                echo($notification->getDescriptionLong(true));
                echo("</li>");
            } else {
                $node = $notification->getNode();
                $path = $node->getPath();

                if (isSet($cumulated[$path])) {
                    $cumulated[$path]->event_occurence ++;
                    continue;
                }
                try {
                    $node->loadNodeInfo();
                } catch (Exception $e) {
                    continue;
                }
                $node->event_is_alert = true;
                $node->event_description = ucfirst($notification->getDescriptionBlock()) . " ".$mess["notification.tpl.block.user_link"] ." ". $notification->getAuthor();
                $node->event_description_long = $notification->getDescriptionLong(true);
                $node->event_date = AJXP_Utils::relativeDate($notification->getDate(), $mess);
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
        $index = 1;
        foreach ($cumulated as $nodeToSend) {
            if ($nodeToSend->event_occurence > 1) {
                $nodeToSend->setLabel(basename($nodeToSend->getPath()) . " (". $nodeToSend->event_occurence .")" );
            } else {
                $nodeToSend->setLabel(basename($nodeToSend->getPath()));
            }
            // Replace PATH
            $nodeToSend->real_path = $path;
            $url = parse_url($nodeToSend->getUrl());
            $nodeToSend->setUrl($url["scheme"]."://".$url["host"]."/alert_".$index);
            $index ++;
            AJXP_XMLWriter::renderAjxpNode($nodeToSend);

        }
        if (!$skipContainingTags) {
            if ($format == "html") {
                echo("</ul>");
            } else {
                AJXP_XMLWriter::close();
            }
        }

    }
    /**
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
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
        AJXP_Controller::applyHook("msg.queue_notification", array($notif));

    }


}
