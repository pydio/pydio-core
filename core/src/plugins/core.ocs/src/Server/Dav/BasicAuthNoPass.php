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
namespace Pydio\OCS\Server\Dav;

defined('AJXP_EXEC') or die('Access not allowed');

use Sabre\HTTP;

/**
 * Class BasicAuthNoPass
 * Implements OCS Spec to log user with "empty_user:password"
 * @package Pydio\OCS\Server\Dav
 */
class BasicAuthNoPass extends HTTP\BasicAuth
{
    /**
     * Returns the supplied username and password.
     *
     * The returned array has two values:
     *   * 0 - username
     *   * 1 - password
     *
     * If nothing was supplied, 'false' will be returned
     *
     * @return mixed
     */
    public function getUserPass() {

        return self::parseUserPass($this->httpRequest);

    }

    /**
     * @param HTTP\Request $httpRequest
     * @return array|bool
     */
    public static function parseUserPass(HTTP\Request $httpRequest){

        // Apache and mod_php
        if (($user = $httpRequest->getRawServerValue('PHP_AUTH_USER'))) {
            $pass = "";
            if(($passw = $httpRequest->getRawServerValue('PHP_AUTH_PW'))){
                $pass = $passw;
            }
            return array($user,$pass);
        }

        // Most other webservers
        $auth = $httpRequest->getHeader('Authorization');

        // Apache could prefix environment variables with REDIRECT_ when urls
        // are passed through mod_rewrite
        if (!$auth) {
            $auth = $httpRequest->getRawServerValue('REDIRECT_HTTP_AUTHORIZATION');
        }

        if (!$auth) return false;

        if (strpos(strtolower($auth),'basic')!==0) return false;

        return explode(':', base64_decode(substr($auth, 6)),2);


    }

}