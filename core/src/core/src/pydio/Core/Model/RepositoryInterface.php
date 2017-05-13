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

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Filter\ContentFilter;
use Pydio\Conf\Core\IGroupPathProvider;
use Pydio\Core\Exception\RepositoryLoadException;


/**
 * The basic abstraction of a data store. Can map a FileSystem, but can also map data from a totally
 * different source, like the application configurations, a mailbox, etc.
 * @package Pydio
 * @subpackage Core
 */
interface RepositoryInterface extends IGroupPathProvider
{

    /**
     * @param ContextInterface $ctx
     * @return AbstractAccessDriver
     * @throws RepositoryLoadException
     */
    public function getDriverInstance(ContextInterface $ctx);

    /**
     * @param AbstractAccessDriver $driverInstance
     */
    public function setDriverInstance($driverInstance);

    /**
     * @param ContentFilter $contentFilter
     */
    public function setContentFilter($contentFilter);

    /**
     * Check if a ContentFilter is set or not
     * @return bool
     */
    public function hasContentFilter();

    /**
     * @return ContentFilter
     */
    public function getContentFilter();

    /**
     * Create a shared version of this repository
     * @param string $newLabel
     * @param array $newOptions
     * @param string $parentId
     * @param string $owner
     * @param string $uniqueUser
     * @return RepositoryInterface
     */
    public function createSharedChild($newLabel, $newOptions, $parentId, $owner, $uniqueUser = null);

    /**
     * Create a child from this repository if it's a template
     * @param string $newLabel
     * @param array $newOptions
     * @param string $creator
     * @param string $uniqueUser
     * @return RepositoryInterface
     */
    public function createTemplateChild($newLabel, $newOptions, $creator = null, $uniqueUser = null);

    /**
     * Recompute uuid
     * @return bool
     */
    public function upgradeId();

    /**
     * Get a uuid
     * @param bool $serial
     * @return string
     */
    public function getUniqueId($serial = false);

    /**
     * Alias for this repository
     * @return string
     */
    public function getSlug();

    /**
     * Use the slugify function to generate an alias from the label
     * @param string $slug
     * @return void
     */
    public function setSlug($slug = null);

    /**
     * Add options
     * @param $oName
     * @param $oValue
     * @return void
     */
    public function addOption($oName, $oValue);

    /**
     * @param ContextInterface $ctx
     * @param string $oName
     * @param null $default
     * @return mixed
     */
    public function getContextOption(ContextInterface $ctx, $oName, $default=null);

    /**
     * @param string $oName
     * @return mixed|string
     * @throws \Exception
     */
    public function getSafeOption($oName);

    /**
     * Get the options that already have a value
     * @return array
     */
    public function getOptionsDefined();

    /**
     * Get the DEFAULT_RIGHTS option
     * @return string
     */
    public function getDefaultRight();

    /**
     * The the access driver type
     * @return String
     */
    public function getAccessType();

    /**
     * The label of this repository
     * @return String
     */
    public function getDisplay();

    /**
     * @return string
     */
    public function getId();

    /**
     * @return boolean
     */
    public function getCreate();

    /**
     * @param boolean $create
     */
    public function setCreate($create);

    /**
     * @param String $accessType
     */
    public function setAccessType($accessType);

    /**
     * @param String $display
     */
    public function setDisplay($display);

    /**
     * @param int $id
     */
    public function setId($id);

    /**
     * @return bool
     */
    public function isWriteable();

    /**
     * @param bool $w
     */
    public function setWriteable($w);

    /**
     * @param string $id
     */
    public function setDisplayStringId($id);

    /**
     * @param string $repoParentId
     * @param string|null $ownerUserId
     * @param string|null $childUserId
     */
    public function setOwnerData($repoParentId, $ownerUserId = null, $childUserId = null);

    /**
     * @return string|null
     */
    public function getOwner();

    /**
     * @return string|null
     */
    public function getParentId();

    /**
     * @return null|RepositoryInterface
     */
    public function getParentRepository();

    /**
     * @return string|null
     */
    public function getUniqueUser();

    /**
     * @return bool
     */
    public function hasOwner();

    /**
     * @return bool
     */
    public function hasParent();

    /**
     * @return bool
     */
    public function isTemplate();

    /**
     * @param $bool
     */
    public function setInferOptionsFromParent($bool);

    /**
     * @return bool
     */
    public function getInferOptionsFromParent();

    /**
     * @param String $descriptionText
     */
    public function setDescription($descriptionText);

    /**
     * @return string
     */
    public function getAccessStatus();

    /**
     * @param string $accessStatus
     */
    public function setAccessStatus($accessStatus);

    /**
     * @return string
     */
    public function getRepositoryType();

    /**
     * @param string $repositoryType
     */
    public function setRepositoryType($repositoryType);

    /**
     * @param bool $public
     * @param null $ownerLabel
     * @return String
     */
    public function getDescription($public = false, $ownerLabel = null);

    /**
     * Infer a security scope for this repository. Will determine to whome the messages
     * will be broadcasted.
     * @return bool|string
     */
    public function securityScope();
}