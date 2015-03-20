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
 * Interface must be implemented for access drivers that can be accessed via a wrapper protocol.
 * @package Pydio
 * @subpackage Core
 * @interface AjxpWrapperProvider
 */
interface AjxpWrapperProvider
{
    /**
     * @return string
     */
    public function getWrapperClassName();
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
     */
    public function mkDir($path, $newDirName);

    /**
     * Creates an empty file
     * @param String $path
     * @param String $newDirName
     */
    public function createEmptyFile($path, $newDirName);

    /**
     * @param String $from
     * @param String $to
     * @param Boolean $copy
     */
    public function nodeChanged(&$from, &$to, $copy = false);

    /**
     * @param String $node
     * @param null $newSize
     * @return
     */
    public function nodeWillChange($node, $newSize = null);

    /**
     * @param $nodePath
     * @param $nodeName
     * @param $isLeaf
     * @param $lsOptions
     * @return mixed
     */
    public function filterNodeName($nodePath, $nodeName, &$isLeaf, $lsOptions);
}
