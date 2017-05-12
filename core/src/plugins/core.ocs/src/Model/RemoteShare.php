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

use Pydio\Access\Core\Filter\ContentFilter;

use Pydio\Core\Services\RepositoryService;
use Sabre\DAV;
use Sabre\DAV\Exception;

defined('AJXP_EXEC') or die('Access not allowed');
if(!defined('OCS_INVITATION_STATUS_PENDING')){
    define('OCS_INVITATION_STATUS_PENDING', 1);
    define('OCS_INVITATION_STATUS_ACCEPTED', 2);
    define('OCS_INVITATION_STATUS_REJECTED', 4);
}

/**
 * Class RemoteShare
 * @package Pydio\OCS\Model
 */
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
    var $host;
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
     * @var bool
     */
    var $documentIsLeaf;

    /**
     * @var bool
     */
    var $documentTypeResolved;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $id
     */
    public function setId($id){
        $this->id = $id;
    }

    /**
     * @return \Pydio\Access\Core\Model\Repository
     */
    public function buildVirtualRepository(){
        $repositoryId = "ocs_remote_share_".$this->getOcsToken();
        // Create REPO
        $parts = parse_url($this->getOcsDavUrl());
        $data = array(
            "DISPLAY"		=>	$this->getDocumentName(),
            "DESCRIPTION"   =>  "Shared by ".$this->getSender(),
            "AJXP_SLUG"		=>  "remote-".$this->getOcsToken(),
            "DRIVER"		=>	"webdav",
            "DRIVER_OPTIONS"=> array(
                "HOST"			=>	$parts["scheme"]."://".$parts["host"],
                "PATH"          =>  $parts["path"],
                "USER"		    =>	$this->getOcsToken(),
                "PASS" 	        => 	($this->hasPassword()?$this->getPassword() : ""),
                "DEFAULT_RIGHTS"=>  "",
                "META_SOURCES"		=> array()
            )
        );

        $remoteHost = $this->getHost();
        $remoteHost = !empty($remoteHost) ? '@' . $remoteHost : ' [remote]';
        $repo = RepositoryService::createRepositoryFromArray($repositoryId, $data);
        $repo->setRepositoryType("remote");
        $repo->setAccessStatus($this->getStatus() == OCS_INVITATION_STATUS_ACCEPTED ? "accepted":"");
        $repo->setWriteable(false);
        $repo->setOwnerData(null, $this->getSender().$remoteHost);
        if($this->isDocumentIsLeaf()){
            $contentFilter = new ContentFilter(array());
            $contentFilter->filters["/".$this->getDocumentName()] = "/"; // . $this->getDocumentName();
            $repo->setContentFilter($contentFilter);
        }
        return $repo;
    }

    /**
     * @return bool
     */
    public function pingRemoteDAVPoint(){

        $fullPath = rtrim($this->getOcsDavUrl(), "/")."/".$this->getDocumentName();
        $parts = parse_url($fullPath);
        $client = new DAV\Client(array(
            'baseUri' => $parts["scheme"]."://".$parts["host"],
            'userName' => $this->getOcsToken(),
            'password' => ''
        ));
        try {
            $result = $client->propFind($parts["path"],
                [
                    '{DAV:}getlastmodified',
                    '{DAV:}getcontentlength',
                    '{DAV:}resourcetype'
                ]
            );
        } catch (Exception\NotFound $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
        /**
         * @var \Sabre\DAV\Property\ResourceType $resType;
         */
        $resType = $result["{DAV:}resourcetype"];
        if($resType->is("{DAV:}collection")) {
            $this->setDocumentIsLeaf(false);
        } else{
            $this->setDocumentIsLeaf(true);
        }
        $this->documentTypeResolved = true;
        return true;
    }

    /**
     * @return boolean
     */
    public function isDocumentIsLeaf()
    {
        return $this->documentIsLeaf;
    }

    /**
     * @param boolean $documentIsLeaf
     */
    public function setDocumentIsLeaf($documentIsLeaf)
    {
        $this->documentIsLeaf = $documentIsLeaf;
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
    public function getHost()
    {
        return $this->host;
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
    public function hasPassword()
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

    /**
     * @param string $ocsToken
     */
    public function setOcsToken($ocsToken)
    {
        $this->ocsToken = $ocsToken;
    }

    /**
     * @param int $ocsRemoteId
     */
    public function setOcsRemoteId($ocsRemoteId)
    {
        $this->ocsRemoteId = $ocsRemoteId;
    }

    /**
     * @param string $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @param string $sender
     */
    public function setSender($sender)
    {
        $this->sender = $sender;
    }

    /**
     * @param string $host
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * @param string $ocsServiceUrl
     */
    public function setOcsServiceUrl($ocsServiceUrl)
    {
        $this->ocsServiceUrl = $ocsServiceUrl;
    }

    /**
     * @param int $receptionDate
     */
    public function setReceptionDate($receptionDate)
    {
        $this->receptionDate = $receptionDate;
    }

    /**
     * @param string $documentName
     */
    public function setDocumentName($documentName)
    {
        $this->documentName = $documentName;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @param string $ocsDavUrl
     */
    public function setOcsDavUrl($ocsDavUrl)
    {
        $this->ocsDavUrl = $ocsDavUrl;
    }

    /**
     * @param boolean $hasPassword
     */
    public function setHasPassword($hasPassword)
    {
        $this->hasPassword = $hasPassword;
    }

    /**
     * @param null|string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @param int $answerDate
     */
    public function setAnswerDate($answerDate)
    {
        $this->answerDate = $answerDate;
    }

    /**
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }


}