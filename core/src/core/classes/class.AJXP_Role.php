<?php
/*
 * Copyright 2007-2012 Charles du Jeu <contact (at) cdujeu.me>
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

define('AJXP_VALUE_CLEAR', "AJXP_VALUE_CLEAR");
define('AJXP_REPO_SCOPE_ALL',"AJXP_REPO_SCOPE_ALL");
define('AJXP_REPO_SCOPE_SHARED',"AJXP_REPO_SCOPE_SHARED");
define('AJXP_PLUGINS_SCOPE_ALL',"plugin_all");

/**
 * @package Pydio
 * @subpackage Core
 */
class AJXP_Role implements AjxpGroupPathProvider
{

    protected $groupPath;
    protected $roleId;

    protected $acls = array();
    protected $parameters = array();
    protected $actions = array();
    protected $autoApplies = array();

    static $cypheredPassPrefix = '$pydio_password$';

    public function __construct($id)
    {
        $this->roleId = $id;
    }

    public function migrateDeprectated($repositoriesList, AjxpRole $oldRole)
    {
        $repositoriesList["ajxp.all"] = "";
        foreach ($repositoriesList as $repoId => $repoObject) {
            $right = $oldRole->getRight($repoId);
            if(!empty($right)) $this->setAcl($repoId, $right);
            $actions = $oldRole->getSpecificActionsRights($repoId);
            if (count($actions)) {
                foreach ($actions as $act => $status) {
                    if ($repoId == "ajxp.all") {
                        $this->setActionState(AJXP_PLUGINS_SCOPE_ALL, $act, AJXP_REPO_SCOPE_ALL, $status);
                    } else {
                        $this->setActionState(AJXP_PLUGINS_SCOPE_ALL, $act, $repoId, $status);
                    }
                }
            }
        }
        $this->setGroupPath($oldRole->getGroupPath());
        if ($oldRole->isDefault()) {
            $this->setAutoApplies(array("all"));
        }
    }

    public function isGroupRole()
    {
        return strpos($this->roleId, "AJXP_GRP_") === 0;
    }
    public function isUserRole()
    {
        return strpos($this->roleId, "AJXP_USER_") === 0;
    }


    /**
     * Whether this role can read the given repo
     * @param string $repositoryId Repository ID
     * @return bool
     */
    public function canRead($repositoryId)
    {
        $right = $this->getAcl($repositoryId);
        if($right == "rw" || $right == "r") return true;
        return false;
    }

    /**
     * Whether this role can write the given repo
     * @param string $repositoryId Repository ID
     * @return bool
     */
    public function canWrite($repositoryId)
    {
        $right = $this->getAcl($repositoryId);
        if($right == "rw" || $right == "w") return true;
        return false;
    }


    /**
     * @param string $repositoryId
     * @param string $rightString
     * @return void
     */
    public function setAcl($repositoryId, $rightString)
    {
        if (empty($rightString)) {
            if(isSet($this->acls[$repositoryId])) unset($this->acls[$repositoryId]);
        } else {
            $this->acls[$repositoryId] = $rightString;
        }
        return;
    }
    /**
     * @param string $repositoryId
     * @return string
     */
    public function getAcl($repositoryId)
    {
        if (isSet($this->acls[$repositoryId])) {
            return $this->acls[$repositoryId];
        }
        return "";
    }
    /**
     * @return array Associative array[REPO_ID] => RIGHT_STRING (r / w / rw / AJXP_VALUE_CLEAR)
     */
    public function listAcls()
    {
        return $this->acls;
    }

    public function clearAcls()
    {
        $this->acls = array();
    }

    /**
     * Send all role informations as an associative array
     * @return array
     */
    public function getDataArray($blurPasswords = false)
    {
        $roleData = array();
        $roleData["ACL"] = $this->listAcls();
        $roleData["ACTIONS"] = $this->listActionsStates();
        $roleData["PARAMETERS"] = $this->listParameters(false, $blurPasswords);
        $roleData["APPLIES"] = $this->listAutoApplies();
        return $roleData;
    }

    /**
     * Update the role information from an associative array
     * @see getDataArray()
     * @param array $roleData
     */
    public function bunchUpdate($roleData)
    {
        $this->acls = $roleData["ACL"];
        $this->actions = $roleData["ACTIONS"];
        $this->parameters = $roleData["PARAMETERS"];
        $this->autoApplies = $roleData["APPLIES"];

    }


    /**
     * @param string $pluginId
     * @param string $parameterName
     * @param mixed $parameterValue can be AJXP_VALUE_CLEAR (force clear previous), or empty string for clearing value (apply previous).
     * @param string|null $repositoryId
     */
    public function setParameterValue($pluginId, $parameterName, $parameterValue, $repositoryId = null)
    {
        if($repositoryId === null) $repositoryId = AJXP_REPO_SCOPE_ALL;
        if (empty($parameterValue) && $parameterValue !== false && $parameterValue !== "0") {
            if (isSet($this->parameters[$repositoryId][$pluginId][$parameterName])) {
                unset($this->parameters[$repositoryId][$pluginId][$parameterName]);
                if(!count($this->parameters[$repositoryId][$pluginId])) unset($this->parameters[$repositoryId][$pluginId]);
                if(!count($this->parameters[$repositoryId])) unset($this->parameters[$repositoryId]);
            }
        } else {
            $this->parameters = $this->setArrayValue($this->parameters, $repositoryId, $pluginId, $parameterName, $parameterValue);
        }
        return;
    }

    /**
     * @param string $pluginId
     * @param string $parameterName
     * @param string $repositoryId
     * @param mixed $parameterValue
     * @return mixed
     */
    public function filterParameterValue($pluginId, $parameterName, $repositoryId, $parameterValue)
    {
        if (isSet($this->parameters[AJXP_REPO_SCOPE_ALL][$pluginId][$parameterName])) {
            $v = $this->parameters[AJXP_REPO_SCOPE_ALL][$pluginId][$parameterName];
            if($v === AJXP_VALUE_CLEAR) return "";
            else return $this->filterCypheredPasswordValue($v);
        }
        if (isSet($this->parameters[$repositoryId][$pluginId][$parameterName])) {
            $v = $this->parameters[$repositoryId][$pluginId][$parameterName];
            if($v === AJXP_VALUE_CLEAR) return "";
            else return $this->filterCypheredPasswordValue($v);
        }
        return $parameterValue;
    }

    /**
     * @param bool $preserveCypheredPasswords
     * @param bool $blurCypheredPasswords
     * @return array Associative array of parameters : array[REPO_ID][PLUGIN_ID][PARAMETER_NAME] = PARAMETER_VALUE
     */
    public function listParameters($preserveCypheredPasswords = false, $blurCypheredPasswords = false)
    {
        if($preserveCypheredPasswords) return $this->parameters;

        $copy = $this->parameters;
        foreach($copy as $repo => &$plugs){
            foreach($plugs as $plugName => &$plugData){
                foreach($plugData as $paramName => &$paramValue){
                    $testValue = $this->filterCypheredPasswordValue($paramValue);
                    if($testValue != $paramValue){
                        if($blurCypheredPasswords) $paramValue = "__AJXP_VALUE_SET__";
                        else $paramValue = $testValue;
                    }
                }
            }
        }
        return $copy;
    }

    public function listAutoApplies()
    {
        return $this->autoApplies;
    }

    /**
     * @param String $value
     * @return String
     */
    private function filterCypheredPasswordValue($value){
        if(is_string($value) && strpos($value, self::$cypheredPassPrefix) === 0) return str_replace(self::$cypheredPassPrefix, "", $value);
        return $value;
    }

    /**
     * @param string $pluginId
     * @param string $actionName
     * @param string|null $repositoryId
     * @param string $state
     */
    public function setActionState($pluginId, $actionName, $repositoryId = null, $state = "disabled")
    {
        $this->actions = $this->setArrayValue($this->actions, $repositoryId, $pluginId, $actionName, $state);
        return;
    }

    public function listActionsStates()
    {
        return $this->actions;
    }

    /**
     * @param Repository $repository
     * @return array
     */
    public function listActionsStatesFor($repository)
    {
        $actions = array();
        if (isSet($this->actions[AJXP_REPO_SCOPE_ALL])) {
            $actions = $this->actions[AJXP_REPO_SCOPE_ALL];
        }
        if ($repository != null && isSet($this->actions[AJXP_REPO_SCOPE_SHARED]) && $repository->hasParent()) {
            $actions = array_merge($actions, $this->actions[AJXP_REPO_SCOPE_SHARED]);
        }
        if ($repository != null && isSet($this->actions[$repository->getId()])) {
            $actions = array_merge($actions, $this->actions[$repository->getId()]);
        }
        return $actions;
    }

    /**
     * @param string $pluginId
     * @param string $actionName
     * @param string $repositoryId
     * @param boolean $inputState
     * @return boolean
     */
    public function actionEnabled($pluginId, $actionName, $repositoryId, $inputState)
    {
        if (isSet($this->actions[AJXP_REPO_SCOPE_ALL][$pluginId][$actionName])) {
            return $this->actions[AJXP_REPO_SCOPE_ALL][$pluginId][$actionName] == "enabled" ? true : false ;
        }
        if (isSet($this->actions[$repositoryId][$pluginId][$actionName])) {
            return $this->actions[$repositoryId][$pluginId][$actionName]  == "enabled" ? true : false ;
        }
        return $inputState;
    }

    /**
     * @return array
     */
    public function listAllActionsStates()
    {
        return $this->actions;
    }

    /**
     * @param AJXP_Role $role
     * @return AJXP_Role
     */
    public function override(AJXP_Role $role)
    {
        $newRole = new AJXP_Role($role->getId());

        $newAcls = $this->array_merge_recursive2($role->listAcls(), $this->listAcls());
        foreach ($newAcls as $repoId => $rightString) {
            if($rightString == AJXP_VALUE_CLEAR) continue;
            $newRole->setAcl($repoId, $rightString);
        }

        $newParams = $this->array_merge_recursive2($role->listParameters(true), $this->listParameters(true));
        foreach ($newParams as $repoId => $data) {
            foreach ($data as $pluginId => $param) {
                foreach ($param as $parameterName => $parameterValue) {
                    if ($parameterValue === true || $parameterValue === false) {
                        $newRole->setParameterValue($pluginId, $parameterName, $parameterValue, $repoId);
                        continue;
                    }
                    if($parameterValue == AJXP_VALUE_CLEAR) continue;
                    $newRole->setParameterValue($pluginId, $parameterName, $parameterValue, $repoId);
                }
            }
        }

        $newActions = $this->array_merge_recursive2($role->listActionsStates(), $this->listActionsStates());
        foreach ($newActions as $repoId => $data) {
            foreach ($data as $pluginId => $action) {
                foreach ($action as $actionName => $actionState) {
                    $newRole->setActionState($pluginId, $actionName, $repoId, $actionState);
                }
            }
        }

        return $newRole;
    }

    /**
     * @param array
     * @param key1
     * @param key2
     * @param key3...
     * @param value
     */
    public function setArrayValue()
    {
        $args = func_get_args();
        $arr = $args[0]; //array_shift($args);
        $argMaxIndex = count($args)-1;
        $value = $args[$argMaxIndex]; //array_pop($args);
        $current = &$arr;
        foreach ($args as $index => $key) {
            if($index == 0) continue;
            if ($index < $argMaxIndex -1) {
                if(!isset($current[$key])) $current[$key] = array();
                $current = &$current[$key];
            } else {
                $current[$key] = $value;
                break;
            }
        }
        return $arr;
    }

    public function array_merge_recursive2($array1, $array2)
    {
        $arrays = func_get_args();
        $narrays = count($arrays);

        // check arguments
        // comment out if more performance is necessary (in this case the foreach loop will trigger a warning if the argument is not an array)
        for ($i = 0; $i < $narrays; $i ++) {
            if (!is_array($arrays[$i])) {
                // also array_merge_recursive returns nothing in this case
                trigger_error('Argument #' . ($i+1) . ' is not an array - trying to merge array with scalar! Returning null!', E_USER_WARNING);
                return;
            }
        }

        // the first array is in the output set in every case
        $ret = $arrays[0];

        // merege $ret with the remaining arrays
        for ($i = 1; $i < $narrays; $i ++) {
            foreach ($arrays[$i] as $key => $value) {
                            //if (((string) $key) === ((string) intval($key))) { // integer or string as integer key - append
                //    $ret[] = $value;
                //}
                //{ // string key - megre
                    if (is_array($value) && isset($ret[$key])) {
                        // if $ret[$key] is not an array you try to merge an scalar value with an array - the result is not defined (incompatible arrays)
                        // in this case the call will trigger an E_USER_WARNING and the $ret[$key] will be null.
                        $ret[$key] = $this->array_merge_recursive2($ret[$key], $value);
                    } else {
                        $ret[$key] = $value;
                    }
               // }
            }
        }

        return $ret;
    }

    public function setGroupPath($groupPath)
    {
        $this->groupPath = $groupPath;
    }

    public function getGroupPath()
    {
        return $this->groupPath;
    }

    public function getId()
    {
        return $this->roleId;
    }

    public function setLabel($roleLabel)
    {
        $this->setParameterValue("core.conf", "ROLE_DISPLAY_NAME", $roleLabel);
    }

    public function getLabel()
    {
        $test = $this->filterParameterValue("core.conf", "ROLE_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, $this->roleId);
        if(!empty($test)) return $test;
        return $this->roleId;
   }

    /**
     * @param array $specificRights
     */
    public function setAutoApplies($specificRights)
    {
        $this->autoApplies = $specificRights;
    }

    /**
     * @param string $specificRight
     * @return boolean
     */
    public function autoAppliesTo($specificRight)
    {
        return in_array($specificRight, $this->autoApplies);
    }

}
