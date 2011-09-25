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
 * @package info.ajaxplorer.core
 * @class AjxpRole
 * Authentication "role" concept : set of permissions that can be applied to
 * one or more users.
 */
class AjxpRole
{
	private $id;
	private $rights = array();
	
	function AjxpRole($id){
		$this->id = $id;
	}
	
	function setId($id){
		$this->id = $id;
	}
	
	function getId(){
		return $this->id;
	}
	
	function canRead($rootDirId){
		$right = $this->getRight($rootDirId);
		if($right == "rw" || $right == "r") return true;
		return false;
	}
	
	function canWrite($rootDirId){
		$right = $this->getRight($rootDirId);
		if($right == "rw" || $right == "w") return true;
		return false;
	}	
	
	function getRight($rootDirId){
		if(isSet($this->rights[$rootDirId])) return $this->rights[$rootDirId];
		return "";
	}
	
	function setRight($rootDirId, $rightString){
		$this->rights[$rootDirId] = $rightString;
	}
	
	function removeRights($rootDirId){
		if(isSet($this->rights[$rootDirId])) unset($this->rights[$rootDirId]);
	}
	
	function clearRights(){
		$this->rights = array();
	}
	
	function getSpecificActionsRights($rootDirId){
		$res = array();
		if($rootDirId."" != "ajxp.all"){
			$res = $this->getSpecificActionsRights("ajxp.all");
		}
		if(isSet($this->rights["ajxp.actions"]) && isSet($this->rights["ajxp.actions"][$rootDirId])){
			$res = array_merge($res, $this->rights["ajxp.actions"][$rootDirId]);
		}
		return $res;
	}
	
	function setSpecificActionRight($rootDirId, $actionName, $allowed){		
		if(!isSet($this->rights["ajxp.actions"])) $this->rights["ajxp.actions"] = array();
		if(!isset($this->rights["ajxp.actions"][$rootDirId])) $this->rights["ajxp.actions"][$rootDirId] = array();
		$this->rights["ajxp.actions"][$rootDirId][$actionName] = $allowed;
	}
		
}

?>