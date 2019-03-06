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
namespace Pydio\Editor\Video;

use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Exception\FileNotFoundException;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;

use Pydio\Core\Controller\Controller;
use Pydio\Core\Utils\Vars\PathUtils;

use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Streams video to a client
 * @package Pydio\Editor\Video
 */
class VideoReader extends Plugin
{
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws FileNotFoundException
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function switchAction(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {

        $contextInterface   = $requestInterface->getAttribute("ctx");
        $action             = $requestInterface->getAttribute("action");
        $httpVars           = $requestInterface->getParsedBody();

        $selection = UserSelection::fromContext($contextInterface, $httpVars);
        $node = $selection->getUniqueNode();

        if ($action == "read_video_data") {
            if(!file_exists($node->getUrl()) || !is_readable($node->getUrl())){
                throw new FileNotFoundException($node->getPath());
            }
            $this->logDebug("Reading video");
            session_write_close();
            $filesize = filesize($node->getUrl());
            $filename = $node->getUrl();
            $basename = PathUtils::forwardSlashBasename($filename);

            //$fp = fopen($destStreamURL.$file, "r");
            if (preg_match("/\.ogv$/", $basename)) {
                header("Content-Type: video/ogg; name=\"".$basename."\"");
            } else if (preg_match("/\.mp4$/", $basename)) {
                header("Content-Type: video/mp4; name=\"".$basename."\"");
            } else if (preg_match("/\.m4v$/", $basename)) {
                header("Content-Type: video/x-m4v; name=\"".$basename."\"");
            } else if (preg_match("/\.webm$/", $basename)) {
                header("Content-Type: video/webm; name=\"".$basename."\"");
            }

            if ( isset($_SERVER['HTTP_RANGE']) && $filesize != 0 ) {
                $this->logDebug("Http range", array($_SERVER['HTTP_RANGE']));
                // multiple ranges, which can become pretty complex, so ignore it for now
                $ranges = explode('=', $_SERVER['HTTP_RANGE']);
                $offsets = explode('-', $ranges[1]);
                $offset = floatval($offsets[0]);
                if($offset == 0){
                    $this->logInfo('Preview', 'Streaming content of '.$filename, array("files" => $filename));
                }

                $additionalOffset = 1;
                if (isset($_SERVER['HTTP_USER_AGENT']) && strlen(strstr($_SERVER['HTTP_USER_AGENT'], 'Firefox')) > 0) {
                    $additionalOffset = 0;
                }
                $length = floatval($offsets[1]) - $offset + $additionalOffset;

                if (!$length) $length = $filesize - $offset;
                if ($length + $offset > $filesize || $length < 0) $length = $filesize - $offset;
                header('HTTP/1.1 206 Partial Content');

                header('Content-Range: bytes ' . $offset . '-' . ($offset + $length - 1) . '/' . $filesize);
                header('Accept-Ranges:bytes');
                header("Content-Length: ". $length);
                $file = fopen($filename, 'rb');
                if(!is_resource($file)) throw new FileNotFoundException($file);
                fseek($file, 0);
                $relOffset = $offset;
                while ($relOffset > 2.0E9) {
                    // seek to the requested offset, this is 0 if it's not a partial content request
                    fseek($file, 2000000000, SEEK_CUR);
                    $relOffset -= 2000000000;
                    // This works because we never overcome the PHP 32 bit limit
                }
                fseek($file, $relOffset, SEEK_CUR);

                while(ob_get_level()) ob_end_flush();
                $readSize = 0.0;
                while (!feof($file) && $readSize < $length && connection_status() == 0) {
                    if ($length < 2048){
                        echo fread($file, $length);
                    } else {
                        echo fread($file, 2048);
                    }

                    $readSize += 2048.0;
                    flush();
                }
                fclose($file);
            } else {
                $this->logInfo('Preview', 'Streaming content of '.$filename, array("files" => $filename));
                header("Content-Length: ".$filesize);
                header("Content-Range: bytes 0-" . ($filesize - 1) . "/" . $filesize. ";");
                header('Cache-Control: public');

                $stream = fopen("php://output", "a");
                MetaStreamWrapper::copyFileInStream($node->getUrl(), $stream);
                fflush($stream);
                fclose($stream);
            }
            Controller::applyHook("node.read", array($node));
        }
    }

    /**
     * @param AJXP_Node $ajxpNode
     */
    public function videoAlternateVersions(&$ajxpNode)
    {
        if(!preg_match('/\.mpg$|\.mp4$|\.ogv$|\.webm$/i', $ajxpNode->getLabel())) return;
        if (file_exists(str_replace(".mpg","_PREVIEW.mp4", $ajxpNode->getUrl()))) {
            $ajxpNode->mergeMetadata(array("video_altversion_mp4" => str_replace(".mpg","_PREVIEW.mp4", $ajxpNode->getPath())));
        }
        $rotating = array("mp4","ogv", "webm");
        foreach ($rotating as $ext) {
            if (preg_match('/\.'.$ext.'$/i', $ajxpNode->getLabel())) {
                foreach ($rotating as $other) {
                    if($other == $ext) continue;
                    if (file_exists(str_replace($ext, $other,$ajxpNode->getUrl()))) {
                        $ajxpNode->mergeMetadata(array("video_altversion_".$other => str_replace($ext, $other, $ajxpNode->getPath())));
                    }
                }
            }
        }
    }

}
