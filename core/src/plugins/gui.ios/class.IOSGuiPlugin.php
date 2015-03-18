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

/**
 * Test user agent
 * @package AjaXplorer_Plugins
 * @subpackage Gui
 */
class IOSGuiPlugin extends AJXP_Plugin
{
    public function performChecks()
    {
        if(isSet($_SESSION["CURRENT_MINISITE"])) throw new Exception("Disabled for minisites");

        if (AJXP_Utils::userAgentIsIOS() && !isSet($_GET["skipIOS"]) && !isSet($_COOKIE["SKIP_IOS"])) {
            return;
        }
        if (AJXP_Utils::userAgentIsAndroid() && !isSet($_GET["skipANDROID"]) && !isSet($_COOKIE["SKIP_ANDROID"])) {
            return;
        }
        throw new Exception("Active only when mobile user agent detected.");
    }

}
