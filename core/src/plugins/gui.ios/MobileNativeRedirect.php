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
 * The latest code can be found at <https://pydio.com>.
 */

namespace Pydio\Gui;

use Exception;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Http\UserAgent;

use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Test user agent
 * @package AjaXplorer_Plugins
 * @subpackage Gui
 */
class MobileNativeRedirect extends Plugin
{
    public function performChecks()
    {
        if (ApplicationState::hasMinisiteHash()) {
            throw new Exception("Disabled for minisites");
        }
        if (UserAgent::userAgentIsWindowsPhone()) {
            throw new Exception("No native app for windows phone");
        }

        if (UserAgent::userAgentIsIOS() && !isSet($_GET["skipIOS"]) && !isSet($_COOKIE["SKIP_IOS"])) {
            return;
        }
        if (UserAgent::userAgentIsAndroid() && !isSet($_GET["skipANDROID"]) && !isSet($_COOKIE["SKIP_ANDROID"])) {
            return;
        }
        throw new Exception("Active only when mobile user agent detected.");
    }

}
