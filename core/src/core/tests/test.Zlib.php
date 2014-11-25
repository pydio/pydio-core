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
 * Check that Zlib is enabled
 * @package Pydio
 * @subpackage Tests
 */
class Zlib extends AbstractTest
{
    public function Zlib() { parent::AbstractTest("Zlib extension (ZIP)", "Extension enabled : ".((function_exists('gzopen')||function_exists('gzopen64'))?"1":"0")); }
    public function doTest()
    {
        $this->testedParams["Zlib Enabled"] = ((function_exists('gzopen')||function_exists('gzopen64'))?"Yes":"No");
        $os = PHP_OS;
        /*if (stristr($os, "win")!==false && $this->testedParams["Zlib Enabled"]) {
            $this->failedLevel = "warning";
            $this->failedInfo = "Warning, the zip functions are erraticaly working on Windows, please don't rely too much on them!";
            return FALSE;
        }*/
        $this->failedLevel = "info";
        return false;
    }
};
