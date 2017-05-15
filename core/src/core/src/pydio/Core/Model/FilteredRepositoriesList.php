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
namespace Pydio\Core\Model;

use Pydio\Core\Controller\Controller;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class FilteredRepositoriesList
 * Set of repositories filtered on a specific scope
 * @package Pydio\Core\Model
 */
class FilteredRepositoriesList
{

    /** @var  string */
    private $scope;
    /** @var  bool */
    private $includeShared = true;
    /** @var  UserInterface */
    private $user;
    /** @var bool  */
    private $details = false;

    /**
     * FilteredRepositoriesList constructor.
     * @param UserInterface $userInterface
     */
    public function __construct(UserInterface $userInterface = null) {
        if(!empty($userInterface)){
            $this->user = $userInterface;
            $this->scope = "user";
        }else{
            $this->scope = "all";
        }
    }

    /**
     * @param $bool
     */
    public function setDetails($bool){
        $this->details = $bool;
    }

    /**
     * @param $bool
     */
    public function setIncludeShared($bool){
        $this->includeShared = $bool;
    }

    /**
     * @return RepositoryInterface[]
     */
    public function load()
    {
        // APPEND CONF FILE REPOSITORIES
        $objList = array();
        if($this->user != null){
            $l = $this->user->getLock();
            if( !empty($l)) return $objList;
        }
        $statics = RepositoryService::getStaticRepositories();
        foreach ($statics as $index=>$repository) {
            if(!empty($this->user)
                && !RepositoryService::repositoryIsAccessible($repository, $this->user, $this->details, $this->includeShared)){
                continue;
            }
            $objList["".$repository->getId()] = $repository;
        }
        // LOAD FROM DRIVER
        $confDriver = ConfService::getConfStorageImpl();
        if($this->scope == "user"){
            $acls = array();
            if($this->user != null){
                $acls = $this->user->getMergedRole()->listAcls(true);
            }
            if(!count($acls)) {
                $drvList = array();
            }else{
                $criteria = array(
                    "uuid" => array_keys($acls)
                );
                $drvList = RepositoryService::listRepositoriesWithCriteria($criteria, $count);
            }
        }else{
            if($this->includeShared){
                $drvList = $confDriver->listRepositories();
            }else{
                $drvList = RepositoryService::listRepositoriesWithCriteria(array(
                    "owner_user_id" => AJXP_FILTER_EMPTY
                ), $count);
            }
        }
        if (is_array($drvList)) {
            /**
             * @var $drvList \Pydio\Access\Core\Model\Repository[]
             */
            foreach ($drvList as $repoId=>$repoObject) {
                $driver = PluginsService::getInstance(Context::emptyContext())->getPluginByTypeName("access", $repoObject->getAccessType());
                if (!is_object($driver) || !$driver->isEnabled()) {
                    unset($drvList[$repoId]);
                } else {
                    $repoObject->setId($repoId);
                    $drvList[$repoId] = $repoObject;
                }
                if($repoObject->hasParent() && !RepositoryService::findRepositoryByIdOrAlias($repoObject->getParentId())){
                    Logger::error(__CLASS__, __FUNCTION__, "Disabling repository ".$repoObject->getSlug()." as parent cannot be correctly loaded.");
                    unset($drvList[$repoId]);
                }
            }
            foreach($drvList as $key => $value){
                
                // Refilter with internal options
                if(isSet($this->user) && !RepositoryService::repositoryIsAccessible($value, $this->user, $this->details, $this->includeShared)){
                    continue;
                }
                
                $objList[$key] = $value;
            }
        }
        $args = array(&$objList, $this->scope, $this->user, $this->includeShared);
        Controller::applyIncludeHook("repository.list", $args);
        return $objList;
    }

    /** @return array */
    public function loadLabels(){
        $repos = $this->load();
        $result = [];
        foreach($repos as $repo){
             $result[$repo->getId()] = $repo->getDisplay();
        }
        return $result;
    }
}