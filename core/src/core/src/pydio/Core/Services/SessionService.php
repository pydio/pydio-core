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
namespace Pydio\Core\Services;

use Psr\Http\Message\ServerRequestInterface;

defined('AJXP_EXEC') or die('Access not allowed');

define('PYDIO_SESSION_NAME', 'AjaXplorer');
define('PYDIO_SESSION_QUERY_PARAM', 'ajxp_sessid');

class SessionService
{
    private static $sessionName = PYDIO_SESSION_NAME;

    public static function setSessionName($sessionName){
        self::$sessionName = $sessionName;
    }

    public static function getSessionName(){
        return self::$sessionName;
    }

    public static function start(ServerRequestInterface &$request){

        $getParams = $request->getQueryParams();
        if (isSet($getParams[PYDIO_SESSION_QUERY_PARAM])) {
            $cookies = $request->getCookieParams();
            if (!isSet($cookies[self::$sessionName])) {
                $cookies[self::$sessionName] = $getParams[PYDIO_SESSION_QUERY_PARAM];
                $request = $request->withCookieParams($cookies);
            }
        }

        if(defined("AJXP_SESSION_HANDLER_PATH") && defined("AJXP_SESSION_HANDLER_CLASSNAME") && file_exists(AJXP_SESSION_HANDLER_PATH)){
            require_once(AJXP_SESSION_HANDLER_PATH);
            if(class_exists(AJXP_SESSION_HANDLER_CLASSNAME, false)){
                $sessionHandlerClass = AJXP_SESSION_HANDLER_CLASSNAME;
                $sessionHandler = new $sessionHandlerClass();
                session_set_save_handler($sessionHandler, false);
            }
        }
        session_name(self::$sessionName);
        session_start();

    }

    public static function close(){
        session_write_close();
    }
}