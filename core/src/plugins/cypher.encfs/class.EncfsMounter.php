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
use Pydio\Access\Core\RecycleBinManager;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Utils;
use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Cypher
 */
class EncfsMounter extends Plugin
{

    protected function getWorkingPath(ContextInterface $contextInterface)
    {
        $repo = $contextInterface->getRepository();
        $path = $repo->getOption("PATH");
        return $path;
    }

    public function cypherAllMounted($actionName, &$httpVars, &$fileVars, ContextInterface $ctx)
    {
        $dirs = glob($this->getWorkingPath($ctx)."/ENCFS_CLEAR_*/.ajxp_mount");
        if ($dirs!==false && count($dirs)) {
            foreach ($dirs as $mountedFile) {
                $mountedDir = dirname($mountedFile);
                $this->logDebug("Warning, $mountedDir was not unmounted before $actionName");
                $this->umountFolder($mountedDir);
            }
        }
    }

    public function preProcessMove($actionName, &$httpVars, &$fileVars)
    {
        $destO = Utils::decodeSecureMagic($httpVars["dest"]);
        $dest = substr($destO, 1, strpos(ltrim($destO, "/"), "/"));
        if(empty($dest)) $dest = ltrim($destO, "/");
        $userSelection = new UserSelection();
        $userSelection->initFromHttpVars($httpVars);
        if (!$userSelection->isEmpty()) {
            $testFileO = $userSelection->getUniqueFile();
            $testFile = substr($testFileO, 1, strpos(ltrim($testFileO, "/"), "/"));
            if(empty($testFile)) $testFile = ltrim($testFileO, "/");
            if ($actionName == "move") {
                if( (strstr($dest, "ENCFS_CLEAR_")!=false && strstr($testFile, "ENCFS_CLEAR_")===false)
                    || (strstr($dest, "ENCFS_CLEAR_")===false && strstr($testFile, "ENCFS_CLEAR_")!==false)
                    || (strstr($dest, "ENCFS_CLEAR_")!=false && strstr($testFile, "ENCFS_CLEAR_")!==false
                        &&  $testFile != $dest)
                ){
                    $httpVars["force_copy_delete"] = "true";
                    $this->logDebug("One mount to another, copy/delete instead of move ($dest, $testFile)");
                }
            } else if ( $actionName == "delete" && RecycleBinManager::recycleEnabled() ) {
                if (strstr($testFile, "ENCFS_CLEAR_")!==false) {
                    $httpVars["force_copy_delete"] = "true";
                    $this->logDebug("One mount to another, copy/delete instead of move");
                }
            } else if ($actionName == "restore") {
                if (strstr(RecycleBinManager::getFileOrigin($testFile), "ENCFS_CLEAR_")) {
                    $httpVars["force_copy_delete"] = "true";
                    $this->logDebug("One mount to another, copy/delete instead of move");
                }
            }
        }
    }

    public function switchAction(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {

        $xmlTemplate = $this->getFilteredOption("ENCFS_XML_TEMPLATE");
        $actionName = $requestInterface->getAttribute("action");
        $httpVars = $requestInterface->getParsedBody();
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $userSelection = UserSelection::fromContext($ctx, $httpVars);
        if($userSelection->isEmpty()){
            throw new Exception("Please select a folder");
        }
        $node = $userSelection->getUniqueNode();

        switch ($actionName) {
            case "encfs.cypher_folder" :
                if (empty($xmlTemplate) || !is_file($xmlTemplate)) {
                    throw new Exception("It seems that you have not set the plugin 'Enfcs XML File' configuration, or the system cannot find it!");
                }

                $workingP = rtrim($this->getWorkingPath($ctx), "/");
                $dir = $workingP.$node->getPath();

                if ($node->isRoot() || !$node->getParent()->isRoot()) {
                    throw new Exception("Please cypher only folders at the root of your repository");
                }

                $pass = $httpVars["pass"];
                $raw  = dirname($dir).DIRECTORY_SEPARATOR."ENCFS_RAW_".basename($dir);
                if (!strstr($dir, "ENCFS_CLEAR_") && !is_dir($raw)) {
                    // NEW FOLDER SCENARIO
                    $clear  = dirname($dir).DIRECTORY_SEPARATOR."ENCFS_CLEAR_".basename($dir);
                    mkdir($raw);
                    $result = self::initEncFolder($raw, $xmlTemplate, $this->getFilteredOption("ENCFS_XML_PASSWORD"), $pass);
                    if ($result) {
                        // Mount folder
                        mkdir($clear);
                        $uid = $this->getFilteredOption("ENCFS_UID");
                        self::mountFolder($raw, $clear, $pass, $uid);
                        $content = scandir($dir);
                        foreach ($content as $fileOrFolder) {
                            if($fileOrFolder == "." || $fileOrFolder == "..") continue;
                            $cmd = "mv ". escapeshellarg($dir . DIRECTORY_SEPARATOR . $fileOrFolder)." ". escapeshellarg($clear . DIRECTORY_SEPARATOR);
                            shell_exec($cmd);
                        }
                        rmdir($dir);
                        self::umountFolder($clear);
                    }
                } else if (substr(basename($dir), 0, strlen("ENCFS_CLEAR_")) == "ENCFS_CLEAR_") {
                    // SIMPLY UNMOUNT
                    self::umountFolder($dir);
                }
                break;
            case "encfs.uncypher_folder":
                $dir = $this->getWorkingPath($ctx).$node->getPath();
                $raw = str_replace("ENCFS_CLEAR_", "ENCFS_RAW_", $dir);
                $pass = $httpVars["pass"];
                $uid = $this->getFilteredOption("ENCFS_UID");
                if (is_dir($raw)) {
                    self::mountFolder($raw, $dir, $pass, $uid);
                }
                break;
        }

        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $diff = new \Pydio\Access\Core\Model\NodesDiff();
        $diff->update($node);
        $x->addChunk($diff);
        $responseInterface = $responseInterface->withBody($x);

    }


    /**
     * @param AJXP_Node $ajxpNode
     * @param AJXP_Node|bool $parentNode
     * @param bool $details
     */
    public function filterENCFS(&$ajxpNode, $parentNode = false, $details = false)
    {
        if (substr($ajxpNode->getLabel(), 0, strlen("ENCFS_RAW_")) == "ENCFS_RAW_") {
            $ajxpNode->hidden = true;
        } else if (substr($ajxpNode->getLabel(), 0, strlen("ENCFS_CLEAR_")) == "ENCFS_CLEAR_") {
            $ajxpNode->ENCFS_clear_folder = true;
            $ajxpNode->overlay_icon = "cypher.encfs/overlay_ICON_SIZE.png";
            $ajxpNode->overlay_class = "icon-lock";
            $ajxpNode->ajxp_readonly = "true";
            if (is_file($ajxpNode->getUrl()."/.ajxp_mount")) {
                $ajxpNode->setLabel(substr($ajxpNode->getLabel(), strlen("ENCFS_CLEAR_")));
                $ajxpNode->ENCFS_clear_folder_mounted = true;
                $ajxpNode->ajxp_readonly = "false";
            } else {
                $ajxpNode->setLabel(substr($ajxpNode->getLabel(), strlen("ENCFS_CLEAR_")) . " (encrypted)");
            }
        }
    }

    public static function initEncFolder($raw, $originalXML, $originalSecret,  $secret)
    {
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
            while ($s= fgets($pipes[1], 1024)) {
                $text .= $s;
            }
            fclose($pipes[1]);
            while ($s= fgets($pipes[2], 1024)) {
                $error .= $s . "\n";
            }
            fclose($pipes[2]);
        }
        if (( !empty($error) || stristr($text, "invalid password")!==false ) && file_exists($raw."/".basename($originalXML))) {
            unlink($raw."/".basename($originalXML));
            throw new Exception("Error while creating encfs volume");
        } else {
            return true;
        }
    }


    public static function mountFolder($raw, $clear, $secret, $uid)
    {
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr ?? instead of a file
        );
        $command = 'sudo encfs -o allow_other,uid='.$uid.' -S '.escapeshellarg($raw).' '.escapeshellarg($clear);
        $process = proc_open($command, $descriptorspec, $pipes);
        $text = ""; $error = "";
        if (is_resource($process)) {
            fwrite($pipes[0], $secret);
            fclose($pipes[0]);
            while ($s= fgets($pipes[1], 1024)) {
                $text .= $s;
            }
            fclose($pipes[1]);
            while ($s= fgets($pipes[2], 1024)) {
                $error .= $s . "\n";
            }
            fclose($pipes[2]);
        }
        if (!empty($error)) {
            throw new Exception("Error mounting volume : ".$error);
        }
        if (stristr($text, "error")) {
            throw new Exception("Error mounting volume : ".$text);
        }
        // Mount should have succeeded now
        if (!is_file($clear."/.ajxp_mount")) {
            file_put_contents($clear."/.ajxp_mount", "ajxp encfs mount");
        }
    }

    public static function umountFolder($clear)
    {
        $descriptorspec = array(
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr ?? instead of a file
        );
        $command = 'sudo umount '.escapeshellarg($clear);
        $process = proc_open($command, $descriptorspec, $pipes);
        $text = ""; $error = "";
        if (is_resource($process)) {
            while ($s= fgets($pipes[1], 1024)) {
                $text .= $s;
            }
            fclose($pipes[1]);
            while ($s= fgets($pipes[2], 1024)) {
                $error .= $s . "\n";
            }
            fclose($pipes[2]);
        }
        if(!empty($error)) throw new Exception($error);
    }
}
