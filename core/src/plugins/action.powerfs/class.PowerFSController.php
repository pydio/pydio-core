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

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class PowerFSController extends AJXP_Plugin
{

    public function performChecks(){
        if(ShareCenter::currentContextIsLinkDownload()) {
            throw new Exception("Disable during link download");
        }
    }

    public function switchAction($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return;
        $selection = new UserSelection();
        $dir = $httpVars["dir"] OR "";
        $dir = AJXP_Utils::decodeSecureMagic($dir);
        if($dir == "/") $dir = "";
        $selection->initFromHttpVars($httpVars);
        if (!$selection->isEmpty()) {
            //$this->filterUserSelectionToHidden($selection->getFiles());
        }
        $urlBase = "ajxp.fs://". ConfService::getRepository()->getId();
        $mess = ConfService::getMessages();
        switch ($action) {

            case "monitor_compression" :

                $percentFile = fsAccessWrapper::getRealFSReference($urlBase.$dir."/.zip_operation_".$httpVars["ope_id"]);
                $percent = 0;
                if (is_file($percentFile)) {
                    $percent = intval(file_get_contents($percentFile));
                }
                if ($percent < 100) {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::triggerBgAction(
                        "monitor_compression",
                        $httpVars,
                        $mess["powerfs.1"]." ($percent%)",
                        true,
                        1);
                    AJXP_XMLWriter::close();
                } else {
                    @unlink($percentFile);
                    AJXP_XMLWriter::header();
                    if ($httpVars["on_end"] == "reload") {
                        AJXP_XMLWriter::triggerBgAction("reload_node", array(), "powerfs.2", true, 2);
                    } else {
                        $archiveName =  $httpVars["archive_name"];
                        $jsCode = "
                            var regex = new RegExp('.*?[&\\?]' + 'minisite_session' + '=(.*?)&.*');
                            val = window.ajxpServerAccessPath.replace(regex, \"\$1\");
                            var minisite_session = ( val == window.ajxpServerAccessPath ? false : val );

                            $('download_form').action = window.ajxpServerAccessPath;
                            $('download_form').secure_token.value = window.Connexion.SECURE_TOKEN;
                            $('download_form').select('input').each(function(input){
                                if(input.name!='secure_token') input.remove();
                            });
                            $('download_form').insert(new Element('input', {type:'hidden', name:'ope_id', value:'".$httpVars["ope_id"]."'}));
                            $('download_form').insert(new Element('input', {type:'hidden', name:'archive_name', value:'".$archiveName."'}));
                            $('download_form').insert(new Element('input', {type:'hidden', name:'get_action', value:'postcompress_download'}));
                            if(minisite_session) $('download_form').insert(new Element('input', {type:'hidden', name:'minisite_session', value:minisite_session}));
                            $('download_form').submit();
                            $('download_form').get_action.value = 'download';
                        ";
                        AJXP_XMLWriter::triggerBgJsAction($jsCode, $mess["powerfs.3"], true);
                        AJXP_XMLWriter::triggerBgAction("reload_node", array(), "powerfs.2", true, 2);
                    }
                    AJXP_XMLWriter::close();
                }

                break;

            case "postcompress_download":

                $archive = AJXP_Utils::getAjxpTmpDir().DIRECTORY_SEPARATOR.$httpVars["ope_id"]."_".AJXP_Utils::sanitize(AJXP_Utils::decodeSecureMagic($httpVars["archive_name"]), AJXP_SANITIZE_FILENAME);
                $fsDriver = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("access");
                if (is_file($archive)) {
                    register_shutdown_function("unlink", $archive);
                    $fsDriver->readFile($archive, "force-download", $httpVars["archive_name"], false, null, true);
                } else {
                    echo("<script>alert('Cannot find archive! Is ZIP correctly installed?');</script>");
                }
                break;

            case "compress" :
            case "precompress" :

                $archiveName = AJXP_Utils::sanitize(AJXP_Utils::decodeSecureMagic($httpVars["archive_name"]), AJXP_SANITIZE_FILENAME);
                if (!ConfService::currentContextIsCommandLine() && ConfService::backgroundActionsSupported()) {
                    $opeId = substr(md5(time()),0,10);
                    $httpVars["ope_id"] = $opeId;
                    AJXP_Controller::applyActionInBackground(ConfService::getRepository()->getId(), $action, $httpVars);
                    AJXP_XMLWriter::header();
                    $bgParameters = array(
                        "dir" => SystemTextEncoding::toUTF8($dir),
                        "archive_name"  => SystemTextEncoding::toUTF8($archiveName),
                        "on_end" => (isSet($httpVars["on_end"])?$httpVars["on_end"]:"reload"),
                        "ope_id" => $opeId
                    );
                    AJXP_XMLWriter::triggerBgAction(
                        "monitor_compression",
                        $bgParameters,
                        $mess["powerfs.1"]." (0%)",
                        true);
                    AJXP_XMLWriter::close();
                    session_write_close();
                    exit();
                }

                $rootDir = fsAccessWrapper::getRealFSReference($urlBase) . $dir;
                $percentFile = $rootDir."/.zip_operation_".$httpVars["ope_id"];
                $compressLocally = ($action == "compress" ? true : false);
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
                    $archiveName = AJXP_Utils::getAjxpTmpDir().DIRECTORY_SEPARATOR.$httpVars["ope_id"]."_".$archiveName;
                }
                chdir($rootDir);
                $cmd = $this->getFilteredOption("ZIP_PATH")." -r ".escapeshellarg($archiveName)." ".implode(" ", $args);
                $fsDriver = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("access");
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
