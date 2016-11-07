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
namespace Pydio\Core\Exception;

use Pydio\Access\Core\Model\Repository;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\UserInterface;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class WorkspaceAuthRequired - Extend exception to trigger an authentication error
 * if workspace requires a specific authentication and it cannot be found.
 * @package Pydio\Core\Exception
 */
class WorkspaceAuthRequired extends PydioException {

    private $repositoryId;

    /**
     * WorkspaceAuthRequired constructor.
     * @param string $repositoryId
     * @param string $message
     */
    public function __construct($repositoryId, $message = "Authentication required for this workspace")
    {
        $this->repositoryId = $repositoryId;
        parent::__construct($message, false, null);
    }

    /**
     * @param Repository $workspaceObject
     * @param UserInterface $userObject
     * @throws WorkspaceAuthRequired
     */
    public static function testWorkspace($workspaceObject, $userObject){
        if($workspaceObject->getContextOption(Context::contextWithObjects($userObject, $workspaceObject), "USE_SESSION_CREDENTIALS") !== true){
            return;
        }
        if(MemorySafe::loadCredentials() !== false){
            return;
        }
        throw new WorkspaceAuthRequired($workspaceObject->getId());
    }

}