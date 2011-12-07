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
 *
 * Description : Zoho plugin. First version by Pawel Wolniewicz http://innodevel.net/ 2011
 * Improved by cdujeu / Https Support now necessary for zoho API.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class ZohoEditor extends AJXP_Plugin {

    public function performChecks(){
        if(!extension_loaded("openssl")){
            throw new Exception("Zoho plugin requires PHP 'openssl' extension, as posting the document to the Zoho server requires the Https protocol.");
        }
    }


	public function switchAction($action, $httpVars, $filesVars){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(true)){
			return false;
		}
		
		$streamData = $repository->streamData;
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId();
		    	
		if($action == "post_to_zohoserver"){

            $sheetExt =  explode(",", "xls,xlsx,ods,sxc,csv,tsv");
            $presExt = explode(",", "ppt,pps,odp,sxi");
            $docExt = explode(",", "doc,docx,rtf,odt,sxw");

            require_once(AJXP_BIN_FOLDER."/http_class/http_class.php");

			$file = base64_decode($httpVars["file"]);
			$file = SystemTextEncoding::magicDequote(AJXP_Utils::securePath($file));
			$target = base64_decode($httpVars["parent_url"]);
			$tmp = call_user_func(array($streamData["classname"], "getRealFSReference"), $destStreamURL.$file);			
			$tmp = SystemTextEncoding::fromUTF8($tmp);

			$extension = strtolower(pathinfo(urlencode(basename($file)), PATHINFO_EXTENSION));
			$httpClient = new http_class();
            $httpClient->request_method = "POST";

            $secureToken = $httpVars["secure_token"];
            $_SESSION["ZOHO_CURRENT_EDITED"] = $destStreamURL.$file;
            $_SESSION["ZOHO_CURRENT_UUID"]   = md5(rand()."-".microtime());

            if($this->pluginConf["USE_ZOHO_AGENT"]){
                $saveUrl = $this->pluginConf["ZOHO_AGENT_URL"];
            }else{
                $saveUrl = $target."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/save_zoho.php";
            }


			$params = array(
                'id' => $_SESSION["ZOHO_CURRENT_UUID"],
                'apikey' => $this->pluginConf["ZOHO_API_KEY"],
                'output' => 'url',
                'lang' => "en",
                'skey'=> $this->pluginConf["ZOHO_SECRET_KEY"],
                'filename' => urlencode(basename($file)),
                'persistence' => 'false',
                'format' => $extension,
                'mode' => 'normaledit',
                'saveurl'   => $saveUrl
			);

            $service = "exportwriter";
            if(in_array($extension, $sheetExt)){
                $service = "sheet";
            }else if(in_array($extension, $presExt)){
                $service = "show";
            }else if(in_array($extension, $docExt)){
                $service = "exportwriter";
            }
            $httpClient->GetRequestArguments("https://".$service.".zoho.com/remotedoc.im", $arguments);
            $arguments["PostValues"] = $params;
            $arguments["PostFiles"] = array(
                "content"   => array("FileName" => $tmp, "Content-Type" => "automatic/name")
            );
            $err = $httpClient->Open($arguments);
            if(empty($err)){
                $err = $httpClient->SendRequest($arguments);
                if(empty($err)){
                    $response = "";
                    while(true){
                        $error = $httpClient->ReadReplyBody($body, 1000);
                        if($error != "" || strlen($body) == 0) break;
                        $response .= $body;
                    }
                    $result = trim($response);
                    $matchlines = explode("\n", $result);
                    $resultValues = array();
                    foreach($matchlines as $line){
                        list($key, $val) = explode("=", $line, 2);
                        $resultValues[$key] = $val;
                    }
                    if($resultValues["RESULT"] == "TRUE" && isSet($resultValues["URL"])){
                        header("Location: ".$resultValues["URL"]);
                    }else{
                        echo "Zoho API Error ".$resultValues["ERROR_CODE"]." : ".$resultValues["WARNING"];
                        echo "<script>window.parent.setTimeout(function(){parent.hideLightBox();}, 2000);</script>";
                    }
                }
                $httpClient->Close();
            }

		}else if($action == "retrieve_from_zohoagent"){
            $targetFile = $_SESSION["ZOHO_CURRENT_EDITED"];
            $id = $_SESSION["ZOHO_CURRENT_UUID"].".".pathinfo($targetFile, PATHINFO_EXTENSION);

            if($this->pluginConf["USE_ZOHO_AGENT"]){
                $data = file_get_contents($this->pluginConf["ZOHO_AGENT_URL"]."?ajxp_action=get_file&name=".$id);
                if(strlen($data)){
                    file_put_contents($targetFile, $data);
                    echo "MODIFIED";
                }
            }else{
                if(is_file(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/files/".$id)){
                    copy(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/files/".$id, $targetFile);
                    unlink(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/files/".$id);
                    echo "MODIFIED";
                }
            }

        }


	}
	
}
?>
