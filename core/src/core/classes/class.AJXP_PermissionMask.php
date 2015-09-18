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


class AJXP_PermissionMask implements JsonSerializable
{
    /**
     * @var array
     */
    private $permissionTree = array();

    /**
     * @param array|null $serializedForm
     */
    function __construct($serializedForm = null){
        if($serializedForm != null){
            foreach($serializedForm as $path => $permissionValue){
                $path = AJXP_Utils::sanitize(AJXP_Utils::securePath($path), AJXP_SANITIZE_DIRNAME);
                if(!is_array($permissionValue) || $permissionValue["children"]) continue;
                $perm = new AJXP_Permission();
                if($permissionValue["read"]) $perm->setRead();
                if($permissionValue["write"]) $perm->setWrite();
                if($permissionValue["deny"]) $perm->setDeny();
                $this->updateBranch($path, $perm);
            }
        }
    }

    /**
     * @return array
     */
    function getTree(){
        if($this->permissionTree == null) return array();
        return $this->permissionTree;
    }

    /**
     * @param array $tree
     * @return AJXP_PermissionMask;
     */
    function updateTree($tree){
        $this->permissionTree = $tree;
        return $this;
    }

    /**
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
     * @param string $path
     * @return AJXP_PermissionMask
     */
    function deleteBranch($path){
        // Update the tree
        return $this;
    }

    /**
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
     * @param AJXP_PermissionMask $mask
     * @return AJXP_PermissionMask
     */

    function copyMask($mask){
        $this->updateTree($mask->getTree());
    }

    /**
     * @param string $test
     * @param  string $permission
     * @return bool
     */
    function match($test, $permission){
        // Check if a path has the given permission

        $pathes = $this->flattenTree();
        //print_r($pathes);
        foreach($pathes as $path => $permObject){
            if(strpos($test, $path) === 0){
//                var_dump("Test $test starts with existing path ".$path.":".$permObject);
                return $permObject->testPermission($permission);
            }
        }
        // test is not under a defined permission, check if it needs traversal
        foreach($pathes as $path => $permObject){
            if(strpos($path, $test) === 0 && !$permObject->denies()){
//                var_dump("Existing path starts with test ($test) >> ".$path.":".$permObject);
                return $permObject->testPermission($permission);
            }
        }

        // Not in any path
        return false;

    }

    /**
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
            if(is_a($value1, "AJXP_Permission") && is_a($value2, "AJXP_Permission")){
                /**
                 * @var AJXP_Permission $value2
                 */
                $result[$key] = $value2->override($value1);
            }else if(is_a($value1, "AJXP_Permission")){
                $result[$key] = $value2;
            }else if(is_a($value2, "AJXP_Permission")){
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
     * @param array|null $tree
     * @param array|null $pathes
     * @param string $currentRoot
     * @return AJXP_Permission[]
     */
    private function flattenTree($tree = null, &$pathes = null, $currentRoot=""){
        if($tree == null) $tree = $this->getTree();
        if($pathes == null) $pathes = array();
        if(!is_array($tree) || $tree == null) $tree = array();
        foreach($tree as $pathPart => $value){
            if(is_a($value, "AJXP_Permission")){
                $pathes[$currentRoot."/".$pathPart] = $value;
            }else{
                $this->flattenTree($value, $pathes, $currentRoot."/".$pathPart);
            }
        }

        return $pathes;
    }



    public function toStr($permissionTree, $level)
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
}