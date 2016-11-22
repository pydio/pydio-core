<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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


use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\UsersService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class Context
 * Main implementation of ContextInterface, propagating a user and a repository all along the application
 * @package Pydio\Core\Model
 */
class Context implements ContextInterface
{

    /**
     * @var string
     */
    private $userId;

    /**
     * @var
     */
    private $userObject;

    /**
     * @var
     */
    private $repositoryId;

    /**
     * @var
     */
    private $repositoryObject;

    /**
     * Context constructor.
     * @param string $userId
     * @param string $repositoryId
     */
    public function __construct($userId = null, $repositoryId = null)
    {
        if($userId !== null) {
            $this->userId = $userId;
        }
        if($repositoryId !== null){
            $this->repositoryId = $repositoryId;
        }
    }

    /**
     * @param $userObject
     * @param $repositoryObject
     * @return Context
     */
    public static function contextWithObjects($userObject, $repositoryObject){
        $ctx = new Context();
        $ctx->setUserObject($userObject);
        $ctx->setRepositoryObject($repositoryObject);
        return $ctx;
    }

    /**
     * @return Context
     */
    public static function emptyContext(){
        return new Context();
    }

    /**
     * @param $userId
     * @return ContextInterface
     */
    public function withUserId($userId){
        return new Context($userId, $this->repositoryId);
    }

    /**
     * @param $repositoryId
     * @return ContextInterface
     */
    public function withRepositoryId($repositoryId){
        return new Context($this->userId, $repositoryId);
    }


    /**
     * @return boolean
     */
    public function hasUser()
    {
        return !empty($this->userId);
    }

    /**
     * @return UserInterface|null
     */
    public function getUser()
    {
        if(isSet($this->userObject)){
            return $this->userObject;
        }
        if(isSet($this->userId)){
            $this->userObject = UsersService::getUserById($this->userId, false);
            return $this->userObject;
        }
        return null;
    }

    /**
     * @param string $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @param UserInterface $user
     */
    public function setUserObject($user)
    {
        $this->userObject = $user;
        if($user !== null){
            $this->userId = $user->getId();
        }
    }

    /**
     * Set userId and userObject to null
     */
    public function resetUser(){
        $this->userId = null;
        $this->userObject = null;
    }

    /**
     * Builds pydio://user@repository url
     * @return string
     */
    public function getUrlBase()
    {
        $uId = $this->hasUser() ? $this->getUser()->getId() : null;
        return "pydio://".$uId."@".$this->getRepositoryId();
    }

    /**
     * @return boolean
     */
    public function hasRepository()
    {
        return ($this->repositoryId !== null && $this->repositoryId !== '*');
    }

    /**
     * @return RepositoryInterface|null
     */
    public function getRepository()
    {
        if(isSet($this->repositoryId) && $this->repositoryId !== '*'){
            if(!isSet($this->repositoryObject)){
                $this->repositoryObject = RepositoryService::getRepositoryById($this->repositoryId);
            }
            return $this->repositoryObject;
        }
        return null;
    }

    /**
     * @param string $repositoryId
     */
    public function setRepositoryId($repositoryId)
    {
        $this->repositoryId = $repositoryId;
    }

    /**
     * @return string|null
     */
    public function getRepositoryId()
    {
        return $this->repositoryId;
    }

    /**
     * @param RepositoryInterface $repository
     */
    public function setRepositoryObject($repository)
    {
        $this->repositoryObject = $repository;
        if($repository !== null){
            $this->repositoryId = $repository->getId();
        }
    }

    /**
     * Set repositoryId and repositoryObject to null
     */
    public function resetRepository()
    {
        $this->repositoryId = $this->repositoryObject = null;
    }

    /**
     * Build a unique string identifier for this context
     * @return string
     */
    public function getStringIdentifier()
    {
        $u = $this->userId == null ? "shared" : $this->userId;
        $a = "norepository";
        $r = $this->getRepository();
        if($r !== null){
            $a = $r->getSlug();
        }
        return $u.":".$a;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return !$this->hasRepository() && !$this->hasUser();
    }
}