<?php
/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Abstract representation of an access to FEU athentification module(CMS Made Simple).
 */
defined('AJXP_EXEC') or die( 'Access not allowed');
require_once(INSTALL_PATH."/server/classes/class.AbstractAuthDriver.php");
require_once(INSTALL_PATH."/server/classes/dibi.compact.php");
class cmsmsAuthDriver extends AbstractAuthDriver {
	
	var $sqlDriver;
	var $driverName = "cmsms";	
	
	function init($options){
		parent::init($options);
		$this->sqlDriver = $options["SQL_DRIVER"];
		try {
			dibi::connect($this->sqlDriver);		
		} catch (DibiException $e) {
			echo get_class($e), ': ', $e->getMessage(), "\n";
			exit(1);
		}
		$this->prefix = $options["PREFIX_TABLE"];
		$this->groupid = $options["FEU_GROUPID"];
		$this->usersSerFile = $options["USERS_FILEPATH"];

	}
			
	function listUsers(){
		$res = dibi::query("SELECT * FROM [".$this->prefix."module_feusers_users], [".$this->prefix."module_feusers_belongs] WHERE id = userid AND groupid=".$this->groupid);
		$pairs = $res->fetchPairs('username', 'password');
		return $pairs;
	}
	
	function userExists($login){
		$res = dibi::query("SELECT * FROM [".$this->prefix."module_feusers_users], [".$this->prefix."module_feusers_belongs] WHERE [username]=%s AND id = userid AND groupid=%s", $login, $this->groupid);
		return($res->getRowCount());
	}	
	
	function checkPassword($login, $pass, $seed){
		$userStoredPass = $this->getUserPass($login);
		if(!$userStoredPass) return false;		
		// jcg on ne check le password uniquement pour se connecter.
		// donc je fais la maj de la bdd feuusers_loggedin ici.
		if(md5($pass) == $userStoredPass) {
		$loggedinData['sessionid']=session_id();
		$loggedinData['lastused']=time;
		$loggedinData['userid']=$this->getUserId($login);
		dibi::query('INSERT INTO ['.$this->prefix.'module_feusers_loggedin]', $loggedinData);
		}

		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){ // Seed = -1 means that password is not encoded.
			return ($userStoredPass == md5($pass));
		}else{
			return (md5($userStoredPass.$seed) == $pass);
		}
	}
	
	function usersEditable(){
		return true;
	}
	function passwordsEditable(){
		return true;
	}
	
	function createUser($login, $passwd){
		$users = $this->listUsers();
		if(!is_array($users)) $users = array();
		if(array_key_exists($login, $users)) return "exists";
		$userData = array("username" => $login);
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$userData["password"] = md5($passwd);
		}else{
			$userData["password"] = $passwd;
		}
		$userData["id"]=$this->getUserSeq()+1;
		$userData["createdate"]=date("Y-m-d H:i:s");
		//maj table users
		dibi::query('INSERT INTO ['.$this->prefix.'module_feusers_users]', $userData);
		$this->setUserSeq($userData["id"]);
		//maj table appartenance au groupe
		$belongsData["userid"]=$userData["id"];
		$belongsData["groupid"]=$this->groupid;
		dibi::query('INSERT INTO ['.$this->prefix.'module_feusers_belongs]', $belongsData);
	}	
	function changePassword($login, $newPass){
		$users = $this->listUsers();
		if(!is_array($users) || !array_key_exists($login, $users)) return ;
		$userData = array("username" => $login);
		if($this->getOption("TRANSMIT_CLEAR_PASS") === true){
			$userData["password"] = md5($newPass);
		}else{
			$userData["password"] = $newPass;
		}
		dibi::query("UPDATE [".$this->prefix."module_feusers_users] SET ", $userData, "WHERE `username`=%s", $login);
	}	
	function deleteUser($login){
		$uid=$this->getUserId($login);
		//suppress all reference in CMSMS FEU tables
		dibi::query("DELETE FROM [".$this->prefix."module_feusers_users] WHERE `username`=%s", $login);
		dibi::query("DELETE FROM [".$this->prefix."module_feusers_belongs] WHERE `userid`=%s", $uid);
		dibi::query("DELETE FROM [".$this->prefix."module_feusers_properties] WHERE `userid`=%s", $uid);
	}

	function getUserPass($login){
		$res = dibi::query("SELECT [password] FROM [".$this->prefix."module_feusers_users], [".$this->prefix."module_feusers_belongs] WHERE [username]=%s AND id = userid AND groupid= %s", $login, $this->groupid);
		$pass = $res->fetchSingle();		
		return $pass;
	}

	function getUserSeq(){
	$res = dibi::query("SELECT [id] FROM [".$this->prefix."module_feusers_users_seq]");
	$seq = $res->fetchSingle();		
	return $seq;
}

	function setUserSeq($num){
	$res = dibi::query("UPDATE [".$this->prefix."module_feusers_users_seq] SET `id`=".$num);
	}

	function getUserId($login){
		$res = dibi::query("SELECT id FROM [".$this->prefix."module_feusers_users], [".$this->prefix."module_feusers_belongs] WHERE [username]=%s AND id = userid AND groupid= %s", $login, $this->groupid);
		$uid = $res->fetchSingle();
		return $uid;
	}
	function listUsersSerial(){
		return AJXP_Utils::loadSerialFile($this->usersSerFile);
	}

}
?>