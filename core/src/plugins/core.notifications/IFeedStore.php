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
namespace Pydio\Notification\Core;

use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
interface IFeedStore
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
     * @param bool $chainLoad
     * @return Notification[]
     */
    public function loadEvents($filterByRepositories, $filterByPath, $userGroup, $offset = 0, $limit = 10, $enlargeToOwned = true, $userId = null, $chainLoad = false);

    /**
     * Delete feed data
     * @param string $types
     * @param null $userId
     * @param null $repositoryId
     * @param int $count
     * @return mixed
     */
    public function deleteFeed($types='event', $userId = null, $repositoryId = null, &$count = 0);

    /**
     * @abstract
     * @param Notification $notif
     * @param bool $repoScopeAll
     * @param bool|string $groupScope
     * @return mixed
     */
    public function persistAlert(Notification $notif, $repoScopeAll = false, $groupScope = false);

    /**
     * @abstract
     * @param UserInterface $userObject
     * @param null $repositoryIdFilter
     * @return Notification[]
     */
    public function loadAlerts($userObject, $repositoryIdFilter = null);


    /**
     * @param ContextInterface $ctx
     * @param $alertId
     * @param $occurrences
     */
    public function dismissAlertById(ContextInterface $ctx, $alertId, $occurrences = 1);

    /**
     * @param ContextInterface $ctx
     * @param $objectId
     */
    public function dismissMetaObjectById(ContextInterface $ctx, $objectId);

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
     * @return mixed
     */
    public function findMetaObjectsByIndexPath($repositoryId, $indexPath, $userId, $userGroup, $offset = 0, $limit = 20, $orderBy = "date", $orderDir = "desc", $recurring=true);

    /**
     * @param $repositoryId
     * @param $oldPath
     * @param null $newPath
     * @param bool $copy
     * @return mixed
     */
    public function updateMetaObject($repositoryId, $oldPath, $newPath = null, $copy = false);

}
