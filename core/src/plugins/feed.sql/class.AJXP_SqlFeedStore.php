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

defined('AJXP_EXEC') or die('Access not allowed');

class AJXP_SqlFeedStore extends AJXP_Plugin implements AJXP_FeedStore
{

    private $sqlDriver;

    public function init($options){
        $this->sqlDriver = $options["SQL_DRIVER"];
        parent::init($options);
    }

    /**
     * @param string $hookName
     * @param mixed $data
     * @param string $repositoryId
     * @param string $repositoryScope
     * @param string $userId
     * @param string $userGroup
     * @return void
     */
    public function persistEvent($hookName, $data, $repositoryId, $repositoryScope, $userId, $userGroup)
    {
        $value = array(
            "edate" => time(),
            "etype"  => "event",
            "htype"  => "node.change",
            "user_id" => $userId,
            "repository_id" => $repositoryId,
            "user_group" => $userGroup,
            "repository_scope" => ($repositoryScope !== false ? $repositoryScope : "ALL"),
            "content" => serialize($data)
        );
        if($this->sqlDriver["password"] == "XXXX") return;
        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($this->sqlDriver);
        dibi::query("INSERT INTO [ajxp_feed]", $value);
    }

    /**
     * @param array $filterByRepositories
     * @param string $userId
     * @param string $userGroup
     * @param integer $offset
     * @param integer $limit
     * @return An array of stdClass objects with keys hookname, arguments, author, date, repository
     */
    public function loadEvents($filterByRepositories, $userId, $userGroup, $offset = 0, $limit = 10)
    {
        if($this->sqlDriver["password"] == "XXXX") return array();
        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($this->sqlDriver);
        $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [etype] = %s AND [repository_id] IN (%s) AND ([repository_scope] = 'ALL' OR  ([repository_scope] = 'USER' AND [user_id] = %s  ) OR  ([repository_scope] = 'GROUP' AND [user_group] = %s  )) ORDER BY [edate] DESC LIMIT 0,100 ", "event", $filterByRepositories, $userId, $userGroup);
        $data = array();
        foreach($res as $n => $row){
            $object = new stdClass();
            $object->hookname = $row->htype;
            $object->arguments = unserialize($row->content);
            $object->author = $row->user_id;
            $object->date = $row->edate;
            $object->repository = $row->repository_id;
            $data[] = $object;
        }
        return $data;
    }

    /**
     * @abstract
     * @param AJXP_Notification $notif
     * @return mixed
     */
    public function persistAlert(AJXP_Notification $notif){
        if(!$notif->getNode()) return;
        $repositoryId = $notif->getNode()->getRepositoryId();
        $userId = $notif->getTarget();
        $value = array(
            "edate" => time(),
            "etype"  => "alert",
            "htype"  => "notification",
            "user_id" => $userId,
            "repository_id" => $repositoryId,
            "content" => serialize($notif)
        );
        if($this->sqlDriver["password"] == "XXXX") return;
        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($this->sqlDriver);
        dibi::query("INSERT INTO [ajxp_feed]", $value);
    }

    /**
     * @abstract
     * @param $userId
     * @param null $repositoryIdFilter
     * @return mixed
     */
    public function loadAlerts($userId, $repositoryIdFilter = null){
        if($this->sqlDriver["password"] == "XXXX") return array();
        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($this->sqlDriver);
        if($repositoryIdFilter != null){
            $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [etype] = %s AND [repository_id] = %s AND [user_id] = %s ORDER BY [edate] DESC LIMIT 0,100 ", "alert", $repositoryIdFilter, $userId);
        }else{
            $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [etype] = %s AND [user_id] = %s ORDER BY [edate] DESC LIMIT 0,100 ", "alert", $userId);
        }
        $data = array();
        foreach($res as $n => $row){
            $test = unserialize($row->content);
            if(is_a($test, "AJXP_Notification")){
                $data[] = $test;
            }
        }
        return $data;
    }

}
