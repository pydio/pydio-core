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
 * Check whether GD is installed or not
 */
class PHPGDVersion extends AbstractTest
{
    function PHPGDVersion() { parent::AbstractTest("PHP GD version", "GD is required for generating thumbnails"); }
    function doTest() 
    { 
        $this->failedLevel = "warning";
        if (!function_exists("gd_info") || !function_exists("imagecopyresized") || !function_exists("imagecopyresampled")){
        	$this->testedParams["GD Enabled"] = "No";
        	return FALSE;
        }
        $this->testedParams["GD Enabled"] = "Yes";
        return TRUE;
    }
};

?>