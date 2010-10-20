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
defined('AJXP_EXEC') or die( 'Access not allowed');
                                 
require_once('../classes/class.AbstractTest.php');

class Writeability extends AbstractTest
{
    function Writeability() { parent::AbstractTest("Required writeable folder", "One of the following folder should be writeable and is not : "); }
    function doTest() 
    { 
    	include(INSTALL_PATH."/server/conf/conf.php");
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
    	$checked = array();
    	$success = true;
    	foreach ($checks as $check){
    		$w = false;
    		$check = str_replace("AJXP_INSTALL_PATH", INSTALL_PATH, $check);
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
