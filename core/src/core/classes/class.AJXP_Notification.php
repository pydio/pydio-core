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
define('AJXP_NOTIF_NODE_COPY_FROM', "copy_to");
define('AJXP_NOTIF_NODE_MOVE_FROM', "move_to");
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
     * @var AJXP_Notification
     */
    var $relatedNotification;

    public static function autoload(){

    }

    /**
     * @return string
     */
    public function getDescriptionShort(){
        return "This is a short description, should be template based!";
    }

    /**
     * @return string
     */
    public function getDescriptionLong(){
        return "this is a long description, also template based";
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
     * @param \AJXP_Notification $relatedNotification
     */
    public function setRelatedNotification($relatedNotification)
    {
        $this->relatedNotification = $relatedNotification;
    }

    /**
     * @return \AJXP_Notification
     */
    public function getRelatedNotification()
    {
        return $this->relatedNotification;
    }
}
