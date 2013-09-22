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

global $MAIN_testsArray;
/**
 * Abstract test class
 * Abstract class for diagnostic tests. These tests are run at the first application start up, and their
 * results are displayed in the Diagnostic page. It's possible to re-run the full diagnostic by calling
 * the runTests.php script (first line needs to be commented first).
 * @package Pydio
 * @subpackage Core
 */
class AbstractTest
{
    /** The test name */
    public $name;
    /** The test information when failed */
    public $failedInfo;
    /** The test results output (used for report) */
    public $resultOutput;
    /** Tested params - When used as a diagnostic tool, can store variables used by the test*/
    public $testedParas;
    /** The test level when failed (warning, info or error, default to error) */
    public $failedLevel;
    /** The test parameters */
    public $params;

    public function AbstractTest($name, $failedInfo, $params = NULL)
    {
        $this->name = $name;
        $this->failedInfo = $failedInfo;
        $this->params = $params;
        $this->failedLevel = "error";
        $this->testedParams = array();
        global $MAIN_testsArray;
        $MAIN_testsArray[] = $this;
    }

    /**
     * Perform the test, should be overwritten in concrete classes
     * @abstract
     * @return Boolean
     */
    public function doTest() { return FALSE; }

    /**
     * Perform the test on a given repository object, should be overwritten in concrete classes
     * @param Repository $repository
     * @return Boolean
     */
    public function doRepositoryTest($repository) { return FALSE; }

    /**
     * Utilitary to convert php config to numerical values.
     *
     * @param String $val
     * @return Integer
     */
    public function returnBytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch ($last) {
            // Le modifieur 'G' est disponible depuis PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
};
