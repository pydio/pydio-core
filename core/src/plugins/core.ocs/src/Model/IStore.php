<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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

namespace Pydio\OCS\Model;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Interface IStore
 * @package Pydio\OCS\Model
 */
interface IStore
{

    /**
     * Generate a unique ID for invitation
     * @param ShareInvitation $invitation
     * @return mixed
     */
    public function generateInvitationId(ShareInvitation &$invitation);
    /**
     * Persists an invitation to store
     * @param ShareInvitation $invitation
     * @return ShareInvitation|false The invitation with its new ID.
     */
    public function storeInvitation(ShareInvitation $invitation);

    /**
     * Find all invitatins for a given token
     * @param string $linkToken
     * @return ShareInvitation[]
     */
    public function invitationsForLink($linkToken);

    /**
     * Find an invitation by ID
     * @param $invitationId
     * @return ShareInvitation|null
     */
    public function invitationById($invitationId);

    /**
     * Delete an invitation
     * @param ShareInvitation $invitation
     * @return bool
     */
    public function deleteInvitation(ShareInvitation $invitation);

    /**
     * Persists a remote share to the store
     * @param RemoteShare $remoteShare
     * @return RemoteShare|false The share with eventually its new ID.
     */
    public function storeRemoteShare(RemoteShare $remoteShare);

    /**
     * Find all remote shares for a given user
     * @param string $userName
     * @return RemoteShare[]
     */
    public function remoteSharesForUser($userName);

    /**
     * Find a remote share by its id
     * @param string $remoteShareId
     * @return RemoteShare
     */
    public function remoteShareById($remoteShareId);

    /**
     * Delete an existing remote share
     * @param RemoteShare $remoteShare
     * @return bool
     */
    public function deleteRemoteShare(RemoteShare $remoteShare);

}