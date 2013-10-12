<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Charles du Jeu
 * Date: 14/10/11
 * Time: 12:20
 * To change this template use File | Settings | File Templates.
 */
define("CRT_PATH", realpath(dirname(__FILE__)));
$requiredVersion = $_GET["version"];
$requiredChannel = (isSet($_GET["channel"])?$_GET["channel"]:"stable");
$basePackage = isSet($_GET["package"]) ? $_GET["package"] : "ajaxplorer-core";
$hashMethod = "md5";

function preparePackages($channel, $hashMethod, $basePackage)
{
    $packages = glob(CRT_PATH."/".$channel."/*.zip");
    $sUrl = detectServerURL().dirname($_SERVER["REQUEST_URI"]);
    $zips = array();
    foreach ($packages as $package) {
        $name = basename($package);
        if (preg_match('/'.$basePackage.'-upgrade-([^-]*)-([^-]*)\.zip/', $name, $matches)) {
            $startV = $matches[1];
            $endV = $matches[2];
            $zips[$startV] = array($sUrl."/".$channel."/".$name, $startV, $endV, hash_file($hashMethod,$package));
        }
    }
    uksort($zips, "version_compare");
    return $zips;
}

function detectServerURL()
{
    $protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http' );
    $port = (( $protocol === 'http' && $_SERVER['SERVER_PORT'] == 80 || $protocol === 'https' && $_SERVER['SERVER_PORT'] == 443 ) ? "" : ":".$_SERVER['SERVER_PORT']);
    $name = $_SERVER["SERVER_NAME"];
    return "$protocol://$name$port";
}


$zips = preparePackages($requiredChannel, $hashMethod, $basePackage);
$upgradePath = array();
$hashes = array();
$note = "";
if (isSet($zips[$requiredVersion])) {
    $nextVersion = $requiredVersion;
    while (isSet($zips[$nextVersion])) {
        $pack = $zips[$nextVersion];
        $upgradePath[] = '"'.$pack[0].'"';
        $hashes[] = '"'.$pack[3].'"';
        $nextVersion = $pack[2];
    }
    if (isSet($pack)) {
        $zip = $pack[0];
        $sUrl = detectServerURL().dirname($_SERVER["REQUEST_URI"]);
        $notefile = CRT_PATH."/$requiredChannel/".str_replace(".zip", ".html", basename($zip));
        if (is_file($notefile)) {
            $note = str_replace(".zip", ".html", $zip);
        }
    }
}

$var_utmac='UA-XXXXX-Y'; //enter the new urchin code
$var_utmhn='ajaxplorer.info'; //enter your domain
$var_utmn=rand(1000000000,9999999999);//random request number
$var_cookie=rand(10000000,99999999);//random cookie number
$var_random=rand(1000000000,2147483647); //number under 2147483647
$var_today=time(); //today
$var_referer='';//$_SERVER['HTTP_REFERRER']; //referer url
$var_uservar='-'; //enter your own user defined variable
$var_utmp='/update/'.$requiredVersion.'/'.$_SERVER['REMOTE_ADDR']; //this example adds a fake page request to the (fake) rss directory (the viewer IP to check for absolute unique RSS readers)
$urchinUrl='http://www.google-analytics.com/__utm.gif?utmwv=1&utmn='.$var_utmn.'&utmsr=-&utmsc=-&utmul=-&utmje=0&utmfl=-&utmdt=-&utmhn='.$var_utmhn.'&utmr='.$var_referer.'&utmp='.$var_utmp.'&utmac='.$var_utmac.'&utmcc=__utma%3D'.$var_cookie.'.'.$var_random.'.'.$var_today.'.'.$var_today.'.'.$var_today.'.2%3B%2B__utmb%3D'.$var_cookie.'%3B%2B__utmc%3D'.$var_cookie.'%3B%2B__utmz%3D'.$var_cookie.'.'.$var_today.'.2.2.utmccn%3D(direct)%7Cutmcsr%3D(direct)%7Cutmcmd%3D(none)%3B%2B__utmv%3D'.$var_cookie.'.'.$var_uservar.'%3B';
$handle = fopen ($urchinUrl, "r");
$test = fgets($handle);
fclose($handle);


header("Content-type:application/json");
if ($requiredChannel == "test") {
    $sUrl = detectServerURL().dirname($_SERVER["REQUEST_URI"]);
    $upgradePath = array('"'.$sUrl.'/test/'.$basePackage.'-upgrade-0.0.0-0.0.0.zip"');
    $hashes = array('"'.md5_file(CRT_PATH."/test/'.$basePackage.'-upgrade-0.0.0-0.0.0.zip").'"');
    $note = $sUrl."/test/'.$basePackage.'-upgrade-0.0.0-0.0.0.html";
}
print("{\"packages\":[".implode(",", $upgradePath)."],\"hashes\":[".implode(",", $hashes)."],\"hash_method\":\"md5\", \"latest_note\":\"".$note."\"}");
