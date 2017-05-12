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
 */
namespace Pydio\Editor\Image;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;

use Pydio\Core\PluginFramework\Plugin;

use GuzzleHttp\Client;
use Pydio\Core\Utils\Vars\UrlUtils;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class PixlrEditor
 * Uses Pixlr.com service to edit images online.
 * @package Pydio\Editor\Image
 */
class PixlrEditor extends Plugin
{

    /**
     * @var Client
     */
    private $client;

    // Plugin initialisation
    /**
     * @param ContextInterface $context
     */
    public function init(\Pydio\Core\Model\ContextInterface $ctx, $options = []) {

        parent::init($ctx, $options);

        $this->client = new Client([
            'base_url' => "https://pixlr.com/editor/"
        ]);
    }

    // Main controller function
    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws PydioException
     * @throws \Exception
     */
    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response) {

        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $action = $request->getAttribute("action");
        $httpVars = $request->getParsedBody();

        $selection = UserSelection::fromContext($ctx, $httpVars);
        $selectedNode = $selection->getUniqueNode();
        $selectedNodeUrl = $selectedNode->getUrl();

        if ($action == "post_to_server") {
            if(!is_writeable($selectedNodeUrl)){
                header("Location:". ApplicationState::detectServerURL(true) ."/plugins/editor.pixlr/fake_error_pixlr.php");
                return;
            }

            // Backward compat
            if(strpos($httpVars["file"], "base64encoded:") !== 0){
                $legacyFilePath = InputFilter::decodeSecureMagic(base64_decode($httpVars["file"]));
                $selectedNode = new AJXP_Node($selection->currentBaseUrl().$legacyFilePath);
                $selectedNodeUrl = $selectedNode->getUrl();
            }

            $target = rtrim(base64_decode($httpVars["parent_url"]), '/') ."/plugins/editor.pixlr";
            $this->logInfo('Preview', 'Sending content of '.$selectedNodeUrl.' to Pixlr server.', array("files" => $selectedNodeUrl));
            Controller::applyHook("node.read", array($selectedNode));

            $saveTarget = $target."/fake_save_pixlr.php";
            if ($this->getContextualOption($ctx, "CHECK_SECURITY_TOKEN")) {
                $saveTarget = $target."/fake_save_pixlr_".md5($httpVars["secure_token"]).".php";
            }

            $type = pathinfo($selectedNodeUrl, PATHINFO_EXTENSION);
            $data = file_get_contents($selectedNodeUrl);
            $rawData = 'data:image/' . $type . ';base64,' . base64_encode($data);

            $params = [
                'allow_redirects' => false,
                'body' => [
                    "referrer"   => "Pydio",
                    "method"     => "get",
                    "type"       => $type,
                    "loc"        => LocaleService::getLanguage(),
                    "target"     => $saveTarget,
                    "exit"       => $target."/fake_close_pixlr.php",
                    "title"      => urlencode(basename($selectedNodeUrl)),
                    "locktarget" => "false",
                    "locktitle"  => "true",
                    "locktype"   => "source",
                    "image"      => $rawData
                ]
            ];

            $postResponse = $this->client->post("/editor/", $params);

            $response = $response
                ->withStatus(302)
                ->withHeader("Location", $postResponse->getHeader("Location"));

        } else if ($action == "retrieve_pixlr_image") {

            $file = InputFilter::decodeSecureMagic($httpVars["original_file"]);
            $selectedNode = new AJXP_Node($selection->currentBaseUrl() . $file);
            $selectedNode->loadNodeInfo();

            if(!is_writeable($selectedNode->getUrl())){
                $this->logError("Pixlr Editor", "Trying to edit an unauthorized file ".$selectedNode->getUrl());
                return;
            }

            $this->logInfo('Edit', 'Retrieving content of '.$file.' from Pixlr server.', array("files" => $file));
            Controller::applyHook("node.before_change", array(&$selectedNode));
            $url = $httpVars["new_url"];
            $urlParts = UrlUtils::mbParseUrl($url);
            $query = $urlParts["query"];
            if ($this->getContextualOption($ctx, "CHECK_SECURITY_TOKEN")) {
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

    /**
     * @return mixed
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param mixed $client
     */
    public function setClient($client)
    {
        $this->client = $client;
    }

}
