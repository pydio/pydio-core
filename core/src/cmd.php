<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 *
 * Description : Command line access of the framework.
 */
if (php_sapi_name() !== "cli") {
    die("This is the command line version of the framework, you are not allowed to access this page");
}
include_once ("base.conf.php");
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\AuthService;
use Symfony\Component\Console\Application;
use Pydio\Core\Http\Cli\Command;

ConfService::init();
ConfService::start();

$input = new \Pydio\Core\Http\Cli\FreeArgvOptions();
$application = new Application();
$application->add(new Command());
$application->setDefaultCommand("pydio");
$application->run($input);