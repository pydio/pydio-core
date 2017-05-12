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

namespace Pydio\Editor\Mime;

use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\UserSelection;

use Pydio\Core\Controller\Controller;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\UsersService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class FileMimeSender
 * Extract the mimetype of a file and send it to the browser
 * @package Pydio\Editor\Mime
 */
class FileMimeSender extends Plugin
{
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws \Pydio\Core\Exception\AuthRequiredException
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function switchAction(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        if($requestInterface->getAttribute("action") !== "open_file"){
            return;
        }

        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx        = $requestInterface->getAttribute("ctx");
        $httpVars   = $requestInterface->getParsedBody();

        $repository = RepositoryService::getRepositoryById($httpVars["repository_id"]);

        if (UsersService::usersEnabled()) {
            $loggedUser = $ctx->getUser();
            if (!$loggedUser->canSwitchTo($repository->getId())) {
                throw new \Pydio\Core\Exception\AuthRequiredException();
            }
        }

        $selection = UserSelection::fromContext($ctx, $httpVars);

        $selectedNode = $selection->getUniqueNode();
        $selectedNodeUrl = $selectedNode->getUrl();

        if (!file_exists($selectedNodeUrl) || !is_readable($selectedNodeUrl)) {
            throw new \Pydio\Core\Exception\PydioException("File does not exist");
        }

        $filesize = filesize($selectedNodeUrl);
        $fp = fopen($selectedNodeUrl, "rb");
        $fileMime = "application/octet-stream";

        //Get mimetype with fileinfo PECL extension
        if (class_exists("finfo")) {
            $finfo = new \finfo(FILEINFO_MIME);
            $fileMime = $finfo->buffer(fread($fp, 2000));
        }
        //Get mimetype with (deprecated) mime_content_type
        if (strpos($fileMime, "application/octet-stream")===0 && function_exists("mime_content_type")) {
            $fileMime = @mime_content_type($fp);
        }
        //Guess mimetype based on file extension
        if (strpos($fileMime, "application/octet-stream")===0 ) {
            $fileExt = substr(strrchr(basename($selectedNodeUrl), '.'), 1);
            if(empty($fileExt))
                $fileMime = "application/octet-stream";
            else {
                $regex = "/^([\w\+\-\.\/]+)\s+(\w+\s)*($fileExt\s)/i";
                $lines = file( $this->getBaseDir()."/res/other/mime.types");
                foreach ($lines as $line) {
                    if(substr($line, 0, 1) == '#')
                        continue; // skip comments
                    $line = rtrim($line) . " ";
                    if(!preg_match($regex, $line, $matches))
                        continue; // no match to the extension
                    $fileMime = $matches[1];
                }
            }
        }
        fclose($fp);
        // If still no mimetype, give up and serve application/octet-stream
        if(empty($fileMime))
            $fileMime = "application/octet-stream";

        if(strpos($fileMime, "image/svg+xml;") === 0){
            // Do not open SVG directly in browser.
            $fileMime = "application/octet-stream";
        }

        if(strpos($fileMime, "application/vnd") === 0){
            // Do not open VND (vendor specific) directly in browser.
            $fileMime = "application/octet-stream";
        }

        //Send headers
        $responseInterface = HTMLWriter::responseWithInlineHeaders($responseInterface, basename($selectedNodeUrl), $filesize, $fileMime);
        $aSyncReader = new \Pydio\Core\Http\Response\AsyncResponseStream(function () use ($selectedNode) {
            $stream = fopen("php://output", "a");
            MetaStreamWrapper::copyFileInStream($selectedNode->getUrl(), $stream);
            fflush($stream);
            fclose($stream);

            Controller::applyHook("node.read", array($selectedNode));
            $this->logInfo('Download', 'Read content of '.$selectedNode->getUrl(), array("files" => $selectedNode->getUrl()));

        });

        $responseInterface = $responseInterface->withBody($aSyncReader);

    }
}
