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

use Pydio\Access\Core\AJXP_MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Utils\Utils;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Utils\TextEncoder;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Uses Pixlr.com service to edit images online.
 * @package AjaXplorer_Plugins
 * @subpackage Editor
 */
class PixlrEditor extends Plugin
{
  public function switchAction($action, $httpVars, $filesVars, \Pydio\Core\Model\ContextInterface $contextInterface)
  {
        $selection = UserSelection::fromContext($contextInterface, $httpVars);
        $repository = $contextInterface->getRepository();
        $selectedNode = $selection->getUniqueNode();
        $selectedNodeUrl = $selectedNode->getUrl();

        if ($action == "post_to_server") {

            if(!is_writeable($selectedNodeUrl)){
                header("Location:".Utils::detectServerURL(true)."/plugins/editor.pixlr/fake_error_pixlr.php");
                return false;
            }

            // Backward compat
            if(strpos($httpVars["file"], "base64encoded:") !== 0){
                $legacyFilePath = Utils::decodeSecureMagic(base64_decode($httpVars["file"]));
                $selectedNode = new AJXP_Node($selection->currentBaseUrl().$legacyFilePath);
                $selectedNodeUrl = $selectedNode->getUrl();
            }

            $target = rtrim(base64_decode($httpVars["parent_url"]), '/') ."/plugins/editor.pixlr";
            $tmp = AJXP_MetaStreamWrapper::getRealFSReference($selectedNodeUrl);
            $tmp = TextEncoder::fromUTF8($tmp);
            $this->logInfo('Preview', 'Sending content of '.$selectedNodeUrl.' to Pixlr server.', array("files" => $selectedNodeUrl));
            Controller::applyHook("node.read", array($selectedNode));


            $saveTarget = $target."/fake_save_pixlr.php";
            if ($this->getContextualOption($contextInterface, "CHECK_SECURITY_TOKEN")) {
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

            require_once(AJXP_BIN_FOLDER."/lib/http_class/http_class.php");
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

            if(isSet($header) && isSet($header["location"])){
                header("Location: {$header['location']}"); //$response");
            }else{
                header("Location:".Utils::detectServerURL(true)."/plugins/editor.pixlr/fake_error_pixlr.php");
            }

        } else if ($action == "retrieve_pixlr_image") {

            $file = Utils::decodeSecureMagic($httpVars["original_file"]);
            $selectedNode = new AJXP_Node($selection->currentBaseUrl() . $file);
            $selectedNode->loadNodeInfo();

            if(!is_writeable($selectedNode->getUrl())){
                $this->logError("Pixlr Editor", "Trying to edit an unauthorized file ".$selectedNode->getUrl());
                return false;
            }

            $this->logInfo('Edit', 'Retrieving content of '.$file.' from Pixlr server.', array("files" => $file));
            Controller::applyHook("node.before_change", array(&$selectedNode));
            $url = $httpVars["new_url"];
            $urlParts = parse_url($url);
            $query = $urlParts["query"];
            if ($this->getContextualOption($contextInterface, "CHECK_SECURITY_TOKEN")) {
                $scriptName = basename($urlParts["path"]);
                $token = str_replace(array("fake_save_pixlr_", ".php"), "", $scriptName);
                if ($token != md5($httpVars["secure_token"])) {
                    throw new PydioException("Invalid Token, this could mean some security problem!");
                }
            }
            $params = array();
            parse_str($query, $params);

            $image = $params['image'];
            $headers = get_headers($image, 1);
            $content_type = explode("/", $headers['Content-Type']);
            if ($content_type[0] != "image") {
                throw new PydioException("Invalid File Type");
            }
            $content_length = intval($headers["Content-Length"]);
            if($content_length != 0) Controller::applyHook("node.before_change", array(&$selectedNode, $content_length));

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
            Controller::applyHook("node.change", array(&$selectedNode, &$selectedNode));
        }

    }

}
