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
 * Check the various plugins folders writeability
 */
class Writeability extends AbstractTest
{
    function Writeability() { parent::AbstractTest("Required writeable folder", "One of the following folder should be writeable and is not : "); }
    function doTest() 
    { 
    	include(AJXP_CONF_PATH."/bootstrap_plugins.php");
    	$checks = array();
    	if(isSet($PLUGINS["CONF_DRIVER"])){
    		$confDriver = $PLUGINS["CONF_DRIVER"];
    		if(isSet($confDriver["OPTIONS"]) && isSet($confDriver["OPTIONS"]["REPOSITORIES_FILEPATH"])){
    			$checks[] =  dirname($confDriver["OPTIONS"]["REPOSITORIES_FILEPATH"]);
    		}
    		if(isSet($confDriver["OPTIONS"]) && isSet($confDriver["OPTIONS"]["USERS_DIRPATH"])){
    			$checks[] = $confDriver["OPTIONS"]["REPOSITORIES_FILEPATH"];
    		}
    	}
    	if(isset($PLUGINS["AUTH_DRIVER"])){
    		$authDriver = $PLUGINS["AUTH_DRIVER"];
    		if(isset($authDriver["OPTIONS"]) && isSet($authDriver["OPTIONS"]["USERS_FILEPATH"])){
    			$checks[] = dirname($authDriver["OPTIONS"]["USERS_FILEPATH"]);
    		}
    	}
    	if(isset($PLUGINS["LOG_DRIVER"])){    		
    		if(isset($PLUGINS["LOG_DRIVER"]["OPTIONS"]) && isSet($PLUGINS["LOG_DRIVER"]["OPTIONS"]["LOG_PATH"])){
    			$checks[] = $PLUGINS["LOG_DRIVER"]["OPTIONS"]["LOG_PATH"];
    		}
    	}    
    	$checks[] = AJXP_CACHE_DIR;	
    	$checked = array();
    	$success = true;
    	foreach ($checks as $check){
    		$w = false;
    		$check = AJXP_VarsFilter::filter($check);
    		if(!is_dir($check)){// Check parent
    			$check = dirname($check);
    		}    		    		
			$w = is_writable($check);
			$checked[basename($check)] = "<b>".basename($check)."</b>:".($w?'true':'false');
    		$success = $success & $w;    		
    	}    	
        $this->testedParams["Writeable Folders"] = "[".implode(',<br> ', array_values($checked))."]";
        if(!$success){
        	$this->failedInfo .= implode(",", $checks);
        	return FALSE;
        }
        $this->failedLevel = "info";
        $this->failedInfo = "[".implode(',<br>', array_values($checked))."]";
        return FALSE;
    }
};

?>