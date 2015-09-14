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


class AJXP_Permission
{
    const READ = "r";
    const WRITE = "w";
    const DENY = "d";

    private $value = array(
        self::WRITE => false,
        self::READ => false,
        self::DENY => false
    );

    /**
     * @param array|null $value
     */
    function __construct($value = null){
        if($value != null){
            if(is_array($value)) $this->value = $value;
            else if(is_string($value)){
                if(strpos($value, "r") !== false) $this->setRead();
                if(strpos($value, "w") !== false) $this->setWrite();
                if(strpos($value, "d") !== false) $this->setDeny();
            }
        }
    }

    function getCopy(){
        return new AJXP_Permission($this->value);
    }

    /**
     * @return bool
     */
    function canRead(){
        return $this->value[self::READ];
    }

    /**
     * @return bool
     */
    function canWrite(){
        return $this->value[self::WRITE];
    }

    /**
     * @return bool
     */
    function denies(){
        if($this->value[self::DENY]) return true;
        if(!$this->value[self::DENY] && !$this->value[self::READ] && !$this->value[self::WRITE]){
            return true;
        }
        return false;
    }

    function testPermission($stringPerm){
        if($stringPerm == self::READ) return $this->canRead();
        else if($stringPerm == self::WRITE) return $this->canWrite();
        else{
            throw new Exception("Unimplemented permission : " . $stringPerm);
        }
    }

    function setRead($value = true){
        $this->value[self::READ] = $value;
    }
    function setWrite($value = true){
        $this->value[self::WRITE] = $value;
    }
    function setDeny($value = true){
        if($value){
            $this->value[self::WRITE] = $this->value[self::READ] = false;
            $this->value[self::DENY] = true;
        }else{
            $this->value[self::DENY] = false;
        }
    }

    /**
     * @param AJXP_Permission $perm
     * @return AJXP_Permission
     */
    function override($perm){
        $newPerm = $perm->getCopy();
        if($this->denies()){
            $newPerm->setDeny();
        }else{
            if($this->canRead()) $newPerm->setRead();
            if($this->canWrite()) $newPerm->setWrite();
        }
        return $newPerm;
    }

    function __toString(){
        if($this->denies()) {
            return "DENY";
        }else if($this->canRead() && !$this->canWrite()) {
            return "READONLY";
        }else if(!$this->canRead() && $this->canWrite()) {
            return "WRITEONLY";
        }else{
            return "READ WRITE";
        }
    }

}