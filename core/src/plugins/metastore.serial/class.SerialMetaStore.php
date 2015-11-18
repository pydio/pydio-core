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
/**
 * Simple metadata implementation, stored in hidden files inside the
 * folders
 * @package AjaXplorer_Plugins
 * @subpackage Metastore
 */
class SerialMetaStore extends AJXP_AbstractMetaSource implements MetaStoreProvider
{
    private static $currentMetaName;
    private static $metaCache;
    private static $fullMetaCache;

    protected $globalMetaFile;


    public function init($options)
    {
        $this->options = $options;
        $this->loadRegistryContributions();
        $this->globalMetaFile = AJXP_DATA_PATH."/plugins/metastore.serial/ajxp_meta";
    }

    /**
     * @abstract
     * @return bool
     */
    public function inherentMetaMove()
    {
        return false;
    }


    /**
     * @param AJXP_Node $node
     * @return string
     */
    protected function getUserId($node)
    {
        if($node->hasUser()) return $node->getUser();
        if(AuthService::usersEnabled()) return AuthService::getLoggedUser()->getId();
        return "shared";
    }

    public function setMetadata($ajxpNode, $nameSpace, $metaData, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $this->loadMetaFileData(
            $ajxpNode,
            $scope,
            ($private?$this->getUserId($ajxpNode):AJXP_METADATA_SHAREDUSER)
        );
        if (!isSet(self::$metaCache[$nameSpace])) {
            self::$metaCache[$nameSpace] = array();
        }
        self::$metaCache[$nameSpace] = array_merge(self::$metaCache[$nameSpace], $metaData);
        if(is_array(self::$metaCache[$nameSpace])){
            foreach(self::$metaCache[$nameSpace] as $k => $v){
                if($v == AJXP_VALUE_CLEAR) unset(self::$metaCache[$nameSpace][$k]);
            }
        }
        $this->saveMetaFileData(
            $ajxpNode,
            $scope,
            ($private?$this->getUserId($ajxpNode):AJXP_METADATA_SHAREDUSER)
        );
    }

    public function removeMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        $this->loadMetaFileData(
            $ajxpNode,
            $scope,
            ($private?$this->getUserId($ajxpNode):AJXP_METADATA_SHAREDUSER)
        );
        if(!isSet(self::$metaCache[$nameSpace])) return;
        unset(self::$metaCache[$nameSpace]);
        $this->saveMetaFileData(
            $ajxpNode,
            $scope,
            ($private?$this->getUserId($ajxpNode):AJXP_METADATA_SHAREDUSER)
        );
    }

    public function retrieveMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY)
    {
        if($private === AJXP_METADATA_ALLUSERS){
            $userScope = AJXP_METADATA_ALLUSERS;
        }else if($private === true){
            $userScope = $this->getUserId($ajxpNode);
        }else{
            $userScope = AJXP_METADATA_SHAREDUSER;
        }
        $this->loadMetaFileData(
            $ajxpNode,
            $scope,
            $userScope
        );
        if(!isSet(self::$metaCache[$nameSpace])) return array();
        else return self::$metaCache[$nameSpace];
    }



    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    public function enrichNode(&$ajxpNode)
    {
        // Try both
        $all = array();
        $this->loadMetaFileData($ajxpNode, AJXP_METADATA_SCOPE_GLOBAL, AJXP_METADATA_SHAREDUSER);
        $all[] = self::$metaCache;
        $this->loadMetaFileData($ajxpNode, AJXP_METADATA_SCOPE_GLOBAL, $this->getUserId($ajxpNode));
        $all[] = self::$metaCache;
        $this->loadMetaFileData($ajxpNode, AJXP_METADATA_SCOPE_REPOSITORY, AJXP_METADATA_SHAREDUSER);
        $all[] = self::$metaCache;
        $this->loadMetaFileData($ajxpNode, AJXP_METADATA_SCOPE_REPOSITORY, $this->getUserId($ajxpNode));
        $all[] = self::$metaCache;
        $allMeta = array();
        foreach ($all as $metadata) {
            foreach ($metadata as $namespace => $meta) {
                foreach ($meta as $key => $value) {
                    $allMeta[$namespace."-".$key] = $value;
                }
            }
        }
        $ajxpNode->mergeMetadata($allMeta);
    }

    protected function updateSecurityScope($metaFile, $repositoryId, $resolveUserId = null)
    {
        $repo = ConfService::getRepositoryById($repositoryId);
        if (!is_object($repo)) {
            return $metaFile;
        }
        $securityScope = $repo->securityScope();
        if($securityScope == false) return $metaFile;
        if($resolveUserId != null){
            if ($securityScope == "USER") {
                $metaFile .= "_".$resolveUserId;
            }else if($securityScope == "GROUP"){
                $uObj= ConfService::getConfStorageImpl()->createUserObject($resolveUserId);
                if($uObj != null){
                    $u = str_replace("/", "__", $uObj->getGroupPath());
                    $metaFile.= "_".$u;
                }
            }
        }else if (AuthService::getLoggedUser() != null) {
            if ($securityScope == "USER") {
                $u = AuthService::getLoggedUser();
                if($u->getResolveAsParent()) $id = $u->getParent();
                else $id = $u->getId();
                $metaFile .= "_".$id;
            } else if ($securityScope == "GROUP") {
                $u = AuthService::getLoggedUser()->getGroupPath();
                $u = str_replace("/", "__", $u);
                $metaFile .= "_".$u;
            }
        }
        return $metaFile;
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @param String $scope
     * @param String $userId
     * @return void
     */
    protected function loadMetaFileData($ajxpNode, $scope, $userId)
    {
        $currentFile = $ajxpNode->getUrl();
        $fileKey = $ajxpNode->getPath();
        if($fileKey == null) $fileKey = "/";
        if (isSet($this->options["METADATA_FILE_LOCATION"]) && $this->options["METADATA_FILE_LOCATION"] == "outside") {
            // Force scope
            $scope = AJXP_METADATA_SCOPE_REPOSITORY;
        }
        if ($scope == AJXP_METADATA_SCOPE_GLOBAL) {
            $metaFile = dirname($currentFile)."/".$this->options["METADATA_FILE"];
            if (preg_match("/\.zip\//",$currentFile)) {
                self::$fullMetaCache[$metaFile] = array();
                self::$metaCache = array();
                return ;
            }
            $fileKey = basename($fileKey);
        } else {
            $metaFile = $this->globalMetaFile."_".$ajxpNode->getRepositoryId();
            $metaFile = $this->updateSecurityScope($metaFile, $ajxpNode->getRepositoryId(), $ajxpNode->getUser());
        }
        self::$metaCache = array();
        if (!isSet(self::$fullMetaCache[$metaFile])) {
            self::$currentMetaName = $metaFile;
            $rawData = @file_get_contents($metaFile);
            if ($rawData !== false) {
                self::$fullMetaCache[$metaFile] = unserialize($rawData);
            }
        }
        if (isSet(self::$fullMetaCache[$metaFile]) && is_array(self::$fullMetaCache[$metaFile])) {
            if($userId == AJXP_METADATA_ALLUSERS && is_array(self::$fullMetaCache[$metaFile][$fileKey])){
                foreach(self::$fullMetaCache[$metaFile][$fileKey] as $uId => $data){
                    self::$metaCache = array_merge_recursive(self::$metaCache, $data);
                }
            }else{
                if (isSet(self::$fullMetaCache[$metaFile][$fileKey][$userId])) {
                    self::$metaCache = self::$fullMetaCache[$metaFile][$fileKey][$userId];
                } else {
                    if ($this->options["UPGRADE_FROM_METASERIAL"] == true && count(self::$fullMetaCache[$metaFile]) && !isSet(self::$fullMetaCache[$metaFile]["AJXP_METASTORE_UPGRADED"])) {
                        self::$fullMetaCache[$metaFile] = $this->upgradeDataFromMetaSerial(self::$fullMetaCache[$metaFile]);
                        if (isSet(self::$fullMetaCache[$metaFile][$fileKey][$userId])) {
                            self::$metaCache = self::$fullMetaCache[$metaFile][$fileKey][$userId];
                        }
                        // Save upgraded version
                        file_put_contents($metaFile, serialize(self::$fullMetaCache[$metaFile]));
                    }
                }
            }
        } else {
            self::$fullMetaCache[$metaFile] = array();
            self::$metaCache = array();
        }
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @param String $scope
     * @param String $userId
     */
    protected function saveMetaFileData($ajxpNode, $scope, $userId)
    {
        $currentFile = $ajxpNode->getUrl();
        $repositoryId = $ajxpNode->getRepositoryId();
        $fileKey = $ajxpNode->getPath();
        if (isSet($this->options["METADATA_FILE_LOCATION"]) && $this->options["METADATA_FILE_LOCATION"] == "outside") {
            // Force scope
            $scope = AJXP_METADATA_SCOPE_REPOSITORY;
        }
        if ($scope == AJXP_METADATA_SCOPE_GLOBAL) {
            $metaFile = dirname($currentFile)."/".$this->options["METADATA_FILE"];
            $fileKey = basename($fileKey);
        } else {
            if (!is_dir(dirname($this->globalMetaFile))) {
                mkdir(dirname($this->globalMetaFile), 0755, true);
            }
            $metaFile = $this->globalMetaFile."_".$repositoryId;
            $metaFile = $this->updateSecurityScope($metaFile, $ajxpNode->getRepositoryId(), $ajxpNode->getUser());
        }
        if($scope==AJXP_METADATA_SCOPE_REPOSITORY
            || (@is_file($metaFile) && call_user_func(array($this->accessDriver, "isWriteable"), $metaFile))
            || call_user_func(array($this->accessDriver, "isWriteable"), dirname($metaFile)) ){
            if (is_array(self::$metaCache) && count(self::$metaCache)) {
                if (!isset(self::$fullMetaCache[$metaFile])) {
                    self::$fullMetaCache[$metaFile] = array();
                }
                if (!isset(self::$fullMetaCache[$metaFile][$fileKey])) {
                    self::$fullMetaCache[$metaFile][$fileKey] = array();
                }
                if (!isset(self::$fullMetaCache[$metaFile][$fileKey][$userId])) {
                    self::$fullMetaCache[$metaFile][$fileKey][$userId] = array();
                }
                self::$fullMetaCache[$metaFile][$fileKey][$userId] = self::$metaCache;
            } else {
                // CLEAN
                if (isset(self::$fullMetaCache[$metaFile][$fileKey][$userId])) {
                    unset(self::$fullMetaCache[$metaFile][$fileKey][$userId]);
                }
                if(isset(self::$fullMetaCache[$metaFile][$fileKey])
                    && !count(self::$fullMetaCache[$metaFile][$fileKey])){
                    unset(self::$fullMetaCache[$metaFile][$fileKey]);
                }
            }
            $fp = @fopen($metaFile, "w");
            if ($fp !== false) {
                @fwrite($fp, serialize(self::$fullMetaCache[$metaFile]), strlen(serialize(self::$fullMetaCache[$metaFile])));
                @fclose($fp);
            }else{
                $this->logError(__FUNCTION__, "Error while trying to open the meta file, maybe a permission problem?");
            }
            if ($scope == AJXP_METADATA_SCOPE_GLOBAL) {
                 AJXP_Controller::applyHook("version.commit_file", array($metaFile, $ajxpNode));
            }
        }
    }

    protected function upgradeDataFromMetaSerial($data)
    {
        $new = array();
        foreach ($data as $fileKey => $fileData) {
            $new[$fileKey] = array(AJXP_METADATA_SHAREDUSER => array( "users_meta" => $fileData ));
            $new["AJXP_METASTORE_UPGRADED"] = true;
        }
        return $new;
    }

}
