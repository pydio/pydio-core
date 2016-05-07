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

use Pydio\Core\Utils\Utils;
use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Config loader overrider
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
class CoreUploaderLoader extends Plugin
{
    public $AUTO_LOAD_TYPE = true;

    public function getConfigs()
    {
        $data = parent::getConfigs();
        $this->filterData($data);
        return $data;
    }
    public function loadConfigs($data)
    {
        $this->filterData($data);
        parent::loadConfigs($data);

    }

    private function filterData(&$data)
    {
        $confMaxSize = Utils::convertBytes($data["UPLOAD_MAX_SIZE"]);
        $UploadMaxSize = min(Utils::convertBytes(ini_get('upload_max_filesize')), Utils::convertBytes(ini_get('post_max_size')));
        if (intval($confMaxSize) != 0) {
            $UploadMaxSize = min ($UploadMaxSize, $confMaxSize);
        }
        $data["UPLOAD_MAX_SIZE"] = $UploadMaxSize;

    }
}
