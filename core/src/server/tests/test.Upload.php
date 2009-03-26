<?php
/**
 * @package info.ajaxplorer
 *
 * Copyright 2007-2009 Cyril Russo
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 *
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 *
 * The main conditions are as follow :
 * You must conspicuously and appropriately publish on each copy distributed
 * an appropriate copyright notice and disclaimer of warranty and keep intact
 * all the notices that refer to this License and to the absence of any warranty;
 * and give any other recipients of the Program a copy of the GNU Lesser General
 * Public License along with the Program.
 *
 * If you modify your copy or copies of the library or any portion of it, you may
 * distribute the resulting library provided you do so under the GNU Lesser
 * General Public License. However, programs that link to the library may be
 * licensed under terms of your choice, so long as the library itself can be changed.
 * Any translation of the GNU Lesser General Public License must be accompanied by the
 * GNU Lesser General Public License.
 *
 * If you copy or distribute the program, you must accompany it with the complete
 * corresponding machine-readable source code or with a written offer, valid for at
 * least three years, to furnish the complete corresponding machine-readable source code.
 *
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * Description : Abstract representation of an action driver. Must be implemented.
 */
                                 
require_once('../classes/class.AbstractTest.php');
if ( !function_exists('sys_get_temp_dir')) 
{
    function sys_get_temp_dir() 
    {
        if (!empty($_ENV['TMP'])) { return realpath($_ENV['TMP']); }
        if (!empty($_ENV['TMPDIR'])) { return realpath( $_ENV['TMPDIR']); }
        if (!empty($_ENV['TEMP'])) { return realpath( $_ENV['TEMP']); }
        $tempfile=tempnam(uniqid(rand(),TRUE),'');
        if (file_exists($tempfile)) {
            unlink($tempfile);
            return realpath(dirname($tempfile));
        }
    }
}

class Upload extends AbstractTest
{
    function Upload() { parent::AbstractTest("Upload particularities", "<b>Testing configs</b>"); }
    function doTest() 
    { 
    	include("../conf/conf.php");
    	$tmpDir = ini_get("upload_tmp_dir");
    	if (!$tmpDir) $tmpDir = sys_get_temp_dir();
    	$this->testedParams["Upload Tmp Dir Writeable"] = is_writable($tmpDir);
    	$this->testedParams["PHP Upload Max Size"] = $this->returnBytes(ini_get("upload_max_filesize"));
    	$this->testedParams["AJXP Upload Max Size"] = $this->returnBytes($upload_max_size_per_file);
    	foreach ($this->testedParams as $paramName => $paramValue){
    		$this->failedInfo .= "\n$paramName=$paramValue";
    	}
    	if(!$this->testedParams["Upload Tmp Dir Writeable"]){
    		$this->failedLevel = "error";
    		$this->failedInfo = "The temporary folder used by PHP to upload files is either incorrect or not writeable! Upload will not work. Please check : ".ini_get("upload_tmp_dir");
    		return FALSE;
    	}
    	if($this->testedParams["AJXP Upload Max Size"] > $this->testedParams["PHP Upload Max Size"]){
    		$this->failedLevel = "warning";
    		$this->failedInfo .= "\nAjaxplorer cannot override the PHP setting! Unless you edit your php.ini, your upload will be limited to ".ini_get("upload_max_filesize")." per file.";
    		return FALSE;
    	}
        $this->failedLevel = "info";
        return FALSE;
    }
};

?>
