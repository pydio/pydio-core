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
namespace Pydio\Access\Core\Filter;

use Pydio\Core\Utils\Vars\InputFilter;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class AJXP_PermissionMask
 *
 * Stores a mapping of path => AJXP_Permission that can be used as a mask on an existing folder structure.
 *
 * @package Pydio
 * @subpackage Core
 *
 */
class AJXP_PermissionMask implements \JsonSerializable, \Serializable
{
    /**
     * @var array
     */
    private $permissionTree = array();

    /**
     * Initialize an empty mask, or from a serializedForm.
     * @param array|null $serializedForm
     */
    function __construct($serializedForm = null){
        if($serializedForm != null){
            foreach($serializedForm as $path => $permissionValue){
                $path = InputFilter::sanitize(InputFilter::securePath($path), InputFilter::SANITIZE_DIRNAME);
                if(!is_array($permissionValue) || $permissionValue["children"]) continue;
                $perm = new AJXP_Permission();
                if($permissionValue["read"]) $perm->setRead();
                if($permissionValue["write"]) $perm->setWrite();
                if($permissionValue["deny"]) $perm->setDeny();
                if($perm->isEmpty()) continue;
                $this->updateBranch($path, $perm);
            }
        }
    }

    /**
     * Returns the whole permission tree
     * @return array
     */
    function getTree(){
        if($this->permissionTree == null) return array();
        return $this->permissionTree;
    }

    /**
     * Set the permision tree at once
     * @param array $tree
     * @return AJXP_PermissionMask;
     */
    function updateTree($tree){
        $this->permissionTree = $tree;
        return $this;
    }

    /**
     * Add a branch in the permissions tree
     * @param string $path
     * @param AJXP_Permission $permission
     * @return AJXP_PermissionMask
     */
    function updateBranch($path, $permission){
        // Update the tree
        $this->permissionTree = $this->mergeTrees($this->permissionTree, $this->pathToBranch($path, $permission));
        return $this;
    }

    /**
     * Delete a branch from the permission tree
     * @param string $path
     * @return AJXP_PermissionMask
     */
    function deleteBranch($path){
        // Update the tree
        return $this;
    }

    /**
     * Merge the current permission tree with the one passed in parameter. The latter takes precedence on the first.
     * @param AJXP_PermissionMask $mask
     * @return AJXP_PermissionMask
     */
    function override($mask){
        // Return a new mask
        $newMask = new AJXP_PermissionMask();
        $newMask->updateTree($this->mergeTrees($this->permissionTree, $mask->getTree()));
        return $newMask;
    }

    /**
     * Copy the full tree from the one passed in parameter
     * @param AJXP_PermissionMask $mask
     * @return AJXP_PermissionMask
     */

    function copyMask($mask){
        $this->updateTree($mask->getTree());
    }

    /**
     * Test if a given path does have the given permission according to this mask permission tree.
     * @param string $test
     * @param  string $permission
     * @return bool
     */
    function match($test, $permission){
        // Check if a path has the given permission
        $pathes = $this->flattenTree();
        if (is_array($pathes) && !count($pathes)) return true;
        
        if(empty($test) || $test == "/" || $test == "/." || $test == "/..") {
            if(!count($pathes)) return true;
            if(isSet($pathes["/"])) {
                $permObject = $pathes["/"];
                // If not read or write, must be read at least for root
                if($permObject->denies()) $permObject->setRead(true);
                return $permObject->testPermission($permission);
            }
            if($permission == AJXP_Permission::READ) return true;
            else if($permission == AJXP_Permission::WRITE) return false;
            return true;
        }

        foreach($pathes as $path => $permObject){
            if(strpos($test, rtrim($path, "/")."/") === 0 || $test === $path){
                return $permObject->testPermission($permission);
            }
        }
        // test is not under a defined permission, check if it needs traversal
        foreach($pathes as $path => $permObject){
            if(strpos($path, $test) === 0 && !$permObject->denies()){
                if($permission == AJXP_Permission::READ) return true;
                else if($permission == AJXP_Permission::WRITE) return false;
                return $permObject->testPermission($permission);
            }
        }

        // Not in any path
        return false;

    }

    /**
     * Merge two trees
     * @param array $t1
     * @param array $t2
     * @return array
     */
    private function mergeTrees($t1, $t2){
        $result = $t1;
        foreach($t2 as $key => $value2){
            if(!isset($t1[$key])){
                // Add branch
                $result[$key] = $value2;
                continue;
            }
            $value1 = $t1[$key];
            if($value1 instanceof AJXP_Permission && $value2 instanceof AJXP_Permission){
                /**
                 * @var AJXP_Permission $value2
                 */
                $result[$key] = $value2->override($value1);
            }else if($value1 instanceof AJXP_Permission){
                $result[$key] = $value2;
            }else if($value2 instanceof AJXP_Permission){
                /**
                 * @var AJXP_Permission $value2
                 */
                //if($value2->denies()) {
                    $result[$key] = $value2;
                //} else{
                    // do nothing, keep original branch
                //}
            }else{
                // Two arrays
                $result[$key] = $this->mergeTrees($value1, $value2);
            }
        }
        return $result;
    }

    /**
     * Translate a path=> AJXP_Permission to an array-based branch.
     * @param string $path
     * @param AJXP_Permission $permission
     * @return array
     */
    private function pathToBranch($path, $permission){
        $parts = explode("/", trim($path, "/"));
        $l = count($parts);
        $br = array();
        $current = &$br;
        for($i=0;$i<$l; $i++){
            if($i < $l - 1){
                $current[$parts[$i]] = array();
                $current = &$current[$parts[$i]];
            }else{
                $current[$parts[$i]] = $permission;
            }
        }
        return $br;
    }

    /**
     * Transform the permission tree into a flat structure of pathes => permissions.
     * @param array|null $tree
     * @param array|null $pathes
     * @param string $currentRoot
     * @return AJXP_Permission[]
     */
    public function flattenTree($tree = null, &$pathes = null, $currentRoot=""){
        if($tree == null) $tree = $this->getTree();
        if($pathes == null) $pathes = array();
        if(!is_array($tree) || $tree == null) $tree = array();
        foreach($tree as $pathPart => $value){
            if($value instanceof AJXP_Permission){
                $pathes[$currentRoot."/".$pathPart] = $value;
            }else{
                $this->flattenTree($value, $pathes, $currentRoot."/".$pathPart);
            }
        }

        return $pathes;
    }


    /**
     * Print the tree as a string (for debug puprpose).
     * @param $permissionTree
     * @param $level
     */
    public function toStr($permissionTree, &$level)
    {
        $level = $level + 1;
        foreach ($permissionTree as $key => $node) {

            $this->printSpace($level);
            echo "[" . $key . "]";
            if ($node instanceof AJXP_Permission) echo "(".$node.")";

            echo "\n";
            if (is_array($node) && count($node) > 0) {
                $permissionTree = $node;
                $this->toStr($permissionTree, $level);
            }

        }
        $level = $level - 1;
        //echo "\n";
    }

    /**
     * @param int $number
     */
    public function printSpace($number){
        for ($i = 0; $i < $number; $i++){
            echo "--";
        }
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        return $this->flattenTree();
    }

    /**
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     * @since 5.1.0
     */
    public function serialize()
    {
        return serialize($this->permissionTree);
    }

    /**
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        $this->permissionTree = unserialize($serialized);
    }
}