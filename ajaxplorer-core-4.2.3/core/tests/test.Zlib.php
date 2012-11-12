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
 * Check that Zlib is enabled
 */
class Zlib extends AbstractTest
{
    function Zlib() { parent::AbstractTest("Zlib extension (ZIP)", "Extension enabled : ".(function_exists('gzopen')?"1":"0")); }
    function doTest() 
    { 
    	$this->testedParams["Zlib Enabled"] = (function_exists('gzopen')?"Yes":"No");
    	$os = PHP_OS;
    	/*if(stristr($os, "win")!==false && $this->testedParams["Zlib Enabled"]){
    		$this->failedLevel = "warning";
    		$this->failedInfo = "Warning, the zip functions are erraticaly working on Windows, please don't rely too much on them!";
    		return FALSE;
    	}*/
        $this->failedLevel = "info";
        return false;
    }
};

?>