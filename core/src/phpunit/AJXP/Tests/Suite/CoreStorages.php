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

class AJXP_Suite_CoreStorages extends PHPUnit_Framework_TestSuite
{
    public static function suite()
    {
        $s =  new AJXP_Suite_CoreStorages();
        $s->addTestFile("AJXP/Core/Conf/StoragesTest.php");
        return $s;
    }

    protected function setUp()
    {
        $pServ = AJXP_PluginsService::getInstance();
        ConfService::init();
        $confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);
        $pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", $confPlugin);
        ConfService::start();
    }


    protected function tearDown()
    {
    }

}
