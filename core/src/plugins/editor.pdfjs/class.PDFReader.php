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

/*
 * Adapted from "editor.video/class.VideoReader.php" to serve PDFs
 * by Kristian Garnét.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Streams PDF to a client
 * @package AjaXplorer_Plugins
 * @subpackage Editor
 */
class PDFReader extends AJXP_Plugin
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

        if ($action == "read_pdf_data") {
            $this->logDebug("Reading PDF");
            $file = $selection->getUniqueFile();
            $node = new AJXP_Node($destStreamURL.$file);
            session_write_close();
            $filesize = filesize($destStreamURL.$file);
            $filename = $destStreamURL.$file;

            header("Content-Type: application/pdf; name=\"".basename($file)."\"");

            $fp = fopen($filename, "rb");
            header("Content-Length: ".$filesize);
            header('Cache-Control: public');

            $class = $streamData["classname"];
            $stream = fopen("php://output", "a");
            call_user_func(array($streamData["classname"], "copyFileInStream"), $destStreamURL.$file, $stream);
            fflush($stream);
            fclose($stream);

            AJXP_Controller::applyHook("node.read", array($node));
        } else if ($action == "get_sess_id") {
            HTMLWriter::charsetHeader("text/plain");
            print(session_id());
        }
    }
}
