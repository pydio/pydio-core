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
 * Check php session writeability
 */
class PHPSession extends AbstractTest
{
    function PHPSession() { parent::AbstractTest("PHP Session", "<b>Testing configs</b>"); }
    function doTest() 
    { 
    	$tmpDir = session_save_path();
    	$this->testedParams["Session Save Path"] = $tmpDir;
    	if($tmpDir != ""){
	    	$this->testedParams["Session Save Path Writeable"] = is_writable($tmpDir);
	    	if(!$this->testedParams["Session Save Path Writeable"]){
	    		$this->failedLevel = "error";
	    		$this->failedInfo = "The temporary folder used by PHP to save the session data is either incorrect or not writeable! Please check : ".session_save_path();
	    		return FALSE;
	    	}    	
    	}else{
    		$this->failedLevel = "warning";
    		$this->failedInfo = "Warning, it seems that your temporary folder used to save session data is not set. If you are encountering troubles with logging and sessions, please check session.save_path in your php.ini. Otherwise you can ignore this.";
    		return FALSE;    		
    	}
        $this->failedLevel = "info";
        return FALSE;
    }
};

?>