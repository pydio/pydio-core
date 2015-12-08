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
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Uses Pixlr.com service to edit images online.
 * @package AjaXplorer_Plugins
 * @subpackage Editor
 */
class PixlrEditor extends AJXP_Plugin
{
  public function switchAction($action, $httpVars, $filesVars)
  {
    $repository = ConfService::getRepository();
    if (!$repository->detectStreamWrapper(true)) {
      return false;
    }

        $selection = new UserSelection($repository, $httpVars);
        $selectedNode = $selection->getUniqueNode();
        $selectedNodeUrl = $selectedNode->getUrl();

        if ($action == "post_to_server") {

            // Backward compat
            if(strpos($httpVars["file"], "base64encoded:") !== 0){
                $legacyFilePath = AJXP_Utils::decodeSecureMagic(base64_decode($httpVars["file"]));
                $selectedNode = new AJXP_Node($selection->currentBaseUrl().$legacyFilePath);
                $selectedNodeUrl = $selectedNode->getUrl();
            }

            $target = rtrim(base64_decode($httpVars["parent_url"]), '/') ."/plugins/editor.pixlr";
            $tmp = AJXP_MetaStreamWrapper::getRealFSReference($selectedNodeUrl);
            $tmp = SystemTextEncoding::fromUTF8($tmp);
            $this->logInfo('Preview', 'Sending content of '.$selectedNodeUrl.' to Pixlr server.', array("files" => $selectedNodeUrl));
            AJXP_Controller::applyHook("node.read", array($selectedNode));


            $saveTarget = $target."/fake_save_pixlr.php";
            if ($this->getFilteredOption("CHECK_SECURITY_TOKEN", $repository)) {
                $saveTarget = $target."/fake_save_pixlr_".md5($httpVars["secure_token"]).".php";
            }
            $params = array(
                "referrer"  => "Pydio",
                "method"  => "get",
                "loc"    => ConfService::getLanguage(),
                "target"  => $saveTarget,
                "exit"    => $target."/fake_close_pixlr.php",
                "title"    => urlencode(basename($selectedNodeUrl)),
                "locktarget"=> "false",
                "locktitle" => "true",
                "locktype"  => "source"
            );

            require_once(AJXP_BIN_FOLDER."/http_class/http_class.php");
            $arguments = array();
            $httpClient = new http_class();
            $httpClient->request_method = "POST";
            $httpClient->GetRequestArguments("https://pixlr.com/editor/", $arguments);
            $arguments["PostValues"] = $params;
            $arguments["PostFiles"] = array(
                "image"   => array("FileName" => $tmp, "Content-Type" => "automatic/name"));

            $err = $httpClient->Open($arguments);
            if (empty($err)) {
                $err = $httpClient->SendRequest($arguments);
                if (empty($err)) {
                    $response = "";
                    while (true) {
                        $header = array();
                        $error = $httpClient->ReadReplyHeaders($header, 1000);
                        if ($error != "" || $header != null) break;
                            $response .= $header;
                    }
                }
            }

        header("Location: {$header['location']}"); //$response");

        } else if ($action == "retrieve_pixlr_image") {
            $file = AJXP_Utils::decodeSecureMagic($httpVars["original_file"]);
            $selectedNode = new AJXP_Node($selection->currentBaseUrl() . $file);
            $selectedNode->loadNodeInfo();
            $this->logInfo('Edit', 'Retrieving content of '.$file.' from Pixlr server.', array("files" => $file));
            AJXP_Controller::applyHook("node.before_change", array(&$selectedNode));
            $url = $httpVars["new_url"];
            $urlParts = parse_url($url);
            $query = $urlParts["query"];
            if ($this->getFilteredOption("CHECK_SECURITY_TOKEN", $repository)) {
                $scriptName = basename($urlParts["path"]);
                $token = str_replace(array("fake_save_pixlr_", ".php"), "", $scriptName);
                if ($token != md5($httpVars["secure_token"])) {
                    throw new AJXP_Exception("Invalid Token, this could mean some security problem!");
                }
            }
            $params = array();
            parse_str($query, $params);

            $image = $params['image'];
            $headers = get_headers($image, 1);
            $content_type = explode("/", $headers['Content-Type']);
            if ($content_type[0] != "image") {
                throw new AJXP_Exception("Invalid File Type");
            }
            $content_length = intval($headers["Content-Length"]);
            if($content_length != 0) AJXP_Controller::applyHook("node.before_change", array(&$selectedNode, $content_length));

            $orig = fopen($image, "r");
            $target = fopen($selectedNode->getUrl(), "w");
            if(is_resource($orig) && is_resource($target)){
                while (!feof($orig)) {
                    fwrite($target, fread($orig, 4096));
                }
                fclose($orig);
                fclose($target);
            }
            clearstatcache(true, $selectedNode->getUrl());
            $selectedNode->loadNodeInfo(true);
            AJXP_Controller::applyHook("node.change", array(&$selectedNode, &$selectedNode));
        }

    }

}
