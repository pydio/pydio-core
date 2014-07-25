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
 * @subpackage Core
 */
interface AJXP_FeedStore
{
    /**
     * @abstract
     * @param string $hookName
     * @param string $data
     * @param string $repositoryId
     * @param string $repositoryScope
     * @param string $repositoryOwner
     * @param string $userId
     * @param string $userGroup
     * @return void
     */
    public function persistEvent($hookName, $data, $repositoryId, $repositoryScope, $repositoryOwner, $userId, $userGroup);

    /**
     * @abstract
     * @param array $filterByRepositories
     * @param $filterByPath
     * @param string $userGroup
     * @param integer $offset
     * @param integer $limit
     * @param boolean $enlargeToOwned
     * @param string $userId
     * @return AJXP_Notification[]
     */
    public function loadEvents($filterByRepositories, $filterByPath, $userGroup, $offset = 0, $limit = 10, $enlargeToOwned = true, $userId);

    /**
     * @abstract
     * @param AJXP_Notification $notif
     * @return mixed
     */
    public function persistAlert(AJXP_Notification $notif);

    /**
     * @abstract
     * @param $userId
     * @param null $repositoryIdFilter
     * @return AJXP_Notification[]
     */
    public function loadAlerts($userId, $repositoryIdFilter = null);


    /**
     * @param $alertId
     * @param $occurrences
     */
    public function dismissAlertById($alertId, $occurrences = 1);

    /**
     * @param string $indexPath
     * @param mixed $data
     * @param string $repositoryId
     * @param string $repositoryScope
     * @param string $repositoryOwner
     * @param string $userId
     * @param string $userGroup
     * @return void
     */
    public function persistMetaObject($indexPath, $data, $repositoryId, $repositoryScope, $repositoryOwner, $userId, $userGroup);
    public function findMetaObjectsByIndexPath($repositoryId, $indexPath, $userId, $userGroup, $offset = 0, $limit = 20, $orderBy = "date", $orderDir = "desc");
    public function updateMetaObject($repositoryId, $oldPath, $newPath = null, $copy = false);

}
