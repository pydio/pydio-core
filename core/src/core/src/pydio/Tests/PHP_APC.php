<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Tests;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Check that DOMXml is enabled
 * @package Pydio\Tests
 */
class PHP_APC extends AbstractTest
{
    /**
     * @inheritdoc
     */
    public function __construct() { parent::__construct("PHP Opcode Cache extension", "Pydio framework loads a lot of PHP files at each query, and using a PHP accelerator is greatly recommanded."); }

    /**
     * @inheritdoc
     */
    public function doTest()
    {
        $this->failedLevel = "warning";
        $v = @extension_loaded('apc');
        if (isSet($v) && (is_numeric($v) || strtolower($v) == "on")) {
            $this->testedParams["PHP APC extension loaded"] = "No";
            $v = false;
        } else if (!isSet($v)) {
            $v = false;
        }
        if(!$v && !@extension_loaded("Zend OPcache")){
            $this->failedInfo = "Pydio framework loads a lot of PHP files at each query, and using a PHP accelerator is greatly recommanded.";
            return FALSE;
        }
        $this->failedInfo = "PHP accelerator extension detected, this is good for performances";
        $this->testedParams["PHP Opcode Cache extension loaded"] = "Yes";
        return TRUE;
    }
}