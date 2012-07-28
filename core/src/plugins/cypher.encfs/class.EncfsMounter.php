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
            //fwrite($pipes[0], $secret);
            fclose($pipes[0]);
            while($s= fgets($pipes[1], 1024)) {
              // read from the pipe
              $text .= $s;
            }
            fclose($pipes[1]);
            // optional:
            while($s= fgets($pipes[2], 1024)) {
              $error .= $s . "\n";
            }
            fclose($pipes[2]);
        }
        var_dump($text);
        var_dump($error);
        if(( !empty($error) || stristr($text, "invalid password")!==false ) && file_exists($raw."/".basename($originalXML))){
            unlink($raw."/".basename($originalXML));
            return false;
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
        $command = 'sudo encfs -S '.escapeshellarg($raw).' '.escapeshellarg($clear);
        $process = proc_open($command, $descriptorspec, $pipes);
        $text = ""; $error = "";
        if (is_resource($process)) {
            fwrite($pipes[0], $secret);
            fclose($pipes[0]);
            while($s= fgets($pipes[1], 1024)) {
              // read from the pipe
              $text .= $s;
            }
            fclose($pipes[1]);
            // optional:
            while($s= fgets($pipes[2], 1024)) {
              $error .= $s . "\n";
            }
            fclose($pipes[2]);
        }
        var_dump($text);
        var_dump($error);
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
              // read from the pipe
              $text .= $s;
            }
            fclose($pipes[1]);
            // optional:
            while($s= fgets($pipes[2], 1024)) {
              $error .= $s . "\n";
            }
            fclose($pipes[2]);
        }
        var_dump($text);
        var_dump($error);
    }
}
