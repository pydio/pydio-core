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

    protected function getRoot($string){
        if(empty($string)) return "/";
        return $string;
    }

    protected function replaceVars($tplString, $mess, $rich = true){
        $repoId = $this->getNode()->getRepositoryId();
        if(ConfService::getRepositoryById($repoId) != null){
            $repoLabel = ConfService::getRepositoryById($repoId)->getDisplay();
        }else{
            $repoLabel = "Repository";
        }
        $uLabel = "";
        if(strstr($tplString, "AJXP_USER") !== false && AuthService::userExists($this->getAuthor())){
            $obj = ConfService::getConfStorageImpl()->createUserObject($this->getAuthor());
            $uLabel = $obj->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
        }
        if(empty($uLabel)){
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
            "AJXP_LINK"             => AJXP_Utils::detectServerURL(true)."/?goto=".$repoId.$this->node->getPath(),
            "AJXP_USER"             => $uLabel,
            "AJXP_DATE"             => date($mess["date_format"], $this->getDate()),
        );

        if((strstr($tplString, "AJXP_TARGET_FOLDER") !== false || strstr($tplString, "AJXP_SOURCE_FOLDER")) &&
            isSet($this->secondaryNode)
        ){
            $replaces["AJXP_TARGET_FOLDER"] = $replaces["AJXP_SOURCE_FOLDER"] = $this->secondaryNode->getPath();
        }

        return str_replace(array_keys($replaces), array_values($replaces), $tplString);
    }

    /**
     * @return string
     */
    public function getDescriptionShort(){
        $mess = ConfService::getMessages();
        $tpl = $mess["notification.tpl.short.".($this->getNode()->isLeaf()?"file":"folder").".".$this->action];
        return $this->replaceVars($tpl, $mess, false);
    }


    /**
     * @return string
     */
    public function getDescriptionLong($skipLink = false){
        $mess = ConfService::getMessages();

        if(count($this->relatedNotifications)){
            $key = "notification.tpl.group.".($this->getNode()->isLeaf()?"file":"folder").".".$this->action;
            $tpl = $this->replaceVars($mess[$key], $mess).": ";
            $tpl .= "<ul>";
            foreach($this->relatedNotifications as $relatedNotification){
                $tpl .= "<li>".$relatedNotification->getDescriptionLong(true)."</li>";
            }
            $tpl .= "</ul>";
        }else{
            $tpl = $this->replaceVars($mess["notification.tpl.long.".($this->getNode()->isLeaf()?"file":"folder").".".$this->action], $mess);
        }
        if(!$skipLink){
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
