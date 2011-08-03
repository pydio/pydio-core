<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2010 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
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
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Simple hook plugin to open files in browser
 *               Contributed by Sylvester Lykkehus <zly@solidonline.dk>
 */
defined('AJXP_EXEC') or die('Access not allowed');

class FileMimeSender extends AJXP_Plugin {
    
    public function switchAction($action, $httpVars, $filesVars) {

        if(!isSet($this->actions[$action]))
            return false;

        $repository = ConfService::getRepositoryById($httpVars["repository_id"]);

        if(!$repository->detectStreamWrapper(true))
            return false;
        
        if(AuthService::usersEnabled()){
            $loggedUser = AuthService::getLoggedUser();
            if($loggedUser === null && ConfService::getCoreConf("ALLOW_GUEST_BROWSING", "auth")){
                AuthService::logUser("guest", null);
                $loggedUser = AuthService::getLoggedUser();
            }
            if(!$loggedUser->canSwitchTo($repository->getId())){
                echo("You do not have permissions to access this resource");
                return false;
            }
        }
        
        $streamData = $repository->streamData;
        $destStreamURL = $streamData["protocol"] . "://" . $repository->getId();
        
        if($action == "open_file") {
            $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
            if(!file_exists($destStreamURL . $file)){
                echo("File does not exist");
                return false;
            }

            $filesize = filesize($destStreamURL . $file);
            $fp = fopen($destStreamURL . $file, "rb");
            
            //Get mimetype with fileinfo PECL extension
            if(class_exists("finfo")) {
                $finfo = new finfo(FILEINFO_MIME);
                $fileMime = $finfo->buffer(fread($fp, 100));
            }
            //Get mimetype with (deprecated) mime_content_type
            elseif(function_exists("mime_content_type")) {
                $fileMime = @mime_content_type($fp);
            }
            //Guess mimetype based on file extension
            else {
                $fileExt = substr(strrchr(basename($file), '.'), 1);
                if(empty($fileExt))
                    $fileMime = "application/octet-stream";
                else {
                    $regex = "/^([\w\+\-\.\/]+)\s+(\w+\s)*($fileExt\s)/i";
                    $lines = file(AJXP_INSTALL_PATH . "/plugins/editor.browser/mime.types");
                    foreach($lines as $line) {
                        if(substr($line, 0, 1) == '#')
                            continue; // skip comments
                        $line = rtrim($line) . " ";
                        if(!preg_match($regex, $line, $matches))
                            continue; // no match to the extension
                        $fileMime = $matches[1];
                    }
                }
            }
            // If still no mimetype, give up and serve application/octet-stream
            if(empty($fileMime))
                $fileMime = "application/octet-stream";
                
            //Send headers
            header("Content-Type: " . $fileMime . "; name=\"" . basename($file) . "\"");
            header("Content-Disposition: inline; filename=\"" . basename($file) . "\"");
            header("Content-Length: " . $filesize);
            header("Cache-Control: public");
            
            //Send data
            $class = $streamData["classname"];
            $stream = fopen("php://output", "a");
            call_user_func(array($streamData["classname"], "copyFileInStream"), $destStreamURL . $file, $stream);
            fflush($stream);
            fclose($stream);
            exit(1);
        }
    }
}

?>