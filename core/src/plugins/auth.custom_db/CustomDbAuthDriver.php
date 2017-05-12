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
namespace Pydio\Auth\Backend;

use dibi;
use DibiException;
use Exception;
use Pydio\Auth\Core\AbstractAuthDriver;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Utils\Vars\OptionsHelper;
use Pydio\Core\Utils\Vars\PasswordEncoder;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Core\Utils\Vars\VarsFilter;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Store authentication data in a custom SQL database with custom schema.
 * Users and password are NOT editable.
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class CustomDbAuthDriver extends AbstractAuthDriver
{
    public $sqlDriver;
    public $driverName = "custom_db";

    protected $customTableName;
    protected $customTableUid;
    protected $customTablePwd;
    protected $customTableHashing;

    protected $coreSqlDriver;

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        $this->sqlDriver = OptionsHelper::cleanDibiDriverParameters($options["SQL_CUSTOM_DRIVER"]);
        $this->coreSqlDriver = OptionsHelper::cleanDibiDriverParameters(array("group_switch_value" => "core"));

        $this->customTableName = $options["SQL_CUSTOM_TABLE"];
        $this->customTableUid = $options["SQL_CUSTOM_TABLE_USER_FIELD"];
        $this->customTablePwd = $options["SQL_CUSTOM_TABLE_PWD_FIELD"];
        $this->customTableHashing = $options["SQL_CUSTOM_TABLE_PWD_HASH"];
    }

    private function connect()
    {
        try {
            dibi::connect($this->sqlDriver);
        } catch (DibiException $e) {
            echo get_class($e), ': ', $e->getMessage(), "\n";
            exit(1);
        }
    }

    private function close()
    {
        dibi::disconnect();
        dibi::connect($this->coreSqlDriver);
    }

    public function performChecks()
    {
        if (!isSet($this->options)) return;
        $test = OptionsHelper::cleanDibiDriverParameters($this->options["SQL_CUSTOM_DRIVER"]);
        if (!count($test)) {
            throw new Exception("Could not find any driver definition for CustomDB plugin! To fix this issue you have to remove the file \"bootstrap.json\" and rename the backup file \"bootstrap.json.bak\" into \"bootsrap.json\" in data/plugins/boot.conf/");
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
     * @return UserInterface[]
     */
    public function listUsersPaginated($baseGroup, $regexp, $offset, $limit, $recursive = true)
    {
        $this->connect();
        $orderBy = "ORDER BY [" . $this->customTableUid . "] ASC";
        if ($this->sqlDriver["driver"] == "mssql") {
            $orderBy = "";
            $offset = $limit = -1;
        }
        if ($regexp != null) {
            if ($offset != -1 && $limit != -1) {
                $res = dibi::query("SELECT [" . $this->customTableUid . "],[" . $this->customTablePwd . "] FROM [" . $this->customTableName . "] WHERE [" . $this->customTableUid . "] " . StringHelper::regexpToLike($regexp) . " $orderBy %lmt %ofs", StringHelper::cleanRegexp($regexp), $limit, $offset);
            } else {
                $res = dibi::query("SELECT [" . $this->customTableUid . "],[" . $this->customTablePwd . "] FROM [" . $this->customTableName . "] WHERE [" . $this->customTableUid . "] " . StringHelper::regexpToLike($regexp) . " $orderBy", StringHelper::cleanRegexp($regexp));
            }
        } else if ($offset != -1 && $limit != -1) {
            $res = dibi::query("SELECT [" . $this->customTableUid . "],[" . $this->customTablePwd . "] FROM [" . $this->customTableName . "] $orderBy %lmt %ofs", $limit, $offset);
        } else {
            $res = dibi::query("SELECT [" . $this->customTableUid . "],[" . $this->customTablePwd . "] FROM [" . $this->customTableName . "] $orderBy");
        }
        $pairs = $res->fetchPairs($this->customTableUid, $this->customTablePwd);
        $this->close();
        return $pairs;
    }

    /**
     * Applicable if supportsUsersPagination(), try to detect at what page the user is
     * @param string $baseGroup
     * @param string $userLogin
     * @param int $usersPerPage
     * @param int $offset
     * @return int
     */
    public function findUserPage($baseGroup, $userLogin, $usersPerPage, $offset = 0)
    {
        $this->connect();
        $res = dibi::query("SELECT COUNT(*) FROM [" . $this->customTableName . "] WHERE [" . $this->customTableUid . "] <= %s", $userLogin);
        $count = $res->fetchSingle();
        $this->close();
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
        $this->connect();
        $ands = array();

        if (!empty($regexp)) {
            $select = "SELECT COUNT(*) FROM [" . $this->customTableName . "] WHERE %and";
            $ands[] = array("[" . $this->customTableUid . "] " . StringHelper::regexpToLike($regexp), StringHelper::cleanRegexp($regexp));
            $res = dibi::query($select);
        } else {
            $select = "SELECT COUNT(*) FROM [" . $this->customTableName . "]";
            $res = dibi::query($select, $ands);
        }
        $this->close();
        return $res->fetchSingle();
    }

    /**
     *
     * @param string $baseGroup
     * @param bool $recursive
     * @return UserInterface[]
     */
    public function listUsers($baseGroup = "/", $recursive = true)
    {
        return $this->listUsersPaginated($baseGroup, null, 0, -1);
    }

    /**
     * @param $login
     * @return boolean
     */
    public function userExists($login)
    {
        $this->connect();
        $res = dibi::query("SELECT COUNT(*) FROM [" . $this->customTableName . "] WHERE [" . $this->customTableUid . "]=%s", $login);
        $this->close();
        return (intval($res->fetchSingle()) > 0);
    }

    /**
     * @param $login
     * @return mixed
     */
    public function getUserPass($login)
    {
        $this->connect();
        $res = dibi::query("SELECT [" . $this->customTablePwd . "] FROM [" . $this->customTableName . "] WHERE [" . $this->customTableUid . "]=%s", $login);
        $pass = $res->fetchSingle();
        $this->close();
        return $pass;
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
        $hashAlgo = $this->getOption("SQL_CUSTOM_TABLE_PWD_HASH");
        if ($hashAlgo == "pbkdf2") {
            return PasswordEncoder::pbkdf2_validate_password($pass, $userStoredPass);
        } else if ($hashAlgo == "md5") {
            return md5($pass) === $userStoredPass;
        } else if ($hashAlgo == "clear") {
            return $pass == $userStoredPass;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function usersEditable()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function passwordsEditable()
    {
        return false;
    }


    /**
     * @param $httpVars
     * @throws Exception
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function testSQLConnexion($httpVars)
    {
        $p = OptionsHelper::cleanDibiDriverParameters($httpVars["SQL_CUSTOM_DRIVER"]);
        if ($p["driver"] == "sqlite3") {
            $dbFile = VarsFilter::filter($p["database"], Context::emptyContext());
            if (!file_exists(dirname($dbFile))) {
                mkdir(dirname($dbFile), 0755, true);
            }
        }

        // Should throw an exception if there was a problem.
        dibi::connect($p);
        $cTableName = $httpVars["SQL_CUSTOM_TABLE"];
        $cUserField = $httpVars["SQL_CUSTOM_TABLE_USER_FIELD"];
        $cUserValue = $httpVars["SQL_CUSTOM_TABLE_TEST_USER"];
        $res = dibi::query("SELECT COUNT(*) FROM [" . $cTableName . "] WHERE [" . $cUserField . "]=%s", $cUserValue);
        $found = (intval($res->fetchSingle()) > 0);
        if (!$found) {
            throw new Exception("Could connect to the DB but could not find user " . $cUserValue);
        }
        dibi::disconnect();
        echo "SUCCESS:Connexion established and user $cUserValue found in DB";

    }

}
