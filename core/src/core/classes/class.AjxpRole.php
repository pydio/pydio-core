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
 * Authentication "role" concept : set of permissions that can be applied to
 * one or more users, plus set of actions to be disabled.
 * @package Pydio
 * @subpackage Core
 * @deprecated use AJXP_Role instead. Still present in package for automatic migration.
 */
class AjxpRole implements AjxpGroupPathProvider
{
    private $id;
    private $rights = array();
    private $default = false;
    /**
     * @var String
     */
    protected $groupPath;
    /**
     * Constructor
     * @param string $id
     * @return void
     */
    public function AjxpRole($id)
    {
        $this->id = $id;
    }
    /**
     * @param $id
     * @return void
     */
    public function setId($id)
    {
        $this->id = $id;
    }
    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * Whether this role can read the given repo
     * @param string $rootDirId Repository ID
     * @return bool
     */
    public function canRead($rootDirId)
    {
        $right = $this->getRight($rootDirId);
        if($right == "rw" || $right == "r") return true;
        return false;
    }

    /**
     * Whether this role can write the given repo
     * @param string $rootDirId Repository ID
     * @return bool
     */
    public function canWrite($rootDirId)
    {
        $right = $this->getRight($rootDirId);
        if($right == "rw" || $right == "w") return true;
        return false;
    }

    /**
     * Current definitioon (r, rw, w, empty string) for the given repo
     * @param string $rootDirId Repository ID
     * @return string
     */
    public function getRight($rootDirId)
    {
        if(isSet($this->rights[$rootDirId])) return $this->rights[$rootDirId];
        return "";
    }

    /**
     * Set the right
     * @param string $rootDirId Repo id
     * @param string $rightString ("r", "rw", "w", "")
     * @return void
     */
    public function setRight($rootDirId, $rightString)
    {
        $this->rights[$rootDirId] = $rightString;
    }
    /**
     * Remove a right entry for the repository
     * @param string $rootDirId
     * @return void
     */
    public function removeRights($rootDirId)
    {
        if(isSet($this->rights[$rootDirId])) unset($this->rights[$rootDirId]);
    }
    /**
     * Remove all rights
     * @return void
     */
    public function clearRights()
    {
        $this->rights = array();
    }
    /**
     * Get the specific actions rights (see setSpecificActionsRights)
     * @param $rootDirId
     * @return array
     */
    public function getSpecificActionsRights($rootDirId)
    {
        $res = array();
        if ($rootDirId."" != "ajxp.all") {
            $res = $this->getSpecificActionsRights("ajxp.all");
        }
        if (isSet($this->rights["ajxp.actions"]) && isSet($this->rights["ajxp.actions"][$rootDirId])) {
            $res = array_merge($res, $this->rights["ajxp.actions"][$rootDirId]);
        }
        return $res;
    }
    /**
     * This method allows to specifically disable some actions for a given role for one or more repository.
     * @param string $rootDirId Repository id or "ajxp.all" for all repositories
     * @param string $actionName
     * @param bool $allowed
     * @return void
     */
    public function setSpecificActionRight($rootDirId, $actionName, $allowed)
    {
        if(!isSet($this->rights["ajxp.actions"])) $this->rights["ajxp.actions"] = array();
        if(!isset($this->rights["ajxp.actions"][$rootDirId])) $this->rights["ajxp.actions"][$rootDirId] = array();
        $this->rights["ajxp.actions"][$rootDirId][$actionName] = $allowed;
    }

    public function setDefault($default)
    {
        $this->default = $default;
    }

    public function isDefault()
    {
        return $this->default;
    }

    /**
     * @param String $groupPath
     */
    public function setGroupPath($groupPath)
    {
        $this->groupPath = $groupPath;
    }

    /**
     * @return String
     */
    public function getGroupPath()
    {
        return $this->groupPath;
    }

}
