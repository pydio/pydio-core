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

define('AJXP_NOTIF_NODE_ADD', "add");
define('AJXP_NOTIF_NODE_DEL', "delete");
define('AJXP_NOTIF_NODE_CHANGE', "change");
define('AJXP_NOTIF_NODE_VIEW', "view");
define('AJXP_NOTIF_NODE_COPY_TO', "copy_to");
define('AJXP_NOTIF_NODE_MOVE_TO', "move_to");
define('AJXP_NOTIF_NODE_COPY_FROM', "copy_from");
define('AJXP_NOTIF_NODE_MOVE_FROM', "move_from");
/**
 * Simple properties container
 */
class AJXP_Notification
{

    /**
     * @var AJXP_Node
     */
    var $node;
    /**
     * @var String
     */
    var $action;
    /**
     * @var String
     */
    var $author;
    /**
     * @var int
     */
    var $date;
    /**
     * @var string;
     */
    var $target;

    /**
     * @var AJXP_Node
     */
    var $secondaryNode;

    /**
     * @var AJXP_Notification[]
     */
    var $relatedNotifications;

    public static function autoload(){

    }

    protected function replaceVars($tplString, $mess){
        $repoId = $this->getNode()->getRepositoryId();
        $repoLabel = ConfService::getRepositoryById($repoId)->getDisplay();
        $replaces = array(
            "AJXP_NODE_PATH"        => $this->getNode()->getPath(),
            "AJXP_NODE_LABEL"       => $this->getNode()->getLabel(),
            "AJXP_PARENT_PATH"      => dirname($this->getNode()->getPath()),
            "AJXP_PARENT_LABEL"     => basename(dirname($this->getNode()->getPath())),
            "AJXP_REPOSITORY_ID"    => $repoId,
            "AJXP_REPOSITORY_LABEL" => $repoLabel,
            "AJXP_LINK"             => AJXP_Utils::detectServerURL(true)."/?repository_id=$repoId&folder=".$this->node->getPath(),
            "AJXP_USER"             => $this->getTarget(),
            "AJXP_DATE"             => date($mess["date_format"], $this->getDate()),
        );
        return str_replace(array_keys($replaces), array_values($replaces), $tplString);
    }

    /**
     * @return string
     */
    public function getDescriptionShort(){
        $mess = ConfService::getMessages();
        $tpl = $mess["notification.tpl.short.".($this->getNode()->isLeaf()?"file":"folder").".".$this->action];
        return $this->replaceVars($tpl, $mess);
    }


    /**
     * @return string
     */
    public function getDescriptionLong(){
        $mess = ConfService::getMessages();

        if(count($this->relatedNotifications)){
            $key = "notification.tpl.group.".($this->getNode()->isLeaf()?"file":"folder").".".$this->action;
            $tpl = $this->replaceVars($mess[$key], $mess).": ";
            $tpl .= "<ul>";
            foreach($this->relatedNotifications as $relatedNotification){
                $tpl .= "<li>".$relatedNotification->getDescriptionLong()."</li>";
            }
            $tpl .= "</ul>";
        }else{
            $tpl = $this->replaceVars($mess["notification.tpl.long.".($this->getNode()->isLeaf()?"file":"folder").".".$this->action], $mess);
        }
        $tpl .= "<br><br>".$this->replaceVars($mess["notification.tpl.long.ajxp_link"], $mess);
        return $tpl;
    }

    public function setAction($action)
    {
        $this->action = $action;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAuthor($author)
    {
        $this->author = $author;
    }

    public function getAuthor()
    {
        return $this->author;
    }

    public function setDate($date)
    {
        $this->date = $date;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setNode($node)
    {
        $this->node = $node;
    }

    public function getNode()
    {
        return $this->node;
    }

    public function setSecondaryNode($secondaryNode)
    {
        $this->secondaryNode = $secondaryNode;
    }

    public function getSecondaryNode()
    {
        return $this->secondaryNode;
    }

    /**
     * @param string $target
     */
    public function setTarget($target)
    {
        $this->target = $target;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param AJXP_Notification $relatedNotification
     */
    public function addRelatedNotification($relatedNotification)
    {
        $this->relatedNotifications[] = $relatedNotification;
    }

    /**
     * @return AJXP_Notification
     */
    public function getRelatedNotifications()
    {
        return $this->relatedNotification;
    }
}
