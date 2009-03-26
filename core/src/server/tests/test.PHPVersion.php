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

class PHPVersion extends AbstractTest
{
    function PHPVersion() { parent::AbstractTest("PHP version", "Minimum required version is PHP4.2.0, PHP5.2 or higher recommended when using foreign language"); }
    function doTest() 
    { 
        $version = phpversion(); 
    	$this->testedParams["PHP Version"] = $version;
    	//return false;
        if (floatval($version) < 4.2) return FALSE; 
        $locale = setlocale(LC_CTYPE, 0);
        $dirSep = DIRECTORY_SEPARATOR;
        $this->testedParams["Locale"] = $locale;
        $this->testedParams["Directory Separator"] = $dirSep;
        if (floatval($version) < 5.1 && $locale != "C" && $dirSep != '\\') { $this->failedLevel = "warning"; return FALSE; } // PHP4 doesn't work well with foreign encoding
        return TRUE;
    }
};

?>
