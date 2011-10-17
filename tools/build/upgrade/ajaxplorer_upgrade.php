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

define("CRT_PATH", realpath(dirname(__FILE__)));
$requiredVersion = $_GET["version"];
$requiredChannel = "stable";
$hashMethod = "md5";

function preparePackages($channel, $hashMethod){
    $packages = glob(CRT_PATH."/".$channel."/*.zip");
    $sUrl = detectServerURL().dirname($_SERVER["REQUEST_URI"]);
    $zips = array();
    foreach($packages as $package){
        $name = basename($package);
        if(preg_match('/ajaxplorer-core-upgrade-([^-]*)-([^-]*)\.zip/', $name, $matches)){
            $startV = $matches[1];
            $endV = $matches[2];
            $zips[$startV] = array($sUrl."/".$channel."/".$name, $startV, $endV, hash_file($hashMethod,$package));
        }
    }
    uksort($zips, "version_compare");
    return $zips;
}

function detectServerURL(){
    $protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http' );
    $port = (( $protocol === 'http' && $_SERVER['SERVER_PORT'] == 80 || $protocol === 'https' && $_SERVER['SERVER_PORT'] == 443 ) ? "" : ":".$_SERVER['SERVER_PORT']);
    $name = $_SERVER["SERVER_NAME"];
    return "$protocol://$name$port";
}


$zips = preparePackages($requiredChannel, $hashMethod);
$upgradePath = array();
$hashes = array();
if(isSet($zips[$requiredVersion])){
    $nextVersion = $requiredVersion;
    while(isSet($zips[$nextVersion])){
        $pack = $zips[$nextVersion];
        $upgradePath[] = '"'.$pack[0].'"';
        $hashes[] = '"'.$pack[3].'"';
        $nextVersion = $pack[2];
    }
}


header("Content-type:application/json");
print("{\"packages\":[".implode(",", $upgradePath)."],\"hashes\":[".implode(",", $hashes)."],\"hash_method\":\"md5\"}");