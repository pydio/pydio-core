<?php
/*
 * Copyright 2007-2013 Charles du Jeu <contact (at) cdujeu.me>
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
 *
 * Description : Real RESTful API access
 */
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\PluginFramework\PluginsService;

include_once("base.conf.php");

ConfService::currentContextIsRestAPI("/api");

ConfService::registerCatchAll();
ConfService::init();
ConfService::start();

$server = new \Pydio\Core\Http\Server(PYDIO_SERVER_MODE_REST);
$request = $server->getRequest();
$server->bootRestServer($request);
ConfService::reloadServicesAndActivePlugins();

$server->listen();