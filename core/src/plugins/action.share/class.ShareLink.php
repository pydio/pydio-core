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

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class ShareLink
 * Model for a public link
 */
class ShareLink
{
    /**
     * @var string
     */
    private $hash;

    /**
     * @var ShareStore
     */
    private $store;

    /**
     * @var array
     */
    private $internal;

    /**
     * @var string
     */
    private $newHash;

    /**
     * @var string
     */
    private $parentRepositoryId;

    public function __construct($store, $storeData = array()){
        $this->store = $store;
        $this->internal = $storeData;
    }

    /**
     * Persist the share to DB using the ShareStore
     * @return string
     * @throws Exception
     */
    public function save(){
        $newHash = $this->store->storeShare(
            $this->getParentRepositoryId(),
            $this->internal,
            "minisite",
            $this->getHash(),
            $this->getNewHash()
        );
        $this->setHash($newHash);
        return $newHash;
    }

    /**
     * @param string $hash
     */
    public function setHash($hash){
        $this->hash = $hash;
    }

    /**
     * @param string $repositoryId
     */
    public function attachToRepository($repositoryId){
        $this->internal["REPOSITORY"] = $repositoryId;
    }

    public function isAttachedToRepository(){
        return isSet($this->internal["REPOSITORY"]);
    }

    /**
     * @return Repository
     * @throws Exception
     */
    public function getRepository(){
        if(isSet($this->internal["REPOSITORY"])){
            return ConfService::getRepositoryById($this->internal["REPOSITORY"]);
        }else{
            throw new Exception("No repository attached!");
        }
    }

    /**
     * Update some internal configs from httpVars
     * @param $httpVars
     * @throws Exception
     */
    public function parseHttpVars($httpVars){

        $data = &$this->internal;
        $data["DOWNLOAD_DISABLED"] = (isSet($httpVars["simple_right_download"]) ? false : true);
        $data["AJXP_APPLICATION_BASE"] = AJXP_Utils::detectServerURL(true);
        if(isSet($httpVars["minisite_layout"])){
            $data["AJXP_TEMPLATE_NAME"] = $httpVars["minisite_layout"];
        }
        if(isSet($httpVars["expiration"])){
            if(intval($httpVars["expiration"]) > 0){
                $data["EXPIRE_TIME"] = time() + intval($httpVars["expiration"]) * 86400;
            }else if(isSet($data["EXPIRE_TIME"])) {
                unset($data["EXPIRE_TIME"]);
            }
        }
        if(isSet($httpVars["downloadlimit"])){
            if(intval($httpVars["downloadlimit"]) > 0){
                $data["DOWNLOAD_LIMIT"] = intval($httpVars["downloadlimit"]);
            }else if(isSet($data["DOWNLOAD_LIMIT"])){
                unset($data["DOWNLOAD_LIMIT"]);
            }
        }

        if(isSet($httpVars["custom_handle"]) && !empty($httpVars["custom_handle"]) &&
            (!isSet($this->hash) || $httpVars["custom_handle"] != $this->hash)){
            // Existing already
            $value = AJXP_Utils::sanitize($httpVars["custom_handle"], AJXP_SANITIZE_ALPHANUM);
            $value = strtolower($value);
            $test = $this->store->loadShare($value);
            $mess = ConfService::getMessages();
            if(!empty($test)) {
                throw new Exception($mess["share_center.172"]);
            }
            if(!isSet($this->hash)){
                $this->hash = $value;
            }else{
                $this->newHash = $value;
            }
        }

    }

    /**
     * @param string $ownerId
     */
    public function setOwnerId($ownerId){
        if(!empty($ownerId)){
            $this->internal["OWNER_ID"] = $ownerId;
        }
    }

    /**
     * Generate a random user ID. Set in PRELOG_USER or PRESET_LOGIN depending on the hasPassword value.
     * @param string $prefix
     * @param bool|false $hasPassword
     */
    public function createHiddenUserId($prefix = "", $hasPassword = false){
        $userId = substr(md5(time()), 0, 12);
        if (!empty($prefix)) {
            $userId = $prefix.$userId;
        }
        $this->setUniqueUser($userId, $hasPassword);
    }

    /**
     * Generate a random password
     * @return string
     */
    public function createHiddenUserPassword(){
        return $userPass = substr(md5(time()), 13, 24);
    }

    /**
     * @return string
     */
    public function getUniqueUser(){
        if(isSet($this->internal["PRELOG_USER"])) return $this->internal["PRELOG_USER"];
        else return $this->internal["PRESET_LOGIN"];
    }

    /**
     * @param string $userId
     * @param bool|false $requirePassword
     */
    public function setUniqueUser($userId, $requirePassword = false){
        if(isSet($this->internal["PRELOG_USER"])) unset($this->internal["PRELOG_USER"]);
        if(isSet($this->internal["PRESET_LOGIN"])) unset($this->internal["PRESET_LOGIN"]);
        if($requirePassword){
            $this->internal["PRESET_LOGIN"] = $userId;
        }else{
            $this->internal["PRELOG_USER"] = $userId;
        }
    }

    /**
     * @return bool
     */
    public function shouldRequirePassword(){
        return isSet($this->internal["PRESET_LOGIN"]);
    }

    /**
     * @return bool
     */
    public function disableDownload(){
        return $this->internal["DISABLE_DOWNLOAD"];
    }

    /**
     * @return string
     */
    public function getApplicationBase(){
        return $this->internal["AJXP_APPLICATION_BASE"];
    }

    /**
     * @return array
     */
    public function getData(){
        return $this->internal;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @return string
     */
    public function getNewHash()
    {
        return $this->newHash;
    }

    /**
     * @return string
     */
    public function getParentRepositoryId()
    {
        return $this->parentRepositoryId;
    }

    /**
     * @param string $parentRepositoryId
     */
    public function setParentRepositoryId($parentRepositoryId)
    {
        $this->parentRepositoryId = $parentRepositoryId;
    }

}