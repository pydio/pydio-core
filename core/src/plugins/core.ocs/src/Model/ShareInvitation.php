<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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
namespace Pydio\OCS\Model;

defined('AJXP_EXEC') or die('Access not allowed');
if(!defined('OCS_INVITATION_STATUS_PENDING')){
    define('OCS_INVITATION_STATUS_PENDING', 1);
    define('OCS_INVITATION_STATUS_ACCEPTED', 2);
    define('OCS_INVITATION_STATUS_REJECTED', 4);
}

class ShareInvitation
{
    /**
     * @var integer
     */
    var $id;
    /**
     * @var string
     */
    var $linkHash;
    /**
     * @var integer
     */
    var $status;
    /**
     * @var string
     */
    var $message;
    /**
     * @var string
     */
    var $owner;
    /**
     * @var string
     */
    var $targetHost;
    /**
     * @var string
     */
    var $targetUser;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function setId($id){
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getLinkHash()
    {
        return $this->linkHash;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return string
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @return string
     */
    public function getTargetHost()
    {
        return $this->targetHost;
    }

    /**
     * @return string
     */
    public function getTargetUser()
    {
        return $this->targetUser;
    }




}