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
 * Check current PHP Version
 */
class PHPVersion extends AbstractTest
{
    function PHPVersion() { parent::AbstractTest("PHP version", "Minimum required version is PHP 5.1.0, PHP 5.2 or higher recommended when using foreign language"); }
    function doTest() 
    { 
        $version = phpversion(); 
    	$this->testedParams["PHP Version"] = $version;
    	//return false;
        if (floatval($version) < 5.1) return FALSE; 
        $locale = setlocale(LC_CTYPE, 0);
        $dirSep = DIRECTORY_SEPARATOR;
        $this->testedParams["Locale"] = $locale;
        $this->testedParams["Directory Separator"] = $dirSep;
        if (floatval($version) < 5.1 && $locale != "C" && $dirSep != '\\') { $this->failedLevel = "warning"; return FALSE; } // PHP4 doesn't work well with foreign encoding
        return TRUE;
    }
};

?>