<?php
/**
 * @package info.ajaxplorer
 *
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of Pydio.
 * The latest code can be found at http://pyd.io/
 *
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with Pydio.
 *
 * The main conditions are as follow :
 * You must conspicuously and appropriately publish on each copy distributed
 * an appropriate copyright notice and disclaimer of warranty and keep intact
 * all the notices that refer to this License and to the absence of any warranty;
 * and give any other recipients of the Program a copy of the GNU Lesser General
 * Public License along with the Program.
 *
 * If you modify your copy or copies of the library or any portion of it, you may
 * distribute the resulting library provided you do so under the GNU Lesser
 * General Public License. However, programs that link to the library may be
 * licensed under terms of your choice, so long as the library itself can be changed.
 * Any translation of the GNU Lesser General Public License must be accompanied by the
 * GNU Lesser General Public License.
 *
 * If you copy or distribute the program, you must accompany it with the complete
 * corresponding machine-readable source code or with a written offer, valid for at
 * least three years, to furnish the complete corresponding machine-readable source code.
 *
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * Pydio is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * Description : Class for handling flex upload
 */
use Pydio\Access\Core\AJXP_MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;

use Pydio\Core\Controller\Controller;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die( 'Access not allowed');

class PluploadProcessor extends Plugin
{
// 15 minutes execution time
//@set_time_limit(15 * 60);

// Uncomment this one to fake upload time
// usleep(5000);

    public function unifyChunks($action, &$httpVars, &$fileVars, \Pydio\Core\Model\ContextInterface $ctx)
    {

            $filename = Utils::decodeSecureMagic($httpVars["name"]);

            $tmpName = $fileVars["file"]["tmp_name"];
            $chunk = $httpVars["chunk"];
            $chunks = $httpVars["chunks"];

            //error_log("currentChunk:".$chunk."  chunks: ".$chunks);

            $repository = $ctx->getRepository();
            $userSelection = UserSelection::fromContext($ctx, []);
            $dir = Utils::securePath($httpVars["dir"]);
            $destStreamURL = $userSelection->currentBaseUrl().$dir."/";

            $parentNode = new AJXP_Node($userSelection->currentBaseUrl());
            $driver = $parentNode->getDriver();
            $remote = false;
            if (method_exists($driver, "storeFileToCopy")) {
                $remote = true;
                $destCopy = XMLWriter::replaceAjxpXmlKeywords($repository->getContextOption($ctx, "TMP_UPLOAD"));
                // Make tmp folder a bit more unique using secure_token
                $tmpFolder = $destCopy."/".$httpVars["secure_token"];
                if(!is_dir($tmpFolder)){
                    @mkdir($tmpFolder, 0700, true);
                }
                $target = $tmpFolder.'/'.$filename;
                $fileVars["file"]["destination"] = base64_encode($dir);
            }else if(AJXP_MetaStreamWrapper::wrapperIsRemote($destStreamURL)){
                $remote = true;
                $tmpFolder = Utils::getAjxpTmpDir()."/".$httpVars["secure_token"];
                if(!is_dir($tmpFolder)){
                    @mkdir($tmpFolder, 0700, true);
                }
                $target = $tmpFolder.'/'.$filename;
            }else{

                $target = $destStreamURL.$filename;
            }


            //error_log("Directory: ".$dir);

            // Clean the fileName for security reasons
            //$filename = preg_replace('/[^\w\._]+/', '', $filename);
            $contentType =  "";
            // Look for the content type header
            if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
                $contentType = $_SERVER["HTTP_CONTENT_TYPE"];

            if (isset($_SERVER["CONTENT_TYPE"]))
                $contentType = $_SERVER["CONTENT_TYPE"];

            // Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
            if (strpos($contentType, "multipart") !== false) {
                if (isset($tmpName) && is_uploaded_file($tmpName)) {
                    //error_log("tmpName: ".$tmpName);

                    // Open temp file
                    $out = fopen($target, $chunk == 0 ? "wb" : "ab");
                    if ($out) {
                        // Read binary input stream and append it to temp file
                        $in = fopen($tmpName, "rb");

                        if ($in) {
                            while ($buff = fread($in, 4096))
                                fwrite($out, $buff);
                        } else
                            die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
                        fclose($in);
                        fclose($out);
                        @unlink($tmpName);
                    } else
                        die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
                } else
                    die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
            } else {
                // Open temp file
                $out = fopen($target, $chunk == 0 ? "wb" : "ab");
                if ($out) {
                    // Read binary input stream and append it to temp file
                    $in = fopen("php://input", "rb");

                    if ($in) {
                        while ($buff = fread($in, 4096))
                            fwrite($out, $buff);
                    } else
                        die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');

                    fclose($in);
                    fclose($out);
                } else
                    die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
            }
            /* we apply the hook if we are uploading the last chunk */
            if($chunk == $chunks-1){
                if(!$remote){
                    Controller::applyHook("node.change", array(null, new AJXP_Node($destStreamURL.$filename), false));
                }else{
                    if(method_exists($driver, "storeFileToCopy")){
                        $fileVars["file"]["tmp_name"] = $target;
                        $fileVars["file"]["name"] = $filename;
                        $driver->storeFileToCopy($fileVars["file"]);
                        $request = \Zend\Diactoros\ServerRequestFactory::fromGlobals();
                        $request = $request->withAttribute("action", "next_to_remote")->withParsedBody([]);
                        Controller::run($request);

                    }else{
                        // Remote Driver case: copy temp file to destination
                        $node = new AJXP_Node($destStreamURL.$filename);
                        Controller::applyHook("node.before_create", array($node, filesize($target)));
                        Controller::applyHook("node.before_change", array(new AJXP_Node($destStreamURL)));
                        $res = copy($target, $destStreamURL.$filename);
                        if($res) @unlink($target);
                        Controller::applyHook("node.change", array(null, $node, false));
                    }
                }
            }
            // Return JSON-RPC response
            die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
    }
}
