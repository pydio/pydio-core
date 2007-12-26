<?php

class AbstractDriver {
	
	/**
	* @var Repository
	*/
	var $repository;
	
	function AbstractDriver() {	
	}
	
	function setRepository($repository){
		$this->repository = $repository;
	}
		
	function initName($dir)
	{
	}
	
	/**
	 * Read raw data with various headers
	 *
	 * @param string $filePath
	 * @param string $headerType : "plain", "image", "mp3", "download"
	 * @return mixed
	 */
	function readFile($filePath, $headerType="plain")
	{
	}
	
	
	function listing($nom_rep, $dir_only = false)
	{
	}
	
	function date_modif($file)
	{
	}
	
	function copyOrMove($destDir, $selectedFiles, &$error, &$success, $move = false)
	{
	}
	
	
	function rename($filePath, $filename_new)
	{
	}
	
	function mkDir($crtDir, $newDirName)
	{
	}
	
	function createEmptyFile($crtDir, $newFileName)
	{
	}
	
	
	function delete($selectedFiles, &$logMessages)
	{
	}
	
	
	
	function copyOrMoveFile($destDir, $srcFile, &$error, &$success, $move = false)
	{		
	}

	// A function to copy files from one directory to another one, including subdirectories and
	// nonexisting or newer files. Function returns number of files copied.
	// This function is PHP implementation of Windows xcopy  A:\dir1\* B:\dir2 /D /E /F /H /R /Y
	// Syntaxis: [$number =] dircopy($sourcedirectory, $destinationdirectory [, $verbose]);
	// Example: $num = dircopy('A:\dir1', 'B:\dir2', 1);

	function dircopy($srcdir, $dstdir, &$errors, &$success, $verbose = false) 
	{
	}
	
	function simpleCopy($origFile, $destFile)
	{
	}
	
	function isWriteable($dir)
	{
	}
	
	function deldir($location)
	{
	}

	
}

?>