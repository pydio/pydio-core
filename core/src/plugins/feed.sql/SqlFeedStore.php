<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Notification\Feed;

use Pydio\Access\Core\Filter\AJXP_Permission;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\Model\UserInterface;
use Pydio\Core\Utils\DBHelper;
use Pydio\Core\Utils\Vars\OptionsHelper;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\SqlTableProvider;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Enterprise\Session\PydioSessionManager;
use Pydio\Notification\Core\IFeedStore;
use Pydio\Notification\Core\Notification;

use \dibi as dibi;
use \DibiException as DibiException;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Feed
 */
class SqlFeedStore extends Plugin implements IFeedStore, SqlTableProvider
{

    private $sqlDriver;

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        $this->sqlDriver = OptionsHelper::cleanDibiDriverParameters($options["SQL_DRIVER"]);
        parent::init($ctx, $options);
    }

    public function performChecks()
    {
        if(!isSet($this->options)) return;
        $test = OptionsHelper::cleanDibiDriverParameters($this->options["SQL_DRIVER"]);
        if (is_array($test) && !count($test)) {
            throw new \Exception("Please define an SQL connexion in the core configuration");
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
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        try {
            /** @var AJXP_Node $node */
            $node = null;
            if(is_object($data[1]) && $data[1] instanceof \Pydio\Access\Core\Model\AJXP_Node && $data[1]->getContext()->getRepositoryId() === $repositoryId) {
                $node = $data[1];
            } else if(is_object($data[0]) && $data[0] instanceof \Pydio\Access\Core\Model\AJXP_Node && $data[0]->getContext()->getRepositoryId() === $repositoryId) {
                $node = $data[0];
            }
            dibi::query("INSERT INTO [ajxp_feed] ([edate],[etype],[htype],[user_id],[repository_id],[repository_owner],[user_group],[repository_scope],[content],[index_path]) VALUES (%i,%s,%s,%s,%s,%s,%s,%s,%bin,%s)",
                time(),
                "event",
                $hookName,
                $userId,
                $repositoryId,
                $repositoryOwner,
                $userGroup,
                ($repositoryScope !== false ? $repositoryScope : "ALL"),
                serialize($data),
                ($node!=null ? $node->getUrl():'')
            );
        } catch (DibiException $e) {
            $this->logError("DibiException", "trying to persist event", $e->getMessage());
        }
    }

    /**
     * @param array $filterByRepositories
     * @param $filterByPath
     * @param string $userGroup
     * @param integer $offset
     * @param integer $limit
     * @param bool $enlargeToOwned
     * @param string $userId
     * @return array An array of stdClass objects with keys hookname, arguments, author, date, repository
     */
    public function loadEvents($filterByRepositories, $filterByPath, $userGroup, $offset = 0, $limit = 10, $enlargeToOwned = true, $userId = null, $chainLoad = false)
    {
        if($this->sqlDriver["password"] == "XXXX") return array();
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        if($this->sqlDriver["driver"] == "postgre"){
            dibi::query("SET bytea_output=escape");
        }

        // Add some permission mask if necessary
        /** @var PydioSessionManager $sessionManager */
        $sessionManager = PluginsService::findPluginWithoutCtxt("sec", "session");

        $repoOrs = array();
        foreach($filterByRepositories as $repoId){
            $masks = array();
            if($sessionManager !== false){
                $sessionManager->listCurrentMasks(new Context($userId, $repoId), $masks, AJXP_Permission::READ);
            }
            if(count($masks)){
                $pathesOr = array();
                foreach($masks as $mask){
                    $filterLike = "://%@".$repoId.rtrim($mask, "/") . "/";
                    $pathesOr[] = array("[index_path] LIKE %~like~", $filterLike);
                }
                if(count($pathesOr)){
                    $repoOrs[] = array("[repository_id]=%s AND %or", $repoId, $pathesOr);
                }else{
                    $repoOrs[] = array("[repository_id]=%s", $repoId);
                }
            }
        }


        if ($enlargeToOwned) {
            $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [etype] = %s AND
            ( [repository_id] IN (%s) OR [repository_owner] = %s )
            AND (
                [repository_scope] = 'ALL'
                OR  ([repository_scope] = 'USER' AND [user_id] = %s  )
                OR  ([repository_scope] = 'GROUP' AND [user_group] = %s  )
            )
            ORDER BY [edate] DESC %lmt %ofs", "event", $filterByRepositories, $userId, $userId, $userGroup, $limit, $offset);
        } else {
            if(!empty($filterByPath)){
                $groupByClause = "";
                if($filterByPath[strlen($filterByPath)-1]=='/'){
                    //$groupByClause = " GROUP BY [index_path] ";
                }
                $index_path = "%://%@".$filterByRepositories[0].$filterByPath."%";
                $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [etype] = %s
                AND
                  ( %or )
                AND
                  ([index_path] LIKE %s)
                AND (
                    [repository_scope] = 'ALL'
                    OR  ([repository_scope] = 'USER' AND [user_id] = %s  )
                    OR  ([repository_scope] = 'GROUP' AND [user_group] = %s  )
                )
                $groupByClause ORDER BY [edate] DESC %lmt %ofs", "event", $repoOrs, $index_path, $userId, $userGroup, $limit, $offset);
            }else{
                    $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [etype] = %s AND
                ( %or )
                AND (
                    [repository_scope] = 'ALL'
                    OR  ([repository_scope] = 'USER' AND [user_id] = %s  )
                    OR  ([repository_scope] = 'GROUP' AND [user_group] = %s  )
                )
                ORDER BY [edate] DESC %lmt %ofs", "event", $repoOrs, $userId, $userGroup, $limit, $offset);
            }
        }
        $data = array();
        foreach ($res as $n => $row) {
            $object = new \stdClass();
            $object->hookname = $row->htype;
            $object->arguments = StringHelper::safeUnserialize($row->content, ["Pydio\\Access\\Core\\Model\\AJXP_Node", "Pydio\\Notification\\Core\\Notification"]);
            $object->author = $row->user_id;
            $object->date = $row->edate;
            $object->repository = $row->repository_id;
            $object->event_id = $row->id;
            if(!empty($filterByPath) && !empty($chainLoad) && substr($row->index_path, -strlen($filterByPath)) === $filterByPath
                && $object->arguments !== null && isSet($object->arguments[0]) && $object->arguments[0] instanceOf AJXP_Node ){
                $oldNode = $object->arguments[0];
                $oldPath = $oldNode->getPath();
                if(!is_array($chainLoad)) $chainLoad = [];
                $chainLoad[] = $filterByPath;
                if(!in_array($oldPath, $chainLoad) && count($chainLoad) <= 10){
                    // Load previous events when path was different.
                    $chainData = $this->loadEvents($filterByRepositories, $oldPath, $userGroup, 0, $limit, $enlargeToOwned, $userId, $chainLoad);
                    foreach($chainData as $chainObject)  $data[] = $chainObject;
                }
            }
            $data[] = $object;
        }
        return $data;
    }

    /**
     * @abstract
     * @param Notification $notif
     * @param bool $repoScopeAll
     * @param bool|string $groupScope
     * @return mixed
     */
    public function persistAlert(Notification $notif, $repoScopeAll = false, $groupScope = false)
    {
        if(!$notif->getNode()) return;
        if($repoScopeAll){
            $repositoryId = "*";
        }else{
            $repositoryId = $notif->getNode()->getRepositoryId();
        }
        $userId = $notif->getTarget();
        if($this->sqlDriver["password"] == "XXXX") return;
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        try {
            dibi::query("INSERT INTO [ajxp_feed] ([edate],[etype],[htype],[user_id],[repository_id],[user_group],[content],[index_path]) VALUES (%i,%s,%s,%s,%s,".($groupScope?"'$groupScope'":"NULL").",%bin,%s)",
                time(),
                "alert",
                "notification",
                $userId,
                $repositoryId,
                serialize($notif),
                ($notif->getNode()!=null ? $notif->getNode()->getUrl():'')
            );
        } catch (DibiException $e) {
            $this->logError("DibiException", "trying to persist alert", $e->getMessage());
        }
    }

    /**
     * @abstract
     * @param UserInterface $userObject
     * @param null $repositoryIdFilter
     * @return mixed
     */
    public function loadAlerts($userObject, $repositoryIdFilter = null)
    {
        $userId = $userObject->getId();
        $userGroup = $userObject->getGroupPath();
        if($this->sqlDriver["password"] == "XXXX") return array();
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        if ($repositoryIdFilter !== null) {
            $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [etype] = %s
            AND ([repository_id] = %s OR [repository_id] IN  (SELECT [uuid] FROM [ajxp_repo] WHERE [parent_uuid]=%s) OR [repository_id] = %s)
            AND ([user_id] = %s OR [user_group] = %s ) ORDER BY [edate] DESC %lmt", "alert", $repositoryIdFilter, $repositoryIdFilter, '*', $userId, $userGroup, 100);
        } else {
            $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [etype] = %s AND ([user_id] = %s OR [user_group] = %s) ORDER BY [edate] DESC %lmt", "alert", $userId, $userGroup, 100);
        }
        $data = array();
        foreach ($res as $n => $row) {
            $test = StringHelper::safeUnserialize($row->content, ["Pydio\\Access\\Core\\Model\\AJXP_Node", "Pydio\\Notification\\Core\\Notification"]);
            if ($test instanceof Notification) {
                $test->alert_id = $row->id;
                $data[] = $test;
            }
        }
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function dismissAlertById(ContextInterface $contextInterface, $alertId, $occurrences = 1)
    {
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        $userId = $contextInterface->getUser()->getId();
        $userGroup = $contextInterface->getUser()->getGroupPath();
        if ($occurrences == 1) {
            dibi::query("DELETE FROM [ajxp_feed] WHERE [id] = %i AND ([user_id] = %s OR [user_group] = %s) AND [etype] = %s", $alertId, $userId, $userGroup, "alert");
        } else {
            $res = dibi::query("SELECT * FROM [ajxp_feed] WHERE [id] = %i AND ([user_id] = %s OR [user_group] = %s) AND [etype] = %s", $alertId, $userId, $userGroup, "alert");
            if(!count($res)){
                return;
            }
            $startEventRow = null;
            foreach ($res as $n => $row) {
                $startEventRow = $row;
                break;
            }
            /**
             * @var $startEventNotif Notification
             */
            $startEventNotif = StringHelper::safeUnserialize($startEventRow->content, ["Pydio\\Access\\Core\\Model\\AJXP_Node", "Pydio\\Notification\\Core\\Notification"]);
            if(empty($startEventNotif) || ! $startEventNotif instanceof Notification) {
                return;
            }
            $url = $startEventNotif->getNode()->getUrl();
            if($url !== $startEventRow->index_path){
                $url = $startEventRow->index_path;
            }
            $date = $startEventRow->edate;
            $newRes = dibi::query("SELECT [id] FROM [ajxp_feed] WHERE [etype] = %s AND ([user_id] = %s OR [user_group] = %s) AND [edate] <= %s AND [index_path] = %s ORDER BY [edate] DESC %lmt", "alert", $userId, $userGroup, $date, $url, $occurrences);
            $a = $newRes->fetchPairs();
            if (!count($a)) {
                // Weird, probably not upgraded!
                $this->upgradeAlertsContentToIndexPath();
            }
            dibi::query("DELETE FROM [ajxp_feed] WHERE [id] IN %in",  $a);
        }
    }


    public function upgradeAlertsContentToIndexPath()
    {
        // Load alerts with empty index_path
        $res = dibi::query("SELECT [id],[content],[index_path] FROM [ajxp_feed] WHERE [etype] = %s AND [index_path] IS NULL", "alert");
        foreach ($res as $row) {
            $test = StringHelper::safeUnserialize($row->content, ["Pydio\\Access\\Core\\Model\\AJXP_Node", "Pydio\\Notification\\Core\\Notification"]);
            if ($test instanceof Notification) {
                $url = $test->getNode()->getUrl();
                try {
                    dibi::query("UPDATE [ajxp_feed] SET [index_path]=%s WHERE [id] = %i", $url, $row->id);
                } catch (\Exception $e) {
                    $this->logError("[sql]", $e->getMessage());
                }
            }
        }
    }

    /**
     * @param string $indexPath
     * @param mixed $data
     * @param string $repositoryId
     * @param string $repositoryScope
     * @param string $repositoryOwner
     * @param string $userId
     * @param string $userGroup
     * @return int last insert ID
     */
    public function persistMetaObject($indexPath, $data, $repositoryId, $repositoryScope, $repositoryOwner, $userId, $userGroup)
    {
        if($this->sqlDriver["password"] == "XXXX") return;
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        try {
            dibi::query("INSERT INTO [ajxp_feed] ([edate],[etype],[htype],[index_path],[user_id],[repository_id],[repository_owner],[user_group],[repository_scope],[content]) VALUES (%i,%s,%s,%s,%s,%s,%s,%s,%s,%bin)", time(), "meta", "comment", $indexPath, $userId, $repositoryId, $repositoryOwner, $userGroup, ($repositoryScope !== false ? $repositoryScope : "ALL"), serialize($data));
            return dibi::getInsertId();
        } catch (DibiException $e) {
            $this->logError("DibiException", "trying to persist meta", $e->getMessage());
        }
    }

    /**
     * @param $repositoryId
     * @param $indexPath
     * @param $userId
     * @param $userGroup
     * @param int $offset
     * @param int $limit
     * @param string $orderBy
     * @param string $orderDir
     * @param bool $recurring
     * @return array
     */
    public function findMetaObjectsByIndexPath($repositoryId, $indexPath, $userId, $userGroup, $offset = 0, $limit = 20, $orderBy = "date", $orderDir = "desc", $recurring = true)
    {
        if($this->sqlDriver["password"] == "XXXX") return array();
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        if($recurring){
            $res = dibi::query("SELECT * FROM [ajxp_feed]
                WHERE [etype] = %s AND [repository_id] = %s AND [index_path] LIKE %like~
                AND (
                    [repository_scope] = 'ALL'
                    OR  ([repository_scope] = 'USER' AND [user_id] = %s  )
                    OR  ([repository_scope] = 'GROUP' AND [user_group] = %s  )
                )                
                ORDER BY %by %lmt %ofs
            ", "meta", $repositoryId, $indexPath, $userId, $userGroup, array('edate' => $orderDir), $limit, $offset);
        }else{
            $res = dibi::query("SELECT * FROM [ajxp_feed]
                WHERE [etype] = %s AND [repository_id] = %s AND [index_path] = %s
                AND (
                    [repository_scope] = 'ALL'
                    OR  ([repository_scope] = 'USER' AND [user_id] = %s  )
                    OR  ([repository_scope] = 'GROUP' AND [user_group] = %s  )
                )                
                ORDER BY %by %lmt %ofs
            ", "meta", $repositoryId, $indexPath, $userId, $userGroup, array('edate' => $orderDir), $limit, $offset);
        }

        $data = array();
        foreach ($res as $n => $row) {
            $object = new \stdClass();
            $object->path = $row->index_path;
            $object->content = StringHelper::safeUnserialize($row->content, ["Pydio\\Access\\Core\\Model\\AJXP_Node", "Pydio\\Notification\\Core\\Notification"]);
            $object->author = $row->user_id;
            $object->date = $row->edate;
            $object->repository = $row->repository_id;
            $object->uuid = $row->id;
            $data[] = $object;
        }
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function dismissMetaObjectById(ContextInterface $ctx, $objectId){
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        $userId = $ctx->getUser()->getId();
        $userGroup = $ctx->getUser()->getGroupPath();
        dibi::query("DELETE FROM [ajxp_feed] WHERE [id] = %i AND ([user_id] = %s OR [user_group] = %s) AND [etype] = %s", $objectId, $userId, $userGroup, "meta");
    }


    /**
     * @param $repositoryId
     * @param $oldPath
     * @param null $newPath
     * @param bool $copy
     * @return mixed|void
     */
    public function updateMetaObject($repositoryId, $oldPath, $newPath = null, $copy = false)
    {
        if($this->sqlDriver["password"] == "XXXX") return;
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        if ($oldPath != null && $newPath == null) {// DELETE

            dibi::query("DELETE FROM [ajxp_feed] WHERE [repository_id]=%s and [index_path] LIKE %like~", $repositoryId, $oldPath);

        } else if ($oldPath != null && $newPath != null) { // MOVE or COPY

            if ($copy) {

                // ?? Do we want to duplicate metadata?

            } else {

                $starter = "__START__";
                dibi::query("UPDATE [ajxp_feed] SET [index_path] = CONCAT(%s, [index_path]) WHERE [index_path] LIKE %s AND [repository_id]=%s", $starter, $oldPath."%", $repositoryId);
                dibi::query("UPDATE [ajxp_feed] SET [index_path] = REPLACE([index_path], %s, %s) WHERE [index_path] LIKE %s AND [repository_id]=%s", $starter.$oldPath, $starter.$newPath, $starter.$oldPath."%", $repositoryId);
                dibi::query("UPDATE [ajxp_feed] SET [index_path] = REPLACE([index_path], %s, %s) WHERE [index_path] LIKE %s AND [repository_id]=%s", $starter, '', $starter.$newPath."%", $repositoryId);

            }

        }

    }

    /**
     * @param array $param
     * @return string
     * @throws \Exception
     */
    public function installSQLTables($param)
    {
        $p = OptionsHelper::cleanDibiDriverParameters($param["SQL_DRIVER"]);
        return DBHelper::runCreateTablesQuery($p, $this->getBaseDir() . "/create.sql");
    }

    /**
     * Delete feed data
     * @param array|string $types
     * @param null $userId
     * @param null $repositoryId
     * @param int $count
     * @return mixed
     */
    public function deleteFeed($types = 'event', $userId = null, $repositoryId = null, &$count = 0)
    {
        $wheres = [];
        if($types !== 'both') {
            $wheres[] = ['etype = %s', $types];
        }
        if($userId != null) {
            $wheres[] = ['user_id = %s OR index_path LIKE %s', $userId, '%pydio://'.$userId.'@%'];
        }
        if($repositoryId != null) {
            $wheres[] = ['repository_id = %s OR index_path LIKE %s', $repositoryId, '%pydio://%@'.$repositoryId.'/%'];
        }
        if(count($wheres)){
            $count = dibi::query("SELECT count(*) FROM [ajxp_feed] WHERE %and ", $wheres)->fetchSingle();
            dibi::query("DELETE FROM [ajxp_feed] WHERE %and", $wheres);
        }else{
            $count = dibi::query("SELECT count(*) FROM [ajxp_feed]")->fetchSingle();
            dibi::query("DELETE FROM [ajxp_feed]");
        }
    }
}
