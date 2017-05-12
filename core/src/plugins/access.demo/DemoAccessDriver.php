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
 */
namespace Pydio\Access\Driver\StreamProvider\FS;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\Repository;

use Pydio\Core\Exception\PydioException;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 * @class demoAccessDriver
 * Plugin to access a filesystem with all write actions disabled
 */
class DemoAccessDriver extends FsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws PydioException
     * @throws \Exception
     * @return array|void
     */
    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response)
    {
        $errorMessage = "This is a demo, all 'write' actions are disabled!";
        switch ($request->getAttribute("action")) {
            //------------------------------------
            //	WRITE ACTIONS
            //------------------------------------
            case "put_content":
            case "copy":
            case "move":
            case "rename":
            case "delete":
            case "mkdir":
            case "mkfile":
            case "chmod":
            case "compress":
                throw new PydioException($errorMessage);
            break;

            //------------------------------------
            //	UPLOAD
            //------------------------------------
            case "upload":

                return array("ERROR" => array("CODE" => "", "MESSAGE" => $errorMessage));

            break;

            default:
            break;
        }

        return parent::switchAction($request, $response);

    }

}
