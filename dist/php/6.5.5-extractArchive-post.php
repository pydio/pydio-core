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

/**
 * @param string $version
 * @throws Exception
 */
function checkPhpVersion($version){
    if(version_compare(PHP_VERSION, $version) < 0){
        throw new Exception("For Pydio 7, PHP version must be greater or equal to $version, detected version is ".PHP_VERSION." - Upgrade aborted.");
    }else{
        echo "<div class='upgrade_result success'>- Checking Php Version (".PHP_VERSION.") : OK</div>";
    }
}

/**
 * @param string $type
 * @param string $name
 * @throws Exception
 */
function checkPluginUsed($type, $name){

    if($type === "conf"){
        $p = ConfService::getConfStorageImpl();
        if($p->getName() === $name){
            throw new Exception("You are currently using $type.$name as configuration storage. This was deprecated in Pydio 6 and is now removed in Pydio7. Aborting upgrade");
        }else{
            echo "<div class='upgrade_result success'>- Checking plugin $type ($name) : OK</div>";
        }
    }else if($type === "auth") {
        $p = ConfService::getAuthDriverImpl();
        if ($p->getName() === $name) {
            throw new Exception("You are currently using $type.$name for authentication backend. This was deprecated in Pydio 6 and is now removed in Pydio7. Aborting upgrade");
        } else {
            if ($p->getName() === "multi") {
                $drivers = $p->drivers;
                if (isSet($drivers[$name])) {
                    throw new Exception("You are currently using $type.$name for authentication backend. This was deprecated in Pydio 6 and is nowremoved in Pydio7. Aborting upgrade");
                } else {
                    echo "<div class='upgrade_result success'>- Checking plugin $type (" . implode(", ", array_keys($drivers)) . ") : OK</div>";
                }
            }
            echo "<div class='upgrade_result success'>- Checking plugin $type ($name) : OK</div>";
        }
    }else if($type === "access"){

        // Check if a workspace is currently using this plugin
        echo "<div class='upgrade_result success'>- Should check usage of plugin $type ($name) in active workspaces : TODO</div>";
        

    }else{
        $plugs = AJXP_PluginsService::getInstance()->getActivePluginsForType($type);
        if(isSet($plugs[$name])){
            throw new Exception("You are currently using plugin $type.$name. This is removed in Pydio7. Please disable it before running upgrade. Aborting upgrade");
        }
        echo "<div class='upgrade_result success'>- Checking plugin $type ($name) : OK</div>";
    }

}

/**
 * @param string $themeName
 * @throws Exception
 */
function checkThemeUsed($themeName){

    $p = AJXP_PluginsService::getInstance()->findPlugin("gui", "ajax");
    $options = $p->getConfigs();
    if(isSet($options["GUI_THEME"]) && $options["GUI_THEME"] === $themeName){
        throw new Exception("You are currently using theme ".$options["GUI_THEME"]." which was removed from Pydio 7. If you want to be able to upgrade, you have to switch to Orbit theme. Aborting upgrade.");
    }else{
        echo "<div class='upgrade_result success'>- Checking usage of remove theme ($themeName): OK</div>";
    }

}

function blockAllXHRInPage(){
    print '
        <script type="text/javascript">
            (function(open) {
                parent.XMLHttpRequest.prototype.open = function(method, url, async, user, pass) {
                    console.error("XHR Call to "+url+" blocked by upgrade process!");
                };                                                
            })(parent.XMLHttpRequest.prototype.open);      
        </script>
        <div class="upgrade_result success">Blocking all XHR in page: OK</div>
    ';
}

blockAllXHRInPage();
checkPhpVersion('5.5.9');
if(AJXP_VERSION === '6.4.2'){
    checkPluginUsed("conf", "serial");
    checkPluginUsed("auth", "serial");
    checkPluginUsed("auth", "cmsms");
    checkPluginUsed("access", "remote_fs");
    checkThemeUsed("vision");
    checkThemeUsed("umbra");
}
