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
 * Store authentication data in an SQL database
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class sqlAuthDriver extends AbstractAuthDriver
{
    public $sqlDriver;
    public $driverName = "sql";

    public function init($options)
    {
        parent::init($options);
        $this->sqlDriver = AJXP_Utils::cleanDibiDriverParameters($options["SQL_DRIVER"]);
        try {
            dibi::connect($this->sqlDriver);
        } catch (DibiException $e) {
            echo get_class($e), ': ', $e->getMessage(), "\n";
            exit(1);
        }
    }

    public function performChecks()
    {
        if(!isSet($this->options)) return;
        $test = AJXP_Utils::cleanDibiDriverParameters($this->options["SQL_DRIVER"]);
        if (!count($test)) {
            throw new Exception("You probably did something wrong! To fix this issue you have to remove the file \"bootstrap.json\" and rename the backup file \"bootstrap.json.bak\" into \"bootsrap.json\" in data/plugins/boot.conf/");
        }
    }

    public function supportsUsersPagination()
    {
        return true;
    }

    // $baseGroup = "/"
    public function listUsersPaginated($baseGroup, $regexp, $offset, $limit, $recursive = true)
    {
        $ignoreHiddens = "NOT EXISTS (SELECT * FROM [ajxp_user_rights] AS c WHERE [c.login]=[u.login] AND [c.repo_uuid] = 'ajxp.hidden')";
        if($recursive){
            $groupPathCondition = "[groupPath] LIKE %like~";
        }else{
            $groupPathCondition = "[groupPath] = %s";
        }
        if ($regexp != null) {
            $res = dibi::query("SELECT * FROM [ajxp_users] AS u WHERE [login] ".AJXP_Utils::regexpToLike($regexp)." AND $groupPathCondition AND $ignoreHiddens ORDER BY [login] ASC %lmt %ofs", AJXP_Utils::cleanRegexp($regexp), $baseGroup, $limit, $offset) ;
        } else if ($offset != -1 || $limit != -1) {
            $res = dibi::query("SELECT * FROM [ajxp_users] AS u WHERE $groupPathCondition AND $ignoreHiddens ORDER BY [login] ASC %lmt %ofs", $baseGroup, $limit, $offset);
        } else {
            $res = dibi::query("SELECT * FROM [ajxp_users] AS u WHERE $groupPathCondition AND $ignoreHiddens ORDER BY [login] ASC", $baseGroup);
        }
        $pairs = $res->fetchPairs('login', 'password');
        return $pairs;
    }

    public function findUserPage($baseGroup, $userLogin, $usersPerPage, $offset){

        $res = dibi::query("SELECT COUNT(*) FROM [ajxp_users] WHERE [login] <= %s", $userLogin);
        $count = $res->fetchSingle();
        return ceil(($count - $offset) / $usersPerPage) - 1;

    }

    public function getUsersCount($baseGroup = "/", $regexp = "", $filterProperty = null, $filterValue = null, $recursive = true)
    {
        // WITH PARENT
        // SELECT COUNT(*) FROM ajxp_users AS u, ajxp_user_rights AS r WHERE u.groupPath LIKE '/%' AND r.login=u.login AND r.repo_uuid = 'ajxp.parent_user'
        // WITH SPECIFIC PARENT 'username'
        // SELECT COUNT(*) FROM ajxp_users AS u, ajxp_user_rights AS r WHERE u.groupPath LIKE '/%' AND r.login=u.login AND r.repo_uuid = 'ajxp.parent_user' AND r.rights = 'username'
        // WITHOUT PARENT
        // SELECT COUNT(*) FROM ajxp_users AS u WHERE NOT EXISTS (SELECT * FROM ajxp_user_rights AS c WHERE c.login=u.login AND c.repo_uuid='ajxp.parent_user')
        $ands = array();
        $select = "SELECT COUNT(*) FROM [ajxp_users] AS u WHERE %and";

        if(!empty($regexp)){
            $ands[] = array("[u.login] ".AJXP_Utils::regexpToLike($regexp), AJXP_Utils::cleanRegexp($regexp));
        }
        if($recursive){
            $ands[] = array("[u.groupPath] LIKE %like~", $baseGroup);
        }else{
            $ands[] = array("[u.groupPath] = %s", $baseGroup);
        }
        $ands[] = array("NOT EXISTS (SELECT * FROM [ajxp_user_rights] AS c WHERE [c.login]=[u.login] AND [c.repo_uuid] = 'ajxp.hidden')");

        if($filterProperty !== null && $filterValue !== null){
            if($filterProperty == "parent"){
                $filterProperty = "ajxp.parent_user";
            }else if($filterProperty == "admin"){
                $filterProperty = "ajxp.admin";
            }
            if($filterValue == AJXP_FILTER_EMPTY){
                $ands[] = array("NOT EXISTS (SELECT * FROM [ajxp_user_rights] AS c WHERE [c.login]=[u.login] AND [c.repo_uuid] = %s)",$filterProperty);
            }else if($filterValue == AJXP_FILTER_NOT_EMPTY){
                $select = "SELECT COUNT(*) FROM [ajxp_users] AS u, [ajxp_user_rights] AS r WHERE %and";
                $ands[] = array("[r.login]=[u.login]");
                $ands[] = array("[r.repo_uuid] = %s", $filterProperty);
            }else{
                $select = "SELECT COUNT(*) FROM [ajxp_users] AS u, [ajxp_user_rights] AS r WHERE %and";
                $ands[] = array("[r.login]=[u.login]");
                $ands[] = array("[r.repo_uuid] = %s", $filterProperty);
                $ands[] = array("[r.rights] ".AJXP_Utils::likeToLike($filterValue), AJXP_Utils::cleanLike($filterValue));
            }
        }

        $res = dibi::query($select, $ands);
        return $res->fetchSingle();
    }

    public function listUsers($baseGroup="/")
    {
        $pairs = array();
        $ignoreHiddens = "NOT EXISTS (SELECT * FROM [ajxp_user_rights] AS c WHERE [c.login]=[u.login] AND [c.repo_uuid] = 'ajxp.hidden')";
        $res = dibi::query("SELECT * FROM [ajxp_users] as u WHERE [u.groupPath] LIKE %like~ AND $ignoreHiddens ORDER BY [u.login] ASC", $baseGroup);
        $rows = $res->fetchAll();
        foreach ($rows as $row) {
            $grp = $row["groupPath"];
            if(strlen($grp) > strlen($baseGroup)) continue;
            $pairs[$row["login"]] = $row["password"];
        }
        return $pairs;
    }

    public function userExists($login)
    {
        $res = dibi::query("SELECT COUNT(*) FROM [ajxp_users] WHERE [login]=%s", $login);
        return (intval($res->fetchSingle()) > 0);
    }

    public function checkPassword($login, $pass, $seed)
    {
        $userStoredPass = $this->getUserPass($login);
        if(!$userStoredPass) return false;

        if ($this->getOptionAsBool("TRANSMIT_CLEAR_PASS")) { // Seed = -1 means that password is not encoded.
            return AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass); //($userStoredPass == md5($pass));
        } else {
            return (md5($userStoredPass.$seed) == $pass);
        }
    }

    public function usersEditable()
    {
        return true;
    }
    public function passwordsEditable()
    {
        return true;
    }

    public function createUser($login, $passwd)
    {
        if($this->userExists($login)) return "exists";
        $userData = array("login" => $login);
        if ($this->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            $userData["password"] = AJXP_Utils::pbkdf2_create_hash($passwd); //md5($passwd);
        } else {
            $userData["password"] = $passwd;
        }
        $userData['groupPath'] = '/';
        dibi::query('INSERT INTO [ajxp_users]', $userData);
    }
    public function changePassword($login, $newPass)
    {
        if(!$this->userExists($login)) throw new Exception("User does not exists!");
        $userData = array("login" => $login);
        if ($this->getOptionAsBool("TRANSMIT_CLEAR_PASS")) {
            $userData["password"] = AJXP_Utils::pbkdf2_create_hash($newPass); //md5($newPass);
        } else {
            $userData["password"] = $newPass;
        }
        dibi::query("UPDATE [ajxp_users] SET ", $userData, "WHERE [login]=%s", $login);
    }
    public function deleteUser($login)
    {
        dibi::query("DELETE FROM [ajxp_users] WHERE [login]=%s", $login);
    }

    public function getUserPass($login)
    {
        $res = dibi::query("SELECT [password] FROM [ajxp_users] WHERE [login]=%s", $login);
        $pass = $res->fetchSingle();
        return $pass;
    }

    public function installSQLTables($param)
    {
        $p = AJXP_Utils::cleanDibiDriverParameters($param["SQL_DRIVER"]);
        return AJXP_Utils::runCreateTablesQuery($p, $this->getBaseDir()."/create.sql");
    }

}
