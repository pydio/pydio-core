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
 * Check current php error level
 */
class PHPErrorLevel extends AbstractTest
{
    function PHPErrorLevel() { parent::AbstractTest("PHP error level", PHPErrorLevel::error2string(error_reporting())); }
    function doTest() 
    { 
        if (error_reporting() & E_NOTICE)
        {
            $this->failedLevel = "error";
            $this->failedInfo = "You must lower your PHP error level in php.ini NOT TO INCLUDE E_NOTICE (you have:".$this->failedInfo.")";
            return false;
        }
        $this->failedLevel = "info";
        return FALSE;        
    }
	    
	function error2string($value)
	{
	    $level_names = array(
	        E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING',
	        E_PARSE => 'E_PARSE', E_NOTICE => 'E_NOTICE',
	        E_CORE_ERROR => 'E_CORE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING',
	        E_COMPILE_ERROR => 'E_COMPILE_ERROR', E_COMPILE_WARNING => 'E_COMPILE_WARNING',
	        E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING',
	        E_USER_NOTICE => 'E_USER_NOTICE' );
	    if(defined('E_STRICT')) $level_names[E_STRICT]='E_STRICT';
	    $levels=array();
	    if(($value&E_ALL)==E_ALL)
	    {
	        $levels[]='E_ALL';
	        $value&=~E_ALL;
	    }
	    foreach($level_names as $level=>$name)
	        if(($value&$level)==$level) $levels[]=$name;
	    return implode(' | ',$levels);
	}    
};

?>