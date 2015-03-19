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
 *
 * Re-run the diagnostic tests
 */

/**
 * If you want to run the tests, first comment this line!
 * It is disabled for security purpose
 */
die("You are not allowed to see this page (comment first line of the file to access it!)");
require_once("base.conf.php");

function apiPost($baseData, $url, $parameters, $private){

    $nonce = substr(md5(microtime()), 0, 6);
    $url = $baseData["path"].$url;
    $msg = "$url:$nonce:$private";
    $hash = $nonce.":".hash_hmac("sha256", $msg, $parameters["auth_token"]);
    $client = new HttpClient($baseData["host"]);
    $parameters["auth_hash"] = $hash;
    $res  = $client->post($url, $parameters);
    return $client;

}

if(isSet($_GET["api"])){

    if(isSet($_POST["ws-alias"])){

        $ws = $_POST["ws-alias"];
        $user = $_POST["user"];
        $password = $_POST["password"];

        $host = $_POST["host"];
        $protocol = $_POST["protocol"];
        $path = $_POST["path"];
        if($path == "/") $path = "";
        $urlBaseData = array("host" => $host, "protocol" => $protocol, "path" => $path);

        echo "<style>*{font-family: Arial,sans-serif;}</style>";
        echo "<h3>Getting authentication token with user credentials</h3>";
        flush();
        $tokens = file_get_contents("${protocol}://${user}:${password}@${host}${path}/api/pydio/keystore_generate_auth_token/php_client");
        $data = json_decode($tokens, true);
        if(is_array($data)){
            echo "\n\nData is correctly decoded as JSON.";
            var_dump($data);
        }else{
            echo "\n\nData returned is not JSON! There is something wrong with the API. Exiting.";
            var_dump($data);
            exit();
        }
        // Now use tokens
        $token = $data["t"];
        $private = $data["p"];
        $tokens = array("auth_token" => $token);
        flush();

        echo "<hr><h3>Now checking workspace root listing. We expect XML result</h3>";
        flush();
        $client = apiPost($urlBaseData, "/api/$ws/ls", array("auth_token" => $token), $private);
        $crtListing = $client->getContent();
        if(strpos($crtListing, '<?xml version="1.0"') !== FALSE){
            echo "\n\nData is correctly received as XML.";
            var_dump($crtListing);
        }else{
            echo "\n\nDid not receive XML data! Did you enter a correct workspace? Exiting.";
            var_dump($client->getHeaders());
            var_dump($crtListing);
            exit();
        }
        echo ("<script>window.scrollTo(0, 100000);</script>");
        flush();

        echo "<hr><h3>Checking indexation output</h3>";
        flush();
        $lastSeq = 0;
        $client = apiPost($urlBaseData, "/api/$ws/changes/$lastSeq", array("auth_token" => $token, "flatten" => "true"), $private);
        $headers = $client->getHeaders();
        if($headers["content-type"] != "application/json; charset=UTF-8"){
            echo "\n\n Seems like response header is not JSON. Did you correctly enable the Syncable feature on this workspace?";
            var_dump($headers);
            exit();
        }
        $data = json_decode($client->getContent(), true);
        if(is_array($data)){
            $lastSeq = $data["last_seq"];
            echo "\n\nData is correctly decoded as JSON. Saving Last Seq Id to ".$lastSeq;
            var_dump($data["last_seq"]);
        }else{
            echo "\n\nData not decoded as JSON, normal?";
            var_dump($headers);
            var_dump($data);
            exit();
        }

        $nonce = substr(md5(microtime()), 0, 5);
        $tmpFile = "test-pydio-".$nonce.".txt";
        echo ("<script>window.scrollTo(0, 100000);</script>");
        flush();
        echo "<hr><h3>Creating a simple file inside workspace</h3>";
        flush();

        if(strpos($crtListing, 'filename="/'.$tmpFile.'"') !== FALSE){
            echo "\n\n Skipping this step, file already exists. Is it normal?\n\n";
        }else{
            $client = apiPost($urlBaseData, "/api/$ws/mkfile/" . $tmpFile, array("auth_token" => $token, "content" => "my file content"), $private);
            $data = $client->getContent();
            if(strpos($data, '<?xml version="1.0"') !== FALSE){
                echo "\n\nResponse is XML, see result:";
                var_dump($data);
            }else{
                echo "\n\nDid not receive XML data! Did you enter a correct workspace? Exiting.";
                var_dump($data);
                exit();
            }
        }

        echo ("<script>window.scrollTo(0, 100000);</script>");
        flush();
        echo "<hr><h3>Checking indexation output</h3>";
        flush();

        $client = apiPost($urlBaseData, "/api/$ws/changes/" . $lastSeq, array("auth_token" => $token), $private);
        $headers = $client->getHeaders();
        if($headers["content-type"] != "application/json; charset=UTF-8"){
            echo "\n\n Seems like response header is not JSON";
            var_dump($headers);
            exit();
        }
        $data = json_decode($client->getContent(), true);
        if(is_array($data)){
            echo "\n\nData is correctly decoded as JSON. Looking for the new file in the last sequences";
            $found = false;
            foreach($data["changes"] as $change){
                if($change["source"] == "NULL" && $change["target"] == "/".$tmpFile){
                    echo "<br> Found file creation event in recent changes:";
                    var_dump($change);
                    $found = true;
                    break;
                }
            }
            if(!$found){
                echo "\n\nNo change detected reflecting file creation, this is not normal, exiting.";
                var_dump($data);
                exit();
            }

        }else{
            echo "\n\nData not decoded as JSON, normal?";
            var_dump($data);
            exit();
        }

        echo ("<script>window.scrollTo(0, 100000);</script>");
        flush();
        echo "<hr><h3>Deleting temporary file from the workspace</h3>";
        flush();

        $client = apiPost($urlBaseData, "/api/$ws/delete", array("auth_token" => $token, "file" => "/$tmpFile"), $private);
        $data = $client->getContent();
        if(strpos($data, '<?xml version="1.0"') !== FALSE){
            echo "\n\nResponse is the following XML.";
            var_dump($data);
        }else{
            echo "\n\nDid not receive XML data! Did you enter a correct workspace? Exiting.";
            var_dump($client->getHeaders());
            var_dump($data);
            exit();
        }

        echo ("<script>window.scrollTo(0, 100000);</script>");
        flush();
        echo "<hr><h3>Rechecking indexation output</h3>";
        flush();

        $client = apiPost($urlBaseData, "/api/$ws/changes/" . $lastSeq, array("auth_token" => $token), $private);
        $headers = $client->getHeaders();
        if($headers["content-type"] != "application/json; charset=UTF-8"){
            echo "\n\n Seems like response header is not JSON. Did you correctly enable the Syncable feature on this workspace?";
            var_dump($headers);
            exit();
        }
        $data = json_decode($client->getContent(), true);
        if(is_array($data)){
            echo "\n\nData is correctly decoded as JSON. Looking for the new file in the last sequences";
            $found = false;
            foreach($data["changes"] as $change){
                if($change["source"] == "/".$tmpFile && $change["target"] == "NULL"){
                    echo "<br> Found file move to NULL in sequence!";
                    var_dump($change);
                    $found = true;
                    break;
                }
            }
            if(!$found){
                echo "\n\nNo change detected reflecting file deletion, this is not normal, exiting.";
                var_dump($data);
                exit();
            }

        }else{
            echo "\n\nData not decoded as JSON, normal?";
            var_dump($data);
            exit();
        }

        echo ("<script>window.scrollTo(0, 100000);</script>");
        //echo "</pre>";

    }else{

        $host = $_SERVER["HTTP_HOST"];
        $protocol = $_SERVER["HTTPS"] === "on" ? "https" : "http";
        $path = dirname($_SERVER["REQUEST_URI"]);

        HTMLWriter::charsetHeader();
        require(AJXP_TESTS_FOLDER."/api_test.phtml");

    }

}else{

    $outputArray = array();
    $testedParams = array();
    $passed = true;
    $passed = AJXP_Utils::runTests($outputArray, $testedParams);
    AJXP_Utils::testResultsToTable($outputArray, $testedParams, true);
    AJXP_Utils::testResultsToFile($outputArray, $testedParams);

}