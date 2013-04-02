<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Implementation of the configuration driver on serial files
 * @package AjaXplorer_Plugins
 * @subpackage Boot
 */
class BootConfLoader extends AbstractConfDriver {

    private static $internalConf;
    private static $jsonConf;
    private static $jsonPath;

    private function getInternalConf(){
        if(!isSet(BootConfLoader::$internalConf)){
            if(file_exists(AJXP_CONF_PATH."/bootstrap_plugins.php")){
                include(AJXP_CONF_PATH."/bootstrap_plugins.php");
                if(isSet($PLUGINS)){
                    BootConfLoader::$internalConf = $PLUGINS;
                }
            }else{
                BootConfLoader::$internalConf = array();
            }
        }
        return BootConfLoader::$internalConf;
    }

    public function init($options){
        parent::init($options);
    }

    function _loadPluginConfig($pluginId, &$options)
    {
        $internal = self::getInternalConf();
        if($pluginId == "core.conf" && isSet($internal["CONF_DRIVER"])){
            // Reformat
            $options["UNIQUE_INSTANCE_CONFIG"] = array(
                "instance_name" => "conf.".$internal["CONF_DRIVER"]["NAME"],
                "group_switch_value" => "conf.".$internal["CONF_DRIVER"]["NAME"],
            );
            unset($internal["CONF_DRIVER"]["NAME"]);
            $options["UNIQUE_INSTANCE_CONFIG"] = array_merge($options["UNIQUE_INSTANCE_CONFIG"], $internal["CONF_DRIVER"]["OPTIONS"]);
            return;

        }else if($pluginId == "core.auth" && isSet($internal["AUTH_DRIVER"])){

            $options = $this->authLegacyToBootConf($internal["AUTH_DRIVER"]);
            return;

        }
        $jsonPath = $this->getPluginWorkDir(true)."/bootstrap.json";
        $jsonData = AJXP_Utils::loadSerialFile($jsonPath, false, "json");
        if(is_array($jsonData) && isset($jsonData[$pluginId])){
            $options = array_merge($options, $jsonData[$pluginId]);
        }
    }

    protected function authLegacyToBootConf($legacy){
        $data = array();
        $kOpts = array("LOGIN_REDIRECT","TRANSMIT_CLEAR_PASS", "AUTOCREATE_AJXPUSER");
        foreach($kOpts as $k){
            if(isSet($legacy["OPTIONS"][$k])) $data[$k] = $legacy["OPTIONS"][$k];
        }
        if($legacy["NAME"] == "multi"){
            $drivers = $legacy["OPTIONS"]["DRIVERS"];
            $master = $legacy["OPTIONS"]["MASTER_DRIVER"];
            $slave = array_pop(array_diff(array_keys($drivers), array($master)));

            $data["MULTI_MODE"] = array("instance_name" => $legacy["OPTIONS"]["MODE"]);
            $data["MULTI_USER_BASE_DRIVER"] = $legacy["OPTIONS"]["USER_BASE_DRIVER"] == $master ? "master" : ($legacy["OPTIONS"]["USER_BASE_DRIVER"] == $slave ? "slave" :  "" ) ;

            $data["MASTER_INSTANCE_CONFIG"] = array_merge($legacy["OPTIONS"]["DRIVERS"][$master]["OPTIONS"], array("instance_name" => "auth.".$master));
            $data["SLAVE_INSTANCE_CONFIG"] = array_merge($legacy["OPTIONS"]["DRIVERS"][$slave]["OPTIONS"], array("instance_name" => "auth.".$slave));

        }else{
            $data["MASTER_INSTANCE_CONFIG"] = array_merge($legacy["OPTIONS"], array("instance_name" => "auth.".$legacy["NAME"]));
        }
        return $data;
    }

    /**
     * @param String $pluginId
     * @param String $options
     */
    function _savePluginConfig($pluginId, $options)
    {
        $jsonPath = $this->getPluginWorkDir(true)."/bootstrap.json";
        $jsonData = AJXP_Utils::loadSerialFile($jsonPath, false, "json");
        if(!is_array($jsonData)) $jsonData = array();
        $jsonData[$pluginId] = $options;
        if($pluginId == "core.conf" || $pluginId == "core.auth"){
            $testKey = ($pluginId == "core.conf" ? "UNIQUE_INSTANCE_CONFIG" : "MASTER_INSTANCE_CONFIG" );
            $current = array();
            $this->_loadPluginConfig($pluginId, $current);
            if(isSet($current[$testKey]["instance_name"]) && $current[$testKey]["instance_name"] != $options[$testKey]["instance_name"]){
                $forceDisconnexion = $pluginId;
            }
        }
        AJXP_Utils::saveSerialFile($jsonPath, $jsonData, true, false, "json");
        if(isSet($forceDisconnexion)){
            if($pluginId == "core.conf"){
                // DISCONNECT
                AuthService::disconnect();
            }else if($pluginId == "core.auth"){
                // DELETE admin_counted file and DISCONNECT
                @unlink(AJXP_CACHE_DIR."/admin_counted");
            }
        }
    }

    /**
     * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
     * @param AbstractAjxpUser $user
     * @return Array
     */
    function listRepositories($user = null)
    {
        // TODO: Implement listRepositories() method.
    }

    /**
     * Retrieve a Repository given its unique ID.
     *
     * @param String $repositoryId
     * @return Repository
     */
    function getRepositoryById($repositoryId)
    {
        // TODO: Implement getRepositoryById() method.
    }

    /**
     * Retrieve a Repository given its alias.
     *
     * @param String $repositorySlug
     * @return Repository
     */
    function getRepositoryByAlias($repositorySlug)
    {
        // TODO: Implement getRepositoryByAlias() method.
    }

    /**
     * Stores a repository, new or not.
     *
     * @param Repository $repositoryObject
     * @param Boolean $update
     * @return -1 if failed
     */
    function saveRepository($repositoryObject, $update = false)
    {
        // TODO: Implement saveRepository() method.
    }

    /**
     * Delete a repository, given its unique ID.
     *
     * @param String $repositoryId
     */
    function deleteRepository($repositoryId)
    {
        // TODO: Implement deleteRepository() method.
    }

    /**
     * Must return an associative array of roleId => AjxpRole objects.
     * @param array $roleIds
     * @param boolean $excludeReserved,
     * @return array AjxpRole[]
     */
    function listRoles($roleIds = array(), $excludeReserved = false)
    {
        // TODO: Implement listRoles() method.
    }

    function saveRoles($roles)
    {
        // TODO: Implement saveRoles() method.
    }

    /**
     * @param AJXP_Role $role
     * @return void
     */
    function updateRole($role)
    {
        // TODO: Implement updateRole() method.
    }

    /**
     * @param AJXP_Role|String $role
     * @return void
     */
    function deleteRole($role)
    {
        // TODO: Implement deleteRole() method.
    }

    /**
     * Specific queries
     */
    function countAdminUsers()
    {
        // TODO: Implement countAdminUsers() method.
    }

    /**
     * @param array $context
     * @param String $fileName
     * @param String $ID
     * @return String $ID
     */
    function saveBinary($context, $fileName, $ID = null)
    {
        // TODO: Implement saveBinary() method.
    }

    /**
     * @param array $context
     * @param String $ID
     * @param Stream $outputStream
     * @return boolean
     */
    function loadBinary($context, $ID, $outputStream = null)
    {
        // TODO: Implement loadBinary() method.
    }

    /**
     * @param array $context
     * @param String $ID
     * @return boolean
     */
    function deleteBinary($context, $ID)
    {
        // TODO: Implement deleteBinary() method.
    }

    /**
     * Function for deleting a user
     *
     * @param String $userId
     * @param Array $deletedSubUsers
     */
    function deleteUser($userId, &$deletedSubUsers)
    {
        // TODO: Implement deleteUser() method.
    }

    /**
     * Instantiate the right class
     *
     * @param string $userId
     * @return AbstractAjxpUser
     */
    function instantiateAbstractUserImpl($userId)
    {
        // TODO: Implement instantiateAbstractUserImpl() method.
    }

    function getUserClassFileName()
    {
        // TODO: Implement getUserClassFileName() method.
    }

    /**
     * @param $userId
     * @return AbstractAjxpUser[]
     */
    function getUserChildren($userId)
    {
        // TODO: Implement getUserChildren() method.
    }

    /**
     * @param string $repositoryId
     * @return array()
     */
    function getUsersForRepository($repositoryId)
    {
        // TODO: Implement getUsersForRepository() method.
    }

    /**
     * @param AbstractAjxpUser[] $flatUsersList
     * @param string $baseGroup
     * @param bool $fullTree
     * @return void
     */
    function filterUsersByGroup(&$flatUsersList, $baseGroup = "/", $fullTree = false)
    {
        // TODO: Implement filterUsersByGroup() method.
    }

    /**
     * @param string $groupPath
     * @param string $groupLabel
     * @return mixed
     */
    function createGroup($groupPath, $groupLabel)
    {
        // TODO: Implement createGroup() method.
    }

    /**
     * @param $groupPath
     * @return void
     */
    function deleteGroup($groupPath)
    {
        // TODO: Implement deleteGroup() method.
    }

    /**
     * @param string $groupPath
     * @param string $groupLabel
     * @return void
     */
    function relabelGroup($groupPath, $groupLabel)
    {
        // TODO: Implement relabelGroup() method.
    }

    /**
     * @param string $baseGroup
     * @return string[]
     */
    function getChildrenGroups($baseGroup = "/")
    {
        // TODO: Implement getChildrenGroups() method.
    }
}