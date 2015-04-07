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
 * Streams video to a client
 * @package AjaXplorer_Plugins
 * @subpackage Editor
 */
class VideoReader extends AJXP_Plugin
{
    public function switchAction($action, $httpVars, $filesVars)
    {
        if(!isSet($this->actions[$action])) return false;

        $repository = ConfService::getRepository();
        if (!$repository->detectStreamWrapper(true)) {
            return false;
        }

        $streamData = $repository->streamData;
        $destStreamURL = $streamData["protocol"]."://".$repository->getId();

        $selection = new UserSelection($repository, $httpVars);

        if ($action == "read_video_data") {
            $this->logDebug("Reading video");
            $file = $selection->getUniqueFile();
            $node = new AJXP_Node($destStreamURL.$file);
            session_write_close();
            $filesize = filesize($destStreamURL.$file);
             $filename = $destStreamURL.$file;

            //$fp = fopen($destStreamURL.$file, "r");
             if (preg_match("/\.ogv$/", $file)) {
                header("Content-Type: video/ogg; name=\"".basename($file)."\"");
             } else if (preg_match("/\.mp4$/", $file)) {
                 header("Content-Type: video/mp4; name=\"".basename($file)."\"");
             } else if (preg_match("/\.m4v$/", $file)) {
                 header("Content-Type: video/x-m4v; name=\"".basename($file)."\"");
             } else if (preg_match("/\.webm$/", $file)) {
                 header("Content-Type: video/webm; name=\"".basename($file)."\"");
             }

            if ( isset($_SERVER['HTTP_RANGE']) && $filesize != 0 ) {
                $this->logDebug("Http range", array($_SERVER['HTTP_RANGE']));
                // multiple ranges, which can become pretty complex, so ignore it for now
                $ranges = explode('=', $_SERVER['HTTP_RANGE']);
                $offsets = explode('-', $ranges[1]);
                $offset = floatval($offsets[0]);
                if($offset == 0){
                    $this->logInfo('Preview', 'Streaming content of '.$file);
                }

                $length = floatval($offsets[1]) - $offset;
                if (!$length) $length = $filesize - $offset;
                if ($length + $offset > $filesize || $length < 0) $length = $filesize - $offset;
                header('HTTP/1.1 206 Partial Content');

                header('Content-Range: bytes ' . $offset . '-' . ($offset + $length - 1) . '/' . $filesize);
                header('Accept-Ranges:bytes');
                header("Content-Length: ". $length);
                $file = fopen($filename, 'rb');
                if(!is_resource($file)) throw new Exception("Cannot open file $file!");
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
                    echo fread($file, 2048);
                    $readSize += 2048.0;
                    flush();
                }
                fclose($file);
            } else {
                $this->logInfo('Preview', 'Streaming content of '.$file);
                 $fp = fopen($filename, "rb");
                header("Content-Length: ".$filesize);
                header("Content-Range: bytes 0-" . ($filesize - 1) . "/" . $filesize. ";");
                header('Cache-Control: public');

                $class = $streamData["classname"];
                $stream = fopen("php://output", "a");
                call_user_func(array($streamData["classname"], "copyFileInStream"), $destStreamURL.$file, $stream);
                fflush($stream);
                fclose($stream);
            }
            AJXP_Controller::applyHook("node.read", array($node));
        } else if ($action == "get_sess_id") {
            HTMLWriter::charsetHeader("text/plain");
            print(session_id());
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
