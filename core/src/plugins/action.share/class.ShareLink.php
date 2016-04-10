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
    protected $hash;

    /**
     * @var ShareStore
     */
    protected $store;

    /**
     * @var array
     */
    protected $internal;

    /**
     * @var string
     */
    protected $newHash;

    /**
     * @var array
     */
    protected $additionalMeta;

    /**
     * @var string
     */
    protected $parentRepositoryId;

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

    public function getRepositoryId(){
        return $this->internal["REPOSITORY"];
    }

    /**
     * @return array
     */
    public function getAdditionalMeta()
    {
        return $this->additionalMeta;
    }

    /**
     * @param array $additionalMeta
     */
    public function setAdditionalMeta($additionalMeta)
    {
        $this->additionalMeta = $additionalMeta;
    }

    /**
     * @return Repository
     * @throws Exception
     */
    public function getRepository(){
        if(isSet($this->internal["REPOSITORY"])){
            return ConfService::getRepositoryById($this->internal["REPOSITORY"]);
        }else{
            $mess = ConfService::getMessages();
            throw new Exception(str_replace('%s', 'No repository attached to link', $mess["share_center.219"]));
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
            if(strlen($value) < $this->store->hashMinLength){
                $mess = ConfService::getMessages();
                throw new Exception(str_replace("%s", $this->store->hashMinLength, $mess["share_center.223"]));
            }
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
     * @param PublicAccessManager $publicAccessManager
     * @param array $messages
     * @return mixed
     */
    public function getJsonData($publicAccessManager, $messages){

        $storedData = $this->internal;
        $minisiteIsPublic = isSet($storedData["PRELOG_USER"]);
        $dlDisabled = isSet($storedData["DOWNLOAD_DISABLED"]) && $storedData["DOWNLOAD_DISABLED"] === true;
        $shareMeta = isSet($this->additionalMeta) ? $this->additionalMeta : array();
        $internalUserId = (isSet($storedData["PRELOG_USER"]) ? $storedData["PRELOG_USER"] : $storedData["PRESET_LOGIN"]);
        if(empty($internalUserId)){
            throw new Exception("Oups, link ".$this->getHash()." has no internal user id, this is not normal.");
        }

        $jsonData = array(
            "public"            => $minisiteIsPublic?"true":"false",
            "disable_download"  => $dlDisabled,
            "hash"              => $this->getHash(),
            "hash_is_shorten"   => isSet($shareMeta["short_form_url"]),
            "internal_user_id"   => $internalUserId
        );

        if(!isSet($storedData["TARGET"]) || $storedData["TARGET"] == "public"){
            if (isSet($shareMeta["short_form_url"])) {
                $jsonData["public_link"] = $shareMeta["short_form_url"];
            } else {
                $jsonData["public_link"] = $publicAccessManager->buildPublicLink($this->getHash());
            }
        }

        if(!empty($storedData["DOWNLOAD_LIMIT"]) && !$dlDisabled){
            $jsonData["download_counter"] = $this->store->getCurrentDownloadCounter($this->getHash());
            $jsonData["download_limit"] = $storedData["DOWNLOAD_LIMIT"];
        }
        if(!empty($storedData["EXPIRE_TIME"])){
            $delta = $storedData["EXPIRE_TIME"] - time();
            $days = round($delta / (60*60*24));
            $jsonData["expire_time"] = date($messages["date_format"], $storedData["EXPIRE_TIME"]);
            $jsonData["expire_after"] = $days;
        }else{
            $jsonData["expire_after"] = 0;
        }
        $jsonData["is_expired"] = $this->store->isShareExpired($this->getHash(), $storedData);
        if(isSet($storedData["AJXP_TEMPLATE_NAME"])){
            $jsonData["minisite_layout"] = $storedData["AJXP_TEMPLATE_NAME"];
        }
        if(!$minisiteIsPublic){
            $jsonData["has_password"] = true;
        }
        foreach($this->store->modifiableShareKeys as $key){
            if(isSet($storedData[$key])) $jsonData[$key] = $storedData[$key];
        }

        return $jsonData;

    }

    /**
     * @param string $ownerId
     */
    public function setOwnerId($ownerId){
        if(!empty($ownerId)){
            $this->internal["OWNER_ID"] = $ownerId;
        }
    }

    public function getOwnerId(){
        return $this->internal["OWNER_ID"];
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
        return $this->internal["DOWNLOAD_DISABLED"];
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