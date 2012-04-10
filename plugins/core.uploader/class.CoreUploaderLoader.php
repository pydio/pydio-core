<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * Config loader overrider
 */
class CoreUploaderLoader extends AJXP_Plugin{

    public function getConfigs(){
        $data = parent::getConfigs();
        $this->filterData($data);
        return $data;
    }
	public function loadConfigs($data){

        $this->filterData($data);
        parent::loadConfigs($data);

	}

	private function filterData(&$data){

        $confMaxSize = AJXP_Utils::convertBytes($data["UPLOAD_MAX_SIZE"]);
        $UploadMaxSize = min(AJXP_Utils::convertBytes(ini_get('upload_max_filesize')), AJXP_Utils::convertBytes(ini_get('post_max_size')));
        if(intval($confMaxSize) != 0) {
            $UploadMaxSize = min ($UploadMaxSize, $confMaxSize);
        }
        $data["UPLOAD_MAX_SIZE"] = $UploadMaxSize;

    }
}
?>