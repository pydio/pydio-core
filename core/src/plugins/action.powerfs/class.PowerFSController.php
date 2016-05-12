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

use Pydio\Access\Core\Model\UserSelection;
use Pydio\Access\Driver\StreamProvider\FS\fsAccessWrapper;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Utils\Utils;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Utils\TextEncoder;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Pydio\Core\Http\Message\BgActionTrigger;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class PowerFSController extends Plugin
{

    public function performChecks(){
        if(ShareCenter::currentContextIsLinkDownload()) {
            throw new Exception("Disable during link download");
        }
    }

    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response)
    {

        $selection = new UserSelection();
        $httpVars = $request->getParsedBody();
        $dir = $httpVars["dir"] OR "";
        $dir = Utils::decodeSecureMagic($dir);
        if($dir == "/") $dir = "";
        $selection->initFromHttpVars($httpVars);
        if (!$selection->isEmpty()) {
            //$this->filterUserSelectionToHidden($selection->getFiles());
        }
        $urlBase = "pydio://". ConfService::getRepository()->getId();
        $mess = ConfService::getMessages();
        $bodyStream = new \Pydio\Core\Http\Response\SerializableResponseStream();
        if($request->getAttribute("action") != "postcompress_download"){
            $response = $response->withBody($bodyStream);
        }

        switch ($request->getAttribute("action")) {

            case "monitor_compression" :

                $percentFile = fsAccessWrapper::getRealFSReference($urlBase.$dir."/.zip_operation_".$httpVars["ope_id"]);
                $percent = 0;
                if (is_file($percentFile)) {
                    $percent = intval(file_get_contents($percentFile));
                }
                if ($percent < 100) {
                    $bodyStream->addChunk(new BgActionTrigger(
                        "monitor_compression",
                        $httpVars,
                        $mess["powerfs.1"]." ($percent%)",
                        1));
                } else {
                    @unlink($percentFile);
                    if ($httpVars["on_end"] == "reload") {
                        $bodyStream->addChunk(new BgActionTrigger("reload_node", [], "powerfs.2", 2));
                    } else {
                        $archiveName = Utils::sanitize($httpVars["archive_name"], AJXP_SANITIZE_FILENAME);
                        $archiveName = str_replace("'", "\'", $archiveName);
                        $jsCode = "
                            PydioApi.getClient().downloadSelection(null, $('download_form'), 'postcompress_download', {ope_id:'".$httpVars["ope_id"]."',archive_name:'".$archiveName."'});
                        ";
                        $bodyStream->addChunk(BgActionTrigger::createForJsAction($jsCode, $mess["powerfs.3"]));
                        $bodyStream->addChunk(new BgActionTrigger("reload_node", array(), "powerfs.2", 2));
                    }
                }
                break;

            case "postcompress_download":

                $archive = Utils::getAjxpTmpDir().DIRECTORY_SEPARATOR.$httpVars["ope_id"]."_".Utils::sanitize(Utils::decodeSecureMagic($httpVars["archive_name"]), AJXP_SANITIZE_FILENAME);
                /** @var \Pydio\Access\Driver\StreamProvider\FS\fsAccessDriver $fsDriver */
                $fsDriver = PluginsService::getInstance()->getUniqueActivePluginForType("access");
                $archiveName = $httpVars["archive_name"];
                if (is_file($archive)) {
                    $response = $response->withBody(new \Pydio\Core\Http\Response\AsyncResponseStream(function() use($fsDriver, $archive, $archiveName){
                        register_shutdown_function("unlink", $archive);
                        $fsDriver->readFile($archive, "force-download", $archiveName, false, null, true);
                    }));
                } else {
                    $response = $response->withHeader("Content-type", "text/html");
                    $response->getBody()->write("<script>alert('Cannot find archive! Is ZIP correctly installed?');</script>");
                }
                break;

            case "compress" :
            case "precompress" :

                $archiveName = Utils::sanitize(Utils::decodeSecureMagic($httpVars["archive_name"]), AJXP_SANITIZE_FILENAME);
                if (!ConfService::currentContextIsCommandLine() && ConfService::backgroundActionsSupported()) {
                    $opeId = substr(md5(time()),0,10);
                    $httpVars["ope_id"] = $opeId;
                    Controller::applyActionInBackground(ConfService::getRepository()->getId(), $request->getAttribute("action"), $httpVars);
                    $bgParameters = array(
                        "dir" => TextEncoder::toUTF8($dir),
                        "archive_name"  => TextEncoder::toUTF8($archiveName),
                        "on_end" => (isSet($httpVars["on_end"])?$httpVars["on_end"]:"reload"),
                        "ope_id" => $opeId
                    );
                    $bodyStream->addChunk(new BgActionTrigger(
                        "monitor_compression",
                        $bgParameters,
                        $mess["powerfs.1"]." (0%)")
                    );
                    return;
                }

                $rootDir = fsAccessWrapper::getRealFSReference($urlBase) . $dir;
                $percentFile = $rootDir."/.zip_operation_".$httpVars["ope_id"];
                $compressLocally = ($request->getAttribute("action") == "compress" ? true : false);
                // List all files
                $todo = array();
                $args = array();
                $replaceSearch = array($rootDir, "\\");
                $replaceReplace = array("", "/");
                foreach ($selection->getFiles() as $selectionFile) {
                    $baseFile = $selectionFile;
                    $args[] = escapeshellarg(substr($selectionFile, strlen($dir)+($dir=="/"?0:1)));
                    $selectionFile = fsAccessWrapper::getRealFSReference($urlBase.$selectionFile);
                    $todo[] = ltrim(str_replace($replaceSearch, $replaceReplace, $selectionFile), "/");
                    if (is_dir($selectionFile)) {
                        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($selectionFile), RecursiveIteratorIterator::SELF_FIRST);
                        foreach ($objects as $name => $object) {
                            $todo[] = str_replace($replaceSearch, $replaceReplace, $name);
                        }
                    }
                    if(trim($baseFile, "/") == ""){
                        // ROOT IS SELECTED, FIX IT
                        $args = array(escapeshellarg(basename($rootDir)));
                        $rootDir = dirname($rootDir);
                        break;
                    }
                }
                $cmdSeparator = ((PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows")? "&" : ";");
                if (!$compressLocally) {
                    $archiveName = Utils::getAjxpTmpDir().DIRECTORY_SEPARATOR.$httpVars["ope_id"]."_".$archiveName;
                }
                chdir($rootDir);
                $cmd = $this->getFilteredOption("ZIP_PATH")." -r ".escapeshellarg($archiveName)." ".implode(" ", $args);
                $fsDriver = PluginsService::getInstance()->getUniqueActivePluginForType("access");
                $c = $fsDriver->getConfigs();
                if ((!isSet($c["SHOW_HIDDEN_FILES"]) || $c["SHOW_HIDDEN_FILES"] == false) && stripos(PHP_OS, "win") === false) {
                    $cmd .= " -x .\*";
                }
                $cmd .= " ".$cmdSeparator." echo ZIP_FINISHED";
                $proc = popen($cmd, "r");
                $toks = array();
                $handled = array();
                $finishedEchoed = false;
                while (!feof($proc)) {
                    set_time_limit (20);
                    $results = fgets($proc, 256);
                    if (strlen($results) == 0) {
                    } else {
                        $tok = strtok($results, "\n");
                        while ($tok !== false) {
                            $toks[] = $tok;
                            if ($tok == "ZIP_FINISHED") {
                                $finishedEchoed = true;
                            } else {
                                $test = preg_match('/(\w+): (.*) \(([^\(]+)\) \(([^\(]+)\)/', $tok, $matches);
                                if ($test !== false) {
                                    $handled[] = $matches[2];
                                }
                            }
                            $tok = strtok("\n");
                        }
                        if($finishedEchoed) $percent = 100;
                        else $percent = min( round(count($handled) / count($todo) * 100),  100);
                        file_put_contents($percentFile, $percent);
                    }
                    // avoid a busy wait
                    if($percent < 100) usleep(1);
                }
                pclose($proc);
                file_put_contents($percentFile, 100);

                break;

            default:
                break;
        }

    }
}
