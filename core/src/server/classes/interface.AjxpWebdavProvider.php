<?php

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