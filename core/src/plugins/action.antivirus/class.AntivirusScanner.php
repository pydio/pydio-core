<?php
defined('AJXP_EXEC') or die( 'Access not allowed');
class AntivirusScanner extends AJXP_Plugin
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
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * Main function, it is called by the hook.
     */
    public function scanFile ($oldNode = null, $newNode = null)
    {
        if ($oldNode!=null || $newNode == null) {
            return;
        }

        $this->callSet($newNode);			//initializes attributes


        // This block scans or doesn't scan the file. This is based on plugin parameters
        if ($this->file_size < $this->scan_max_size) {
            if ($this->scan_all == true) {
                if ($this->inList()==true) {
                    if ($this->getFilteredOption("TRACE") == false) {return;}
                    $this->scanLater();
                    return ;
                } else {
                    $this->scanNow();
                    return ;
                }
            } else {
                if ($this->inList()==true) {
                    $this->scanNow();
                    return ;
                } else {
                    if ($this->getFilteredOption("TRACE") == false) {return;}
                    $this->scanLater();
                    return ;
                }
            }
        } else {
            $this->scanLater();
            return ;
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
            if (preg_match("/[A-Za-z0-9]+/",$result)) {
                $this->extension_scan = substr($this->extension_scan, $start_pos + $leng_ext);
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * This function immediatly scans the file, it calls the antivirus command
     */
    private function scanNow()
    {
        $command = $this->getFilteredOption("COMMAND");
        $command = str_replace('$' . 'FILE', escapeshellarg($this->path), $command);

        ob_start();
        passthru($command, $int);
        $output=ob_get_contents();
        ob_end_clean();

        if ($int != 0) {
            if (self::DEBUG_ON == 1) {
                echo $output;
            } else {
                $filename = strrchr($this->path, DIRECTORY_SEPARATOR);
                $filename = substr($filename, 1);
                echo 'Virus has been found in : '  . $filename .  '         File removed';
            }
            //$this->logInfo("Upload Virus File" . $this->path, array("file"=>SystemTextEncoding::fromUTF8($dir).DIRECTORY_SEPARATOR.$realpath));
        }
        return;
    }

    /**
     * This function generates a trace of the file
     */
    private function scanLater()
    {
        $numero=0;
        $scanned=false;
        if (is_dir($this->scan_diff_folder) == false) {
            $create_folder = mkdir($this->scan_diff_folder, 0755);
            if ($create_folder == false) {
                throw new Exception("can-t create scan_diff_folder, check permission");
                return;
            }
        }
        while ($scanned == false) {
            if (file_exists( $this->scan_diff_folder .DIRECTORY_SEPARATOR. 'file_' . $numero)) {
                $numero++;
            } else {
                $command = 'echo "'. '\"' . $this->path . '\"' . '" >' . $this->scan_diff_folder .DIRECTORY_SEPARATOR. 'file_' . $numero;
                passthru($command);
                $scanned=true;
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
        $this->setExtensionScan();
        $this->setScanDiffFolder ();
        $this->setScanMaxSize ();
        $this->setFileSize($nodeObject);
        $this->setScanAll();

        //debug option, put in a file attribute values
        if (self::DEBUG_ON == 1) {
            $debug = 'echo "' . $this->path . "     " . $this->file_extension . "     " . $this->extension_scan . "     " . $this->scan_all . "     " . $this->scan_diff_folder . "     " . $this->scan_max_size . "	" . $this->file_size . '" >> plugins/action.antivirus/debug';
            passthru($debug);
        }
        return ;
    }

    /**
     * This function initializes the file path
     * @param $nodeObject
     */
    public function setPath($nodeObject)
    {
        $realpath = $nodeObject->getRealFile();
        $realpath = realpath($realpath);
        $this->path= $realpath;
        return ;
    }

    /**
     * This function initializes the file extension
     * @param $nodeObject
     */
    public function setFileExtension ($nodeObject)
    {
        $realpath = $nodeObject->getRealFile();
        $realpath = realpath($realpath);
        $realpath = str_replace(" ", "_",$realpath );
        $realpath = strrchr($realpath, DIRECTORY_SEPARATOR);
        $this->file_extension=strrchr($realpath, '.');
        if ($this->file_extension == ( "") ) {$this->file_extension = ".no_ext";}
        return ;
    }

    /**
     * This function initializes the extension list
     */
    public function setExtensionScan()
    {
        $this->extension_scan =  $this->getFilteredOption("EXT");
        return ;
    }

    /**
     * this function initializes attribute scan_all
     */
    public function setScanAll()
    {
        $extension = $this->getFilteredOption("EXT");
        if (substr($extension, 0, 2) == "*/") {
            $this->scan_all = true;
        } else {
            $this->scan_all = false;
        }
        return ;
    }

    /**
     * this function initializes the trace folder
     */
    public function setScanDiffFolder ()
    {
        $this->scan_diff_folder = $this->getFilteredOption("PATH");
        return ;
    }

    /**
     * this function initializes max size of the scanned file
     */
    public function setScanMaxSize ()
    {
        $this->scan_max_size = AJXP_Utils::convertBytes($this->getFilteredOption("SIZE"));
        return ;
    }

    /**
     * This function initializes the size of the file
     * @param AJXP_Node $nodeObject
     */
    public function setFileSize ($nodeObject)
    {
        $realpath = $nodeObject->getRealFile();
        $realpath = realpath($realpath);
        $this->file_size = filesize($realpath);
        return ;
    }

}
