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

function updateSharePhpContent($installPath, $publicFolder){

    $publicFolder = preg_replace("/^".str_replace(array("\\","/"), array("\\\\", "\/"), $installPath)."/", "", $publicFolder);
    $sharePhpPath = $installPath."/".trim($publicFolder, "/")."/"."share.php";
    if(!is_file($sharePhpPath)){
        echo "No share.php file was found in public folder. If it does exist, you may have to manually upgrade its content.\n";
        return;
    }
    echo "Upgrading content of $sharePhpPath file\n";
    $folders = array_map(function($value){
        return "..";
    }, explode("/", trim($publicFolder, "/")));
    $baseConfPath = implode("/", $folders);

    $content = '<?php
        include_once("'.$baseConfPath.'/base.conf.php");
        define(\'AJXP_EXEC\', true);
        require_once AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/vendor/autoload.php";
        $base = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/");
        \Pydio\Share\ShareCenter::publicRoute($base, "/proxy", ["hash" => $_GET["hash"]]);';
    file_put_contents($sharePhpPath, $content);

}

function updateHtAccessContent($htAccessPath){

    if(!is_file($htAccessPath)){
        echo "No htaccess file found. Skipping Htaccess update.\n";
        return;
    }
    echo "Upgrading content of Htaccess file\n";
    $lines = file($htAccessPath, FILE_IGNORE_NEW_LINES);
    $removeFlag = false;
    $newLines = [];
    // Remove unnecessary lines
    foreach($lines as $index => $line){
        if(!$removeFlag){
            $newLines[] = $line;
            if(trim($line) === "RewriteCond %{REQUEST_FILENAME} !-d"){
                $removeFlag = true;
            }
        }else{
            if(trim($line) === 'RewriteRule (.*) index.php [L]'){
                $newLines[] = $line;
                $removeFlag = false;
            }
        }
    }
    $contents = implode("\n", $newLines);
    if(is_writable($htAccessPath)){
        file_put_contents($htAccessPath, $contents);
    }else{
        echo "ERROR: Htaccess file could not be written. Update it manually to the following content: <pre>$contents</pre>";
    }

}

function awsSdkVersion(){

    $s3Options = ConfService::getConfStorageImpl()->loadPluginConfig("access", "s3");
    if($s3Options["SDK_VERSION"] === "v2"){
        $s3Options["SDK_VERSION"] = "v3";
        ConfService::getConfStorageImpl()->savePluginConfig("access.s3", $s3Options);
    }

}

function forceRenameConfFile($prefix){

    // FORCE bootstrap_repositories copy
    if (is_file(AJXP_INSTALL_PATH."/conf/$prefix.php".".new-".date("Ymd"))) {
        rename(AJXP_INSTALL_PATH."/conf/$prefix.php", AJXP_INSTALL_PATH."/conf/$prefix.php.pre-update");
        rename(AJXP_INSTALL_PATH."/conf/$prefix.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/$prefix.php");
    }


}

if(AJXP_VERSION === '6.4.2'){
    awsSdkVersion();
    updateHtAccessContent(AJXP_INSTALL_PATH."/.htaccess");
    updateSharePhpContent(AJXP_INSTALL_PATH, ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"));
    forceRenameConfFile("bootstrap_conf");
    forceRenameConfFile("bootstrap_context");
    forceRenameConfFile("extensions.conf");
}