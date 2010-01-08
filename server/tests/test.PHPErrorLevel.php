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
