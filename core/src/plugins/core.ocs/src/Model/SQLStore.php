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

use Pydio\Core\Services\ConfService;
use Pydio\Conf\Sql\SqlConfDriver;

defined('AJXP_EXEC') or die('Access not allowed');

define('OCS_SQLSTORE_FORMAT', 'serial');
define('OCS_SQLSTORE_NS_INVITATION', 'ocs_invitation');
define('OCS_SQLSTORE_NS_REMOTE_SHARE', 'ocs_remote_share');

/**
 * Class SQLStore
 * @package Pydio\OCS\Model
 */
class SQLStore implements IStore
{
    /**
     * @var SqlConfDriver
     */
    protected $storage;

    /**
     * SQLStore constructor.
     */
    public function __construct()
    {
        $storage = ConfService::getConfStorageImpl();
        if($storage->getId() == "conf.sql") {
            $this->storage = $storage;
        }
    }

    /**
     * @param ShareInvitation $invitation
     * @return int
     */
    public function generateInvitationId(ShareInvitation &$invitation){
        if(empty($id)){
            $id = $this->findAvailableID(OCS_SQLSTORE_NS_INVITATION);
            $invitation->setId($id);
        }
        return $invitation->getId();
    }

    /**
     * Persists an invitation to store
     * @param ShareInvitation $invitation
     * @return ShareInvitation|false The invitation with its new ID.
     */
    public function storeInvitation(ShareInvitation $invitation)
    {
        $id = $invitation->getId();
        /*
        if(empty($id)){
            $id = $this->findAvailableID(OCS_SQLSTORE_NS_INVITATION);
        }
        $invitation->setId($id);
        */
        $this->storage->simpleStoreSet(OCS_SQLSTORE_NS_INVITATION, $id, $invitation, OCS_SQLSTORE_FORMAT, $invitation->getLinkHash());
        return $invitation;
    }

    /**
     * Find all invitatins for a given token
     * @param string $linkToken
     * @return ShareInvitation[]
     */
    public function invitationsForLink($linkToken)
    {
        $cursor = null;
        return $this->storage->simpleStoreList(OCS_SQLSTORE_NS_INVITATION, $cursor, "", OCS_SQLSTORE_FORMAT, "", $linkToken);
    }

    /**
     * Find an invitation by ID
     * @param $invitationId
     * @return ShareInvitation|null
     */
    public function invitationById($invitationId)
    {
        $this->storage->simpleStoreGet(OCS_SQLSTORE_NS_INVITATION, $invitationId, OCS_SQLSTORE_FORMAT, $data);
        return $data;
    }

    /**
     * Delete an invitation
     * @param ShareInvitation $invitation
     * @return bool
     */
    public function deleteInvitation(ShareInvitation $invitation)
    {
        $this->storage->simpleStoreClear(OCS_SQLSTORE_NS_INVITATION, $invitation->getId());
        return true;
    }

    /**
     * Persists a remote share to the store
     * @param RemoteShare $remoteShare
     * @return RemoteShare|false The share with eventually its new ID.
     */
    public function storeRemoteShare(RemoteShare $remoteShare)
    {
        $id = $remoteShare->getId();
        if(empty($id)){
            $id = $remoteShare->getOcsToken();
            $remoteShare->setId($id);
        }
        $this->storage->simpleStoreSet(OCS_SQLSTORE_NS_REMOTE_SHARE, $id, $remoteShare, OCS_SQLSTORE_FORMAT, $remoteShare->getUser());
        return $remoteShare;

    }

    /**
     * Find all remote shares for a given user
     * @param string $userName
     * @return RemoteShare[]
     */
    public function remoteSharesForUser($userName)
    {
        $cursor = null;
        return $this->storage->simpleStoreList(OCS_SQLSTORE_NS_REMOTE_SHARE, $cursor, "", OCS_SQLSTORE_FORMAT, "", $userName);
    }

    /**
     * Find a remote share by its id
     * @param string $remoteShareId
     * @return RemoteShare|false
     */
    public function remoteShareById($remoteShareId)
    {
        $this->storage->simpleStoreGet(OCS_SQLSTORE_NS_REMOTE_SHARE, $remoteShareId, OCS_SQLSTORE_FORMAT, $data);
        return $data;
    }

    /**
     * @param $ocsRemoteId
     * @return mixed|null
     */
    public function remoteShareForOcsRemoteId($ocsRemoteId){
        $searchString = 's:11:"ocsRemoteId";s:'.strlen($ocsRemoteId).':"'.$ocsRemoteId.'"';
        $cursor = null;
        $l = $this->storage->simpleStoreList(OCS_SQLSTORE_NS_REMOTE_SHARE, $cursor,  "", OCS_SQLSTORE_FORMAT, "%$searchString%");
        if(count($l)){
            return array_shift(array_values($l));
        }else{
            return null;
        }
    }

    /**
     * Delete an existing remote share
     * @param RemoteShare $remoteShare
     * @return bool
     */
    public function deleteRemoteShare(RemoteShare $remoteShare)
    {
        $this->storage->simpleStoreClear(OCS_SQLSTORE_NS_REMOTE_SHARE, $remoteShare->getId());
        return true;
    }

    /**
     * @param $namespace
     * @return int
     */
    protected function findAvailableID($namespace){
        $id = 0;
        while(true){
            $id = rand();
            $this->storage->simpleStoreGet($namespace, $id, OCS_SQLSTORE_FORMAT, $data);
            if(empty($data)) {
                break;
            }
        }
        return $id;
    }

    /**
     * @return string
     */
    protected function getGUID(){
        if (function_exists('com_create_guid')){
            return com_create_guid();
        }else{
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = ""//chr(123)// "{"
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12);
                //.chr(125);// "}"
            return $uuid;
        }
    }

}