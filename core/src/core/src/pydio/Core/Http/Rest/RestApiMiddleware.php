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
namespace Pydio\Core\Http\Rest;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Middleware\SapiMiddleware;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class RestApiMiddleware
 * Main middleware for routing REST API.
 * @package Pydio\Core\Http\Rest
 */
class RestApiMiddleware extends SapiMiddleware
{
    protected $base;

    /**
     * RestApiMiddleware constructor.
     * @param string $base
     */
    public function __construct($base)
    {
        $this->base = $base;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws PydioException
     */
    protected function parseRequestRouteAndParams(ServerRequestInterface &$request, ResponseInterface &$response){

        $router = new ApiRouter($this->base);
        if(!$router->route($request, $response)){
            throw new PydioException("Could not find any endpoint for this URI");
        }

    }

}
