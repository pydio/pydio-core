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
 * @package info.ajaxplorer.plugins
 * Implementation of the configuration driver on serial files
 */
class BootConfLoader extends AbstractConfDriver {

    private static $internalConf;
    private static $jsonConf;
    private static $jsonPath;

    private function getInternalConf(){
        if(!isSet(BootConfLoader::$internalConf)){
            include(AJXP_INSTALL_PATH."/conf/bootstrap_plugins.php");
            if(isSet($PLUGINS)){
                BootConfLoader::$internalConf = $PLUGINS;
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
            );
            unset($internal["CONF_DRIVER"]["NAME"]);
            $options["UNIQUE_INSTANCE_CONFIG"] = array_merge($options["UNIQUE_INSTANCE_CONFIG"], $internal["CONF_DRIVER"]["OPTIONS"]);
        }
        $jsonPath = $this->getPluginWorkDir(true)."/bootstrap.json";
        $jsonData = AJXP_Utils::loadSerialFile($jsonPath, false, "json");
        if(is_array($jsonData) && isset($jsonData[$pluginId])){
            $options = array_merge($options, $jsonData[$pluginId]);
        }
    }

    /**
     * @param String $pluginId
     * @param String $options
     */
    function savePluginConfig($pluginId, $options)
    {
        $jsonPath = $this->getPluginWorkDir(true)."/bootstrap.json";
        $jsonData = AJXP_Utils::loadSerialFile($jsonPath, false, "json");
        if(!is_array($jsonData)) $jsonData = array();
        $jsonData[$pluginId] = $options;
        AJXP_Utils::saveSerialFile($jsonPath, $jsonData, true, false, "json");
    }

    /**
     * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
     * @return Array
     */
    function listRepositories()
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