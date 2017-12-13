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
 * The latest code can be found at <https://pydio.com/>.
 */

if(!function_exists('checkIonCubeVersion')){

    function checkIonCubeVersion(){

        if(AJXP_PACKAGE_NAME !== 'pydio-enterprise'){
            // Ignore
            return;
        }
        $ionCubeVersion = phpversion("ionCube Loader");
        if($ionCubeVersion === false){
            throw new Exception("Warning, you must install the IonCube Loaders (v10+) in order to run this upgrade. Aborting.");
        } else if (version_compare($ionCubeVersion, "10.0.0") < 0){
            throw new Exception("Warning, you must upgrade your IonCube Loader to the latest version (v10+) in order to run this upgrade. Aborting.");
        }else{
            print "<div class='upgrade_result success'>IonCube Loader Version is correct ($ionCubeVersion) : OK</div>";
        }

    }

}


checkIonCubeVersion();