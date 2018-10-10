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

namespace Pydio\Editor\Audio;

use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Exception\FileNotFoundException;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Http\Middleware\SecureTokenMiddleware;

use Pydio\Core\Controller\Controller;
use Pydio\Core\Utils\Vars\InputFilter;


use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class AudioPreviewer
 * Streams MP3 files to a client
 * @package Pydio\Editor\Audio
 */
class AudioPreviewer extends Plugin
{

    /**
     * Action Controller
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws FileNotFoundException
     */
    public function switchAction(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $contextInterface   = $requestInterface->getAttribute("ctx");
        $action             = $requestInterface->getAttribute("action");
        $httpVars           = $requestInterface->getParsedBody();

        if ($action == "audio_proxy") {

            $selection = UserSelection::fromContext($contextInterface, $httpVars);
            $destStreamURL = $selection->currentBaseUrl();

            $node = $selection->getUniqueNode();
            // Backward compat
            // May be a backward compatibility problem, try to base64decode the filepath
            $exist = false;
            try{
                $exist = file_exists($node->getUrl());
            }catch(\Exception $e){
            }
            if(!$exist && strpos($httpVars["file"], "base64encoded:") === false){
                $file = InputFilter::decodeSecureMagic(base64_decode($httpVars["file"]));
                if(!file_exists($destStreamURL.$file)){
                    throw new FileNotFoundException($file);
                }else{
                    $user = $node->getUserId();
                    $node = new AJXP_Node($destStreamURL.$file);
                    $node->setUserId($user);
                }
            }
            if(!is_readable($node->getUrl())){
                throw new FileNotFoundException($node->getPath());
            }

            session_write_close();
            $aSyncReader = new \Pydio\Core\Http\Response\AsyncResponseStream(function () use ($node){

                $fileUrl    = $node->getUrl();
                $localName  = $node->getLabel();
                $cType      = "audio/".array_pop(explode(".", $localName));
                $size       = filesize($fileUrl);

                header("Content-Type: ".$cType."; name=\"".$localName."\"");
                header("Content-Length: ".$size);
                header("Content-Range: bytes 0-". ($size - 1) . "/" . $size);

                $stream = fopen("php://output", "a");
                MetaStreamWrapper::copyFileInStream($fileUrl, $stream);
                fflush($stream);
                fclose($stream);

                Controller::applyHook("node.read", array($node));
                $this->logInfo('Preview', 'Read content of '.$node->getUrl(), array("files" => $node->getUrl()));

            });

            $responseInterface = $responseInterface->withStatus(206, 'Partial Content');
            $responseInterface = $responseInterface->withBody($aSyncReader);

        } else if ($action == "ls") {

            if (!isSet($httpVars["playlist"])) {
                return;
            }

            // Transform the XML into XSPF
            $xmlString = $responseInterface->getBody()->getContents();
            $responseInterface->getBody()->rewind();
            $xmlDoc = new \DOMDocument();
            $xmlDoc->loadXML($xmlString);
            $xElement = $xmlDoc->documentElement;
            $xmlBuff = "";
            header("Content-Type:application/xspf+xml;charset=UTF-8");
            $xmlBuff.='<?xml version="1.0" encoding="UTF-8"?>';
            $xmlBuff.='<playlist version="1" xmlns="http://xspf.org/ns/0/">';
            $xmlBuff.="<trackList>";
            /** @var \DOMElement $child */
            foreach ($xElement->childNodes as $child) {
                $isFile = ($child->getAttribute("is_file") == "true");
                $label = $child->getAttribute("text");
                $ar = explode(".", $label);
                $ext = strtolower(end($ar));
                if(!$isFile || $ext != "mp3") continue;
                $xmlBuff.="<track><location>".AJXP_SERVER_ACCESS."?&get_action=audio_proxy&file=".base64_encode($child->getAttribute("filename"))."</location><title>".$label."</title></track>";
            }
            $xmlBuff.="</trackList>";
            $xmlBuff.= "</playlist>";

            $responseInterface = $responseInterface->withHeader("Content-type", "application/xspf+xml;charset=UTF-8");
            $responseInterface->getBody()->write($xmlBuff);

        }

    }
}
