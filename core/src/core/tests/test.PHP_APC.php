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
 * Check that DOMXml is enabled
 * @package AjaXplorer
 * @subpackage Tests
 */
class PHP_APC extends AbstractTest
{
    function PHP_APC() { parent::AbstractTest("PHP APC extension", "AjaXplorer framework loads a lot of PHP files at each query, and using a PHP accelerator is greatly recommanded."); }
    function doTest()
    {
        $this->failedLevel = "warning";
        $v = @extension_loaded('apc');
        if (isSet($v) && (is_numeric($v) || strtolower($v) == "on")){
            $this->testedParams["PHP APC extension loaded"] = "No";
            return FALSE;
        }else if(!isSet($v)){
            $this->failedInfo = "AjaXplorer framework loads a lot of PHP files at each query, and using a PHP accelerator is greatly recommanded.";
            return FALSE;
        }
        $this->failedInfo = "PHP APC extension detected, this is good for better performances";
        $this->testedParams["PHP APC extension loaded"] = "Yes";
        return TRUE;
    }
};
