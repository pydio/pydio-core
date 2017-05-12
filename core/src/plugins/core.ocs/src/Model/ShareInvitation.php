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
if(!defined('OCS_INVITATION_STATUS_PENDING')){
    define('OCS_INVITATION_STATUS_PENDING', 1);
    define('OCS_INVITATION_STATUS_ACCEPTED', 2);
    define('OCS_INVITATION_STATUS_REJECTED', 4);
}

/**
 * Class ShareInvitation
 * @package Pydio\OCS\Model
 */
class ShareInvitation implements \JsonSerializable
{
    /**
     * @var integer
     */
    var $id;
    /**
     * @var string
     */
    var $documentName;
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

    /**
     * @param string $id
     */
    public function setId($id){
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getDocumentName()
    {
        return $this->documentName;
    }

    /**
     * @param string $documentName
     */
    public function setDocumentName($documentName){
        $this->documentName = $documentName;
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

    /**
     * @param string $linkHash
     */
    public function setLinkHash($linkHash)
    {
        $this->linkHash = $linkHash;
    }

    /**
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @param string $owner
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
    }

    /**
     * @param string $targetHost
     */
    public function setTargetHost($targetHost)
    {
        $this->targetHost = $targetHost;
    }

    /**
     * @param string $targetUser
     */
    public function setTargetUser($targetUser)
    {
        $this->targetUser = $targetUser;
    }


    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        return array(
            "HOST" => $this->targetHost,
            "USER" => $this->targetUser,
            "STATUS" => $this->status,
            "INVITATION_ID" => $this->id
        );
    }
}
