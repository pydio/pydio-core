<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

use dibi;
use DibiException;
use Exception;
use Pydio\Auth\Core\AbstractAuthDriver;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Utils\DBHelper;
use Pydio\Core\Utils\Vars\OptionsHelper;
use Pydio\Core\Utils\Vars\PasswordEncoder;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Core\PluginFramework\SqlTableProvider;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Store authentication data in an SQL database
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class SqlAuthDriver extends AbstractAuthDriver implements SqlTableProvider
{
    public $sqlDriver;
    public $driverName = "sql";

    /**
     * @param ContextInterface $ctx
     * @param array $options
     * @throws Exception
     * @throws \Pydio\Core\Exception\DBConnectionException
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        if (empty($options["SQL_DRIVER"])) return;
        $this->sqlDriver = OptionsHelper::cleanDibiDriverParameters($options["SQL_DRIVER"]);
        try {
            if (!dibi::isConnected()) {
                dibi::connect($this->sqlDriver);
            }
        } catch (DibiException $e) {
            throw new \Pydio\Core\Exception\DBConnectionException();
        }
    }

    public function performChecks()
    {
        if (!isSet($this->options)) return;
        $test = OptionsHelper::cleanDibiDriverParameters($this->options["SQL_DRIVER"]);
        if (!count($test)) {
            throw new Exception("You probably did something wrong! To fix this issue you have to remove the file \"bootstrap.json\" and rename the backup file \"bootstrap.json.bak\" into \"bootsrap.json\" in data/plugins/boot.conf/");
        }
    }

    /**
     * Wether users can be listed using offset and limit
     * @return bool
     */
    public function supportsUsersPagination()
    {
        return true;
    }

    // $baseGroup = "/"
    /**
     * List users using offsets
     * @param string $baseGroup
     * @param string $regexp
     * @param int $offset
     * @param int $limit
     * @param bool $recursive
     * @return []
     */
    public function listUsersPaginated($baseGroup, $regexp, $offset, $limit, $recursive = true)
    {
        $ignoreHiddens = "NOT EXISTS (SELECT * FROM [ajxp_user_rights] AS c WHERE [c.login]=[u.login] AND [c.repo_uuid] = 'ajxp.hidden')";
        if ($recursive) {
            $groupPathCondition = "[groupPath] LIKE %like~";
        } else {
            $groupPathCondition = "[groupPath] = %s";
        }
        if ($regexp != null) {
            $res = dibi::query("SELECT * FROM [ajxp_users] AS u WHERE [login] " . StringHelper::regexpToLike($regexp) . " AND $groupPathCondition AND $ignoreHiddens ORDER BY [login] ASC %lmt %ofs", StringHelper::cleanRegexp($regexp), $baseGroup, $limit, $offset);
        } else if ($offset != -1 || $limit != -1) {
            $res = dibi::query("SELECT * FROM [ajxp_users] AS u WHERE $groupPathCondition AND $ignoreHiddens ORDER BY [login] ASC %lmt %ofs", $baseGroup, $limit, $offset);
        } else {
            $res = dibi::query("SELECT * FROM [ajxp_users] AS u WHERE $groupPathCondition AND $ignoreHiddens ORDER BY [login] ASC", $baseGroup);
        }
        $pairs = $res->fetchPairs('login', 'password');
        return $pairs;
    }

    /**
     * See parent method
     * @param string $baseGroup
     * @param string $userLogin
     * @param int $usersPerPage
     * @param int $offset
     * @return float
     */
    public function findUserPage($baseGroup, $userLogin, $usersPerPage, $offset = 0)
    {

        $res = dibi::query("SELECT COUNT(*) FROM [ajxp_users] WHERE [login] <= %s", $userLogin);
        $count = $res->fetchSingle();
        return ceil(($count - $offset) / $usersPerPage) - 1;

    }

    /**
     * @param string $baseGroup
     * @param string $regexp
     * @param null|string $filterProperty Can be "admin" or "parent"
     * @param null|string $filterValue Can be a user Id, or AJXP_FILTER_EMPTY or AJXP_FILTER_NOT_EMPTY
     * @param bool $recursive
     * @return int
     */
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

        if (!empty($regexp)) {
            $ands[] = array("[u.login] " . StringHelper::regexpToLike($regexp), StringHelper::cleanRegexp($regexp));
        }
        if ($recursive) {
            $ands[] = array("[u.groupPath] LIKE %like~", $baseGroup);
        } else {
            $ands[] = array("[u.groupPath] = %s", $baseGroup);
        }
        $ands[] = array("NOT EXISTS (SELECT * FROM [ajxp_user_rights] AS c WHERE [c.login]=[u.login] AND [c.repo_uuid] = 'ajxp.hidden')");

        if ($filterProperty !== null && $filterValue !== null) {
            if ($filterProperty == "parent") {
                $filterProperty = "ajxp.parent_user";
            } else if ($filterProperty == "admin") {
                $filterProperty = "ajxp.admin";
            }
            if ($filterValue == AJXP_FILTER_EMPTY) {
                $ands[] = array("NOT EXISTS (SELECT * FROM [ajxp_user_rights] AS c WHERE [c.login]=[u.login] AND [c.repo_uuid] = %s)", $filterProperty);
            } else if ($filterValue == AJXP_FILTER_NOT_EMPTY) {
                $select = "SELECT COUNT(*) FROM [ajxp_users] AS u, [ajxp_user_rights] AS r WHERE %and";
                $ands[] = array("[r.login]=[u.login]");
                $ands[] = array("[r.repo_uuid] = %s", $filterProperty);
            } else {
                $select = "SELECT COUNT(*) FROM [ajxp_users] AS u, [ajxp_user_rights] AS r WHERE %and";
                $ands[] = array("[r.login]=[u.login]");
                $ands[] = array("[r.repo_uuid] = %s", $filterProperty);
                $ands[] = array("[r.rights] " . StringHelper::likeToLike($filterValue), StringHelper::cleanLike($filterValue));
            }
        }

        $res = dibi::query($select, $ands);
        return $res->fetchSingle();
    }

    /**
     * See parent method
     * @param string $baseGroup
     * @param bool|true $recursive
     * @return array
     */
    public function listUsers($baseGroup = "/", $recursive = true)
    {
        $pairs = array();
        $ignoreHiddens = "NOT EXISTS (SELECT * FROM [ajxp_user_rights] AS c WHERE [c.login]=[u.login] AND [c.repo_uuid] = 'ajxp.hidden')";
        $res = dibi::query("SELECT * FROM [ajxp_users] as u WHERE [u.groupPath] LIKE %like~ AND $ignoreHiddens ORDER BY [u.login] ASC", $baseGroup);
        $rows = $res->fetchAll();
        foreach ($rows as $row) {
            $grp = $row["groupPath"];
            if (strlen($grp) > strlen($baseGroup)) continue;
            $pairs[$row["login"]] = $row["password"];
        }
        return $pairs;
    }

    /**
     * @param $login
     * @return boolean
     */
    public function userExists($login)
    {
        $res = dibi::query("SELECT COUNT(*) FROM [ajxp_users] WHERE [login]=%s", $login);
        return (intval($res->fetchSingle()) > 0);
    }

    /**
     * @param string $login
     * @param string $pass
     * @return bool
     */
    public function checkPassword($login, $pass)
    {
        $userStoredPass = $this->getUserPass($login);
        if (!$userStoredPass) return false;
        return PasswordEncoder::pbkdf2_validate_password($pass, $userStoredPass);
    }

    /**
     * @return bool
     */
    public function usersEditable()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function passwordsEditable()
    {
        return true;
    }

    /**
     * @param $login
     * @param $passwd
     */
    public function createUser($login, $passwd)
    {
        if ($this->userExists($login)) return;
        $userData = array("login" => $login);
        $userData["password"] = PasswordEncoder::pbkdf2_create_hash($passwd);
        $userData['groupPath'] = '/';
        dibi::query('INSERT INTO [ajxp_users]', $userData);
    }

    /**
     * @param $login
     * @param $newPass
     * @throws PydioException
     */
    public function changePassword($login, $newPass)
    {
        if (!$this->userExists($login)) {
            throw new PydioException("User does not exists!");
        }
        $userData = array("login" => $login);
        $userData["password"] = PasswordEncoder::pbkdf2_create_hash($newPass);
        dibi::query("UPDATE [ajxp_users] SET ", $userData, "WHERE [login]=%s", $login);
    }

    /**
     * @param $login
     */
    public function deleteUser($login)
    {
        dibi::query("DELETE FROM [ajxp_users] WHERE [login]=%s", $login);
    }

    /**
     * @param $login
     * @return mixed
     */
    public function getUserPass($login)
    {
        $res = dibi::query("SELECT [password] FROM [ajxp_users] WHERE [login]=%s", $login);
        $pass = $res->fetchSingle();
        return $pass;
    }

    /**
     * @param array $param
     * @return string
     * @throws Exception
     */
    public function installSQLTables($param)
    {
        $p = OptionsHelper::cleanDibiDriverParameters($param["SQL_DRIVER"]);
        return DBHelper::runCreateTablesQuery($p, $this->getBaseDir() . "/create.sql");
    }

}
