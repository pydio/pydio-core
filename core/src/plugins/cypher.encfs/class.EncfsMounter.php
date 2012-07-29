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

class EncfsMounter extends AJXP_Plugin
{

    protected function getWorkingPath(){
        $repo = ConfService::getRepository();
        $path = $repo->getOption("PATH");
        return $path;
    }

    public function switchAction($actionName, $httpVars, $fileVars){

        //var_dump($httpVars);

        switch($actionName){
            case "encfs.cypher_folder" :
                $dir = $this->getWorkingPath().AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
                $pass = $httpVars["pass"];
                $raw  = dirname($dir).DIRECTORY_SEPARATOR."ENCFS_RAW_".basename($dir);
                if(!strstr($dir, "ENCFS_CLEAR_") && !is_dir($raw)){
                    // NEW FOLDER SCENARIO
                    $clear  = dirname($dir).DIRECTORY_SEPARATOR."ENCFS_CLEAR_".basename($dir);

                    mkdir($raw);
                    $result = self::initEncFolder($raw, "/home/charles/encfs-raw2/.encfs6.xml", "123456", $pass);
                    if($result){
                        // Mount folder
                        mkdir($clear);
                        self::mountFolder($raw, $clear, $pass);
                        $content = scandir($dir);
                        foreach($content as $fileOrFolder){
                            if($fileOrFolder == "." || $fileOrFolder == "..") continue;
                            rename($dir . DIRECTORY_SEPARATOR . $fileOrFolder, $clear . DIRECTORY_SEPARATOR . $fileOrFolder );
                        }
                        self::umountFolder($clear);
                    }
                }else if(substr(basename($dir), 0, strlen("ENCFS_CLEAR_")) == "ENCFS_CLEAR_"){
                    // SIMPLY UNMOUNT
                    self::umountFolder($dir);
                }
            break;
            case "encfs.uncypher_folder":
                $dir = $this->getWorkingPath().AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
                $raw = str_replace("ENCFS_CLEAR_", "ENCFS_RAW_", $dir);
                $pass = $httpVars["pass"];
                if(is_dir($raw)){
                    self::mountFolder($raw, $dir, $pass);
                }
            break;
        }
        AJXP_XMLWriter::header();
        AJXP_XMLWriter::reloadDataNode();
        AJXP_XMLWriter::close();

    }


    /**
     * @param AJXP_Node $ajxpNode
     * @param AJXP_Node|bool $parentNode
     * @param bool $details
     */
    public function filterENCFS(&$ajxpNode, $parentNode = false, $details = false){
        if(substr($ajxpNode->getLabel(), 0, strlen("ENCFS_RAW_")) == "ENCFS_RAW_"){
            $ajxpNode->hidden = true;
        }else if(substr($ajxpNode->getLabel(), 0, strlen("ENCFS_CLEAR_")) == "ENCFS_CLEAR_"){
            $ajxpNode->ENCFS_clear_folder = true;
            $ajxpNode->overlay_icon = "cypher.encfs/overlay_ICON_SIZE.png";
            if(is_file($ajxpNode->getUrl()."/.ajxp_mount")){
                $ajxpNode->setLabel(substr($ajxpNode->getLabel(), strlen("ENCFS_CLEAR_")));
                $ajxpNode->ENCFS_clear_folder_mounted = true;
            }else{
                $ajxpNode->setLabel(substr($ajxpNode->getLabel(), strlen("ENCFS_CLEAR_")) . " (encrypted)");
            }
        }
    }

    public static function initEncFolder($raw, $originalXML, $originalSecret,  $secret){

        copy($originalXML, $raw."/".basename($originalXML));
        $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin
                1 => array("pipe", "w"),  // stdout
                2 => array("pipe", "w")   // stderr ?? instead of a file
                );
        $command = 'sudo encfsctl autopasswd '.escapeshellarg($raw);
        $process = proc_open($command, $descriptorspec, $pipes);
        $text = ""; $error = "";
        if (is_resource($process)) {
            fwrite($pipes[0], $originalSecret);
            fwrite($pipes[0], "\n");
            fwrite($pipes[0], $secret);
            fflush($pipes[0]);
            fclose($pipes[0]);
            while($s= fgets($pipes[1], 1024)) {
              $text .= $s;
            }
            fclose($pipes[1]);
            while($s= fgets($pipes[2], 1024)) {
              $error .= $s . "\n";
            }
            fclose($pipes[2]);
        }
        if(( !empty($error) || stristr($text, "invalid password")!==false ) && file_exists($raw."/".basename($originalXML))){
            unlink($raw."/".basename($originalXML));
            throw new Exception("Error while creating encfs volume");
        }else{
            return true;
        }
    }


    public static function mountFolder($raw, $clear, $secret){

        $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin
                1 => array("pipe", "w"),  // stdout
                2 => array("pipe", "w")   // stderr ?? instead of a file
                );
        $command = 'sudo encfs -o allow_other,uid=33 -S '.escapeshellarg($raw).' '.escapeshellarg($clear);
        $process = proc_open($command, $descriptorspec, $pipes);
        $text = ""; $error = "";
        if (is_resource($process)) {
            fwrite($pipes[0], $secret);
            fclose($pipes[0]);
            while($s= fgets($pipes[1], 1024)) {
              $text .= $s;
            }
            fclose($pipes[1]);
            while($s= fgets($pipes[2], 1024)) {
              $error .= $s . "\n";
            }
            fclose($pipes[2]);
        }
        if(!empty($error)){
            throw new Exception("Error mounting volume : ".$error);
        }
        if(stristr($text, "error")){
            throw new Exception("Error mounting volume : ".$text);
        }
        // Mount should have succeeded now
        if(!is_file($clear."/.ajxp_mount")){
            file_put_contents($clear."/.ajxp_mount", "ajxp encfs mount");
        }
    }

    public static function umountFolder($clear){
        $descriptorspec = array(
                1 => array("pipe", "w"),  // stdout
                2 => array("pipe", "w")   // stderr ?? instead of a file
                );
        $command = 'sudo umount '.escapeshellarg($clear);
        $process = proc_open($command, $descriptorspec, $pipes);
        $text = ""; $error = "";
        if (is_resource($process)) {
            while($s= fgets($pipes[1], 1024)) {
              $text .= $s;
            }
            fclose($pipes[1]);
            while($s= fgets($pipes[2], 1024)) {
              $error .= $s . "\n";
            }
            fclose($pipes[2]);
        }
        if(!empty($error)) throw new Exception($error);
    }
}
