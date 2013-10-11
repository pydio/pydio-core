<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');
require_once('../classes/class.AbstractTest.php');

/**
 * Check that DOMXml is enabled
 * @package AjaXplorer
 * @subpackage Tests
 */
class PHP_OB extends AbstractTest
{
    public function PHP_OB() { parent::AbstractTest("PHP Output Buffer disabled", "You should disable php output_buffering parameter for better performances with Pydio."); }
    public function doTest()
    {
        $this->failedLevel = "warning";
        $v = @ini_get("output_buffering");
        if (isSet($v) && (is_numeric($v) || strtolower($v) == "on")) {
            $this->testedParams["PHP Output Buffer disabled"] = "No";
            return FALSE;
        } else if (!isSet($v)) {
            $this->failedInfo = "Unable to detect the output_buffering value, please make sure that it is disabled (Off) in your php.ini or your virtual host.";
            return FALSE;
        }
        $this->failedInfo = "PHP Output Buffering is disabled, this is good for better performances";
        $this->testedParams["PHP Output Buffer disabled"] = "Yes";
        return TRUE;
    }
};
