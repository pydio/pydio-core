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

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Interface ContextInterface
 * @package Pydio\Core\Model
 */
interface ContextInterface
{
    /**
     * @return bool
     */
    public function isEmpty();

    /**
     * @return boolean
     */
    public function hasUser();

    /**
     * @return UserInterface|null
     */
    public function getUser();

    /**
     * @param string $userId
     */
    public function setUserId($userId);

    /**
     * @param UserInterface $user
     */
    public function setUserObject($user);

    public function resetUser();

    /**
     * Build Url base pydio:://user'@'repoId
     * @return string
     */
    public function getUrlBase();

    /**
     * @return boolean
     */
    public function hasRepository();

    /**
     * @return RepositoryInterface|null
     */
    public function getRepository();

    /**
     * @param string $repositoryId
     */
    public function setRepositoryId($repositoryId);

    /**
     * @return string|null
     */
    public function getRepositoryId();

    /**
     * @param RepositoryInterface $repository
     */
    public function setRepositoryObject($repository);

    /**
     * @return mixed
     */
    public function resetRepository();

    /**
     * @return string
     */
    public function getStringIdentifier();

    /**
     * @param $userId
     * @return ContextInterface
     */
    public function withUserId($userId);

    /**
     * @param $repositoryId
     * @return ContextInterface
     */
    public function withRepositoryId($repositoryId);
}