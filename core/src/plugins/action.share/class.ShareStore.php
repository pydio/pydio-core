<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

defined('AJXP_EXEC') or die( 'Access not allowed');

require_once("class.PublicletCounter.php");

class ShareStore {

    var $sqlSupported = false;
    var $downloadFolder;
    var $hashMinLength;
    /**
     * @var sqlConfDriver
     */
    var $confStorage;

    public function __construct($downloadFolder, $hashMinLength = 32){
        $this->downloadFolder = $downloadFolder;
        $this->hashMinLength = $hashMinLength;
        $storage = ConfService::getConfStorageImpl();
        if($storage->getId() == "conf.sql") {
            $this->sqlSupported = true;
            $this->confStorage = $storage;
        }
    }

    private function createGenericLoader(){
        if(!is_file($this->downloadFolder."/share.php")){
            $loader_content = '<'.'?'.'php
                    $hash = $_GET["hash"];
                    if(file_exists($hash.".php")){
                        require_once($hash.".php");
                    }else{
                        require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/publicLet.inc.php");
                        ShareCenter::loadShareByHash($_GET["hash"]);
                    }
                ';
            if (@file_put_contents($this->downloadFolder."/share.php", $loader_content) === FALSE) {
                throw new Exception("Can't write to PUBLIC URL");
            }
        }
    }

    /**
     * @param Array $shareData
     * @param string $type
     * @return string $hash
     * @throws Exception
     */
    public function storeShare($shareData, $type="minisite"){

        $data = serialize($shareData);
        $hash = $this->computeHash($data, $this->downloadFolder);
        if($this->sqlSupported){
            $this->createGenericLoader();
            $shareData["SHARE_TYPE"] = $type;
            $this->confStorage->simpleStoreSet("share", $hash, $shareData, "serial");
            return $hash;
        }

        $loader = 'ShareCenter::loadMinisite($data);';
        if($type == "publiclet"){
            $loader = 'ShareCenter::loadPubliclet($data);';
        }

        $outputData = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $hash, $data, MCRYPT_MODE_ECB));
        $fileData = "<"."?"."php \n".
            '   require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/publicLet.inc.php"); '."\n".
            '   $id = str_replace(".php", "", basename(__FILE__)); '."\n". // Not using "" as php would replace $ inside
            '   $cypheredData = base64_decode("'.$outputData.'"); '."\n".
            '   $inputData = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $id, $cypheredData, MCRYPT_MODE_ECB), "\0");  '."\n".
            '   if (!ShareCenter::checkHash($inputData, $id)) { header("HTTP/1.0 401 Not allowed, script was modified"); exit(); } '."\n".
            '   // Ok extract the data '."\n".
            '   $data = unserialize($inputData); '.$loader;
        if (@file_put_contents($this->downloadFolder."/".$hash.".php", $fileData) === FALSE) {
            throw new Exception("Can't write to PUBLIC URL");
        }
        @chmod($this->downloadFolder."/".$hash.".php", 0755);
        return $hash;

    }

    public function loadShare($hash){

        $dlFolder = $this->downloadFolder;
        $file = $dlFolder."/".$hash.".php";
        if(!is_file($file)) {
            if($this->sqlSupported){
                $this->confStorage->simpleStoreGet("share", $hash, "serial", $data);
                if(!empty($data)){
                    $data["DOWNLOAD_COUNT"] = PublicletCounter::getCount($hash);
                    return $data;
                }
            }
            return array();
        }
        $lines = file($file);
        $inputData = '';
        // Necessary for the eval
        $id = $hash;
        $code = $lines[3] . $lines[4] . $lines[5];
        eval($code);
        if(empty($inputData)) return false;
        $dataModified = $this->checkHash($inputData, $hash); //(md5($inputData) != $id);
        $publicletData = unserialize($inputData);
        $publicletData["SECURITY_MODIFIED"] = $dataModified;
        if (!isSet($publicletData["REPOSITORY"])) {
            $publicletData["DOWNLOAD_COUNT"] = PublicletCounter::getCount($hash);
        }
        $publicletData["PUBLICLET_PATH"] = $file;
        if($this->sqlSupported){
            // Move old file to DB-storage
            $type = (isset($publicletData["REPOSITORY"]) ? "minisite" : "publiclet");
            $this->createGenericLoader();
            $shareData["SHARE_TYPE"] = $type;
            $this->confStorage->simpleStoreSet("share", $hash, $publicletData, "serial");
            unlink($file);
        }

        return $publicletData;

    }

    public function listShares($limitToUser = '', $cursor = null){

        // Get DB files
        $dbLets = $this->confStorage->simpleStoreList("share", $cursor, "", "serial", (!empty($limitToUser)?'%"OWNER_ID";s:'.strlen($limitToUser).':"'.$limitToUser.'"%':''));

        // Get hardcoded files
        $files = glob(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")."/*.php");
        if($files === false) return $dbLets;
        foreach ($files as $file) {
            if(basename($file) == "share.php") continue;
            $ar = explode(".", basename($file));
            $id = array_shift($ar);
            $publicletData = $this->loadShare($id);
            if($publicletData === false) continue;
            if ($limitToUser && ( !isSet($publicletData["OWNER_ID"]) || $publicletData["OWNER_ID"] != $limitToUser )) {
                continue;
            }
            $dbLets[$id] = $publicletData;
        }

        return $dbLets;
    }

    /**
     * @param String $type
     * @param String $element
     * @param AbstractAjxpUser $loggedUser
     * @throws Exception
     */
    public function deleteShare($type, $element, $loggedUser)
    {
        $mess = ConfService::getMessages();
        $shareCenter = AJXP_PluginsService::getInstance()->findPluginById("action.share");
        AJXP_Logger::debug(__CLASS__, __FILE__, "Deleting shared element ".$type."-".$element);
        if ($type == "repository") {
            $repo = ConfService::getRepositoryById($element);
            if($repo == null) return;
            if (!$repo->hasOwner() || $repo->getOwner() != $loggedUser->getId()) {
                throw new Exception($mess["ajxp_shared.12"]);
            } else {
                $res = ConfService::deleteRepository($element);
                if ($res == -1) {
                    throw new Exception($mess["ajxp_conf.51"]);
                }
            }
        } else if ($type == "minisite") {
            $minisiteData = $this->loadShare($element);
            $repoId = $minisiteData["REPOSITORY"];
            $repo = ConfService::getRepositoryById($repoId);
            if ($repo == null) {
                return false;
            }
            if (!$repo->hasOwner() || $repo->getOwner() != $loggedUser->getId()) {
                throw new Exception($mess["ajxp_shared.12"]);
            } else {
                $res = ConfService::deleteRepository($repoId);
                if ($res == -1) {
                    throw new Exception($mess["ajxp_conf.51"]);
                }
                // Silently delete corresponding role if it exists
                AuthService::deleteRole("AJXP_SHARED-".$repoId);
                // If guest user created, remove it now.
                if (isSet($minisiteData["PRELOG_USER"])) {
                    AuthService::deleteUser($minisiteData["PRELOG_USER"]);
                }
                if(isSet($minisiteData["PUBLICLET_PATH"]) && is_file($minisiteData["PUBLICLET_PATH"])){
                    unlink($minisiteData["PUBLICLET_PATH"]);
                }else if($this->sqlSupported){
                    $this->confStorage->simpleStoreClear("share", $element);
                }
            }
        } else if ($type == "user") {
            $confDriver = ConfService::getConfStorageImpl();
            $object = $confDriver->createUserObject($element);
            if (!$object->hasParent() || $object->getParent() != $loggedUser->getId()) {
                throw new Exception($mess["ajxp_shared.12"]);
            } else {
                AuthService::deleteUser($element);
            }
        } else if ($type == "file") {
            $publicletData = $this->loadShare($element);
            if (isSet($publicletData["OWNER_ID"]) && $publicletData["OWNER_ID"] == $loggedUser->getId()) {
                PublicletCounter::delete($element);
                if(isSet($minisiteData["PUBLICLET_PATH"]) && is_file($minisiteData["PUBLICLET_PATH"])){
                    unlink($minisiteData["PUBLICLET_PATH"]);
                }else if($this->sqlSupported){
                    $this->confStorage->simpleStoreClear("share", $element);
                }
            } else {
                throw new Exception($mess["ajxp_shared.12"]);
            }
        }
    }


    public function shareExists($type, $element)
    {
        if ($type == "repository") {
            return (ConfService::getRepositoryById($element) != null);
        } else if ($type == "file" || $type == "minisite") {
            $fileExists = is_file($this->downloadFolder."/".$element.".php");
            if($fileExists) {
                return true;
            }
            if($this->sqlSupported) {
                $this->confStorage->simpleStoreGet("share", $element, "serial", $data);
                if(is_array($data)) return true;
            }
            return false;
        }
    }


    public function getCurrentDownloadCounter($hash){
        return PublicletCounter::getCount($hash);
    }

    public function incrementDownloadCounter($hash){
        PublicletCounter::increment($hash);
    }

    public function resetDownloadCounter($hash){
        PublicletCounter::reset($hash);
    }

    public function isShareExpired($hash, $data){
        return ($data["EXPIRE_TIME"] && time() > $data["EXPIRE_TIME"]) ||
        ($data["DOWNLOAD_LIMIT"] && $data["DOWNLOAD_LIMIT"]> 0 && $data["DOWNLOAD_LIMIT"] <= $this->getCurrentDownloadCounter($hash));
    }



    /**
     * Computes a short form of the hash, checking if it already exists in the folder,
     * in which case it increases the hashlength until there is no collision.
     * @static
     * @param String $outputData Serialized data
     * @param String|null $checkInFolder Path to folder
     * @return string
     */
    private function computeHash($outputData, $checkInFolder = null)
    {
        $length = $this->hashMinLength;
        $full =  md5($outputData);
        $starter = substr($full, 0, $length);
        if ($checkInFolder != null) {
            while (file_exists($checkInFolder.DIRECTORY_SEPARATOR.$starter.".php")) {
                $length ++;
                $starter = substr($full, 0, $length);
            }
        }
        return $starter;
    }

    /**
     * Check if the hash seems to correspond to the serialized data.
     * @static
     * @param String $outputData serialized data
     * @param String $hash Id to check
     * @return bool
     */
    private function checkHash($outputData, $hash)
    {
        $full = md5($outputData);
        return (!empty($hash) && strpos($full, $hash."") === 0);
    }


}