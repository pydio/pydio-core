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


class RemoteShare
{
    /**
     * @var integer
     */
    var $id;
    /**
     * @var string
     */
    var $status;
    /**
     * @var string
     */
    var $ocsToken;
    /**
     * @var integer
     */
    var $ocsRemoteId;
    /**
     * @var string
     */
    var $user;
    /**
     * @var string
     */
    var $sender;

    /**
     * @var string
     */
    var $ocsServiceUrl;
    /**
     * @var string
     */
    var $ocsDavUrl;

    /**
     * @var bool
     */
    var $hasPassword;
    /**
     * @var string|null
     */
    var $password;
    /**
     * @var integer
     */
    var $receptionDate;
    /**
     * @var integer
     */
    var $answerDate;
    /**
     * @var string
     */
    var $message;
    /**
     * @var string
     */
    var $documentName;

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
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getOcsToken()
    {
        return $this->ocsToken;
    }

    /**
     * @return int
     */
    public function getOcsRemoteId()
    {
        return $this->ocsRemoteId;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * @return string
     */
    public function getOcsServiceUrl()
    {
        return $this->ocsServiceUrl;
    }

    /**
     * @return string
     */
    public function getOcsDavUrl()
    {
        return $this->ocsDavUrl;
    }

    /**
     * @return boolean
     */
    public function isHasPassword()
    {
        return $this->hasPassword;
    }

    /**
     * @return null|string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return int
     */
    public function getReceptionDate()
    {
        return $this->receptionDate;
    }

    /**
     * @return int
     */
    public function getAnswerDate()
    {
        return $this->answerDate;
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
    public function getDocumentName()
    {
        return $this->documentName;
    }


}