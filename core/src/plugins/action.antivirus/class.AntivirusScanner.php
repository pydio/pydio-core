<?php
defined('AJXP_EXEC') or die( 'Access not allowed');
class AntivirusScanner extends AJXP_Plugin {

	const DEBUG_ON = 0;

	protected $path;
	protected $file_extension;
	protected $extension_scan;
	protected $scan_all;
	protected $scan_diff_folder;
	protected $scan_max_size;
	protected $file_size;

//main function, it is called by the hook.

	public function scan_file ($oldNode, $newNode) {

		$this->CallSet($newNode);			//initializes attributes

		if($oldNode==null && $newNode != null){

		} else{
			return;
		}

//this block scans or doesn't scan the file. This is based on plugin parameters

		if($this->file_size < $this->scan_max_size) {
			if($this->scan_all == true) {
				if($this->In_list()==true) {
					if($this->pluginConf["TRACE"] == false) {return;}
					$this->scan_later();
					return ;
				}else {
					$this->scan_now();
					return ;
				}
			} else {
				if($this->In_list()==true) {
					$this->scan_now();
					return ;
				}else {
					if($this->pluginConf["TRACE"] == false) {return;}
					$this->scan_later();
					return ;
				}
			}
		}else {
			$this->scan_later();
			return ;
		}
	}

//return true if file_extension is in the list extension_scan
	private function In_list() {
		while(strripos($this->extension_scan, $this->file_extension)) {
			$start_pos = strripos($this->extension_scan, $this->file_extension);
			$leng_ext = strlen($this->file_extension);
			$result = substr($this->extension_scan, $start_pos);
			$result = substr($result, $leng_ext, 1);
			if(preg_match("/[A-Za-z0-9]+/",$result)) {
				$this->extension_scan = substr($this->extension_scan, $start_pos + $leng_ext);
			} else {
				return true;
			}
		}
		return false;
	}

//this function immediatly scans the file, it calls the antivirus command
	private function scan_now() {
		$command = $this->pluginConf["COMMAND"];
		$command = str_replace('$' . 'FILE', '"' . $this->path . '"', $command);
	
		
			ob_start();
			passthru($command, $int);
			$output=ob_get_contents();
			ob_end_clean();
		
	
		if($int != 0) {
			if(self::DEBUG_ON == 1) {														
				echo $output;
			} else {
				$filename = strrchr($this->path, DIRECTORY_SEPARATOR);
				$filename = substr($filename, 1);
				echo 'Virus has been found in : '  . $filename .  '         File removed';
			}
			AJXP_Logger::logAction("Upload Virus File" . $this->path, array("file"=>SystemTextEncoding::fromUTF8($dir).DIRECTORY_SEPARATOR.$realpath));
			break;
		}
		return;
	}
//this function generates a trace of the file
	private function scan_later() {
		$numero=0;
		$fichier_scanne=false;
		if(is_dir($this->scan_diff_folder) == false) {
			$create_folder = mkdir($this->scan_diff_folder, 0755);
			if($create_folder == false) {
				throw new Exception("can-t create scan_diff_folder, check permission");
				return;
			}
		}
		while($fichier_scanne == false) {
			if(file_exists( $this->scan_diff_folder .DIRECTORY_SEPARATOR. 'file_' . $numero)) {
				$numero++;
			}
			else {	
				$command = 'echo "'. '\"' . $this->path . '\"' . '" >' . $this->scan_diff_folder .DIRECTORY_SEPARATOR. 'file_' . $numero;
				passthru($command);
				$fichier_scanne=true;
				return;
			}
		}
	}

//this function initializes attributes. 
	private function CallSet($nodeobject) {
		$this->setPath($nodeobject);
		$this->setFileExtension($nodeobject);
		$this->setExtensionScan();
		$this->setScanDiffFolder ();
		$this->setScanMaxSize ();
		$this->setFileSize($nodeobject);
		$this->setScanAll();

//debug option, put in a file attribute values
		if(self::DEBUG_ON == 1) {
			$debug = 'echo "' . $this->path . "     " . $this->file_extension . "     " . $this->extension_scan . "     " . $this->scan_all . "     " . $this->scan_diff_folder . "     " . $this->scan_max_size . "	" . $this->file_size . '" >> plugins/action.antivirus/debug';
			passthru($debug);
		}
		return ;
	}
//this function initializes the file path
	public function setPath($nodeobject){
		$realpath = $nodeobject->getRealFile();
		$realpath = realpath($realpath);
		$this->path= $realpath;
		return ;
	}
//this function initializes the file extension
	public function setFileExtension ($nodeobject) {
		$realpath = $nodeobject->getRealFile();
		$realpath = realpath($realpath);
		$realpath = str_replace(" ", "_",$realpath );
		$realpath = strrchr($realpath, DIRECTORY_SEPARATOR);
		$this->file_extension=strrchr($realpath, '.');
		if($this->file_extension == ( "") ){$this->file_extension = ".no_ext";}	
		return ;
	}

//this function initializes the extenension list
	public function setExtensionScan() {
		$this->extension_scan = $this->pluginConf["EXT"];
		return ;
	}
//this function initializes attribute scan_all
	public function setScanAll(){
		$extension = $this->pluginConf["EXT"];
		if(substr($extension, 0, 2) == "*/"){
			$this->scan_all = true;
		}else{
			$this->scan_all = false;
		}
		return ;
	}
//this function initializes the trace folder
	public function setScanDiffFolder () {
		$this->scan_diff_folder = $this->pluginConf["PATH"];	
		return ;
	}
//this function initializes max size of the scanned file
	public function setScanMaxSize () {
		$this->scan_max_size = AJXP_Utils::convertBytes($this->pluginConf["SIZE"]);
		return ;
	}
//this function initializes the size of the file
	public function setFileSize ($nodeobject) {
		$realpath = $nodeobject->getRealFile();
		$realpath = realpath($realpath);
		$this->file_size = filesize($realpath);
		return ;
	}

}
?>
