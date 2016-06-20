<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Core\Http;

use Pydio\Core\Services\ConfService;

defined('AJXP_EXEC') or die('Access not allowed');


class Base
{

    /**
     * @param string $base
     * @param string $route
     */
    public static function handleRoute($base, $route){

        if($route === "/api") {

            $server = new Rest\RestServer($base.$route);

        }else if($route === "/user") {

            $_GET["get_action"] = "user_access_point";
            $server = new Server($base);

        }else if($route == "/favicon"){

            $_GET["get_action"] = "serve_favicon";
            $server = new Server($base);

        }else{

            $server = new Server($base);
            
        }
        $server->registerCatchAll();

        ConfService::init();
        ConfService::start();
        
        $server->listen();
    }

}