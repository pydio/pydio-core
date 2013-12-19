<?php

$key = <<<EOF
-----BEGIN PUBLIC KEY-----
COPY AND PASTE THE PUBLIC KEY HERE
-----END PUBLIC KEY-----
EOF;

/**
 * Remove all "../../" tentatives, replace double slashes
 * @static
 * @param string $path
 * @return string
 */
function securePath($path)
{
    if ($path == null) $path = "";
    //
    // REMOVE ALL "../" TENTATIVES
    //
    $path = str_replace(chr(0), "", $path);
    $dirs = explode('/', $path);
    for ($i = 0; $i < count($dirs); $i++) {
        if ($dirs[$i] == '.' or $dirs[$i] == '..') {
            $dirs[$i] = '';
        }
    }
    // rebuild safe directory string
    $path = implode('/', $dirs);

    //
    // REPLACE DOUBLE SLASHES
    //
    while (preg_match('/\/\//', $path)) {
        $path = str_replace('//', '/', $path);
    }
    return $path;
}
$vars = array_merge($_GET, $_POST);
$whiteList = explode(",","xls,xlsx,ods,sxc,csv,tsv,ppt,pps,odp,sxi,doc,docx,rtf,odt,sxw");
if (!isSet($vars["ajxp_action"]) && isset($vars["id"]) && isset($vars["format"]) && in_array($vars["format"], $whiteList)) {

    $sig = base64_decode($vars["signature"]);
    $test = openssl_verify($vars["id"], $sig, $key);
    if(!$test){
        die();
    }

    $filezoho = $_FILES['content']["tmp_name"];
    $cleanId = securePath("files/".$vars["id"].".".$vars["format"]);
    move_uploaded_file($filezoho, $cleanId);

} else if ($vars["ajxp_action"] == "get_file" && isSet($vars["name"])) {

    $sig = base64_decode($vars["signature"]);
    $test = openssl_verify($vars["name"], $sig, $key);
    if(!$test){
        die();
    }

    $path = securePath($vars["name"].".".$vars["ext"]);
    if (file_exists("files/".$path)) {
        readfile("files/".$path);
        unlink("files/".$path);
    }

}
