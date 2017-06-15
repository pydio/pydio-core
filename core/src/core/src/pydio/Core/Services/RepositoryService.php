<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Services;

use Pydio\Access\Core\Model\Repository;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\FilteredRepositoriesList;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class RepositoryService
 * @package Pydio\Core\Services
 */
class RepositoryService
{
    private $cache = [];
    /**
     * @var RepositoryService
     */
    private static $instance;
    
    /**
     * Singleton method
     *
     * @return RepositoryService The service instance
     */
    public static function getInstance()
    {
        if (!isSet(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }
        return self::$instance;
    }

    /**
     * RepositoryService constructor.
     */
    private function __construct(){

        if (is_file(AJXP_CONF_PATH."/bootstrap_repositories.php")) {
            $REPOSITORIES = array();
            include(AJXP_CONF_PATH."/bootstrap_repositories.php");
            $this->cache["DEFAULT_REPOSITORIES"] = $REPOSITORIES;
        } else {
            $this->cache["DEFAULT_REPOSITORIES"] = array();
        }

    }

    /**
     * @param RepositoryInterface $repositoryObject
     * @param UserInterface $userObject
     * @param bool $details
     * @param bool $includeShared
     * @return bool
     */
    public static function repositoryIsAccessible($repositoryObject, $userObject, $details=false, $includeShared=true)
    {
        if ($userObject === null && UsersService::usersEnabled()) {
            return false;
        }
        if (!$userObject->canSee($repositoryObject)) {
            return false;
        }
        if ($repositoryObject->isTemplate()) {
            return false;
        }
        $isAdminRepo = ($repositoryObject->getAccessType()==="ajxp_conf" || $repositoryObject->getAccessType()==="ajxp_admin");
        if ($isAdminRepo && $userObject !== null) {
            if (UsersService::usersEnabled() && !$userObject->isAdmin()) {
                return false;
            }
        }
        $adminURI = ConfService::getGlobalConf("ADMIN_URI");
        if(ApplicationState::sapiIsCli() && !empty($adminURI) && (($isAdminRepo && !ApplicationState::isAdminMode()) || (!$isAdminRepo && ApplicationState::isAdminMode()))){
            return false;
        }
        $repositoryId = $repositoryObject->getId();
        if ($repositoryObject->getAccessType()=="ajxp_user" && $userObject != null) {
            return ($userObject->canRead($repositoryId) || $userObject->canWrite($repositoryId)) ;
        }
        if ($repositoryObject->getAccessType() == "ajxp_shared" && !UsersService::usersEnabled()) {
            return false;
        }
        if ($repositoryObject->getUniqueUser() && (!UsersService::usersEnabled() || $userObject == null  || $userObject->getId() == "shared" || $userObject->getId() != $repositoryObject->getUniqueUser() )) {
            return false;
        }
        if ( $userObject != null && !($userObject->canRead($repositoryId) || $userObject->canWrite($repositoryId)) && !$details) {
            return false;
        }
        if ($userObject == null || $userObject->canRead($repositoryId) || $userObject->canWrite($repositoryId) || $details) {
            // Do not display standard repositories even in details mode for "sub"users
            if ($userObject != null && $userObject->hasParent() && !($userObject->canRead($repositoryId) || $userObject->canWrite($repositoryId) )) {
                return false;
            }
            // Do not display shared repositories otherwise.
            if ($repositoryObject->hasOwner() && !$includeShared && ($userObject == null || $userObject->getParent() != $repositoryObject->getOwner())) {
                return false;
            }
            if ($userObject != null && $repositoryObject->hasOwner() && !$userObject->hasParent()) {
                // Display the repositories if allow_crossusers is ok
                $fakeCtx = Context::contextWithObjects($userObject, $repositoryObject);
                if(ConfService::getContextConf($fakeCtx, "ALLOW_CROSSUSERS_SHARING", "conf") === false
                    || ConfService::getContextConf($fakeCtx, "ALLOW_CROSSUSERS_SHARING", "conf") === 0) {
                    return false;
                }
                // But still do not display its own shared repositories!
                if ($repositoryObject->getOwner() == $userObject->getId()) {
                    return false;
                }
            }
            if ($repositoryObject->hasOwner() && $userObject != null &&  $details && !($userObject->canRead($repositoryId) || $userObject->canWrite($repositoryId) ) ) {
                return false;
            }
        }
        $res = null;
        $args = array($repositoryId, $repositoryObject, $userObject, &$res);
        Controller::applyIncludeHook("repository.test_access", $args);
        if($res === false){
            return false;
        }
        return true;
    }
    
    
    /**
     * PUBLIC STATIC METHODS
     */

    /**
     * @return RepositoryInterface[]
     */
    public static function getStaticRepositories(){
        $self = self::getInstance();
        if(!isSet($self->cache["STATIC"])){
            $self->cache["STATIC"] = [];
            foreach ($self->cache["DEFAULT_REPOSITORIES"] as $index=>$repository) {
                $repoObject = self::createRepositoryFromArray($index, $repository);
                $repoObject->setWriteable(false);
                $self->cache["STATIC"][$repoObject->getId()] = $repoObject;
            }
        }
        return $self->cache["STATIC"];
    }

    /**
     * @param bool $includeShared
     * @return \Pydio\Core\Model\RepositoryInterface[]
     */
    public static function listAllRepositories($includeShared = false){
        $filtered = new FilteredRepositoriesList();
        $filtered->setIncludeShared($includeShared);
        return $filtered->load();
    }

    /**
     * @param RepositoryInterface[] $repoList
     * @param array $criteria
     * @return RepositoryInterface[] array
     */
    public static function filterRepositoryListWithCriteria($repoList, $criteria)
    {
        $repositories = array();
        $searchableKeys = array("uuid", "parent_uuid", "owner_user_id", "display", "accessType", "isTemplate", "slug", "groupPath");
        foreach ($repoList as $repoId => $repoObject) {
            $failOneCriteria = false;
            foreach ($criteria as $key => $value) {
                if (!in_array($key, $searchableKeys)) continue;
                $criteriumOk = false;
                $comp = null;
                if ($key == "uuid") $comp = $repoObject->getId();
                else if ($key == "parent_uuid") $comp = $repoObject->getParentId();
                else if ($key == "owner_user_id") $comp = $repoObject->getUniqueUser();
                else if ($key == "display") $comp = $repoObject->getDisplay();
                else if ($key == "accessType") $comp = $repoObject->getAccessType();
                else if ($key == "isTemplate") $comp = $repoObject->isTemplate();
                else if ($key == "slug") $comp = $repoObject->getSlug();
                else if ($key == "groupPath") $comp = $repoObject->getGroupPath();
                if (is_array($value) && in_array($comp, $value)) {
                    //$repositories[$repoId] = $repoObject;
                    $criteriumOk = true;
                } else if ($value == AJXP_FILTER_EMPTY && empty($comp)) {
                    //$repositories[$repoId] = $repoObject;
                    $criteriumOk = true;
                } else if ($value == AJXP_FILTER_NOT_EMPTY && !empty($comp)) {
                    //$repositories[$repoId] = $repoObject;
                    $criteriumOk = true;
                } else if (is_string($value) && strpos($value, "regexp:") === 0 && preg_match(str_replace("regexp:", "", $value), $comp)) {
                    //$repositories[$repoId] = $repoObject;
                    $criteriumOk = true;
                } else if ($value == $comp) {
                    //$repositories[$repoId] = $repoObject;
                    $criteriumOk = true;
                }
                if (!$criteriumOk) {
                    $failOneCriteria = true;
                    break;
                }
            }
            if (!$failOneCriteria) {
                $repositories[$repoId] = $repoObject;
            }
        }
        return $repositories;
    }

    /**
     * @param array $criteria
     * @param $count
     * @return \Pydio\Access\Core\Model\Repository[]
     */
    public static function listRepositoriesWithCriteria($criteria, &$count)
    {

        $statics = self::getStaticRepositories();
        $statics = self::filterRepositoryListWithCriteria($statics, $criteria);
        $dyna = ConfService::getConfStorageImpl()->listRepositoriesWithCriteria($criteria, $count);
        $count += count($statics);
        return $statics + $dyna;

    }

    /**
     * Create a repository object from a config options array
     *
     * @param integer $index
     * @param array $repository
     * @return Repository
     */
    public static function createRepositoryFromArray($index, $repository)
    {
        return self::getInstance()->createRepositoryFromArrayInst($index, $repository);
    }

    /**
     * Add dynamically created repository
     *
     * @param \Pydio\Core\Model\RepositoryInterface $oRepository
     * @return -1|null if error
     */
    public static function addRepository($oRepository)
    {
        return self::getInstance()->addRepositoryInst($oRepository);
    }

    /**
     * @param $idOrAlias
     * @return null|\Pydio\Access\Core\Model\Repository
     */
    public static function findRepositoryByIdOrAlias($idOrAlias)
    {
        $repository = RepositoryService::getRepositoryById($idOrAlias);
        if ($repository != null) return $repository;
        $repository = RepositoryService::getRepositoryByAlias($idOrAlias);
        if ($repository != null) return $repository;
        return null;
    }

    /**
     * Get the reserved slugs used for config defined repositories
     * @return array
     */
    public static function reservedSlugsFromConfig()
    {
        $slugs = array();
        $statics = self::getStaticRepositories();
        foreach ($statics as $repo) {
            $slugs[] = $repo->getSlug();
        }
        return $slugs;
    }

    /**
     * Retrieve a repository object
     *
     * @param String $repoId
     * @return RepositoryInterface
     */
    public static function getRepositoryById($repoId)
    {
        return self::getInstance()->getRepositoryByIdInst($repoId);
    }

    /**
     * Retrieve a repository object by its slug
     *
     * @param String $repoAlias
     * @return RepositoryInterface
     */
    public static function getRepositoryByAlias($repoAlias)
    {
        $repo = ConfService::getConfStorageImpl()->getRepositoryByAlias($repoAlias);
        if ($repo !== null) return $repo;
        // check default repositories
        return self::getInstance()->getRepositoryByAliasInstDefaults($repoAlias);
    }

    /**
     * Replace a repository by an update one.
     *
     * @param String $oldId
     * @param RepositoryInterface $oRepositoryObject
     * @return mixed
     */
    public static function replaceRepository($oldId, $oRepositoryObject)
    {
        return self::getInstance()->replaceRepositoryInst($oldId, $oRepositoryObject);
    }

    /**
     * Remove a repository using the conf driver implementation
     * @static
     * @param $repoId
     * @return int
     */
    public static function deleteRepository($repoId)
    {
        return self::getInstance()->deleteRepositoryInst($repoId);
    }

    public function __clone()
    {
        trigger_error("Cannot clone me, i'm a singleton!", E_USER_ERROR);
    }





    /**
     * PRIVATE INSTANCE IMPLEMENTATIONS
     */
    /**
     * See static method
     * @param $repoId
     * @return Repository|null
     */
    private function getRepositoryByIdInst($repoId)
    {
        if (isSet($this->cache["REPOSITORIES"]) && isSet($this->cache["REPOSITORIES"][$repoId])) {
            return $this->cache["REPOSITORIES"][$repoId];
        }
        $test = CacheService::fetch(AJXP_CACHE_SERVICE_NS_SHARED, "pydio:repository:" . $repoId);
        if($test !== false){
            $this->cache["REPOSITORIES"][$repoId] = $test;
            return $test;
        }
        $test =  ConfService::getConfStorageImpl()->getRepositoryById($repoId);
        if($test != null) {
            $this->cache["REPOSITORIES"][$repoId] = $test;
            CacheService::saveWithTimestamp(AJXP_CACHE_SERVICE_NS_SHARED, "pydio:repository:" . $repoId, $test);
            return $test;
        }
        // Finally try to search in default repositories
        $statics = self::getStaticRepositories();
        if (isSet($statics[$repoId])) {
            $repo = $statics[$repoId];
            $this->cache["REPOSITORIES"][$repoId] = $test;
            CacheService::saveWithTimestamp(AJXP_CACHE_SERVICE_NS_SHARED, "pydio:repository:" . $repoId, $repo);
            return $repo;
        }
        $hookedRepo = null;
        $args = array($repoId, &$hookedRepo);
        Controller::applyIncludeHook("repository.search", $args);
        if($hookedRepo !== null){
            return $hookedRepo;
        }
        return null;
    }



    /**
     * See static method
     * @param string $index
     * @param array $repository
     * @return Repository
     */
    private function createRepositoryFromArrayInst($index, $repository)
    {
        $repo = new Repository($index, $repository["DISPLAY"], $repository["DRIVER"]);
        if (isSet($repository["DISPLAY_ID"])) {
            $repo->setDisplayStringId($repository["DISPLAY_ID"]);
        }
        if (isSet($repository["DESCRIPTION_ID"])) {
            $repo->setDescription($repository["DESCRIPTION_ID"]);
        }
        if (isSet($repository["AJXP_SLUG"])) {
            $repo->setSlug($repository["AJXP_SLUG"]);
        }
        if (isSet($repository["IS_TEMPLATE"]) && $repository["IS_TEMPLATE"]) {
            $repo->isTemplate = true;
            $repo->uuid = $index;
        }
        if (array_key_exists("DRIVER_OPTIONS", $repository) && is_array($repository["DRIVER_OPTIONS"])) {
            foreach ($repository["DRIVER_OPTIONS"] as $oName=>$oValue) {
                $repo->addOption($oName, $oValue);
            }
        }
        // BACKWARD COMPATIBILITY!
        if (array_key_exists("PATH", $repository)) {
            $repo->addOption("PATH", $repository["PATH"]);
            $repo->addOption("CREATE", intval($repository["CREATE"]));
            $repo->addOption("RECYCLE_BIN", $repository["RECYCLE_BIN"]);
        }
        return $repo;
    }

    /**
     * @param Repository|\Pydio\Core\Model\RepositoryInterface $oRepository
     * @return -1|null on error
     */
    private function addRepositoryInst($oRepository)
    {
        Controller::applyHook("workspace.before_create", array(Context::emptyContext(), $oRepository));
        $confStorage = ConfService::getConfStorageImpl();
        $res = $confStorage->saveRepository($oRepository);
        if ($res == -1) {
            return $res;
        }
        Controller::applyHook("workspace.after_create", array(Context::emptyContext(), $oRepository));
        Logger::info(__CLASS__,"Create Repository", array("repo_name"=>$oRepository->getDisplay()));
        CacheService::saveWithTimestamp(AJXP_CACHE_SERVICE_NS_SHARED, "pydio:repository:".$oRepository->getId(), $oRepository);
        return null;
    }

    /**
     * See static method
     * @param $repoAlias
     * @return RepositoryInterface|null
     */
    private function getRepositoryByAliasInstDefaults($repoAlias)
    {
        $conf = self::getStaticRepositories();
        foreach ($conf as $repoId => $repo) {
            if ($repo->getSlug() === $repoAlias) {
                return $repo;
            }
        }
        return null;
    }


    /**
     * See static method
     * @param string $oldId
     * @param RepositoryInterface $oRepositoryObject
     * @return int
     */
    private function replaceRepositoryInst($oldId, $oRepositoryObject)
    {
        Controller::applyHook("workspace.before_update", array(Context::emptyContext(), $oRepositoryObject));
        $confStorage = ConfService::getConfStorageImpl();
        $res = $confStorage->saveRepository($oRepositoryObject, true);
        if ($res == -1) {
            return -1;
        }
        Controller::applyHook("workspace.after_update", array(Context::emptyContext(), $oRepositoryObject));
        Logger::info(__CLASS__,"Edit Repository", array("repo_name"=>$oRepositoryObject->getDisplay()));
        CacheService::saveWithTimestamp(AJXP_CACHE_SERVICE_NS_SHARED, "pydio:repository:" . $oRepositoryObject->getId(), $oRepositoryObject);
        return 0;
    }

    /**
     * See static method
     * @param $repoId
     * @return int
     */
    private function deleteRepositoryInst($repoId)
    {
        Controller::applyHook("workspace.before_delete", array(Context::emptyContext(), $repoId));
        $confStorage = ConfService::getConfStorageImpl();
        $shares = $confStorage->listRepositoriesWithCriteria(array("parent_uuid" => $repoId));
        $toDelete = array();
        foreach($shares as $share){
            $toDelete[] = $share->getId();
        }
        $res = $confStorage->deleteRepository($repoId);
        if ($res == -1) {
            return $res;
        }
        foreach($toDelete as $deleteId){
            $this->deleteRepositoryInst($deleteId);
        }
        Controller::applyHook("workspace.after_delete", array(Context::emptyContext(), $repoId));
        Logger::info(__CLASS__,"Delete Repository", array("repo_id"=>$repoId));
        CacheService::delete(AJXP_CACHE_SERVICE_NS_SHARED, "pydio:repository:".$repoId);
        return 0;
    }



}