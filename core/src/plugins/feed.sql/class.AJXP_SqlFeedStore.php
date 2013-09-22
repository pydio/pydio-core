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

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Feed
 */
class AJXP_SqlFeedStore extends AJXP_Plugin implements AJXP_FeedStore
{

    private $sqlDriver;

    public function init($options){
        $this->sqlDriver = AJXP_Utils::cleanDibiDriverParameters($options["SQL_DRIVER"]);
        parent::init($options);
    }

    public function performChecks(){
        if(!isSet($this->options)) return;
        $test = AJXP_Utils::cleanDibiDriverParameters($this->options["SQL_DRIVER"]);
        if(!count($test)){
            throw new Exception("Please define an SQL connexion in the core configuration");
        }
    }


    /**
     * @param string $hookName
     * @param mixed $data
     * @param string $repositoryId
     * @param string $repositoryScope
     * @param string $repositoryOwner
     * @param string $userId
     * @param string $userGroup
     * @return void
     */
    public function persistEvent($hookName, $data, $repositoryId, $repositoryScope, $repositoryOwner, $userId, $userGroup)
    {
        if($this->sqlDriver["password"] == "XXXX") return;
        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($this->sqlDriver);
        try{
            dibi::query("INSERT INTO [ajxp_feed] ([edate],[etype],[htype],[user_id],[repository_id],[repository_owner],[user_group],[repository_scope],[content]) VALUES (%i,%s,%s,%s,%s,%s,%s,%s,%bin)", time(), "event", "node.change", $userId, $repositoryId, $repositoryOwner, $userGroup, ($repositoryScope !== false ? $repositoryScope : "ALL"), serialize($data));
        }catch (DibiException $e){
            AJXP_Logger::logAction("error: (trying to persist event)". $e->getMessage());
        }
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
        $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [etype] = %s AND
            ( [repository_id] IN (%s) OR [repository_owner] = %s )
            AND (
                [repository_scope] = 'ALL'
                OR  ([repository_scope] = 'USER' AND [user_id] = %s  )
                OR  ([repository_scope] = 'GROUP' AND [user_group] = %s  )
            )
            ORDER BY [edate] DESC LIMIT $offset,$limit ", "event", $filterByRepositories, $userId, $userId, $userGroup);
        $data = array();
        foreach($res as $n => $row){
            $object = new stdClass();
            $object->hookname = $row->htype;
            $object->arguments = unserialize($row->content);
            $object->author = $row->user_id;
            $object->date = $row->edate;
            $object->repository = $row->repository_id;
            $object->event_id = $row->id;
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
        if($this->sqlDriver["password"] == "XXXX") return;
        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($this->sqlDriver);
        try{
            dibi::query("INSERT INTO [ajxp_feed] ([edate],[etype],[htype],[user_id],[repository_id],[content]) VALUES (%i,%s,%s,%s,%s,%bin)",
            time(), "alert", "notification", $userId, $repositoryId, serialize($notif));
        }catch (DibiException $e){
            AJXP_Logger::logAction("error: (trying to persist alert)". $e->getMessage());
        }
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
                $test->alert_id = $row->id;
                $data[] = $test;
            }
        }
        return $data;
    }

    /**
     * @param $occurrences
     * @param $alertId
     */
    public function dismissAlertById($alertId, $occurrences = 1){
        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($this->sqlDriver);
        $userId = AuthService::getLoggedUser()->getId();
        if($occurrences == 1){
            dibi::query("DELETE FROM [ajxp_feed] WHERE [id] = %i AND [user_id] = %s", $alertId, $userId);
        }else{
            $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [id] = %i AND [user_id] = %s", $alertId, $userId);
            foreach($res as $n => $row){
                $startEventRow = $row;
                break;
            }
            $startEventNotif = new AJXP_Notification();
            $startEventNotif = unserialize($startEventRow->content);
            $url = $startEventNotif->getNode()->getUrl();
            $date = $startEventRow->edate;
            $newRes = dibi::query("SELECT [id] FROM [ajxp_feed] WHERE [etype] = %s AND [user_id] = %s AND [edate] <= %s AND content LIKE %s ORDER BY [edate] DESC LIMIT 0,$occurrences", "alert", $userId, $date, "%\"$url\"%");
            $a = $newRes->fetchPairs();
            dibi::query("DELETE FROM [ajxp_feed] WHERE [id] IN (%s)",  $a);
        }
    }

    public function installSQLTables($param){
        $p = AJXP_Utils::cleanDibiDriverParameters($param["SQL_DRIVER"]);
        return AJXP_Utils::runCreateTablesQuery($p, $this->getBaseDir()."/create.sql");
    }

}