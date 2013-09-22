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
 *
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 * @class demoAccessDriver
 * AJXP_Plugin to access a filesystem with all write actions disabled
 */
class demoAccessDriver extends fsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;

    public function switchAction($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return;
        $errorMessage = "This is a demo, all 'write' actions are disabled!";
        switch ($action) {
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
                return AJXP_XMLWriter::sendMessage(null, $errorMessage, false);
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

        return parent::switchAction($action, $httpVars, $fileVars);

    }

}
