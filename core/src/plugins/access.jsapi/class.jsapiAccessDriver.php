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
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * AJXP_Plugin to send a javascript source to the browser
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class jsapiAccessDriver extends AbstractAccessDriver
{
    public function switchAction($action, $httpVars, $fileVars)
    {
        switch ($action) {
            case "get_js_source" :
                $jsName = AJXP_Utils::decodeSecureMagic($httpVars["object_name"]);
                $jsType = $httpVars["object_type"]; // class or interface?
                $fName = "class.".strtolower($jsName).".js";
                if ($jsName == "Splitter") {
                    $fName = "splitter.js";
                }
                if (!defined("CLIENT_RESOURCES_FOLDER")) {
                    define("CLIENT_RESOURCES_FOLDER", AJXP_PLUGINS_FOLDER."/gui.ajax/res");
                }
                    // Locate the file class.ClassName.js
                if ($jsType == "class") {
                    $searchLocations = array(
                        CLIENT_RESOURCES_FOLDER."/js/ajaxplorer",
                        CLIENT_RESOURCES_FOLDER."/js/lib",
                        AJXP_INSTALL_PATH."/plugins/"
                    );
                } else if ($jsType == "interface") {
                    $searchLocations = array(
                        CLIENT_RESOURCES_FOLDER."/js/ajaxplorer/interfaces",
                    );
                }
                foreach ($searchLocations as $location) {
                    $dir_iterator = new RecursiveDirectoryIterator($location);
                    $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
                    // could use CHILD_FIRST if you so wish
                    $break = false;
                    foreach ($iterator as $file) {
                        if (strtolower(basename($file->getPathname())) == $fName) {
                            HTMLWriter::charsetHeader("text/plain", "utf-8");
                            echo(file_get_contents($file->getPathname()));
                            $break = true;
                            break;
                        }
                    }
                    if($break) break;
                }
            break;
        }

    }

}
