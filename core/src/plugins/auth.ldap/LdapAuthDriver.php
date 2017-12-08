<?php
/*
 * Copyright 2007-2017 Pierre Wirtz
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
namespace Pydio\Auth\Driver;

use Pydio\Auth\Core\AbstractAuthDriver;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\ProgressBarCLI;
use Pydio\Core\Services\RolesService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\FileHelper;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Authenticate users against an LDAP server
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class LdapAuthDriver extends AbstractAuthDriver
{
    public $ldapUrl;
    public $ldapPort = 389;
    public $ldapAdminUsername;
    public $ldapAdminPassword;
    public $ldapDN;
    public $ldapGDN;
    public $ldapFilter;
    public $ldapGFilter;
    public $dynamicFilter;
    public $dynamicExpected;
    public $ldapUserAttr;
    public $ldapGroupAttr;
    public $fakeAttrMemberOf;
    public $mappedRolePrefix;
    public $pageSize;
    public $userRecursiveMemberOf = false;
    public $referralBind = false;

    public $ldapconn = null;
    public $separateGroup = "";

    public $hasGroupsMapping = false;
    public $attrMemberInGroup = true;


    /**
     * Legacy way, defined through PHP array
     * @var array
     */
    public $customParamsMapping = array();

    /**
     * New way, defined through GUI Options (PARAM_MAPPING replication group).
     * @var array
     */
    public $paramsMapping = array();

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        $options = $this->options;
        $this->ldapUrl = $options["LDAP_URL"];
        if (isSet($options["LDAP_PROTOCOL"]) && $options["LDAP_PROTOCOL"] == "ldaps") {
            $this->ldapUrl = "ldaps://" . $this->ldapUrl;
        }
        if ($options["LDAP_PORT"]) $this->ldapPort = $options["LDAP_PORT"];
        if ($options["LDAP_USER"]) $this->ldapAdminUsername = $options["LDAP_USER"];
        if ($options["LDAP_PASSWORD"]) $this->ldapAdminPassword = $options["LDAP_PASSWORD"];
        if (!empty($options["LDAP_FAKE_MEMBEROF"])) $this->fakeAttrMemberOf = $options["LDAP_FAKE_MEMBEROF"];
        if (isset($options["LDAP_VALUE_MEMBERATTR_IN_GROUP"])) {
            $this->attrMemberInGroup = $options["LDAP_VALUE_MEMBERATTR_IN_GROUP"];
        } else {
            $this->attrMemberInGroup = true;
        }

        if ($options["LDAP_PAGE_SIZE"]) $this->pageSize = $options["LDAP_PAGE_SIZE"];
        if ($options["LDAP_REFERRAL_BIND"]) $this->referralBind = $options["LDAP_REFERRAL_BIND"];
        if ($options["LDAP_GROUP_PREFIX"]) $this->mappedRolePrefix = $options["LDAP_GROUP_PREFIX"];
        if ($options["LDAP_DN"]) $this->ldapDN = $this->parseReplicatedParams($options, array("LDAP_DN"));
        if ($options["LDAP_GDN"]) $this->ldapGDN = $this->parseReplicatedParams($options, array("LDAP_GDN"));
        if (is_array($options["CUSTOM_DATA_MAPPING"])) $this->customParamsMapping = $options["CUSTOM_DATA_MAPPING"];
        $this->paramsMapping = $this->parseReplicatedParams($options, array("MAPPING_LDAP_PARAM", "MAPPING_LOCAL_TYPE", "MAPPING_LOCAL_PARAM"));
        if (count($this->paramsMapping)) {
            foreach ($this->paramsMapping as $param) {
                if (strtolower($param["MAPPING_LOCAL_TYPE"]) == "group_path") {
                    $this->hasGroupsMapping = $param["MAPPING_LDAP_PARAM"];
                    break;
                }
            }
        }
        if (!empty($options["LDAP_FILTER"])) {
            $this->ldapFilter = $options["LDAP_FILTER"];
            if ($this->ldapFilter != "" && !preg_match("/^\(.*\)$/", $this->ldapFilter)) {
                $this->ldapFilter = "(" . $this->ldapFilter . ")";
            }
        } else {
            if (!empty($this->hasGroupsMapping) && !empty($this->ldapGFilter)) {
                $this->ldapFilter = "!(" . $this->ldapGFilter . ")";
            }
        }
        if (!empty($options["LDAP_GROUP_FILTER"])) {
            $this->ldapGFilter = $options["LDAP_GROUP_FILTER"];
            if ($this->ldapGFilter != "" && !preg_match("/^\(.*\)$/", $this->ldapGFilter)) {
                $this->ldapGFilter = "(" . $this->ldapGFilter . ")";
            }
        } else {
            $this->ldapGFilter = "(objectClass=group)";
        }
        if (!empty($options["LDAP_USERATTR"])) {
            $this->ldapUserAttr = strtolower($options["LDAP_USERATTR"]);
        } else {
            $this->ldapUserAttr = 'uid';
        }
        if (!empty($options["LDAP_GROUPATTR"])) {
            $this->ldapGroupAttr = strtolower($options["LDAP_GROUPATTR"]);
        } else {
            $this->ldapGroupAttr = 'cn';
        }
        if (!empty($options["LDAP_RECURSIVE_MEMBEROF"])) {
            $this->userRecursiveMemberOf = $options["LDAP_RECURSIVE_MEMBEROF"];
        }
    }

    /**
     * @param array $options
     * @param array $optionsNames
     * @return array
     */
    public function parseReplicatedParams($options, $optionsNames)
    {
        $i = 0;
        $data = array();
        while (true) {
            $ok = true;
            $occurence = array();
            $suffix = ($i == 0 ? "" : "_" . $i);
            foreach ($optionsNames as $name) {
                if (!isSet($options[$name . $suffix])) {
                    $ok = false;
                    break;
                }
                if (count($optionsNames) == 1) {
                    $occurence = $options[$name . $suffix];
                } else {
                    $occurence[$name] = $options[$name . $suffix];
                }
            }
            if (!$ok) break;
            $data[] = $occurence;
            $i++;
        }
        return $data;
    }

    public function testLDAPConnexion($options)
    {
        $this->ldapUrl = $options["LDAP_URL"];
        if (isSet($options["LDAP_PROTOCOL"]) && $options["LDAP_PROTOCOL"] == "ldaps") {
            $this->ldapUrl = "ldaps://" . $this->ldapUrl;
        }
        if ($options["LDAP_PORT"]) $this->ldapPort = $options["LDAP_PORT"];
        if ($options["LDAP_USER"]) $this->ldapAdminUsername = $options["LDAP_USER"];
        if ($options["LDAP_PASSWORD"]) $this->ldapAdminPassword = $options["LDAP_PASSWORD"];
        if ($options["LDAP_DN"]) $this->ldapDN = $this->parseReplicatedParams($options, array("LDAP_DN"));
        $this->startConnexion();
        if ($this->ldapconn == null) {
            return "ERROR: Cannot connect to the server";
        } else {
            if (!empty($options["TEST_USER"])) {
                $entries = $this->getUserEntries($options["TEST_USER"]);
                if (!is_array($entries)) return false;
                if (UsersService::ignoreUserCase()) {
                    $res = (strcasecmp($options["TEST_USER"], $entries[0][$this->ldapUserAttr][0]) == 0);
                } else {
                    $res = (strcmp($options["TEST_USER"], $entries[0][$this->ldapUserAttr][0]) == 0);
                }
                $this->logDebug(__FUNCTION__, 'checking if user ' . $options["TEST_USER"] . ' exists  : ' . $res);
                if (!$res) {
                    return "ERROR: Could <b>correctly connect</b> to the server, but could <b>not find the specified user</b> in the directory.";
                } else {
                    return "SUCCESS: Could connect to the server, and could find the specified user inside the directory.";
                }
            } else {
                return "SUCCESS: Correctly connected to the server";
            }
        }

    }

    public function startConnexion()
    {
        $this->logDebug(__FUNCTION__, 'start connexion');
        if ($this->ldapconn == null) {
            $this->ldapconn = $this->LDAP_Connect();
            if ($this->ldapconn == null) {
                $this->logError(__FUNCTION__, 'LDAP Server connexion could NOT be established');
            }
        }
    }

    public function __deconstruct()
    {
        if ($this->ldapconn != null) {
            ldap_close($this->ldapconn);
        }
    }

    public function LDAP_Connect()
    {
        $ldapconn = ldap_connect($this->ldapUrl, $this->ldapPort);
        //@todo : return error_code

        if ($ldapconn) {
            $this->logDebug(__FUNCTION__, 'ldap_connect(' . $this->ldapUrl . ',' . $this->ldapPort . ') OK');
            ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
            if($this->referralBind){
                ldap_set_option( $ldapconn, LDAP_OPT_REFERRALS, 1);
            }else{
                ldap_set_option( $ldapconn, LDAP_OPT_REFERRALS, 0);
            }
            if (empty($this->pageSize) || !is_numeric($this->pageSize)) {
                $this->pageSize = 500;
            }
            ldap_set_option($ldapconn, LDAP_OPT_SIZELIMIT, $this->pageSize);

            if (isSet($this->options["LDAP_PROTOCOL"]) &&
                $this->options["LDAP_PROTOCOL"] === 'starttls') {
                ldap_start_tls($ldapconn);
            }

            if ($this->ldapAdminUsername === null) {
                //connecting anonymously
                $this->logDebug(__FUNCTION__, 'Anonymous LDAP connexion');
                $ldapbind = @ldap_bind($ldapconn);
            } else {
                $this->logDebug(__FUNCTION__, 'Standard LDAP connexion');
                $ldapbind = @ldap_bind($ldapconn, $this->ldapAdminUsername, $this->ldapAdminPassword);
            }

            if ($ldapbind) {
                $this->logDebug(__FUNCTION__, 'ldap_bind OK');
                return $ldapconn;
            } else {
                $this->logError(__FUNCTION__, 'ldap_bind FAILED');
                return null;
            }

        } else {
            $this->logError(__FUNCTION__, 'ldap_connect(' . $this->ldapUrl . ',' . $this->ldapPort . ') FAILED');
            die('Auth.ldap :: ldap_connect FAILED');
        }

    }


    public function getUserEntries($login = null, $countOnly = false, $offset = -1, $limit = -1, $regexpOnSearchAttr = false)
    {
        if ($login == null) {
            $filter = $this->ldapFilter;
        } else {
            if ($regexpOnSearchAttr && !empty($this->options["LDAP_SEARCHUSER_ATTR"])) {
                $searchAttr = $this->options["LDAP_SEARCHUSER_ATTR"];
                $searchAttrArray = explode(",", $searchAttr);
            }

            if (isset($searchAttrArray)) {
                if (count($searchAttrArray) > 1) {
                    $searchAttrFilter = "(|";
                    foreach ($searchAttrArray as $attr) {
                        $searchAttrFilter .= "(" . $attr . "=" . $login . ")";
                    }
                    $searchAttrFilter .= ")";
                } else {
                    $searchAttrFilter = "(" . $searchAttrArray[0] . "=" . $login . ")";
                }

            } else {
                $searchAttrFilter = "(" . $this->ldapUserAttr . "=" . $login . ")";
            }

            if ($this->ldapFilter == "") $filter = $searchAttrFilter;
            else  $filter = "(&" . $this->ldapFilter . $searchAttrFilter . ")";
        }
        if (empty($filter)) {
            if (!empty($this->dynamicFilter)) $filter = $this->dynamicFilter;
            else $filter = $this->ldapUserAttr . "=*";
        } else {
            if (!empty($this->dynamicFilter)) $filter = "(&(" . $this->dynamicFilter . ")" . $filter . ")";
        }
        if ($this->ldapconn == null) {
            $this->startConnexion();
        }
        $conn = array();
        if (is_array($this->ldapDN)) {
            foreach ($this->ldapDN as $dn) {
                $conn[] = $this->ldapconn;
            }
        } else {
            $conn = array($this->ldapconn);
        }
        $expected = array($this->ldapUserAttr);
        if ($login != null && (!empty($this->customParamsMapping) || !empty($this->paramsMapping))) {
            if (!empty($this->customParamsMapping)) {
                $expected = array_merge($expected, array_keys($this->customParamsMapping));
            }
            if (!empty($this->paramsMapping)) {
                $keys = array();
                foreach ($this->paramsMapping as $param) $keys[] = $param["MAPPING_LDAP_PARAM"];
                $expected = array_merge($expected, $keys);
            }
        }
        if (is_array($this->dynamicExpected)) {
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
        //$ret = ldap_search($conn,$this->ldapDN,$filter, $expected);

        $cookie = '';
        if (empty($this->pageSize) || !is_numeric($this->pageSize)) {
            $this->pageSize = 500;
        }


        $allEntries = array("count" => 0);
        $isSupportPagedResult = function_exists("ldap_control_paged_result") && function_exists("ldap_control_paged_result_response");

        $gotAllEntries = false;
        $index = 0;

        //Update progress bar in CLI mode
        $isListAll = (($offset == -1) && ($limit == -1) && (is_null($login)) && $regexpOnSearchAttr && (php_sapi_name() == "cli"));
        if ($isListAll) {
            $total = $this->getCountFromCache("/");
            $progressBar = new ProgressBarCLI();
            $progressBar->init($index, $total["count"], "Get ldap users");
        }

        do {
            if ($isSupportPagedResult)
                ldap_control_paged_result($this->ldapconn, $this->pageSize, false, $cookie);

            $ret = ldap_search($conn, $this->ldapDN, $filter, $expected, 0, 0);
            if ($ret === false) break;
            foreach ($ret as $i => $resourceResult) {
                if ($resourceResult === false) continue;
                if ($countOnly) {
                    $allEntries["count"] += ldap_count_entries($conn[$i], $resourceResult);
                    continue;
                }
                if ($limit != -1) {
                    @ldap_sort($conn[$i], $resourceResult, $this->ldapUserAttr);
                }
                $entries = ldap_get_entries($conn[$i], $resourceResult);

                // for better performance
                if ((is_array($entries)) && ($offset != -1) && ($limit != -1) && (($index + $this->pageSize) < $offset)) {
                    $index += $this->pageSize;
                    continue;
                }

                if (!empty($entries["count"])) {
                    $allEntries["count"] += $entries["count"];
                    unset($entries["count"]);
                    foreach ($entries as $entry) {
                        if ($offset != -1 && $index < $offset) {
                            $index++;
                            continue;
                        }

                        // fake memberOf
                        if (($this->fakeAttrMemberOf) && method_exists($this, "fakeMemberOf") && in_array(strtolower("memberof"), array_map("strtolower", $expected))) {
                            if ($this->attrMemberInGroup) {
                                $uid = $entry["dn"];
                            } else {
                                $uidWithEqual = explode(",", $entry["dn"]);
                                $uidShort = explode("=", $uidWithEqual[0]);
                                $uid = $uidShort[1];
                            }

                            $strldap = "(&" . $this->ldapGFilter . "(" . $this->fakeAttrMemberOf . "=" . $uid . "))";
                            $this->fakeMemberOf($conn, $this->ldapGDN, $strldap, array("cn"), $entry);
                        }

                        $allEntries[] = $entry;
                        $index++;

                        //Update progress bar in CLI mode
                        if (isset($progressBar))
                            $progressBar->update($index);


                        if (($offset != -1) && ($limit != -1) && $index > $offset + $limit)
                            break;
                    }

                    if (($index > $offset + $limit) && ($limit != -1) && ($offset != -1))
                        $gotAllEntries = true;
                }
            }
            if ($isSupportPagedResult)
                foreach ($ret as $element) {
                    if (is_resource($element) && count($allEntries["count"]) > 1)
                        @ldap_control_paged_result_response($this->ldapconn, $element, $cookie);
                }
        } while (($cookie !== null && $cookie != '') && ($isSupportPagedResult) && (!$gotAllEntries));

        // reset paged_result for other activities (otherwise we will experience ldap error)
        if ($isSupportPagedResult)
            ldap_control_paged_result($this->ldapconn, 0);

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

    // $baseGroup = "/"
    public function listUsersPaginated($baseGroup, $regexp, $offset, $limit, $recursive = true)
    {
        if (!empty($this->hasGroupsMapping)) {
            if ($baseGroup == "/") {
                $this->dynamicFilter = "!(" . $this->hasGroupsMapping . "=*)";
            } else {
                $this->dynamicFilter = $this->hasGroupsMapping . "=" . basename($baseGroup);
            }

        } else if (!empty($this->separateGroup) && $baseGroup != "/" . $this->separateGroup) {
            return array();
        } else if (empty($this->separateGroup) && empty($this->hasGroupsMapping) && ($baseGroup != "/")){
            return array();
        }

        $entries = $this->getUserEntries(StringHelper::regexpToLdap($regexp), false, $offset, $limit, true);
        $this->dynamicFilter = null;
        $persons = array();
        unset($entries['count']); // remove 'count' entry
        foreach ($entries as $id => $person) {
            $login = $person[$this->ldapUserAttr][0];
            if (UsersService::ignoreUserCase()) $login = strtolower($login);
            $persons[$login] = "XXX";
        }
        return $persons;
    }

    /**
     * @param string $baseGroup
     * @param string $regexp
     * @param null $filterProperty
     * @param null $filterValue
     * @param bool $recursive
     * @return mixed
     */
    public function getUsersCount($baseGroup = "/", $regexp = "", $filterProperty = null, $filterValue = null, $recursive = true)
    {
        $cacheKey = $baseGroup . (!empty($regexp) ? "-" . $regexp : "");
        $check_cache = $this->getCountFromCache($cacheKey);
        if ((is_array($check_cache) && $check_cache["count"] > 0)) {
            return $check_cache["count"];
        }

        if (!empty($this->hasGroupsMapping)) {
            if ($baseGroup !== "/") {
                $this->dynamicFilter = $this->hasGroupsMapping . "=" . basename($baseGroup);
            }
        }

        $res = $this->getUserEntries(StringHelper::regexpToLdap($regexp), true, null);
        $this->saveCountToCache($res, $cacheKey);
        $this->dynamicFilter = null;
        return $res["count"];
    }


    /**
     * List children groups of a given group. By default will report this on the CONF driver,
     * but can be overriden to grab info directly from auth driver (ldap, etc).
     * @param string $baseGroup
     * @return string[]
     */
    public function listChildrenGroups($baseGroup = "/")
    {
        $arr = array();
        if ($baseGroup == "/" && !empty($this->separateGroup)) {
            $arr[$this->separateGroup] = "LDAP Annuary";
            return $arr;
        }
        if (!empty($this->hasGroupsMapping)) {
            $origUsersDN = $this->ldapDN;
            $origUsersFilter = $this->ldapFilter;
            $origUsersAttr = $this->ldapUserAttr;
            $this->ldapDN = $this->ldapGDN;
            $this->ldapFilter = $this->ldapGFilter;
            $this->ldapUserAttr = $this->ldapGroupAttr;

            if ($baseGroup != "/") {
                $this->dynamicFilter = $this->hasGroupsMapping . "=" . basename($baseGroup);
            } else {
                //STRANGE, SHOULD WORK BUT CAN EXCLUDES ALL GROUPS
                $this->dynamicFilter = "!(" . $this->hasGroupsMapping . "=*)";
            }

            $entries = $this->getUserEntries();
            $this->dynamicFilter = null;
            $persons = array();
            unset($entries['count']); // remove 'count' entry
            foreach ($entries as $id => $person) {
                $login = $person[$this->ldapUserAttr][0];
                //if(AuthService::ignoreUserCase()) $login = strtolower($login);
                $dn = $person["dn"];
                $persons["/" . $dn] = $login;

                $branch = array();
                $this->buildGroupBranch($login, $branch);
                $parent = "/";
                if (count($branch)) {
                    $parent = "/" . implode("/", array_reverse($branch));
                }
                // TODO: REMOVE filterBaseGroup() instruction.
                // MAYBE THIS WILL BREAK SOMEHTING
                if (!ConfService::getConfStorageImpl()->groupExists(rtrim($parent, "/") . "/" . $dn)) {
                    UsersService::createGroup($parent, $dn, $login);
                }
            }
            $this->ldapDN = $origUsersDN;
            $this->ldapFilter = $origUsersFilter;
            $this->ldapUserAttr = $origUsersAttr;
            return $persons;
        }

        return $arr;
    }


    public function listUsers($baseGroup = "/", $recursive = true)
    {
        return $this->listUsersPaginated($baseGroup, null, -1, -1);
    }

    public function userExists($login)
    {
        // Check if local storage exists for the user. If it does, assume the user
        // exists. This prevents a barrage of ldap_connect/ldap_bind/ldap_search
        // calls.
        $confDriver = ConfService::getConfStorageImpl();
        $userObject = $confDriver->instantiateAbstractUserImpl($login);
        if ($userObject->storageExists()) {
            //return true;
        }
        $entries = $this->getUserEntries($login);
        if (!is_array($entries)) return false;
        if (UsersService::ignoreUserCase()) {
            $res = (strcasecmp($login, $entries[0][$this->ldapUserAttr][0]) == 0);
        } else {
            $res = (strcmp($login, $entries[0][$this->ldapUserAttr][0]) == 0);
        }
        $this->logDebug(__FUNCTION__, 'checking if user ' . $login . ' exists  : ' . $res);
        return $res;
    }

    public function checkPassword($login, $pass)
    {
        if (empty($pass)) return false;
        $entries = $this->getUserEntries($login);
        if ($entries['count'] > 0) {
            $this->logDebug(__FUNCTION__, 'Ldap Password Check: Got user ' . $login);
            if($this->referralBind){
                $this->rebind_pass = $pass;
                $this->rebind_dn    = $entries[0]["dn"];
                @ldap_set_rebind_proc($this->ldapconn, 'rebind');
                // bind
                if(@ldap_bind($this->ldapconn, $this->rebind_dn, $pass)){
                    return true;
                }
            }
            if (@ldap_bind($this->ldapconn, $entries[0]["dn"], $pass)) {
                $this->logDebug(__FUNCTION__, 'Ldap Password Check: Got user ' . $entries[0]["cn"][0]);
                return true;
            }
            $this->logDebug(__FUNCTION__, 'Password Check: failed for user ' . $login);
            return false;
        } else {
            $this->logDebug(__FUNCTION__, "Ldap Password Check : No user $login found");
            return false;
        }
    }

    public function usersEditable()
    {
        return false;
    }

    public function passwordsEditable()
    {
        return false;
    }

    public function buildGroupBranch($groupAttrValue, &$branch = array())
    {
        // Load group data. Detect memberOf. Load parent group.
        $origUsersDN = $this->ldapDN;
        $origUsersFilter = $this->ldapFilter;
        $origUsersAttr = $this->ldapUserAttr;
        $this->ldapDN = $this->ldapGDN;
        $this->ldapFilter = $this->ldapGFilter;
        $this->ldapUserAttr = $this->ldapGroupAttr;

        $this->dynamicFilter = $this->ldapGroupAttr . "=" . $groupAttrValue;
        $this->dynamicExpected = array($this->hasGroupsMapping);

        $entries = $this->getUserEntries();
        $this->dynamicFilter = null;
        $this->dynamicExpected = null;
        $persons = array();
        unset($entries['count']); // remove 'count' entry
        $groupData = $entries[0];

        /*
         * memberOf could be a array because of a object(such as user) can be belong to multi-groups
         * example: object w10S was belong to 04 groups
         * [
            {"memberof":
                {
                    "0":"CN=globalmarkettings,OU=MARKETING,OU=company,DC=vpydio,DC=fr",
                    "1":"CN=algers,CN=Alger,OU=algeria,OU=Africe,OU=MARKETING,OU=company,DC=vpydio,DC=fr",
                    "2":"CN=algerias,OU=algeria,OU=Africe,OU=MARKETING,OU=company,DC=vpydio,DC=fr",
                    "3":"CN=africes,OU=Africe,OU=MARKETING,OU=company,DC=vpydio,DC=fr"
                }
                ,
                "0":"memberof",
                "samaccountname":
                {
                    "0":"w10s"
                },
                "1":"samaccountname",
                "dn":"CN=w10s,CN=willaya10,CN=Alger,OU=algeria,OU=Africe,OU=MARKETING,OU=company,DC=vpydio,DC=fr"
            }
            ]
         */
        $memberOfs = $groupData[strtolower($this->hasGroupsMapping)];
        //$memberOf = $groupData[strtolower($this->hasGroupsMapping)][0];


        $this->ldapDN = $origUsersDN;
        $this->ldapFilter = $origUsersFilter;
        $this->ldapUserAttr = $origUsersAttr;

        $this->logDebug(__FUNCTION__, "GroupData[]: " . json_encode($groupData));

        if (!empty($memberOfs)) {

            /*
             * recursively build group branch for each memeber of $memberOfs
             *
             */
            foreach ($memberOfs as $memberOf) {
                if (!empty($memberOf)) {
                    $this->logDebug(__FUNCTION__, "memberOf[]: " . json_encode($memberOf));
                    $parts = explode(",", ltrim($memberOf, '/'));
                    foreach ($parts as $part) {
                        list($att, $attVal) = explode("=", $part);
                        /*
                         * In the example above, 1st CN indicates the name of group, from 2nd, CN indicate a container,
                         * therefore, we just take the first "cn" element by breaking the for if we found.
                         *
                         */
                        if (strtolower($att) == "cn") {
                            $parentCN = $attVal;
                            break;
                        }
                    }
                    if (!empty($parentCN)) {
                        $branch[] = $memberOf;
                        $this->logDebug(__FUNCTION__, "branch[]: " . $branch);

                        /*
                         * recursive function call to look for more group deeply
                         */
                        $this->buildGroupBranch($parentCN, $branch);
                    }
                }
            } // foreach
        }

    }

    /**
     * User user object with mapping rules with attributes from LDAP
     * @param \Pydio\Core\Model\UserInterface $userObject
     */
    public function updateUserObject(&$userObject)
    {

        parent::updateUserObject($userObject);
        if (!empty($this->separateGroup)) $userObject->setGroupPath("/" . $this->separateGroup);
        // SHOULD BE DEPRECATED
        if (!empty($this->customParamsMapping)) {
            $checkValues = array_values($this->customParamsMapping);
            $prefs = $userObject->getPref("CUSTOM_PARAMS");
            if (!is_array($prefs)) {
                $prefs = array();
            }
            // If one value exist, we consider the mapping has already been done.
            foreach ($checkValues as $val) {
                if (array_key_exists($val, $prefs)) return;
            }
            $changes = false;
            $entries = $this->getUserEntries($userObject->getId());
            if ($entries["count"]) {
                $entry = $entries[0];
                foreach ($this->customParamsMapping as $key => $value) {
                    if (isSet($entry[$key])) {
                        $prefs[$value] = $entry[$key][0];
                        $changes = true;
                    }
                }
            }
            if ($changes) {
                $userObject->setPref("CUSTOM_PARAMS", $prefs);
                $userObject->save();
            }
        }
        if (!empty($this->paramsMapping)) {

            $changes = false;
            $entries = $this->getUserEntries($userObject->getId());
            if ($entries["count"]) {
                $entry = $entries[0];

                // search memberof recursively.(if ldap is AD)
                if($this->userRecursiveMemberOf){
                    $this->recursiveMemberOf($entry);
                }

                foreach ($this->paramsMapping as $params) {
                    $key = strtolower($params['MAPPING_LDAP_PARAM']);
                    if (isSet($entry[$key])) {
                        $value = $entry[$key][0];

                        $memberValues = array();

                        if ($key == "memberof") {
                            // get CN from value
                            foreach ($entry[$key] as $possibleValue) {
                                $hnParts = array();
                                $parts = explode(",", ltrim($possibleValue, '/'));
                                foreach ($parts as $part) {
                                    list($att, $attVal) = explode("=", $part);

                                    //if (strtolower($att) == "cn")  $hnParts[] = $attVal;

                                    /*
                                     * In the example above, 1st CN indicates the name of group, from 2nd, CN indicate a container,
                                     * therefore, we just take the first "cn" element by breaking the for if we found.
                                     *
                                     */
                                    if (strtolower($att) == "cn") {
                                        $hnParts[] = $attVal;
                                        break;
                                    }
                                }
                                if (count($hnParts)) {
                                    $memberValues[implode(",", $hnParts)] = $possibleValue;
                                }
                            }
                        }
                        switch ($params['MAPPING_LOCAL_TYPE']) {
                            case "role_id":
                                $valueFilters = null;
                                $matchFilter = null;

                                $filter = $params["MAPPING_LOCAL_PARAM"];
                                if (strpos($filter, "preg:") !== false) {
                                    $matchFilter = "/" . str_replace("preg:", "", $filter) . "/i";
                                } else if (!empty($filter)) {
                                    $valueFilters = array_map("trim", explode(",", $filter));
                                }
                                if ($key == "memberof") {
                                    if (empty($valueFilters)) {
                                        $valueFilters = $this->getLdapGroupListFromDN();
                                    }
                                    if ($this->mappedRolePrefix) {
                                        $rolePrefix = $this->mappedRolePrefix;
                                    } else {
                                        $rolePrefix = "";
                                    }

                                    $userroles = $userObject->getRoles();
                                    //remove all mapped roles before

                                    $oldRoles = array();
                                    $newRoles = array();

                                    if (is_array($userroles)) {
                                        foreach ($userroles as $rkey => $role) {
                                            if ((RolesService::getRole($rkey)) && !(strpos($rkey, $this->mappedRolePrefix) === false)) {
                                                if (isSet($matchFilter) && !preg_match($matchFilter, $rkey)) continue;
                                                if (isSet($valueFilters) && !in_array($rkey, $valueFilters)) continue;
                                                //$userObject->removeRole($key);
                                                $oldRoles[$rkey] = $role;
                                            }
                                        }
                                    }
                                    //$userObject->recomputeMergedRole();

                                    // Detect changes
                                    foreach ($memberValues as $uniqValue => $fullDN) {
                                        $uniqValueWithPrefix = $rolePrefix . $uniqValue;
                                        if (isSet($matchFilter) && !preg_match($matchFilter, $uniqValueWithPrefix)) continue;
                                        if (isSet($valueFilters) && !in_array($uniqValueWithPrefix, $valueFilters)) continue;
                                        $roleToAdd = RolesService::getRole($uniqValueWithPrefix);
                                        if($roleToAdd === false){
                                            $roleToAdd = RolesService::getOrCreateRole($uniqValueWithPrefix);
                                            $roleToAdd->setLabel($uniqValue);
                                            RolesService::updateRole($roleToAdd);
                                        }
                                        $newRoles[$roleToAdd->getId()] = $roleToAdd;
                                        //$userObject->addRole($roleToAdd);
                                    }

                                    if((count(array_diff(array_keys($oldRoles), array_keys($newRoles))) > 0) ||
                                        (count(array_diff(array_keys($newRoles), array_keys($oldRoles))) > 0) )
                                    {
                                        // remove old roles
                                        foreach ($oldRoles as $rkey => $role) {
                                            if ((RolesService::getRole($rkey)) && !(strpos($rkey, $this->mappedRolePrefix) === false)) {
                                                $userObject->removeRole($rkey);
                                            }
                                        }

                                        //Add new roles;
                                        foreach($newRoles as $rkey => $role){
                                            if ((RolesService::getRole($rkey)) && !(strpos($rkey, $this->mappedRolePrefix) === false)) {
                                                $userObject->addRole($role);
                                            }
                                        }
                                        $userObject->recomputeMergedRole();
                                        $changes = true;
                                    }

                                } else {  // Others attributes mapping
                                    if(isSet($entry[$key]["count"])) unset($entry[$key]["count"]);

                                    if ($this->mappedRolePrefix) {
                                        $rolePrefix = $this->mappedRolePrefix;
                                    } else {
                                        $rolePrefix = "";
                                    }

                                    $oldRoles = array();
                                    $newRoles = array();
                                    $userroles = $userObject->getRoles();

                                    // Get old roles
                                    if (is_array($userroles)) {
                                        foreach ($userroles as $rkey => $role) {
                                            if ((RolesService::getRole($rkey)) && !(strpos($rkey, $this->mappedRolePrefix) === false)) {
                                                if (isSet($matchFilter) && !preg_match($matchFilter, $rkey)) continue;
                                                if (isSet($valueFilters) && !in_array($rkey, $valueFilters)) continue;
                                                $oldRoles[$rkey] = $rkey;
                                            }
                                        }
                                    }

                                    // Get new roles
                                    foreach ($entry[$key] as $uniqValue) {
                                        $uniqValueWithPrefix = $rolePrefix . $uniqValue;
                                        if (isSet($matchFilter) && !preg_match($matchFilter, $uniqValueWithPrefix)) continue;
                                        if (isSet($valueFilters) && !in_array($uniqValueWithPrefix, $valueFilters)) continue;
                                        if (!empty($uniqValue)) {
                                            $roleToAdd = RolesService::getRole($uniqValueWithPrefix);
                                            if($roleToAdd === false){
                                                $roleToAdd = RolesService::getOrCreateRole($uniqValueWithPrefix);
                                                $roleToAdd->setLabel($uniqValue);
                                                RolesService::updateRole($roleToAdd);
                                            }
                                            $newRoles[$uniqValueWithPrefix]  = $roleToAdd;
                                        }
                                    }

                                    // Do the sync if two sets of roles are different
                                    if ( (count(array_diff(array_keys($oldRoles), array_keys($newRoles))) > 0) ||
                                        (count(array_diff(array_keys($newRoles), array_keys($oldRoles))) > 0)){
                                        // remove old roles
                                        foreach ($oldRoles as $rkey => $role) {
                                            if ((RolesService::getRole($rkey)) && !(strpos($rkey, $this->mappedRolePrefix) === false)) {
                                                $userObject->removeRole($rkey);
                                            }
                                        }
                                        //Add new roles;
                                        foreach($newRoles as $rkey => $role){
                                            if ((RolesService::getRole($rkey)) && !(strpos($rkey, $this->mappedRolePrefix) === false)) {
                                                $userObject->addRole($role);
                                            }
                                        }
                                        $userObject->recomputeMergedRole();
                                        $changes = true;
                                    }
                                }
                                break;
                            case "group_path":
                                if ($key == "memberof") {
                                    $filter = $params["MAPPING_LOCAL_PARAM"];
                                    if (strpos($filter, "preg:") !== false) {
                                        $matchFilter = "/" . str_replace("preg:", "", $filter) . "/i";
                                    } else if (!empty($filter)) {
                                        $valueFilters = array_map("trim", explode(",", $filter));
                                    }
                                    foreach ($memberValues as $uniqValue => $fullDN) {
                                        if (isSet($matchFilter) && !preg_match($matchFilter, $uniqValue)) continue;
                                        if (isSet($valueFilters) && !in_array($uniqValue, $valueFilters)) continue;
                                        if ($userObject->personalRole->filterParameterValue("auth.ldap", "MEMBER_OF", AJXP_REPO_SCOPE_ALL, "") == $fullDN) {
                                            //break;
                                        }
                                        $humanName = $uniqValue;
                                        $branch = array();
                                        $this->buildGroupBranch($uniqValue, $branch);
                                        $parent = "/";
                                        if (count($branch)) {
                                            $parent = "/" . implode("/", array_reverse($branch));
                                        }
                                        if (!ConfService::getConfStorageImpl()->groupExists(rtrim($userObject->getRealGroupPath($parent), "/") . "/" . $fullDN)) {
                                            try{
                                                UsersService::createGroup($parent, $fullDN, $humanName);
                                            }
                                            catch(\Exception $e){}
                                        }
                                        $userObject->setGroupPath(rtrim($parent, "/") . "/" . $fullDN, true);
                                        // Update Roles from groupPath
                                        $b = array_reverse($branch);
                                        $b[] = $fullDN;
                                        for ($i = 1; $i <= count($b); $i++) {
                                            $userObject->addRole(RolesService::getOrCreateRole("AJXP_GRP_/" . implode("/", array_slice($b, 0, $i)), $userObject->getGroupPath()));
                                        }
                                        $userObject->personalRole->setParameterValue("auth.ldap", "MEMBER_OF", $fullDN);
                                        $userObject->recomputeMergedRole();
                                        $changes = true;
                                    }
                                }
                                break;
                            case "profile":
                                if ($userObject->getProfile() != $value) {
                                    $changes = true;
                                    $userObject->setProfile($value);
                                    RolesService::updateAutoApplyRole($userObject);
                                }
                                break;
                            case "plugin_param":
                            default:
                                if (strpos($params["MAPPING_LOCAL_PARAM"], "/") !== false) {
                                    list($pId, $param) = explode("/", $params["MAPPING_LOCAL_PARAM"]);
                                } else {
                                    $pId = $this->getId();
                                    $param = $params["MAPPING_LOCAL_PARAM"];
                                }
                                if ($userObject->personalRole->filterParameterValue($pId, $param, AJXP_REPO_SCOPE_ALL, "") != $value) {
                                    $userObject->personalRole->setParameterValue($pId, $param, $value);
                                    $userObject->recomputeMergedRole();
                                    $changes = true;
                                }
                                break;
                        }
                    }
                }
            }
            if ($changes) {
                $userObject->save("superuser");
            }
        }
    }

    public function fakeMemberOf($conn, $groupDN, $filterString, $atts, &$entry)
    {
        if (!($conn) || !($groupDN)) return null;

        $searchForGroups = ldap_search($conn, $groupDN, $filterString, $atts);
        $memberOf = array();
        foreach ($searchForGroups as $i => $resourceResult) {
            if ($resourceResult === false) continue;
            $res = ldap_get_entries($conn[$i], $resourceResult);
            if (!empty($res)) {
                $memberOf["count"] += $res["count"];
                unset($res["count"]);
                foreach ($res as $element) {
                    $memberOf[] = $element["dn"];
                }
            }
        }
        if ($memberOf) {
            $isMemberOf = false;
            for ($i = 0; $i < $entry["count"]; $i++) {
                if (strcmp("memberof", strtolower($entry[$i])) === 0) {
                    $isMemberOf = true;
                }
            }
            if (!$isMemberOf) {
                $entry[$entry["count"]] = "memberof";
                $entry["count"]++;
            }
            $entry["memberof"] = $memberOf;
        }
    }

    /**
     * Reconstruct memberOf values recursive.
     * @param $entry ldap user object.
     */
    public function recursiveMemberOf(&$entry){
        $filterPrefix = "member:1.2.840.113556.1.4.1941:=";
        $userDN = $entry["dn"];
        $filterString = $filterPrefix.$userDN;

        // backup ldap configs
        $bkUserDN = $this->ldapDN;
        $this->ldapDN = $this->ldapGDN;
        $bkFilter = $this->dynamicFilter;
        $bkUserFilter = $this->ldapFilter;
        $this->ldapFilter = $filterString;
        $bkUserAttribute = $this->ldapUserAttr;
        $this->ldapUserAttr = $this->ldapGroupAttr;
        $bkDynamicExpected = $this->dynamicExpected;
        $this->dynamicExpected = null;
        $bkCustomParamsMapping = $this->customParamsMapping;
        $this->customParamsMapping = null;
        $bkParamsMapping = $this->paramsMapping;
        $this->paramsMapping = null;

        $searchForGroups = $this->getUserEntries();

        // restore ldap configs
        $this->ldapDN = $bkUserDN;
        $this->dynamicFilter = $bkFilter;
        $this->ldapFilter = $bkUserFilter;
        $this->ldapUserAttr = $bkUserAttribute;
        $this->dynamicExpected = $bkDynamicExpected;
        $this->customParamsMapping = $bkCustomParamsMapping;
        $this->paramsMapping = $bkParamsMapping;

        if (empty($searchForGroups) || $searchForGroups["count"] < 1) return;

        // construct recursive ldap
        $memberOf = array();
        $memberOf["count"] = $searchForGroups["count"];
        unset($searchForGroups["count"]);

        foreach ($searchForGroups as $i => $group) {
            $memberOf[] = $group["dn"];
        }

        $entry[$entry["count"]] = "memberof";
        $entry["count"]++;
        $entry["memberof"] = $memberOf;
    }
    /**
     * @return string
     * @throws \Exception
     */
    private function getCacheCountFileName(){
        return $this->getPluginCacheDir() . DIRECTORY_SEPARATOR . "ldap.ser";
    }

    /**
     * @param $baseGroup
     * @return int
     */
    public function getCountFromCache($baseGroup)
    {
        $ttl = $this->getOption("LDAP_COUNT_CACHE_TTL");
        if (empty($ttl)) $ttl = 1;
        $fileContent = FileHelper::loadSerialFile($this->getCacheCountFileName());
        if (!empty($fileContent) && $fileContent[$baseGroup]["count"] && $fileContent[$baseGroup]["timestamp"] && (time() - $fileContent[$baseGroup]["timestamp"]) < 60 * 60 * $ttl ) {
            return $fileContent[$baseGroup];
        }
        return 0;
    }

    /**
     * @param $fileContent
     * @param $baseGroup
     * @throws \Exception
     */
    public function saveCountToCache($fileContent, $baseGroup)
    {
        if (!is_dir($this->getPluginCacheDir(false, true))) return;
        $fileName = $this->getCacheCountFileName();

        $existing = FileHelper::loadSerialFile($fileName);
        if(!empty($existing) && is_array($existing)) {
            $data = $existing;
        }else{
            $data = [];
        }

        if (is_array($fileContent) && count($fileContent) > 0) {
            $fileContent["timestamp"] = time();
            $data[$baseGroup] = $fileContent;
            FileHelper::saveSerialFile($fileName, $data, false);
        }
    }

    public static $allowedGroupList;

    /***
     * @return array : list of groups according to ldap group filter string
     */
    public function getLdapGroupListFromDN()
    {
        if (isset(self::$allowedGroupList) && !(empty(self::$allowedGroupList)) && count(self::$allowedGroupList) > 0)
            return self::$allowedGroupList;

        $origUsersDN = $this->ldapDN;
        $origUsersFilter = $this->ldapFilter;
        $origUsersAttr = $this->ldapUserAttr;
        $this->ldapDN = $this->ldapGDN;
        $this->ldapFilter = $this->ldapGFilter;
        $this->ldapUserAttr = $this->ldapGroupAttr;

        $entries = $this->getUserEntries();
        $returnArray = array();
        if (is_array($entries) && $entries["count"] > 0) {
            unset($entries["count"]);
            foreach ($entries as $key => $entry) {
                if (isset($this->mappedRolePrefix)) {
                    $returnArray[$this->mappedRolePrefix . $entry[$this->ldapGroupAttr][0]] = $this->mappedRolePrefix . $entry[$this->ldapGroupAttr][0];
                } else {
                    $returnArray[$entry[$this->ldapGroupAttr][0]] = $entry[$this->ldapGroupAttr][0];
                }
            }
        }

        $this->dynamicFilter = null;
        $this->ldapDN = $origUsersDN;
        $this->ldapFilter = $origUsersFilter;
        $this->ldapUserAttr = $origUsersAttr;

        self::$allowedGroupList = $returnArray;
        return $returnArray;
    }

    /**
     * By pass sanitizing user id that make sure tha we can use utf8 user_id
     *
     * @param $s
     * @param int $level
     * @return mixed|string
     */
    public function sanitize($s, $level = InputFilter::SANITIZE_HTML)
    {
        $preg = '/[\\/<>\?\*\\\\|;:,+"\]\[]/';
        /**
         * These are illegal characters and can break ldap searching.
         * when we create new user on Windows AD, these illegal characters will be replaced by '_'.
         * Give a try by replacement of '_'
         */
        $newS = preg_replace($preg, '_', $s);
        return $newS;
    }


    public $rebind_dn;
    public $rebind_pass;
    public function rebind($ldap, $referral) {
        $server= preg_replace('!^(ldap://[^/]+)/.*$!', '\\1', $referral);
        if (!($ldap = ldap_connect($server))){
            // return error
            return 1;
        }
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 1);
        ldap_set_rebind_proc($ldap, "rebind");
        if (!ldap_bind($ldap,$this->rebind_dn,$this->rebind_pass)){
            // return error
            return 1;
        }
        // return success
        return 0;
    }
}
