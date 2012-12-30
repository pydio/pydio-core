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
 
interface AJXP_FeedStore{

    /**
     * @abstract
     * @param string $hookName
     * @param string $data
     * @param string $repositoryId
     * @param string $repositoryScope
     * @param string $userId
     * @param string $userGroup
     * @return void
     */
    public function persistEvent($hookName, $data, $repositoryId, $repositoryScope, $userId, $userGroup);

    /**
     * @abstract
     * @param array $filterByRepositories
     * @param string $userId
     * @param string $userGroup
     * @param integer $offset
     * @param integer $limit
     * @return AJXP_Notification[]
     */
    public function loadEvents($filterByRepositories, $userId, $userGroup, $offset = 0, $limit = 10);

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
     * @return mixed
     */
    public function loadAlerts($userId, $repositoryIdFilter = null);

}