<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>, Afterster
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
 * The latest code can be found at <https://pydio.com>.
 */


namespace Pydio\Action\Antivirus;

use Exception;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Utils\Vars\StatHelper;
use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class AntivirusScanner
 * Use ClamAV to scan files
 * @package Pydio\Action\Antivirus
 */
class AntivirusScanner extends Plugin
{
    const DEBUG_ON = 0;

    protected $path;
    protected $file_extension;
    protected $extension_scan;
    protected $scan_all;
    protected $scan_diff_folder;
    protected $scan_max_size;
    protected $file_size;

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldNode
     * @param \Pydio\Access\Core\Model\AJXP_Node $newNode
     * Main function, it is called by the hook.
     */
    public function scanFile($oldNode = null, $newNode = null)
    {
        if ($oldNode != null || $newNode == null) {
            return;
        }
        // ADD THOSE TWO LINES
        $newNode->loadNodeInfo();
        if (!$newNode->isLeaf()) return;

        $this->callSet($newNode);            //initializes attributes


        // This block scans or doesn't scan the file. This is based on plugin parameters
        $trace = $this->getContextualOption($newNode->getContext(), "TRACE");
        if ($this->file_size < $this->scan_max_size) {
            if ($this->scan_all == true) {
                if ($this->inList() == true) {
                    if ($trace === false) {
                        return;
                    }
                    $this->scanLater();
                    return;
                } else {
                    $this->scanNow($newNode);
                    return;
                }
            } else {
                if ($this->inList() == true) {
                    $this->scanNow($newNode);
                    return;
                } else {
                    if ($trace === false) {
                        return;
                    }
                    $this->scanLater();
                    return;
                }
            }
        } else {
            $this->scanLater();
            return;
        }
    }

    /**
     * @return bool true if file_extension is in the list extension_scan
     */
    private function inList()
    {
        while (strripos($this->extension_scan, $this->file_extension)) {
            $start_pos = strripos($this->extension_scan, $this->file_extension);
            $leng_ext = strlen($this->file_extension);
            $result = substr($this->extension_scan, $start_pos);
            $result = substr($result, $leng_ext, 1);
            if (preg_match("/[A-Za-z0-9]+/", $result)) {
                $this->extension_scan = substr($this->extension_scan, $start_pos + $leng_ext);
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * This function immediatly scans the file, it calls the antivirus command
     * @param AJXP_Node $nodeObject
     * @throws Exception
     */
    private function scanNow($nodeObject)
    {
        $command = $this->getContextualOption($nodeObject->getContext(), "COMMAND");
        if(empty($command)){
            $this->logError("Antivirus", "COMMAND parameter not found, could not scan file properly. Please check CONF_DIR/conf.action.antivirus.inc file");
            echo 'Antivirus command failed, the file could not be scanned properly: please check your configuration.';
            return;
        }
        $command = str_replace('$' . 'FILE', escapeshellarg($this->path), $command);

        ob_start();
        passthru($command, $int);
        $output = ob_get_contents();
        ob_end_clean();

        if ($int != 0) {
            if (self::DEBUG_ON == 1) {
                echo $output;
            } else {
                // Check if file has been removed
                if(!file_exists($this->path)){
                    $filename = strrchr($this->path, DIRECTORY_SEPARATOR);
                    $filename = substr($filename, 1);
                    echo 'Virus has been found in : ' . $filename . '. File was removed.';
                } else {
                    $this->logError("Antivirus", "COMMAND parameter not found, could not scan file properly. Please check CONF_DIR/conf.action.antivirus.inc file. Return code was ".$int);
                    echo 'Antivirus command failed, the file could not be scanned properly: please check your configuration.';
                }
            }
        }
        return;
    }

    /**
     * This function generates a trace of the file
     */
    private function scanLater()
    {
        $numero = 0;
        $scanned = false;
        if (is_dir($this->scan_diff_folder) == false) {
            $create_folder = mkdir($this->scan_diff_folder, 0755);
            if ($create_folder == false) {
                throw new Exception("can-t create scan_diff_folder, check permission");
            }
        }
        while ($scanned == false) {
            if (file_exists($this->scan_diff_folder . DIRECTORY_SEPARATOR . 'file_' . $numero)) {
                $numero++;
            } else {
                $command = 'echo "' . '\"' . $this->path . '\"' . '" >' . $this->scan_diff_folder . DIRECTORY_SEPARATOR . 'file_' . $numero;
                passthru($command);
                $scanned = true;
                return;
            }
        }
    }

    /**
     * @param AJXP_Node $nodeObject
     */
    private function callSet($nodeObject)
    {
        $this->setPath($nodeObject);
        $this->setFileExtension($nodeObject);
        $this->setExtensionScan($nodeObject);
        $this->setScanDiffFolder($nodeObject);
        $this->setScanMaxSize($nodeObject);
        $this->setFileSize($nodeObject);
        $this->setScanAll($nodeObject);

        //debug option, put in a file attribute values
        if (self::DEBUG_ON == 1) {
            $debug = 'echo "' . $this->path . "     " . $this->file_extension . "     " . $this->extension_scan . "     " . $this->scan_all . "     " . $this->scan_diff_folder . "     " . $this->scan_max_size . "	" . $this->file_size . '" >> plugins/action.antivirus/debug';
            passthru($debug);
        }
        return;
    }

    /**
     * This function initializes the file path
     * @param AJXP_Node $nodeObject
     */
    public function setPath($nodeObject)
    {
        $realpath = $nodeObject->getRealFile();
        $realpath = realpath($realpath);
        $this->path = $realpath;
        return;
    }

    /**
     * This function initializes the file extension
     * @param AJXP_Node $nodeObject
     */
    public function setFileExtension($nodeObject)
    {
        $realpath = $nodeObject->getRealFile();
        $realpath = realpath($realpath);
        $realpath = str_replace(" ", "_", $realpath);
        $realpath = strrchr($realpath, DIRECTORY_SEPARATOR);
        $this->file_extension = strrchr($realpath, '.');
        if ($this->file_extension == ("")) {
            $this->file_extension = ".no_ext";
        }
        return;
    }

    /**
     * This function initializes the extension list
     * @param AJXP_Node $nodeObject
     */
    public function setExtensionScan($nodeObject)
    {
        $this->extension_scan = $this->getContextualOption($nodeObject->getContext(), "EXT");
        return;
    }

    /**
     * this function initializes attribute scan_all
     * @param AJXP_Node $nodeObject
     */
    public function setScanAll($nodeObject)
    {
        $extension = $this->getContextualOption($nodeObject->getContext(), "EXT");
        if (substr($extension, 0, 2) == "*/") {
            $this->scan_all = true;
        } else {
            $this->scan_all = false;
        }
        return;
    }

    /**
     * this function initializes the trace folder
     * @param AJXP_Node $nodeObject
     */
    public function setScanDiffFolder($nodeObject)
    {
        $this->scan_diff_folder = $this->getContextualOption($nodeObject->getContext(), "PATH");
        return;
    }

    /**
     * this function initializes max size of the scanned file
     * @param AJXP_Node $nodeObject
     */
    public function setScanMaxSize($nodeObject)
    {
        $this->scan_max_size = StatHelper::convertBytes($this->getContextualOption($nodeObject->getContext(), "SIZE"));
        return;
    }

    /**
     * This function initializes the size of the file
     * @param \Pydio\Access\Core\Model\AJXP_Node $nodeObject
     */
    public function setFileSize($nodeObject)
    {
        $realpath = $nodeObject->getRealFile();
        $realpath = realpath($realpath);
        $this->file_size = filesize($realpath);
        return;
    }

}
