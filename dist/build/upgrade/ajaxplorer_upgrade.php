<?php
/**
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
$requiredChannel = (isSet($_GET["channel"])?$_GET["channel"]:"stable");
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

$var_utmac='UA-538750-3'; //enter the new urchin code
$var_utmhn='ajaxplorer.info'; //enter your domain
$var_utmn=rand(1000000000,9999999999);//random request number
$var_cookie=rand(10000000,99999999);//random cookie number
$var_random=rand(1000000000,2147483647); //number under 2147483647
$var_today=time(); //today
$var_referer='';//$_SERVER['HTTP_REFERRER']; //referer url
$var_uservar='-'; //enter your own user defined variable
$var_utmp='/rss/'.$_SERVER['REMOTE_ADDR']; //this example adds a fake page request to the (fake) rss directory (the viewer IP to check for absolute unique RSS readers)
$urchinUrl='http://www.google-analytics.com/__utm.gif?utmwv=1&utmn='.$var_utmn.'&utmsr=-&utmsc=-&utmul=-&utmje=0&utmfl=-&utmdt=-&utmhn='.$var_utmhn.'&utmr='.$var_referer.'&utmp='.$var_utmp.'&utmac='.$var_utmac.'&utmcc=__utma%3D'.$var_cookie.'.'.$var_random.'.'.$var_today.'.'.$var_today.'.'.$var_today.'.2%3B%2B__utmb%3D'.$var_cookie.'%3B%2B__utmc%3D'.$var_cookie.'%3B%2B__utmz%3D'.$var_cookie.'.'.$var_today.'.2.2.utmccn%3D(direct)%7Cutmcsr%3D(direct)%7Cutmcmd%3D(none)%3B%2B__utmv%3D'.$var_cookie.'.'.$var_uservar.'%3B';
$handle = fopen ($urchinUrl, "r");
$test = fgets($handle);
fclose($handle);
  
  
header("Content-type:application/json");
print("{\"packages\":[".implode(",", $upgradePath)."],\"hashes\":[".implode(",", $hashes)."],\"hash_method\":\"md5\"}");