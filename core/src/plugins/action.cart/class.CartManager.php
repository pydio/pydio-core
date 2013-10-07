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

class CartManager extends AJXP_Plugin
{
    public function switchAction ($actionName, $httpVars, $fileVars)
    {
        if ($actionName == "search-cart-download") {

            // Pipe SEARCH + DOWNLOAD actions.

            $indexer = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("index");
            if($indexer == false) return;
            $httpVars["return_selection"] = true;
            unset($httpVars["get_action"]);
            $res = AJXP_Controller::findActionAndApply("search", $httpVars, $fileVars);
            if (isSet($res) && is_array($res)) {
                $newHttpVars = array(
                    "selection_nodes"   => $res,
                    "dir"               => "__AJXP_ZIP_FLAT__/",
                    "archive_name"      => $httpVars["archive_name"]
                );
                AJXP_Controller::findActionAndApply("download", $newHttpVars, array());
            }

        }

    }

}
