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
 * Check current PHP Version
 * @package Pydio\Tests
 */
class PHPVersion extends AbstractTest
{

    /**
     * @inheritdoc
     */
    public function __construct() { parent::__construct("PHP version", "Minimum required version is PHP 5.5.9"); }

    /**
     * @inheritdoc
     */
    public function doTest()
    {
        $version = phpversion();
        $this->testedParams["PHP Version"] = $version;
        if (version_compare($version, '5.5.9')  < 0 ) return FALSE;
        $locale = setlocale(LC_CTYPE, 0);
        $dirSep = DIRECTORY_SEPARATOR;
        $this->testedParams["Locale"] = $locale;
        $this->testedParams["Directory Separator"] = $dirSep;
        return TRUE;
    }
}