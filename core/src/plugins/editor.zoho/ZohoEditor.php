<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 *
 * Description : Zoho plugin. First version by Pawel Wolniewicz http://innodevel.net/ 2011
 * Improved by cdujeu / Https Support now necessary for zoho API.
 */
namespace Pydio\Editor\Office;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Exception\FileNotFoundException;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Utils\FileHelper;
use Pydio\Core\Utils\Vars\InputFilter;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Log\Core\Logger;

use GuzzleHttp\Client;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Editor
 */
class ZohoEditor extends Plugin
{

    /**
     * @var Client
     */
    private $client;


    public function performChecks() {
        if (!extension_loaded("openssl")) {
            throw new \Exception("Zoho plugin requires PHP 'openssl' extension, as posting the document to the Zoho server requires the Https protocol.");
        }
    }

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = []) {
        parent::init($ctx, $options);

        $this->client = new Client();

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
            Logger::error(__CLASS__, __FUNCTION__, "Warning, OpenSSL is active but could not correctly generate a key for Zoho Editor. Please make sure the openssl.cnf file is correctly set up.");
            while($message = openssl_error_string()){
                Logger::debug(__CLASS__,__FUNCTION__,"Open SSL Error: ".$message);
            }
        }else{
            openssl_pkey_export_to_file($res, $keyFile);
        }
    }

    /**
     * Load the configs passed as parameter. This method will
     * + Parse the config definitions and load the default values
     * + Merge these values with the $configData parameter
     * + Publish their value in the manifest if the global_param is "exposed" to the client.
     * @param array $configData
     * @return void
     */
    public function loadConfigs($configData) {

        parent::loadConfigs($configData);

        $keyFile = $this->getPluginWorkDir(true)."/agent.pem";
        if(file_exists($keyFile)) {
            $res = openssl_pkey_get_private(file_get_contents($keyFile));
            $details = openssl_pkey_get_details($res);
            $public = $details["key"];
            $this->pluginConf["ZOHO_AGENT_PUBLIC_KEY"] = $public;
        }
    }

    /**
     * @param $id
     * @return bool|string
     * @throws \Exception
     */
    public function signID($id) {

        $keyFile = $this->getPluginWorkDir(true)."/agent.pem";
        if(file_exists($keyFile)) {
            $keyId = openssl_get_privatekey(file_get_contents($keyFile));
            $message = $id;
            openssl_sign($message, $signature, $keyId);
            openssl_free_key($keyId);
            return urlencode(base64_encode($signature));
        }

        return false;
    }


    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws \Exception
     */
    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response)
    {
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $action = $request->getAttribute("action");
        $httpVars = $request->getParsedBody();

        $loggedUser = $ctx->getUser();

        if($loggedUser != null){
            $repoWriteable = $loggedUser->canWrite($ctx->getRepositoryId());
        }else{
            $repoWriteable = false;
        }

        $selection = UserSelection::fromContext($ctx, $httpVars);
        $destStreamURL = $selection->currentBaseUrl();

        if ($action == "post_to_zohoserver") {

            $sheetExt =  explode(",", "xls,xlsx,ods,sxc,csv,tsv");
            $presExt = explode(",", "ppt,pptx,pps,odp,sxi");
            $docExt = explode(",", "doc,docx,rtf,odt,sxw");

            // Backward compat
            if(strpos($httpVars["file"], "base64encoded:") !== 0){
                $file = InputFilter::decodeSecureMagic(base64_decode($httpVars["file"]));
            }else{
                $file = $selection->getUniqueFile();
            }
            $nodeUrl = $destStreamURL.$file;
            if(!is_readable($nodeUrl)){
                throw new FileNotFoundException($file);
            }

            $target = base64_decode($httpVars["parent_url"]);

            $node = new AJXP_Node($nodeUrl);
            Controller::applyHook("node.read", array($node));
            $this->logInfo('Preview', 'Posting content of '.$file.' to Zoho server', array("files" => $file));

            $extension = strtolower(pathinfo(urlencode(basename($file)), PATHINFO_EXTENSION));

            $_SESSION["ZOHO_CURRENT_EDITED"] = $nodeUrl;
            $_SESSION["ZOHO_CURRENT_UUID"]   = md5(rand()."-".microtime());

            if ($this->getContextualOption($ctx, "USE_ZOHO_AGENT")) {
                $saveUrl = $this->getContextualOption($ctx, "ZOHO_AGENT_URL");
            } else {
                $saveUrl = $target."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/save_zoho.php";
            }

            $b64Sig = $this->signID($_SESSION["ZOHO_CURRENT_UUID"]);

            $service = "exportwriter";
            if (in_array($extension, $sheetExt)) {
                $service = "sheet";
            } else if (in_array($extension, $presExt)) {
                $service = "show";
            } else if (in_array($extension, $docExt)) {
                $service = "exportwriter";
            }

            $contentHandle = fopen($nodeUrl, 'r');

            $postResponse = $this->client->post("https://".$service.".zoho.com/remotedoc.im", [
                'headers' => [
                    'User-Agent' => $request->getHeader('User-Agent')
                ],
                'body' => [
                    'id'            => $_SESSION["ZOHO_CURRENT_UUID"],
                    'apikey'        => $this->getContextualOption($ctx, "ZOHO_API_KEY"),
                    'output'        => 'url',
                    'lang'          => $this->getContextualOption($ctx, "ZOHO_LANGUAGE"),
                    'filename'      => basename($file),
                    'persistence'   => 'false',
                    'format'        => $extension,
                    'mode'          => $repoWriteable && is_writeable($nodeUrl) ? 'normaledit' : 'view',
                    'saveurl'       => $saveUrl."?signature=".$b64Sig."&XDEBUG_SESSION_START=phpstorm",
                    'content'       => fopen($nodeUrl, 'r')
                ]
            ]);

            $body = $postResponse->getBody();

            $contents = $body->getContents();

            $lines = explode("\n", $contents);
            $result = array();
            foreach ($lines as $line) {
                list($key, $val) = explode("=", $line, 2);
                $result[$key] = $val;
            }

            if (!isset($result["RESULT"]) || $result["RESULT"] !== "TRUE" || !isset($result["URL"])) {
                throw new FileNotFoundException("Could not open file");
            }

            fclose($contentHandle);

            $response = $response
                ->withStatus(302)
                ->withHeader("Location", $result["URL"]);

        } else if ($action == "retrieve_from_zohoagent") {

            $targetFile = $_SESSION["ZOHO_CURRENT_EDITED"];
            $id = $_SESSION["ZOHO_CURRENT_UUID"];
            $ext = pathinfo($targetFile, PATHINFO_EXTENSION);
            $node = new AJXP_Node($targetFile);
            $node->loadNodeInfo();

            $file = fopen('php://memory', 'w+');
            $stream = new \Zend\Diactoros\Stream($file);

            $response = $response
                ->withStatus(200)
                ->withBody($stream);

            if(!$repoWriteable || !is_writeable($node->getUrl())){
                $this->logError("Zoho Editor", "Trying to edit an unauthorized file ".$node->getUrl());

                $stream->write("NOT_ALLOWED");
                return ;
            }

            Controller::applyHook("node.before_change", array(&$node));

            $b64Sig = $this->signID($id);
            $nodeChanged = false;
            if ($this->getContextualOption($ctx, "USE_ZOHO_AGENT") ) {

                $url =  $this->getContextualOption($ctx, "ZOHO_AGENT_URL")."?ajxp_action=get_file&name=".$id."&ext=".$ext."&signature=".$b64Sig ;

                $data = FileHelper::getRemoteContent($url);
                if (strlen($data)) {
                    file_put_contents($targetFile, $data);
                    $nodeChanged = true;
                }
            } else {
                if (is_file(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/files/".$id.".".$ext)) {
                    copy(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/files/".$id.".".$ext, $targetFile);
                    unlink(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/editor.zoho/agent/files/".$id.".".$ext);
                    $nodeChanged = true;
                }
            }
            if($nodeChanged){
                $stream->write("MODIFIED");
                $this->logInfo('Edit', 'Retrieved content of '.$node->getUrl(), array("files" => $node->getUrl()));
                Controller::applyHook("node.change", array(&$node, &$node));
            }
        }
    }

}
