<?php
/*
 * Copyright 2007-2011 Pierre Wirtz
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
 * Authenticate users against an LDAP server
 */
class ldapAuthDriver extends AbstractAuthDriver {

    var $ldapUrl;
    var $ldapPort = 389;
    var $ldapAdminUsername;
    var $ldapAdminPassword;
    var $ldapDN;
    var $ldapFilter;
    var $ldapUserAttr;

    var $ldapconn = null;


    function init($options){
        parent::init($options);
        AJXP_Logger::logAction('Auth.ldap :: init');
        $this->ldapUrl = $options["LDAP_URL"];
        if ($options["LDAP_PORT"]) $this->ldapPort = $options["LDAP_PORT"];
        if ($options["LDAP_USER"]) $this->ldapAdminUsername = $options["LDAP_USER"];
        if ($options["LDAP_PASSWORD"]) $this->ldapAdminPassword = $options["LDAP_PASSWORD"];
        if ($options["LDAP_DN"]) $this->ldapDN = $options["LDAP_DN"];
        if ($options["LDAP_FILTER"]){
            $this->ldapFilter = $options["LDAP_FILTER"];
            if (!preg_match("/^\(.*\)$/", $this->ldapFilter)) {
                $this->ldapFilter = "(" . $this->ldapFilter . ")";
            }
        } else {
            $this->ldapFilter = "(objectClass=person)";
        }
        if ($options["LDAP_USERATTR"]){
			$this->ldapUserAttr = $options["LDAP_USERATTR"]; 
		}else{ 
			$this->ldapUserAttr = 'uid' ; 
		}        
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


    function getUserEntries($login = null){
        if ($login == null){
            $filter = $this->ldapFilter;
        } else {
            $filter = "(&" . $this->ldapFilter . "(" . $this->ldapUserAttr . "=" . $login . "))";
        }
        $ret = ldap_search($this->ldapconn,$this->ldapDN,$filter, array($this->ldapUserAttr));
        return ldap_get_entries($this->ldapconn, $ret);
    }


    function listUsers(){
		$entries = $this->getUserEntries();
        $persons = array();
        unset($entries['count']); // remove 'count' entry
        foreach($entries as $id => $person){
            $persons[$person[$this->ldapUserAttr][0]] = "XXX";
        }
        return $persons;
    }

	function userExists($login){
        $entries = $this->getUserEntries($login);
		if(!is_array($entries) || strcmp($login, $entries[0][$this->ldapUserAttr][0]) != 0 ) return false;
		return true;
    }

    function checkPassword($login, $pass, $seed){

        if(empty($pass)) return false;
        $entries = $this->getUserEntries($login);
        if ($entries['count']>0) {
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