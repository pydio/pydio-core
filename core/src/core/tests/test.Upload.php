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
require_once('../classes/class.AbstractTest.php');

/**
 * @package info.ajaxplorer.test
 * Test upload tmp dir
 */
class Upload extends AbstractTest
{
    function Upload() { parent::AbstractTest("Upload particularities", "<b>Testing configs</b>"); }
    function doTest() 
    {     	
    	$tmpDir = ini_get("upload_tmp_dir");
    	if (!$tmpDir) $tmpDir = realpath(sys_get_temp_dir());
        if(ConfService::getCoreConf("AJXP_TMP_DIR") != ""){
            $tmpDir = ConfService::getCoreConf("AJXP_TMP_DIR");
        }
    	if(defined("AJXP_TMP_DIR") && AJXP_TMP_DIR !=""){
    		$tmpDir = AJXP_TMP_DIR;
    	}
    	$this->testedParams["Upload Tmp Dir Writeable"] = is_writable($tmpDir);
    	$this->testedParams["PHP Upload Max Size"] = $this->returnBytes(ini_get("upload_max_filesize"));
    	$this->testedParams["PHP Post Max Size"] = $this->returnBytes(ini_get("post_max_size"));
    	//$this->testedParams["AJXP Upload Max Size"] = $this->returnBytes($upload_max_size_per_file);
    	foreach ($this->testedParams as $paramName => $paramValue){
    		$this->failedInfo .= "\n$paramName=$paramValue";
    	}
    	if(!$this->testedParams["Upload Tmp Dir Writeable"]){
    		$this->failedLevel = "error";
    		$this->failedInfo = "The temporary folder used by PHP to upload files is either incorrect or not writeable! Upload will not work. Please check : ".ini_get("upload_tmp_dir");
    		return FALSE;
    	}
        /*
    	if($this->testedParams["AJXP Upload Max Size"] > $this->testedParams["PHP Upload Max Size"]){
    		$this->failedLevel = "warning";
    		$this->failedInfo .= "\nAjaxplorer cannot override the PHP setting! Unless you edit your php.ini, your upload will be limited to ".ini_get("upload_max_filesize")." per file.";
    		return FALSE;
    	}
    	if($this->testedParams["AJXP Upload Max Size"] > $this->testedParams["PHP Post Max Size"]){
    		$this->failedLevel = "warning";
    		$this->failedInfo .= "\nAjaxplorer cannot override the PHP setting! Unless you edit your php.ini, your upload will be limited to ".ini_get("post_max_size")." per file.";
    		return FALSE;
    	}
        */
        
        $this->failedLevel = "info";
        return FALSE;
    }
};

?>