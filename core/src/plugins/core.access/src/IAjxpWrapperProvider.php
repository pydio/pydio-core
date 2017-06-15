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
namespace Pydio\Access\Core;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Model\ContextInterface;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Interface must be implemented for access drivers that can be accessed via a wrapper protocol.
 * @package Pydio
 * @subpackage Core
 * @interface AjxpWrapperProvider
 */
interface IAjxpWrapperProvider
{
    /**
     * Convert a path (from the repository root) to a fully
     * qualified ajaxplorer url like ajxp.protocol://repoId/path/to/node
     * @param String $path
     * @return String
     */
    public function getResourceUrl($path);

    /**
     * Creates a directory
     * @param String $path
     * @param String $newDirName
     * @param bool $ignoreExists
     * @param bool $recursive
     * @return
     */
    public function mkDir($path, $newDirName, $ignoreExists=false, $recursive=false);

    /**
     * Creates an empty file
     * @param AJXP_Node $node
     * @param string $content
     * @param bool $forceCreation
     * @return
     */
    public function createEmptyFile(AJXP_Node $node, $content = "", $forceCreation = false);

    /**
     * @param AJXP_Node $from
     * @param AJXP_Node $to
     * @param Boolean $copy
     */
    public function nodeChanged(&$from = null, &$to  = null, $copy = false);

    /**
     * @param AJXP_Node $node
     * @param null $newSize
     * @return
     */
    public function nodeWillChange($node, $newSize = null);

    /**
     * @param ContextInterface $ctx
     * @param $nodePath
     * @param $nodeName
     * @param $isLeaf
     * @param $lsOptions
     * @return mixed
     */
    public function filterNodeName(ContextInterface $ctx, $nodePath, $nodeName, &$isLeaf, $lsOptions);
}
