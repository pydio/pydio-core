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
 * Gather various php configurations that will limit the application
 */
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
    	$this->testedParams["Xml parser enabled"] = (function_exists("xml_parser_create")?"1":"0");
    	foreach ($this->testedParams as $paramName => $paramValue){
    		$this->failedInfo .= "\n$paramName=$paramValue";
    	}
        $this->failedLevel = "info";
        return FALSE;
    }
};

?>