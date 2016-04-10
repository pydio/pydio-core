<?php
/*
 * Copyright 2007-2016 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * Check that intl is enabled
 * @package AjaXplorer
 * @subpackage Tests
 */
class PHP_INTL extends AbstractTest
{
    public function __construct() { parent::__construct("PHP INTL extension", "Pydio used intl to localize month names."); }
    public function doTest()
    {
        $this->failedLevel = "warning";

        if (extension_loaded('intl')) {
            $this->failedInfo = "PHP INTL extension detected. Month names can be localized depending on the users language.";
            $this->testedParams["PHP INTL extension loaded"] = "Yes";
            return TRUE;
        } else {
            $this->failedInfo = "PHP INTL extension missing. English month names will be used for all languages.";
            $this->testedParams["PHP INTL extension loaded"] = "No";
            return FALSE;
        }
    }
}
