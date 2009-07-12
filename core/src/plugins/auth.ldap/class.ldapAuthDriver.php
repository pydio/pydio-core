<?php
/**
 * @package info.ajaxplorer
 *
 * Copyright 2007-2009 Pierre Wirtz
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
 * Description : Abstract representation of an access to an authentication system (ajxp, ldap, etc).
 */
require_once(INSTALL_PATH."/server/classes/class.AbstractAuthDriver.php");
class ldapAuthDriver extends AbstractAuthDriver {

    var $ldapUrl;
    var $ldapPort = 389;
    var $ldapAdminUsername;
    var $ldapAdminPassword;
    var $ldapDN;
    var $ldapFilter;

    var $ldapconn = null;


    function init($options){
        parent::init($options);
        AJXP_Logger::logAction('Auth.ldap :: init');
        $this->ldapUrl = $options["LDAP_URL"];
        if ($options["LDAP_PORT"]) $this->ldapPort = $options["LDAP_PORT"];
        if ($options["LDAP_USER"]) $this->ldapAdminUsername = $options["LDAP_USER"];
        if ($options["LDAP_PASSWORD"]) $this->ldapAdminPassword = $options["LDAP_PASSWORD"];
        if ($options["LDAP_DN"]) $this->ldapDN = $options["LDAP_DN"];
        if ($options["LDAP_FILTER"]) $this->ldapFilter = $options["LDAP_FILTER"];
        $this->ldapconn = $this->LDAP_Connect();
        if ($this->ldapconn == null) AJXP_Logger::logAction('LDAP Server connexion could NOT be established');
    }

    function __deconstruct(){
        //@todo : if PHP server < 5, this method will never be closed. Maybe use a close() method ?
        ldap_close($this->ldapconn);
    }

    function LDAP_Connect(){
        $ldapconn = ldap_connect($this->ldapUrl, $this->ldapPort)
        or die("Cannot connect to LDAP server");
        //@todo : return error_code

        if ($ldapconn) {
            //AJXP_Logger::logAction("auth.ldap:We are connected");
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);

            if ($this->ldapAdminUsername === null){
                //connecting anonymously
                AJXP_Logger::logAction('Anonymous LDAP connexion');
                $ldapbind = @ldap_bind($ldapconn);
            } else {
                AJXP_Logger::logAction('Standard LDAP connexion');
                $ldapbind = @ldap_bind($ldapconn, $this->ldapAdminUsername, $this->ldapAdminPassword);
            }

            if ($ldapbind){
                return $ldapconn;
            } else {
                return null;
            }
            
        } else {
            AJXP_Logger::logAction("Error while connection to LDAP server");
        }

    }





    function listUsers(){
		if ($this->ldapFilter === null){
			$ret = ldap_search($this->ldapconn,$this->ldapDN,"objectClass=person");
		} else {
			$ret = ldap_search($this->ldapconn,$this->ldapDN,$this->ldapFilter);
		}    	
        //$ret = ldap_search($this->ldapconn,$this->ldapDN,"objectClass=person");
        $entries = ldap_get_entries($this->ldapconn, $ret);
        $persons = array();
        unset($entries['count']);
        foreach($entries as $id => $person){
            //if ($id != 'count'){
            //AJXP_Logger::logAction('auth.ldap:Handling user '.$person['uid'][0]);
            $persons[$person['uid'][0]] = "XXX";
            //}
        }

        //error_log(print_r($persons, true));
        return $persons;
    }

    function userExists($login){        
        //return true;
        $users = $this->listUsers();
        if(!is_array($users) || !array_key_exists($login, $users)) return false;
        return true;
    }

    function checkPassword($login, $pass, $seed){
       
        //AJXP_Logger::logAction('auth.ldap:ldapAuthDriver::checkPassword');
        $ret = ldap_search($this->ldapconn,$this->ldapDN,"uid=".$login);
        $entries = ldap_get_entries($this->ldapconn, $ret);
        //error_log(print_r($entries, true));
        if ($entries['count']>0) {
            //AJXP_Logger::logAction('auth.ldap:Found user!');

            //error_log(print_r($entries[0]["dn"], true));
            if (@ldap_bind($this->ldapconn,$entries[0]["dn"],$pass)) {
                AJXP_Logger::logAction('Ldap Password Check:Got user '.$entries[0]["cn"][0]);
                return true;
            }
            return false;
        } else {
            AJXP_Logger::logAction("Ldap Password Check:No user $user_id found");
            return false;
        }
    }

    function usersEditable(){
        return false;
    }
    function passwordsEditable(){
        return false;
    }

   


}
?>