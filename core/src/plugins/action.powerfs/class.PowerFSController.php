<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

defined('AJXP_EXEC') or die('Access not allowed');

class PowerFSController extends AJXP_Plugin
{

    function switchAction($action, $httpVars, $fileVars){
        if(!isSet($this->actions[$action])) return;
        $selection = new UserSelection();
        $dir = $httpVars["dir"] OR "";
        $dir = AJXP_Utils::securePath($dir);
        if($dir == "/") $dir = "";
        $selection->initFromHttpVars($httpVars);
        if(!$selection->isEmpty()){
            //$this->filterUserSelectionToHidden($selection->getFiles());
        }
        $urlBase = "ajxp.fs://". ConfService::getRepository()->getId();
        $mess = ConfService::getMessages();
        switch ($action){

            case "monitor_compression" :

                $percentFile = fsAccessWrapper::getRealFSReference($urlBase.$dir."/.zip_operation_".$httpVars["ope_id"]);
                $percent = 0;
                if(is_file($percentFile)){
                    $percent = intval(file_get_contents($percentFile));
                }
                if($percent < 100){
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::triggerBgAction(
                        "monitor_compression",
                        $httpVars,
                        $mess["powerfs.1"]." ($percent%)",
                        true,
                        1);
                    AJXP_XMLWriter::close();
                }else{
                    @unlink($percentFile);
                    AJXP_XMLWriter::header();
                    if($httpVars["on_end"] == "reload"){
                        AJXP_XMLWriter::triggerBgAction("reload_node", array(), "powerfs.2", true, 2);
                    }else{
                        $archiveName =  $dir."/".$httpVars["archive_name"];
                        $jsCode = "
                            $('download_form').action = window.ajxpServerAccessPath;
                            $('download_form').secure_token.value = window.Connexion.SECURE_TOKEN;
                            $('download_form').select('input').each(function(input){
                                if(input.name!='get_action' && input.name!='secure_token') input.remove();
                            });
                            $('download_form').insert(new Element('input', {type:'hidden', name:'file', value:'".$archiveName."'}));
                            $('download_form').submit();
                        ";
                        AJXP_XMLWriter::triggerBgJsAction($jsCode, "powerfs.3", true);
                        AJXP_XMLWriter::triggerBgAction("reload_node", array(), "powerfs.2", true, 2);
                    }
                    AJXP_XMLWriter::close();
                }

                break;

            case "compress" :

                if(!ConfService::currentContextIsCommandLine() && ConfService::backgroundActionsSupported()){
                    $opeId = substr(md5(time()),0,10);
                    $httpVars["ope_id"] = $opeId;
                    AJXP_Controller::applyActionInBackground(ConfService::getRepository()->getId(), "compress", $httpVars);
                    AJXP_XMLWriter::header();
                    $bgParameters = array(
                        "dir" => $dir,
                        "archive_name"  => $httpVars["archive_name"],
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

                // List all files
                $todo = array();
                $args = array();
                foreach($selection->getFiles() as $selectionFile){
                    $args[] = '"'.substr($selectionFile, strlen($dir)+($dir=="/"?0:1)).'"';
                    $selectionFile = fsAccessWrapper::getRealFSReference($urlBase.$selectionFile);
                    $todo[] = ltrim(str_replace(array($rootDir, "\\"), array("", "/"), $selectionFile), "/");
                    if(is_dir($selectionFile)){
                        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($selectionFile), RecursiveIteratorIterator::SELF_FIRST);
                        foreach($objects as $name => $object){
                            $todo[] = str_replace(array($rootDir, "\\"), array("", "/"), $name);
                        }
                    }
                }
                $cmdSeparator = ((PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows")? "&" : ";");
                $archiveName = $httpVars["archive_name"];
                $cmd = "zip -r \"".$archiveName."\" ".implode(" ", $args)." ".$cmdSeparator." echo ZIP_FINISHED";
                chdir($rootDir);
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
                            if($tok == "ZIP_FINISHED") {
                                $finishedEchoed = true;
                            }else{
                                $test = preg_match('/(\w+): (.*) \(([^\(]+)\) \(([^\(]+)\)/', $tok, $matches);
                                if($test !== false){
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
                    if($percent < 100) sleep(1);
                }
                pclose($proc);
                file_put_contents($percentFile, 100);

                break;
            default:
                break;
        }

    }
}
