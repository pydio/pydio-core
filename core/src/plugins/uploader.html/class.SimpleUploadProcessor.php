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

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Utils\TextEncoder;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Processor for standard POST upload
 * @package AjaXplorer_Plugins
 * @subpackage Uploader
 */
class SimpleUploadProcessor extends Plugin
{
    public function getDropBg($action, $httpVars, $fileVars)
    {
        $lang = LocaleService::getLanguage();
        $img = AJXP_INSTALL_PATH."/plugins/uploader.html/i18n/$lang-dropzone.png";
        if(!is_file($img)) $img = AJXP_INSTALL_PATH."/plugins/uploader.html/i18n/en-dropzone.png";
        header("Content-Type: image/png; name=\"dropzone.png\"");
        header("Content-Length: ".filesize($img));
        header('Cache-Control: public');
        readfile($img);
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function preProcess(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response)
    {
        $httpVars = $request->getParsedBody();
        $serverData = $request->getServerParams();

        if (!isSet($httpVars["input_stream"]) || isSet($httpVars["force_post"])) {
            // Nothing to do
            return;
        }

        // Mandatory headers
        if (!isset($serverData['CONTENT_LENGTH'], $serverData['HTTP_X_FILE_NAME'])) {
            throw new \Pydio\Core\Exception\PydioException("Warning, missing headers!");
        }

        // Setting the stream data
        if (isset($serverData['HTTP_X_FILE_TMP_LOCATION'])) {
            // The file has already been transferred

            // Checking headers
            if (!isset($serverData['HTTP_X_FILE_SIZE'])) {
                exit('Warning, wrong headers');
            }

            // Setting the stream to point to the file location
            $streamOrFile = $serverData['HTTP_X_FILE_TMP_LOCATION'];
            $errorStatus = UPLOAD_ERR_OK;
        } else {
            // The file is the post data stream

            // Checking headers
            if (isSet($serverData['HTTP_X_FILE_SIZE'])) {
                if ($serverData['CONTENT_LENGTH'] != $serverData['HTTP_X_FILE_SIZE']) {
                    exit('Warning, wrong headers');
                }
            }

            // Setting the stream to point to the post data
            $streamOrFile = array_shift($request->getUploadedFiles())->getStream();
            $errorStatus = $streamOrFile->getError();
        }

        $fileNameH = $serverData['HTTP_X_FILE_NAME'];
        $fileSizeH = (int)$serverData['HTTP_X_FILE_SIZE'];

        // Clean up dir name
        if (dirname($httpVars["dir"]) == "/" && basename($httpVars["dir"]) == $fileNameH) {
            $httpVars["dir"] = "/";
        }

        $this->logDebug("SimpleUpload::preProcess", $httpVars);

        // Update UploadedFile object built on input stream with file name and size
        $uploadedFile = new \Zend\Diactoros\UploadedFile($streamOrFile, $fileSizeH, $errorStatus, TextEncoder::fromUTF8(basename($fileNameH)));
        
        $request = $request->withUploadedFiles(["userfile_0" => $uploadedFile]);
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    public function postProcess(\Psr\Http\Message\ServerRequestInterface &$request, \Psr\Http\Message\ResponseInterface &$response)
    {
        $httpVars = $request->getParsedBody();
        if ($request->getAttribute("api")!="v2" && !isSet($httpVars["simple_uploader"]) && !isSet($httpVars["xhr_uploader"]) && !isSet($httpVars["force_post"])) {
            return;
        }
        $this->logDebug("SimpleUploadProc is active");
        $result = $request->getAttribute("upload_process_result");

        if (isSet($httpVars["simple_uploader"])) {
            $response = $response->withHeader("Content-type", "text/html; charset=UTF-8");
            print("<html><script language=\"javascript\">\n");
            if (isSet($result["ERROR"])) {
                $message = $result["ERROR"]["MESSAGE"]." (".$result["ERROR"]["CODE"].")";
                $response->getBody()->write("\n if(parent.pydio.getController().multi_selector) parent.pydio.getController().multi_selector.submitNext('".str_replace("'", "\'", $message)."');");
            } else {
                print("\n if(parent.pydio.getController().multi_selector) parent.pydio.getController().multi_selector.submitNext();");
                if (isSet($result["CREATED_NODE"]) || isSet($result["UPDATED_NODE"])) {
                    $s = '<tree>';
                    $s .= XMLWriter::writeNodesDiff(array((isSet($result["UPDATED_NODE"])?"UPDATE":"ADD")=> array($result[(isSet($result["UPDATED_NODE"])?"UPDATED":"CREATED")."_NODE"])), false);
                    $s.= '</tree>';
                    $response->getBody()->write("\n var resultString = '".str_replace("'", "\'", $s)."'; var resultXML = parent.parseXml(resultString);");
                    $response->getBody()->write("\n parent.PydioApi.getClient().parseXmlMessage(resultXML);");
                }
            }
            $response->getBody()->write("</script></html>");
        } else {
            if (isSet($result["ERROR"])) {
                $message = $result["ERROR"]["MESSAGE"]." (".$result["ERROR"]["CODE"].")";
                $response = $response->withHeader("Content-type", "text/plain; charset=UTF-8");
                $response->getBody()->write($message);
            } else {

                $nodesDiff = "";
                if (isSet($result["CREATED_NODE"]) || isSet($result["UPDATED_NODE"])) {
                    $nodesDiff = XMLWriter::writeNodesDiff(array((isSet($result["UPDATED_NODE"])?"UPDATE":"ADD") => array($result[(isSet($result["UPDATED_NODE"])?"UPDATED":"CREATED")."_NODE"])), false);
                }
                $response = $response->withHeader("Content-type", "text/xml; charset=UTF-8");
                $response->getBody()->write(XMLWriter::wrapDocument($nodesDiff));

                /* for further implementation */
                if (!isSet($result["PREVENT_NOTIF"])) {
                    if (isset($result["CREATED_NODE"])) {
                        Controller::applyHook("node.change", array(null, $result["CREATED_NODE"], false));
                    } else if (isSet($result["UPDATED_NODE"])) {
                        Controller::applyHook("node.change", array($result["UPDATED_NODE"], $result["UPDATED_NODE"], false));
                    }
                }
            }
        }
    }

    public function unifyChunks($action, $httpVars, $fileVars, \Pydio\Core\Model\ContextInterface $contextInterface)
    {
        $selection = UserSelection::fromContext($contextInterface, []);
        $dir = Utils::decodeSecureMagic($httpVars["dir"]);
        $destStreamURL = $selection->currentBaseUrl().$dir."/";
        $filename = Utils::decodeSecureMagic($httpVars["file_name"]);

        $chunks = array();
        $index = 0;
        while (isSet($httpVars["chunk_".$index])) {
            $chunks[] = Utils::decodeSecureMagic($httpVars["chunk_".$index]);
            $index++;
        }

        $newDest = fopen($destStreamURL.$filename, "w");
        for ($i = 0; $i < count($chunks) ; $i++) {
            $part = fopen($destStreamURL.$chunks[$i], "r");
            if(is_resource($part)){
                while (!feof($part)) {
                    fwrite($newDest, fread($part, 4096));
                }
                fclose($part);
            }
            unlink($destStreamURL.$chunks[$i]);
        }
        fclose($newDest);
        Controller::applyHook("node.change", array(null, new AJXP_Node($newDest), false));
    }
}
