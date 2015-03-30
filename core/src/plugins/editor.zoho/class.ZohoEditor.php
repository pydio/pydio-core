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
 * Description : Zoho plugin. First version by Pawel Wolniewicz http://innodevel.net/ 2011
 * Improved by cdujeu / Https Support now necessary for zoho API.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Editor
 */
class ZohoEditor extends AJXP_Plugin
{
    public function performChecks()
    {
        if (!extension_loaded("openssl")) {
            throw new Exception("Zoho plugin requires PHP 'openssl' extension, as posting the document to the Zoho server requires the Https protocol.");
        }
    }

    public function init($options){

        parent::init($options);
        if(!extension_loaded("openssl")) return;

        $keyFile = $this->getPluginWorkDir(true)."/agent.pem";
        if(file_exists($keyFile)) return;

        $config = array(
            "digest_alg" => "sha1",
            "private_key_bits" => 1024,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );

        // Create the private and public key
        $res = openssl_pkey_new($config);
        if($res === false){
            AJXP_Logger::error(__CLASS__, __FUNCTION__, "Warning, OpenSSL is active but could not correctly generate a key for Zoho Editor. Please make sure the openssl.cnf file is correctly set up.");
            while($message = openssl_error_string()){
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Open SSL Error: ".$message);
            }
        }else{
            openssl_pkey_export_to_file($res, $keyFile);
        }

    }

    public function loadConfigs($configData){

        parent::loadConfigs($configData);
        $keyFile = $this->getPluginWorkDir(true)."/agent.pem";
        if(file_exists($keyFile)){
            $res = openssl_pkey_get_private(file_get_contents($keyFile));
            $details = openssl_pkey_get_details($res);
            $public = $details["key"];
            $this->pluginConf["ZOHO_AGENT_PUBLIC_KEY"] = $public;
        }

    }

    public function signID($id){
        $keyFile = $this->getPluginWorkDir(true)."/agent.pem";
        if(file_exists($keyFile)){
            $keyId = openssl_get_privatekey(file_get_contents($keyFile));
            $message = $id;
            openssl_sign($message, $signature, $keyId);
            openssl_free_key($keyId);
            return urlencode(base64_encode($signature));
        }
        return false;
    }


    public function switchAction($action, $httpVars, $filesVars)
    {
        if(!isSet($this->actions[$action])) return false;

        $repository = ConfService::getRepository();
        if (!$repository->detectStreamWrapper(true)) {
            return false;
        }

        $streamData = $repository->streamData;
        $destStreamURL = $streamData["protocol"]."://".$repository->getId();

        if ($action == "post_to_zohoserver") {

            $sheetExt =  explode(",", "xls,xlsx,ods,sxc,csv,tsv");
            $presExt = explode(",", "ppt,pps,odp,sxi");
            $docExt = explode(",", "doc,docx,rtf,odt,sxw");

            require_once(AJXP_BIN_FOLDER."/http_class/http_class.php");


            $selection = new UserSelection($repository, $httpVars);
            // Backward compat
            if(strpos($httpVars["file"], "base64encoded:") !== 0){
                $file = AJXP_Utils::decodeSecureMagic(base64_decode($httpVars["file"]));
            }else{
                $file = $selection->getUniqueFile();
            }
            $target = base64_decode($httpVars["parent_url"]);
            $tmp = call_user_func(array($streamData["classname"], "getRealFSReference"), $destStreamURL.$file);
            $tmp = SystemTextEncoding::fromUTF8($tmp);

            $node = new AJXP_Node($destStreamURL.$file);
            AJXP_Controller::applyHook("node.read", array($node));
            $this->logInfo('Preview', 'Posting content of '.$file.' to Zoho server');

            $extension = strtolower(pathinfo(urlencode(basename($file)), PATHINFO_EXTENSION));
            $httpClient = new http_class();
            $httpClient->request_method = "POST";

            $secureToken = $httpVars["secure_token"];
            $_SESSION["ZOHO_CURRENT_EDITED"] = $destStreamURL.$file;
            $_SESSION["ZOHO_CURRENT_UUID"]   = md5(rand()."-".microtime());

            if ($this->getFilteredOption("USE_ZOHO_AGENT", $repository->getId())) {
                $saveUrl = $this->getFilteredOption("ZOHO_AGENT_URL", $repository->getId());
            } else {
                $saveUrl = $target."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/save_zoho.php";
            }

            $b64Sig = $this->signID($_SESSION["ZOHO_CURRENT_UUID"]);

            $params = array(
                'id' => $_SESSION["ZOHO_CURRENT_UUID"],
                'apikey' => $this->getFilteredOption("ZOHO_API_KEY", $repository->getId()),
                'output' => 'url',
                'lang' => "en",
                'filename' => urlencode(basename($file)),
                'persistence' => 'false',
                'format' => $extension,
                'mode' => 'normaledit',
                'saveurl'   => $saveUrl."?signature=".$b64Sig
            );

            $service = "exportwriter";
            if (in_array($extension, $sheetExt)) {
                $service = "sheet";
            } else if (in_array($extension, $presExt)) {
                $service = "show";
            } else if (in_array($extension, $docExt)) {
                $service = "exportwriter";
            }
            $arguments = array();
            $httpClient->GetRequestArguments("https://".$service.".zoho.com/remotedoc.im", $arguments);
            $arguments["PostValues"] = $params;
            $arguments["PostFiles"] = array(
                "content"   => array("FileName" => $tmp, "Content-Type" => "automatic/name")
            );
            $err = $httpClient->Open($arguments);
            if (empty($err)) {
                $err = $httpClient->SendRequest($arguments);
                if (empty($err)) {
                    $response = "";
                    while (true) {
                        $body = "";
                        $error = $httpClient->ReadReplyBody($body, 1000);
                        if($error != "" || strlen($body) == 0) break;
                        $response .= $body;
                    }
                    $result = trim($response);
                    $matchlines = explode("\n", $result);
                    $resultValues = array();
                    foreach ($matchlines as $line) {
                        list($key, $val) = explode("=", $line, 2);
                        $resultValues[$key] = $val;
                    }
                    if ($resultValues["RESULT"] == "TRUE" && isSet($resultValues["URL"])) {
                        header("Location: ".$resultValues["URL"]);
                    } else {
                        echo "Zoho API Error ".$resultValues["ERROR_CODE"]." : ".$resultValues["WARNING"];
                        echo "<script>window.parent.setTimeout(function(){parent.hideLightBox();}, 2000);</script>";
                    }
                }
                $httpClient->Close();
            }

        } else if ($action == "retrieve_from_zohoagent") {
            $targetFile = $_SESSION["ZOHO_CURRENT_EDITED"];
            $id = $_SESSION["ZOHO_CURRENT_UUID"];
            $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
            $node = new AJXP_Node($targetFile);
            $node->loadNodeInfo();
            AJXP_Controller::applyHook("node.before_change", array(&$node));

            $b64Sig = $this->signID($id);

            if ($this->getFilteredOption("USE_ZOHO_AGENT",$repository->getId()) ) {
                $url =  $this->getFilteredOption("ZOHO_AGENT_URL",$repository->getId())."?ajxp_action=get_file&name=".$id."&ext=".$ext."&signature=".$b64Sig ;
                $data = AJXP_Utils::getRemoteContent($url);
                if (strlen($data)) {
                    file_put_contents($targetFile, $data);
                    echo "MODIFIED";
                }
            } else {
                if (is_file(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/files/".$id.".".$ext)) {
                    copy(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/files/".$id.".".$ext, $targetFile);
                    unlink(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/files/".$id.".".$ext);
                    echo "MODIFIED";
                }
            }
            $this->logInfo('Edit', 'Retrieved content of '.$node->getUrl());
            AJXP_Controller::applyHook("node.change", array(null, &$node));
        }


    }

}
