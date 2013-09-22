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
namespace AJXP\Tests\Suite;

class Atomics extends \PHPUnit_Framework_TestSuite
{
    public static function suite()
    {
        $s =  new Atomics();
        $s->addTestFile("AJXP/Tests/Atomics/RolesTest.php");
        $s->addTestFile("AJXP/Tests/Atomics/UtilsTest.php");
        $s->addTestFile("AJXP/Tests/Atomics/MemStoresTest.php");
        $s->addTestFile("AJXP/Tests/Atomics/FiltersTest.php");
        return $s;

    }

    protected function setUp()
    {
    }

    protected function tearDown()
    {
    }

}
