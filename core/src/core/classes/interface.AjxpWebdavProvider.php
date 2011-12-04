<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.core
 */
/**
 * @interface AjxpWebdavProvider
 * Interface must be implemented for access drivers that can be accessed via webdav.
 */
interface AjxpWebdavProvider {

	/**
	 * @return string
	 */
	function getWrapperClassName();
	/**
	 * Convert a path (from the repository root) to a fully 
	 * qualified ajaxplorer url like ajxp.protocol://repoId/path/to/node
	 * @param String $path
	 * @return String
	 */
	function getRessourceUrl($path);
	
	/**
	 * Creates a directory
	 * @param String $path
	 * @param String $newDirName
	 */
	function mkDir($path, $newDirName);	
	
	/**
	 * Creates an empty file
	 * @param String $path
	 * @param String $newDirName
	 */
	function createEmptyFile($path, $newDirName);
}

?>