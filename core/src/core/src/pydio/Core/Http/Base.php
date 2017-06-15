<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Core\Http;

use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Services\ConfService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Very Top Level Routing
 * @package Pydio\Core\Http
 */
class Base
{

    /**
     * @param string $base
     * @param string $route
     * @param array $additionalAttributes
     */
    public static function handleRoute($base, $route, $additionalAttributes = []){

        if ($route === "/api") {

            $server = new Rest\RestApiServer($base.$route, $additionalAttributes);

        } else if ($route == "/wopi") {

            $server = new Wopi\Server($base.$route, $additionalAttributes);

        } else if ($route === "/user") {

            $_GET["get_action"] = "user_access_point";
            $_GET["key"] = $additionalAttributes["key"];
            $server = new Server($base, $additionalAttributes);

        } else if ($route === "/favicon"){

            $_GET["get_action"] = "serve_favicon";
            $server = new Server($base, $additionalAttributes);

        } else {
            $adminURI = ConfService::getGlobalConf("ADMIN_URI");
            if(!empty($adminURI) && $route === $adminURI || isSet($_GET['settings_mode']) || isSet($_POST['settings_mode'])){
                ApplicationState::setAdminMode();
            }
            $server = new Server($base, $additionalAttributes);
        }

        $server->registerCatchAll();

        ConfService::init();
        ConfService::start();
        
        $server->listen();
    }

}