<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>, Pierre Wirtz
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
 * Authenticate users against an LDAP server
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class ldapAuthDriver extends AbstractAuthDriver {

    var $ldapUrl;
    var $ldapPort = 389;
    var $ldapAdminUsername;
    var $ldapAdminPassword;
    var $ldapDN;
    var $ldapGDN;
    var $ldapFilter;
    var $ldapGFilter;
    var $dynamicFilter;
    var $dynamicExpected;
    var $ldapUserAttr;
    var $ldapGroupAttr;

    var $ldapconn = null;
    var $separateGroup = "";

    var $hasGroupsMapping = false;


    /**
     * Legacy way, defined through PHP array
     * @var array
     */
    var $customParamsMapping = array();

    /**
     * New way, defined through GUI Options (PARAM_MAPPING replication group).
     * @var array
     */
    var $paramsMapping = array();

    function init($options){
        parent::init($options);
        $options = $this->options;
        $this->ldapUrl = $options["LDAP_URL"];
        if ($options["LDAP_PORT"]) $this->ldapPort = $options["LDAP_PORT"];
        if ($options["LDAP_USER"]) $this->ldapAdminUsername = $options["LDAP_USER"];
        if ($options["LDAP_PASSWORD"]) $this->ldapAdminPassword = $options["LDAP_PASSWORD"];
        if ($options["LDAP_DN"]) $this->ldapDN = $this->parseReplicatedParams($options, array("LDAP_DN"));
        if ($options["LDAP_GDN"]) $this->ldapGDN = $this->parseReplicatedParams($options, array("LDAP_GDN"));
        if (is_array($options["CUSTOM_DATA_MAPPING"])) $this->customParamsMapping = $options["CUSTOM_DATA_MAPPING"];
        $this->paramsMapping = $this->parseReplicatedParams($options, array("MAPPING_LDAP_PARAM", "MAPPING_LOCAL_TYPE", "MAPPING_LOCAL_PARAM"));
        if(count($this->paramsMapping)){
            foreach($this->paramsMapping as $param){
                if(strtolower($param["MAPPING_LOCAL_TYPE"]) == "group_path"){
                    $this->hasGroupsMapping = $param["MAPPING_LDAP_PARAM"];
                    break;
                }
            }
        }
        if (!empty($options["LDAP_FILTER"])){
            $this->ldapFilter = $options["LDAP_FILTER"];
            if ($this->ldapFilter != "" &&  !preg_match("/^\(.*\)$/", $this->ldapFilter)) {
                $this->ldapFilter = "(" . $this->ldapFilter . ")";
            }
        } else {
            if($this->hasGroupsMapping && !empty($this->ldapGFilter)){
                $this->ldapFilter = "!(".$this->ldapGFilter.")";
            }
        }
        if (!empty($options["LDAP_GROUP_FILTER"])){
            $this->ldapGFilter = $options["LDAP_GROUP_FILTER"];
            if ($this->ldapGFilter != "" &&  !preg_match("/^\(.*\)$/", $this->ldapGFilter)) {
                $this->ldapGFilter = "(" . $this->ldapGFilter . ")";
            }
        } else {
            $this->ldapGFilter = "(objectClass=group)";
        }
        if (!empty($options["LDAP_USERATTR"])){
			$this->ldapUserAttr = strtolower($options["LDAP_USERATTR"]);
		}else{ 
			$this->ldapUserAttr = 'uid' ; 
		}
        if (!empty($options["LDAP_GROUPATTR"])){
			$this->ldapGroupAttr = strtolower($options["LDAP_GROUPATTR"]);
		}else{
			$this->ldapGroupAttr = 'cn' ;
		}
    }

    function parseReplicatedParams($options, $optionsNames){
        $i = 0;
        $data = array();
        while(true){
            $ok = true;
            $occurence = array();
            $suffix = ($i==0 ? "" : "_" . $i);
            foreach($optionsNames as $name){
                if(!isSet($options[$name.$suffix])) {
                    $ok = false;
                    break;
                }
                if(count($optionsNames) == 1){
                    $occurence = $options[$name.$suffix];
                }else{
                    $occurence[$name] = $options[$name.$suffix];
                }
            }
            if(!$ok) break;
            $data[] = $occurence;
            $i++;
        }
        return $data;
    }

    public function testLDAPConnexion($options){

        $this->ldapUrl = $options["LDAP_URL"];
        if ($options["LDAP_PORT"]) $this->ldapPort = $options["LDAP_PORT"];
        if ($options["LDAP_USER"]) $this->ldapAdminUsername = $options["LDAP_USER"];
        if ($options["LDAP_PASSWORD"]) $this->ldapAdminPassword = $options["LDAP_PASSWORD"];
        if ($options["LDAP_DN"]) $this->ldapDN = $this->parseReplicatedParams($options, array("LDAP_DN"));
        $this->startConnexion();
        if($this->ldapconn == null){
            return "ERROR: Cannot connect to the server";
        }else{
            if(!empty($options["TEST_USER"])){
                $entries = $this->getUserEntries($options["TEST_USER"]);
                if(!is_array($entries)) return false;
                if(AuthService::ignoreUserCase()) {
                    $res =  (strcasecmp($options["TEST_USER"], $entries[0][$this->ldapUserAttr][0]) == 0);
                }else {
                    $res =  (strcmp($options["TEST_USER"], $entries[0][$this->ldapUserAttr][0]) == 0 );
                }
                AJXP_Logger::debug('Auth.ldap :: checking if user '.$options["TEST_USER"].' exists  : '.$res);
                if(!$res){
                    return "ERROR: Could <b>correctly connect</b> to the server, but could <b>not find the specified user</b> in the directory.";
                }else{
                    return "SUCCESS: Could connect to the server, and could find the specified user inside the directory.";
                }
            }else{
                return "SUCCESS: Correctly connected to the server";
            }
        }

    }

    function startConnexion(){
        AJXP_Logger::debug('Auth.ldap :: start connexion');
        if($this->ldapconn == null){
            $this->ldapconn = $this->LDAP_Connect();
            if($this->ldapconn == null) {
                AJXP_Logger::logAction('LDAP Server connexion could NOT be established');
            }
        }
        //return $this->ldapconn;
    }

    function __deconstruct(){
        if($this->ldapconn != null){
            ldap_close($this->ldapconn);
        }
    }

    function LDAP_Connect(){
        $ldapconn = ldap_connect($this->ldapUrl, $this->ldapPort)
        or die("Cannot connect to LDAP server");
        //@todo : return error_code
        AJXP_Logger::debug('Auth.ldap :: correctly connected to server '.$this->ldapUrl);

        if ($ldapconn) {
            //AJXP_Logger::logAction("auth.ldap:We are connected");
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);

            if ($this->ldapAdminUsername === null){
                //connecting anonymously
                AJXP_Logger::debug('Auth.ldap :: Anonymous LDAP connexion');
                $ldapbind = @ldap_bind($ldapconn);
            } else {
                AJXP_Logger::debug('Auth.ldap :: Standard LDAP connexion');
                $ldapbind = @ldap_bind($ldapconn, $this->ldapAdminUsername, $this->ldapAdminPassword);
            }

            if ($ldapbind){
                AJXP_Logger::debug('Auth.ldap :: LDAP connexion OK');
                return $ldapconn;
            } else {
                AJXP_Logger::debug('Auth.ldap :: LDAP connexion FAILED');
                return null;
            }
            
        } else {
            AJXP_Logger::logAction("Error while connection to LDAP server");
        }

    }


    function getUserEntries($login = null, $countOnly = false, $offset = -1, $limit = -1){
        if ($login == null){
            $filter = $this->ldapFilter;
        } else {
            if($this->ldapFilter == "") $filter = "(" . $this->ldapUserAttr . "=" . $login . ")";
            else  $filter = "(&" . $this->ldapFilter . "(" . $this->ldapUserAttr . "=" . $login . "))";
        }
        if(empty($filter)){
            if(!empty($this->dynamicFilter)) $filter = $this->dynamicFilter;
            else $filter = $this->ldapUserAttr . "=*";
        }else{
            if(!empty($this->dynamicFilter)) $filter = "(&(".$this->dynamicFilter.")".$filter.")";
        }
        if($this->ldapconn == null){
        	$this->startConnexion();
        }
        $conn = array();
        if(is_array($this->ldapDN)){
            foreach($this->ldapDN as $dn){
                $conn[] = $this->ldapconn;
            }
        }else{
            $conn = array($this->ldapconn);
        }
        $expected = array($this->ldapUserAttr);
        if($login != null && (!empty($this->customParamsMapping) || !empty($this->paramsMapping))){
            if(!empty($this->customParamsMapping)){
                $expected = array_merge($expected, array_keys($this->customParamsMapping));
            }
            if(!empty($this->paramsMapping)){
                $keys = array();
                foreach($this->paramsMapping as $param) $keys[] = $param["MAPPING_LDAP_PARAM"];
                $expected = array_merge($expected, $keys);
            }
        }
        if(is_array($this->dynamicExpected)){
            $expected = array_merge($expected, $this->dynamicExpected);
        }
        foreach ($conn as $dn => $ldapc) {
            if (!$ldapc) {
                unset($conn[$dn]);
            }
        }
        if (count($conn) < 1) {
            return array("count" => 0);
        }
        $ret = ldap_search($conn,$this->ldapDN,$filter, $expected);
        $allEntries = array("count" => 0);
        foreach($ret as $i => $resourceResult){
            if($resourceResult === false) continue;
            if($countOnly){
                $allEntries["count"] += ldap_count_entries($conn[$i], $resourceResult);
                continue;
            }
            $entries = ldap_get_entries($conn[$i], $resourceResult);
            $index = 0;
            if(!empty($entries["count"])){
                $allEntries["count"] += $entries["count"];
                unset($entries["count"]);
                if($limit != -1){
                    usort($entries, array($this, "userSortFunction"));
                }
                foreach($entries as $entry){
                    if($offset != -1 && $index < $offset){
                        $index ++; continue;
                    }
                    $allEntries[] = $entry;
                    $index ++;
                    if($limit!= -1 && $index >= $offset + $limit) break;
                }
            }
        }
        return $allEntries;
    }

    private function userSortFunction($entryA, $entryB)
    {
        return strcasecmp($entryA[$this->ldapUserAttr][0], $entryB[$this->ldapUserAttr][0]);
    }

    public function supportsUsersPagination()
    {
        return true;
    }
    function listUsersPaginated($baseGroup="/", $regexp, $offset, $limit){

        if($this->hasGroupsMapping !== false){
            if($baseGroup == "/"){
                $this->dynamicFilter = "!(".$this->hasGroupsMapping."=*)";
            }else{
                $this->dynamicFilter = $this->hasGroupsMapping."=".ltrim($baseGroup, "/");
            }

        }else if(!empty($this->separateGroup) && $baseGroup != "/".$this->separateGroup) {
            return array();
        }

        if($regexp[0]=="^") $regexp = ltrim($regexp, "^")."*";
        else if($regexp[strlen($regexp)-1] == "$") $regexp = "*".rtrim($regexp, "$");

        $entries = $this->getUserEntries($regexp, false, $offset, $limit);
        $this->dynamicFilter = null;
        $persons = array();
        unset($entries['count']); // remove 'count' entry
        foreach($entries as $id => $person){
            $login = $person[$this->ldapUserAttr][0];
            if(AuthService::ignoreUserCase()) $login = strtolower($login);
            $persons[$login] = "XXX";
        }
        return $persons;
    }
    function getUsersCount($baseGroup = "/", $regexp = ""){
        $re = null;
        if(!empty($regexp)){
            if($regexp[0]=="^") $re = ltrim($regexp, "^")."*";
            else if($regexp[strlen($regexp)-1] == "$") $re = "*".rtrim($regexp, "$");
        }
        $res = $this->getUserEntries($re, true, null);
        return $res["count"];
    }


    /**
     * List children groups of a given group. By default will report this on the CONF driver,
     * but can be overriden to grab info directly from auth driver (ldap, etc).
     * @param string $baseGroup
     * @return string[]
     */
    function listChildrenGroups($baseGroup = "/"){
        $arr = array();
        if($baseGroup == "/" && !empty($this->separateGroup)) {
            $arr[$this->separateGroup] = "LDAP Annuary";
            return $arr;
        }
        if($this->hasGroupsMapping){
            $origUsersDN = $this->ldapDN;
            $origUsersFilter = $this->ldapFilter;
            $origUsersAttr = $this->ldapUserAttr;
            $this->ldapDN = $this->ldapGDN;
            $this->ldapFilter = $this->ldapGFilter;
            $this->ldapUserAttr = $this->ldapGroupAttr;

            if($baseGroup != "/"){
                $this->dynamicFilter = $this->hasGroupsMapping."=".ltrim($baseGroup, "/");
            }else{
                //STRANGE, SHOULD WORK BUT CAN EXCLUDES ALL GROUPS
                $this->dynamicFilter = "!(".$this->hasGroupsMapping."=*)";
            }

            $entries = $this->getUserEntries();
            $this->dynamicFilter = null;
            $persons = array();
            unset($entries['count']); // remove 'count' entry
            foreach($entries as $id => $person){
                $login = $person[$this->ldapUserAttr][0];
                //if(AuthService::ignoreUserCase()) $login = strtolower($login);
                $persons[$person["dn"]] = $login;

                $branch = array();
                $this->buildGroupBranch($login, $branch);
                $parent = "/";
                if(count($branch)){
                    $parent = "/".implode("/", array_reverse($branch));
                }
                AuthService::createGroup($parent, $person["dn"], $login);

            }
            $this->ldapDN = $origUsersDN;
            $this->ldapFilter = $origUsersFilter;
            $this->ldapUserAttr = $origUsersAttr;
            return $persons;
        }

        return $arr;
    }


    function listUsers($baseGroup = "/"){
        if($this->hasGroupsMapping !== false){
            if($baseGroup == "/"){
                $this->dynamicFilter = $this->hasGroupsMapping."=";
            }else{
                $this->dynamicFilter = $this->hasGroupsMapping."=".array_pop(explode("/", $baseGroup));
            }
        }else if(!empty($this->separateGroup) && $baseGroup != "/".$this->separateGroup) {
            return array();
        }

        $entries = $this->getUserEntries();
        $this->dynamicFilter = null;
        $persons = array();
        unset($entries['count']); // remove 'count' entry
        foreach($entries as $id => $person){
            $login = $person[$this->ldapUserAttr][0];
            if(AuthService::ignoreUserCase()) $login = strtolower($login);
            $persons[$login] = "XXX";
        }
        return $persons;
    }

	function userExists($login){
		// Check if local storage exists for the user. If it does, assume the user
		// exists. This prevents a barrage of ldap_connect/ldap_bind/ldap_search
		// calls.
		$confDriver = ConfService::getConfStorageImpl();
		$userObject = $confDriver->instantiateAbstractUserImpl($login);
		if ($userObject->storageExists()) {
			//return true;
		}
        $entries = $this->getUserEntries($login);
        if(!is_array($entries)) return false;
        if(AuthService::ignoreUserCase()) {
            $res =  (strcasecmp($login, $entries[0][$this->ldapUserAttr][0]) == 0);
        }else {
            $res =  (strcmp($login, $entries[0][$this->ldapUserAttr][0]) == 0 );
        }
        AJXP_Logger::debug('Auth.ldap :: checking if user '.$login.' exists  : '.$res);
        return $res;
    }

    function checkPassword($login, $pass, $seed){

        if(empty($pass)) return false;
        $entries = $this->getUserEntries($login);
        if ($entries['count']>0) {
            AJXP_Logger::debug('Ldap Password Check: Got user '.$login);
            if (@ldap_bind($this->ldapconn,$entries[0]["dn"],$pass)) {
                AJXP_Logger::debug('Ldap Password Check: Got user '.$entries[0]["cn"][0]);
                return true;
            }
            AJXP_Logger::debug('Password Check: failed for user '.$login);
            return false;
        } else {
            AJXP_Logger::debug("Ldap Password Check : No user $login found");
            return false;
        }
    }

    function usersEditable(){
        return false;
    }
    function passwordsEditable(){
        return false;
    }

    function buildGroupBranch($groupAttrValue, &$branch = array()){
        // Load group data. Detect memberOf. Load parent group.
        $origUsersDN = $this->ldapDN;
        $origUsersFilter = $this->ldapFilter;
        $origUsersAttr = $this->ldapUserAttr;
        $this->ldapDN = $this->ldapGDN;
        $this->ldapFilter = $this->ldapGFilter;
        $this->ldapUserAttr = $this->ldapGroupAttr;

        $this->dynamicFilter = $this->ldapGroupAttr."=".$groupAttrValue;
        $this->dynamicExpected = array($this->hasGroupsMapping);

        $entries = $this->getUserEntries();
        $this->dynamicFilter = null;
        $this->dynamicExpected = null;
        $persons = array();
        unset($entries['count']); // remove 'count' entry
        $groupData = $entries[0];

        $memberOf = $groupData[strtolower($this->hasGroupsMapping)][0];

        $this->ldapDN = $origUsersDN;
        $this->ldapFilter = $origUsersFilter;
        $this->ldapUserAttr = $origUsersAttr;

        if(!empty($memberOf)){
            $parts = explode(",", ltrim($memberOf, '/'));
            foreach($parts as $part){
                list($att,$attVal) = explode("=", $part);
                if(strtolower($att) == "cn")  $parentCN = $attVal;
            }
            if(!empty($parentCN)){
                $branch[] = $memberOf;
                $this->buildGroupBranch($parentCN, $branch);
            }

        }


    }

    function updateUserObject(&$userObject){
        if(!empty($this->separateGroup)) $userObject->setGroupPath("/".$this->separateGroup);
        // SHOULD BE DEPRECATED
        if(!empty($this->customParamsMapping)){
            $checkValues =  array_values($this->customParamsMapping);
            $prefs = $userObject->getPref("CUSTOM_PARAMS");
            if(!is_array($prefs)) {
                $prefs = array();
            }
            // If one value exist, we consider the mapping has already been done.
            foreach($checkValues as $val){
                if(array_key_exists($val, $prefs)) return;
            }
            $changes = false;
            $entries = $this->getUserEntries($userObject->getId());
            if($entries["count"]){
                $entry = $entries[0];
                foreach($this->customParamsMapping as $key => $value){
                    if(isSet($entry[$key])){
                        $prefs[$value] = $entry[$key][0];
                        $changes = true;
                    }
                }
            }
            if($changes){
                $userObject->setPref("CUSTOM_PARAMS", $prefs);
                $userObject->save();
            }
        }
        if(!empty($this->paramsMapping)){

            $changes = false;
            $entries = $this->getUserEntries($userObject->getId());
            if($entries["count"]){
                $entry = $entries[0];
                foreach($this->paramsMapping as $params){
                    $key = strtolower($params['MAPPING_LDAP_PARAM']);
                    if(isSet($entry[$key])){
                        $value = $entry[$key][0];
                        $memberValues = array();
                        if($key == "memberof"){
                            // get CN from value
                            foreach($entry[$key] as $possibleValue){
                                $hnParts = array();
                                $parts = explode(",", ltrim($possibleValue, '/'));
                                foreach($parts as $part){
                                    list($att,$attVal) = explode("=", $part);
                                    if(strtolower($att) == "cn")  $hnParts[] = $attVal;
                                }
                                if(count($hnParts)) {
                                    $memberValues[implode(",", $hnParts)] = $possibleValue;
                                }
                            }
                        }
                        switch($params['MAPPING_LOCAL_TYPE']){
                            case "role_id":
                                if($key == "memberof"){
                                    foreach($memberValues as $uniqValue => $fullDN){
                                        if(!in_array($uniqValue, array_keys($userObject->getRoles()))){
                                            $userObject->addRole(AuthService::getRole($uniqValue, true));
                                            $userObject->recomputeMergedRole();
                                            $changes = true;
                                        }
                                    }
                                }
                                break;
                            case "group_path":
                                if($key == "memberof"){
                                    $filter = $params["MAPPING_LOCAL_PARAM"];
                                    if(strpos($filter, "preg:") !== false){
                                        $matchFilter = "/".str_replace("preg:", "", $filter)."/i";
                                    }else{
                                        $valueFilters = array_map("trim", explode(",", $filter));
                                    }
                                    foreach($memberValues as $uniqValue => $fullDN){
                                        if(isSet($matchFilter) && !preg_match($matchFilter, $uniqValue)) continue;
                                        if(isSet($valueFilters) && !in_array($uniqValue, $valueFilters)) continue;
                                        if($userObject->personalRole->filterParameterValue("auth.ldap", "MEMBER_OF", AJXP_REPO_SCOPE_ALL, "") == $fullDN){
                                            //break;
                                        }
                                        $humanName = $uniqValue;
                                        $branch = array();
                                        $this->buildGroupBranch($uniqValue, $branch);
                                        $parent = "/";
                                        if(count($branch)){
                                            $parent = "/".implode("/", array_reverse($branch));
                                        }
                                        AuthService::createGroup($parent, $fullDN, $humanName);
                                        $userObject->setGroupPath(rtrim($parent, "/")."/".$fullDN, true);
                                        // Update Roles from groupPath
                                        $b = array_reverse($branch);
                                        $b[] = $fullDN;
                                        for($i=1;$i<=count($b);$i++){
                                            $userObject->addRole(AuthService::getRole("AJXP_GRP_/".implode("/", array_slice($b, 0, $i)), true));
                                        }
                                        $userObject->personalRole->setParameterValue("auth.ldap", "MEMBER_OF", $fullDN);
                                        $userObject->recomputeMergedRole();
                                        $changes = true;
                                    }
                                }
                                break;
                            case "profile":
                                if($userObject->getProfile() != $value){
                                    $changes = true;
                                    $userObject->setProfile($value);
                                    AuthService::updateAutoApplyRole($userObject);
                                }
                                break;
                            case "plugin_param":
                            default:
                                if(strpos($params["MAPPING_LOCAL_PARAM"], "/") !== false){
                                    list($pId, $param) = explode("/", $params["MAPPING_LOCAL_PARAM"]);
                                }else{
                                    $pId = $this->getId();
                                    $param = $params["MAPPING_LOCAL_PARAM"];
                                }
                                if($userObject->personalRole->filterParameterValue($pId, $param, AJXP_REPO_SCOPE_ALL, "") != $value){
                                    $userObject->personalRole->setParameterValue($pId, $param, $value);
                                    $userObject->recomputeMergedRole();
                                    $changes = true;
                                }
                                break;
                        }
                    }
                }
            }
            if($changes){
                $userObject->save("superuser");
            }

        }
    }

}
