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

class PHPLimits extends AbstractTest
{
    function PHPLimits() { parent::AbstractTest("PHP Limits variables", "<b>Testing configs</b>"); }
    function doTest() 
    { 
    	$this->testedParams["Upload Max Size"] = ini_get("upload_max_filesize");
    	$this->testedParams["Memory Limit"] = ((ini_get("memory_limit")!="")?ini_get("memory_limit"):get_cfg_var("memory_limit"));
    	$this->testedParams["Max execution time"] = ini_get("max_execution_time");
    	$this->testedParams["Safe Mode"] = (ini_get("safe_mode")?"1":"0");
    	$this->testedParams["Safe Mode GID"] = (ini_get("safe_mode_gid")?"1":"0");
    	foreach ($this->testedParams as $paramName => $paramValue){
    		$this->failedInfo .= "\n$paramName=$paramValue";
    	}
        $this->failedLevel = "info";
        return FALSE;
    }
};

?>
