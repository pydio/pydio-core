<?php

function updateSharePhpFile(){

    $dlFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
    if(is_file($dlFolder."/share.php")){

        $loader_content = '<'.'?'.'php
                    define("AJXP_EXEC", true);
                    require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/core/classes/class.AJXP_Utils.php");
                    $hash = AJXP_Utils::securePath(AJXP_Utils::sanitize($_GET["hash"], AJXP_SANITIZE_ALPHANUM));
                    if(file_exists($hash.".php")){
                        require_once($hash.".php");
                    }else{
                        require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/publicLet.inc.php");
                        ShareCenter::loadShareByHash($hash);
                    }
                ';
        if (@file_put_contents($dlFolder."/share.php", $loader_content) === FALSE) {
            echo "Could not rewrite the content of the public folder share.php file. Please remove it and create a new shared link to regenerate this file.";
        }


    }
}

updateSharePhpFile();