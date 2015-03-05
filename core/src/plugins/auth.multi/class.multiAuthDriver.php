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
 * Ability to encapsulate many auth drivers and choose the right one at login.
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class multiAuthDriver extends AbstractAuthDriver
{
    public $driverName = "multi";
    public $driversDef = array();
    public $currentDriver;

    public $masterSlaveMode = false;
    public $masterName;
    public $slaveName;
    public $baseName;

    public static $schemesCache = null;

    /**
     * @var $drivers AbstractAuthDriver[]
     */
    public $drivers =  array();

    public function init($options)
    {
        //parent::init($options);
        $this->options = $options;
        $this->driversDef = $this->getOption("DRIVERS");
        $this->masterSlaveMode = ($this->getOption("MODE") == "MASTER_SLAVE");
        $this->masterName = $this->getOption("MASTER_DRIVER");
        $this->baseName = $this->getOption("USER_BASE_DRIVER");
        foreach ($this->driversDef as $def) {
            $name = $def["NAME"];
            $options = $def["OPTIONS"];
            $options["TRANSMIT_CLEAR_PASS"] = $this->options["TRANSMIT_CLEAR_PASS"];
            $options["LOGIN_REDIRECT"] = $this->options["LOGIN_REDIRECT"];
            $instance = AJXP_PluginsService::findPlugin("auth", $name);
            if (!is_object($instance)) {
                throw new Exception("Cannot find plugin $name for type 'auth'");
            }
            $instance->init($options);
            if ($name != $this->getOption("MASTER_DRIVER")) {
                $this->slaveName = $name;
            }
            $this->drivers[$name] = $instance;
        }
        if(!$this->masterSlaveMode){
            // Enable Multiple choice login screen
            $multi = AJXP_PluginsService::getInstance()->findPluginById("authfront.multi");
            $multi->enabled = true;
            $multi->options = $this->options;
        }
        // THE "LOAD REGISTRY CONTRIBUTIONS" METHOD
        // WILL BE CALLED LATER, TO BE SURE THAT THE
        // SESSION IS ALREADY STARTED.
    }

    public function getRegistryContributions( $extendedVersion = true )
    {
        // $this->logDebug("get contributions NOW");
        $this->loadRegistryContributions();
        return parent::getRegistryContributions( $extendedVersion );
    }

    private function detectCurrentDriver()
    {
        //if(isSet($this->currentDriver)) return;
        $authSource = $this->getOption("MASTER_DRIVER");
        if (isSet($_POST["auth_source"])) {
            $_SESSION["AJXP_MULTIAUTH_SOURCE"] = $_POST["auth_source"];
            $authSource = $_POST["auth_source"];
            $this->logDebug("Auth source from POST");
        } else if (isSet($_SESSION["AJXP_MULTIAUTH_SOURCE"])) {
            $authSource = $_SESSION["AJXP_MULTIAUTH_SOURCE"];
            $this->logDebug("Auth source from SESSION");
        } else {
            $this->logDebug("Auth source from MASTER");
        }
        $this->setCurrentDriverName($authSource);
    }

    protected function setCurrentDriverName($name)
    {
        $this->currentDriver = $name;
    }

    protected function getCurrentDriver()
    {
        $this->detectCurrentDriver();
        if (isSet($this->currentDriver) && isSet($this->drivers[$this->currentDriver])) {
            return $this->drivers[$this->currentDriver];
        } else {
            return false;
        }
    }

    protected function extractRealId($userId)
    {
        $parts = explode($this->getOption("USER_ID_SEPARATOR"), $userId);
        if (count($parts) == 2) {
            return $parts[1];
        }
        return $userId;
    }

    public function performChecks()
    {
        foreach ($this->drivers as $driver) {
            $driver->performChecks();
        }
    }

    public function getAuthScheme($login)
    {
        if (!isSet(multiAuthDriver::$schemesCache)) {
            foreach ($this->drivers as $scheme => $d) {
                if($d->userExists($login)) return $scheme;
            }
        } else if (isSet(multiAuthDriver::$schemesCache[$login])) {
            return multiAuthDriver::$schemesCache[$login];
        }
        return null;
    }

    public function supportsAuthSchemes()
    {
        return true;
    }

    public function addToCache($usersList, $scheme)
    {
        if (!isset(multiAuthDriver::$schemesCache)) {
            multiAuthDriver::$schemesCache = array();
        }
        foreach ($usersList as $userName) {
            multiAuthDriver::$schemesCache[$userName] = $scheme;
        }
    }

    public function supportsUsersPagination()
    {
        if (!empty($this->baseName)) {
            return $this->drivers[$this->baseName]->supportsUsersPagination();
        } else {
            return $this->drivers[$this->masterName]->supportsUsersPagination() && $this->drivers[$this->slaveName]->supportsUsersPagination();
        }
    }

    // $baseGroup = "/"
    public function listUsersPaginated($baseGroup, $regexp, $offset, $limit, $recursive = true)
    {
        if (!empty($this->baseName) && $regexp == null) {
            $users = $this->drivers[$this->baseName]->listUsersPaginated($baseGroup, $regexp, $offset, $limit, $recursive);
            $this->addToCache(array_keys($users), $this->baseName);
            return $users;
        } else {
            $keys = array_keys($this->drivers);
            $k0 = $keys[0];
            $k1 = $keys[1];
            $users0 = $this->drivers[$k0]->listUsersPaginated($baseGroup, $regexp, $offset, $limit, $recursive);
            $users1 = $this->drivers[$k1]->listUsersPaginated($baseGroup, $regexp, $offset, $limit, $recursive);
            $this->addToCache(array_keys($users0), $k0);
            $this->addToCache(array_keys($users1), $k1);
            return $users0 + $users1;
        }
    }

    public function getUsersCount($baseGroup = "/", $regexp = "", $filterProperty = null, $filterValue = null, $recursive = true)
    {
        if (empty($this->baseName)) {
            if ($this->masterSlaveMode) {
                return $this->drivers[$this->slaveName]->getUsersCount($baseGroup, $regexp, $filterProperty, $filterValue, $recursive) +  $this->drivers[$this->masterName]->getUsersCount($baseGroup, $regexp, $filterProperty, $filterValue, $recursive);
            } else {
                $keys = array_keys($this->drivers);
                return $this->drivers[$keys[0]]->getUsersCount($baseGroup, $regexp, $filterProperty, $filterValue, $recursive) +  $this->drivers[$keys[1]]->getUsersCount($baseGroup, $regexp, $filterProperty, $filterValue, $recursive);
            }
        } else {
            return $this->drivers[$this->baseName]->getUsersCount($baseGroup, $regexp, $filterProperty, $filterValue, $recursive);
        }
    }

    public function isAjxpAdmin($login)
    {
        $keys = array_keys($this->drivers);
        return ($this->drivers[$keys[0]]->getOption("AJXP_ADMIN_LOGIN") === $login) ||  ($this->drivers[$keys[1]]->getOption("AJXP_ADMIN_LOGIN") === $login);
    }

    public function listUsers($baseGroup="/", $recursive = true)
    {
        if ($this->masterSlaveMode) {
            if (!empty($this->baseName)) {
                $users = $this->drivers[$this->baseName]->listUsers($baseGroup, $recursive);
                $this->addToCache(array_keys($users), $this->baseName);
                return $users;
            }
            $masterUsers = $this->drivers[$this->slaveName]->listUsers($baseGroup, $recursive);
            $this->addToCache(array_keys($masterUsers), $this->slaveName);
            $slaveUsers = $this->drivers[$this->masterName]->listUsers($baseGroup, $recursive);
            $this->addToCache(array_keys($slaveUsers), $this->masterName);
            return array_merge($masterUsers, $slaveUsers);
        }
        if ($this->getCurrentDriver()) {
//			return $this->getCurrentDriver()->listUsers($baseGroup);
        }
        $allUsers = array();
        foreach ($this->drivers as $driver) {
            $allUsers = array_merge($allUsers, $driver->listUsers($baseGroup, $recursive));
        }
        return $allUsers;
    }

    public function updateUserObject(&$userObject)
    {
        $s = $this->getAuthScheme($userObject->getId());
        if (!$this->masterSlaveMode) {
            $test = $this->extractRealId($userObject->getId());
            if ($test != $userObject->getId()) {
                $restore = $userObject->getId();
                $userObject->setId($test);
            }
        }
        if (!empty($s) && isSet($this->drivers[$s])) {
            $this->drivers[$s]->updateUserObject($userObject);
        } else if (!empty($this->currentDriver) && isSet($this->drivers[$this->currentDriver])) {
            $this->drivers[$this->currentDriver]->updateUserObject($userObject);
        }
        if(isSet($restore)) $userObject->setId($restore);
    }

    /**
     * List children groups of a given group. By default will report this on the CONF driver,
     * but can be overriden to grab info directly from auth driver (ldap, etc).
     * @param string $baseGroup
     * @return string[]
     */
    public function listChildrenGroups($baseGroup = "/")
    {
        if ($this->masterSlaveMode) {
            if(!empty($this->baseName)) return $this->drivers[$this->baseName]->listChildrenGroups($baseGroup);
            $aGroups = $this->drivers[$this->masterName]->listChildrenGroups($baseGroup);
            $bGroups = $this->drivers[$this->slaveName]->listChildrenGroups($baseGroup);
            return $aGroups + $bGroups;
        }
        if ($this->getCurrentDriver()) {
//            return $this->drivers[$this->currentDriver]->listChildrenGroups($baseGroup);
        }
        $groups = array();
        foreach ($this->drivers as $d) {
            $groups = array_merge($groups, $d->listChildrenGroups($baseGroup));
        }
        return $groups;
    }


    public function preLogUser($remoteSessionId)
    {
        if ($this->masterSlaveMode) {
            $this->drivers[$this->slaveName]->preLogUser($remoteSessionId);
            if (AuthService::getLoggedUser() == null) {
                return $this->drivers[$this->masterName]->preLogUser($remoteSessionId);
            }
            return;
        }

        if ($this->getCurrentDriver()) {
            return $this->getCurrentDriver()->preLogUser($remoteSessionId);
        } else {
            throw new Exception("No driver instanciated in multi driver!");
        }
    }

    public function userExistsWrite($login)
    {
        if ($this->masterSlaveMode) {
            if ($this->drivers[$this->slaveName]->userExists($login)) {
                return true;
            }
            return false;
        } else {
            return $this->userExists($login);
        }
    }

    public function userExists($login)
    {
        if ($this->masterSlaveMode) {
            if ($this->drivers[$this->slaveName]->userExists($login)) {
                return true;
            }
            if ($this->drivers[$this->masterName]->userExists($login)) {
                return true;
            }
            return false;
        }
        $login = $this->extractRealId($login);
        $this->logDebug("user exists ".$login);
        if ($this->getCurrentDriver()) {
            return $this->getCurrentDriver()->userExists($login);
        } else {
            throw new Exception("No driver instanciated in multi driver!");
        }
    }

    public function checkPassword($login, $pass, $seed)
    {
        if ($this->masterSlaveMode) {
            if ($this->drivers[$this->masterName]->userExists($login)) {
                // check master, and refresh slave if necessary
                if ($this->drivers[$this->masterName]->checkPassword($login, $pass, $seed)) {
                    if($this->getFilteredOption("CACHE_MASTER_USERS_TO_SLAVE")){
                        if ($this->drivers[$this->slaveName]->userExists($login)) {
                            $this->drivers[$this->slaveName]->changePassword($login, $pass);
                        } else {
                            $this->drivers[$this->slaveName]->createUser($login, $pass);
                        }
                    }
                    return true;
                } else {
                    if(!$this->getFilteredOption("CACHE_MASTER_USERS_TO_SLAVE") && $this->drivers[$this->slaveName]->userExists($login)){
                        // User may in fact be a SLAVE user
                        return $this->drivers[$this->slaveName]->checkPassword($login, $pass, $seed);
                    }
                    return false;
                }
            } else {
                $res = $this->drivers[$this->slaveName]->checkPassword($login, $pass, $seed);
                return $res;
            }
        }

        $login = $this->extractRealId($login);
        $this->logDebug("check pass ".$login);
        if ($this->getCurrentDriver()) {
            return $this->getCurrentDriver()->checkPassword($login, $pass, $seed);
        } else {
            throw new Exception("No driver instanciated in multi driver!");
        }
    }

    public function usersEditable()
    {
        if($this->masterSlaveMode) return true;

        if ($this->getCurrentDriver()) {
            return $this->getCurrentDriver()->usersEditable();
        } else {
            throw new Exception("No driver instanciated in multi driver!");
        }
    }

    public function passwordsEditable()
    {
        if($this->masterSlaveMode) return true;

        if ($this->getCurrentDriver()) {
            return $this->getCurrentDriver()->passwordsEditable();
        } else {
            //throw new Exception("No driver instanciated in multi driver!");
            $this->logDebug("passEditable no current driver set??");
            return false;
        }
    }

    public function createUser($login, $passwd)
    {
        if ($this->masterSlaveMode) {
            return $this->drivers[$this->slaveName]->createUser($login, $passwd);
        }

        $login = $this->extractRealId($login);
        if ($this->getCurrentDriver()) {
            return $this->getCurrentDriver()->createUser($login, $passwd);
        } else {
            throw new Exception("No driver instanciated in multi driver!");
        }
    }

    public function changePassword($login, $newPass)
    {
        if ($this->masterSlaveMode) {
            return $this->drivers[$this->slaveName]->changePassword($login, $newPass);
        }

        if ($this->getCurrentDriver() && $this->getCurrentDriver()->usersEditable()) {
            return $this->getCurrentDriver()->changePassword($login, $newPass);
        } else {
            throw new Exception("No driver instanciated in multi driver!");
        }
    }

    public function deleteUser($login)
    {
        if ($this->masterSlaveMode) {
            return $this->drivers[$this->slaveName]->deleteUser($login);
        }

        if ($this->getCurrentDriver()) {
            return $this->getCurrentDriver()->deleteUser($login);
        } else {
            throw new Exception("No driver instanciated in multi driver!");
        }
    }

    public function getUserPass($login)
    {
        if ($this->masterSlaveMode) {
            return $this->drivers[$this->slaveName]->getUserPass($login);
        }

        if ($this->getCurrentDriver()) {
            return $this->getCurrentDriver()->getUserPass($login);
        } else {
            throw new Exception("No driver instanciated in multi driver!");
        }
    }

    public function filterCredentials($userId, $pwd)
    {
        if($this->masterSlaveMode) return array($userId, $pwd);
        return array($this->extractRealId($userId), $pwd);
    }

}
