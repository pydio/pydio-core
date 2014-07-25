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

define('AJXP_NOTIF_NODE_ADD', "add");
define('AJXP_NOTIF_NODE_DEL', "delete");
define('AJXP_NOTIF_NODE_CHANGE', "change");
define('AJXP_NOTIF_NODE_RENAME', "rename");
define('AJXP_NOTIF_NODE_VIEW', "view");
define('AJXP_NOTIF_NODE_COPY', "copy");
define('AJXP_NOTIF_NODE_MOVE', "move");
define('AJXP_NOTIF_NODE_COPY_TO', "copy_to");
define('AJXP_NOTIF_NODE_MOVE_TO', "move_to");
define('AJXP_NOTIF_NODE_COPY_FROM', "copy_from");
define('AJXP_NOTIF_NODE_MOVE_FROM', "move_from");
/**
 * Simple properties container
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
class AJXP_Notification
{

    /**
     * @var AJXP_Node
     */
    public $node;
    /**
     * @var String
     */
    public $action;
    /**
     * @var String
     */
    public $author;
    /**
     * @var int
     */
    public $date;
    /**
     * @var string;
     */
    public $target;

    /**
     * @var AJXP_Node
     */
    public $secondaryNode;

    /**
     * @var AJXP_Notification[]
     */
    public $relatedNotifications;

    public static $usersCaches = array();

    public static function autoload()
    {
    }

    protected function getRoot($string)
    {
        if(empty($string)) return "/";
        return $string;
    }

    protected function replaceVars($tplString, $mess, $rich = true)
    {
        $tplString = SystemTextEncoding::fromUTF8($tplString);
        $repoId = $this->getNode()->getRepositoryId();
        if (ConfService::getRepositoryById($repoId) != null) {
            $repoLabel = ConfService::getRepositoryById($repoId)->getDisplay();
        } else {
            $repoLabel = "Repository";
        }
        $uLabel = "";
        if (array_key_exists($this->getAuthor(), self::$usersCaches)) {
            $uLabel = self::$usersCaches[$this->getAuthor()];
        } else if (strstr($tplString, "AJXP_USER") !== false && AuthService::userExists($this->getAuthor())) {
            $obj = ConfService::getConfStorageImpl()->createUserObject($this->getAuthor());
            $uLabel = $obj->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
            self::$usersCaches[$this->getAuthor()] = $uLabel;
        }
        if (empty($uLabel)) {
            $uLabel = $this->getAuthor();
        }
        $em = ($rich ? "<em>" : "");
        $me = ($rich ? "</em>" : "");

        $replaces = array(
            "AJXP_NODE_PATH"        => $em.$this->getRoot($this->getNode()->getPath()).$me,
            "AJXP_NODE_LABEL"       => $em.$this->getNode()->getLabel().$me,
            "AJXP_PARENT_PATH"      => $em.$this->getRoot(dirname($this->getNode()->getPath())).$me,
            "AJXP_PARENT_LABEL"     => $em.$this->getRoot(basename(dirname($this->getNode()->getPath()))).$me,
            "AJXP_REPOSITORY_ID"    => $em.$repoId.$me,
            "AJXP_REPOSITORY_LABEL" => $em.$repoLabel.$me,
            "AJXP_LINK"             => $this->getMainLink(),
            "AJXP_USER"             => $uLabel,
            "AJXP_DATE"             => SystemTextEncoding::fromUTF8(AJXP_Utils::relativeDate($this->getDate(),$mess)),
        );

        if($replaces["AJXP_NODE_LABEL"]==$em.$me){
            $replaces["AJXP_NODE_LABEL"] = $em. "[".$replaces["AJXP_REPOSITORY_LABEL"]."]".$me;
        }
        if($replaces["AJXP_PARENT_LABEL"] == $em.$me ){
            $replaces["AJXP_PARENT_LABEL"] = $em. "[".$replaces["AJXP_REPOSITORY_LABEL"]."]".$me;
        }
        if((strstr($tplString, "AJXP_TARGET_FOLDER") !== false || strstr($tplString, "AJXP_SOURCE_FOLDER")) &&
            isSet($this->secondaryNode)
        ){
            $p = $this->secondaryNode->getPath();
            if($this->secondaryNode->isLeaf()) $p = $this->getRoot(dirname($p));
            $replaces["AJXP_TARGET_FOLDER"] = $replaces["AJXP_SOURCE_FOLDER"] =  $em.$p.$me;
        }

        if ((strstr($tplString, "AJXP_TARGET_LABEL") !== false || strstr($tplString, "AJXP_SOURCE_LABEL") !== false ) && isSet($this->secondaryNode) ) {
            $replaces["AJXP_TARGET_LABEL"] = $replaces["AJXP_SOURCE_LABEL"] = $em.$this->secondaryNode->getLabel().$me;
        }

        return str_replace(array_keys($replaces), array_values($replaces), $tplString);
    }

    /**
     * @return string
     */
    public function getMainLink()
    {
        $repoId = $this->getNode()->getRepositoryId();
        if(isSet($_SESSION["CURRENT_MINISITE"])){
            $hash = $_SESSION["CURRENT_MINISITE"];
            $shareCenter = ShareCenter::getShareCenter();
            if(!empty($shareCenter)){
                return $shareCenter->buildPublicletLink($hash);
            }
        }
        return trim(AJXP_Utils::detectServerURL(true), "/")."/?goto=".$repoId.$this->node->getPath();
    }

    /**
     * @return string
     */
    public function getDescriptionShort()
    {
        $mess = ConfService::getMessages();
        $tpl = $mess["notification.tpl.short.".($this->getNode()->isLeaf()?"file":"folder").".".$this->action];
        return $this->replaceVars($tpl, $mess, false);
    }

    /**
     * @return string
     */
    public function getDescriptionBlock()
    {
        $mess = ConfService::getMessages();
        $tpl = $mess["notification.tpl.block.".($this->getNode()->isLeaf()?"file":"folder").".".$this->action];
        return $this->replaceVars($tpl, $mess, false);
    }

    /**
     * @return string
     */
    public function getDescriptionLong($skipLink = false)
    {
        $mess = ConfService::getMessages();

        if (count($this->relatedNotifications)) {
            $key = "notification.tpl.group.".($this->getNode()->isLeaf()?"file":"folder").".".$this->action;
            $tpl = $this->replaceVars($mess[$key], $mess).": ";
            $tpl .= "<ul>";
            foreach ($this->relatedNotifications as $relatedNotification) {
                $tpl .= "<li>".$relatedNotification->getDescriptionLong(true)."</li>";
            }
            $tpl .= "</ul>";
        } else {
            $tpl = $this->replaceVars($mess["notification.tpl.long.".($this->getNode()->isLeaf()?"file":"folder").".".$this->action], $mess);
        }
        if (!$skipLink) {
            $tpl .= "<br><br>".$this->replaceVars($mess["notification.tpl.long.ajxp_link"], $mess);
        }
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

    public function getAuthorLabel(){
        if (array_key_exists($this->getAuthor(), self::$usersCaches)) {
            $uLabel = self::$usersCaches[$this->getAuthor()];
        } if (AuthService::userExists($this->getAuthor())) {
            $obj = ConfService::getConfStorageImpl()->createUserObject($this->getAuthor());
            $uLabel = $obj->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
            self::$usersCaches[$this->getAuthor()] = $uLabel;
        }
        if(!empty($uLabel)) return $uLabel;
        else return $this->getAuthor();
    }

    public function setDate($date)
    {
        $this->date = $date;
    }

    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param AJXP_Node $node
     */
    public function setNode($node)
    {
        $this->node = $node;
    }

    /**
     * @return AJXP_Node
     */
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
