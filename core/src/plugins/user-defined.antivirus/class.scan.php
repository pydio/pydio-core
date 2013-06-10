<?php
defined('AJXP_EXEC') or die( 'Access not allowed');
class scan extends AJXP_Plugin {

	const DEBUG_ON = 0;

	protected $path;
	protected $file_extension;
	protected $extension_scan;
	protected $scan_all;
	protected $scan_diff_folder;
	protected $scan_max_size;
	protected $file_size;

//fonction principale du plugin, c'est elle qui est appeler par la hook. La structure de if else correspond a la prise de décision de l'action effectue surle fichier

	public function scan_file ($node) {

		$this->CallSet($node);											//appelle de la fonction qui appelle tout les setteur

		if($this->file_size < $this->scan_max_size) {								//si le fichier est plus petit que la limite de taille
			if($this->scan_all == true) {									//si on scanne tout
				if($this->scann()==true) {		//on verifie que le fichier n'est pas dans la liste d'execption
					if($this->pluginConf["TRACE"] == false) {return;}				//si la trace est désactivé on fait rien
					$this->scan_later();								//sinon on crée une trace
					return ;
				}else {										//sinon le fichier n'est pas dans la liste d'execption on le scanne
					$this->scan_now();
					return ;
				}
			} else {											//si on ne scanne pas tout
				if($this->scann()==true) {		//si le fichier et dans la liste des extension a scanner
					$this->scan_now();								//on le scanne
					return ;
				}else {											//sinon
					if($this->pluginConf["TRACE"] == false) {return;}				//on verifie que la trace est activé
					$this->scan_later();								//on crée la trace
					return ;
				}
			}
		}else {													//si le fichier est plus gros que la limite de taille
			$this->scan_later();
			return ;
		}
	}

//function qui determine si l'extension du fichier est dans la liste des exensions a scanner
	private function scann() {
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

//cette fonction scanne immédiatement le fichier, si un virus est trouver elle le signale a l'utilisateur
	private function scan_now() {													//fonction de scan immédiat des fichier
		$command = $this->pluginConf["COMMAND"];										//récupération de la commande pour l'anti virus
		$command = str_replace('$' . 'FILE', '"' . $this->path . '"', $command);						//mise en forme
	
		
			ob_start();														//debut collecte resultat
			passthru($command, $int);												//exécution de la commande
			$output=ob_get_contents();
			ob_end_clean();														//fin de la collecte
		
	
		if($int != 0) {														//si virus
			if(self::DEBUG_ON == 1) {														
				echo $output;
			} else {
				$filename = strrchr($this->path, DIRECTORY_SEPARATOR);
				$filename = substr($filename, 1);
				echo 'Virus has been found in : '  . $filename .  '         File removed';
			}												//affichage d'un message
			AJXP_Logger::logAction("Upload Virus File" . $this->path, array("file"=>SystemTextEncoding::fromUTF8($dir).DIRECTORY_SEPARATOR.$realpath));	//log	
			break;													//stop
		}
		return;
	}
//fonction qui génére les traces des fichiers
	private function scan_later() {													//fonction de scan différé
		$numero=0;														//numéro du fichier qui sera créé
		$fichier_scanne=false;													//passe a true quand le fichier est créé
		if(is_dir($this->scan_diff_folder) == false) {										//si le dossier pour les fichier n'existe pas
			$create_folder = mkdir($this->scan_diff_folder, 0755);								//on créé le dossier
			if($create_folder == false) {											//si echec
				throw new Exception("can-t create scan_diff_folder, check permission");					//execption
				return;
			}
		}
		while($fichier_scanne == false) {											//boucle pour crée un fichier avec un numéro qui n'est pas déjà pris
			if(file_exists( $this->scan_diff_folder .DIRECTORY_SEPARATOR. 'file_' . $numero)) {						//on test si le fichier existe
				$numero++;												//si oui on incrémente numéro pour tester le numéro suivant
			}
			else {														//sinon on met le chemin dans un fichier	
				$command = 'echo "'. '\"' . $this->path . '\"' . '" >' . $this->scan_diff_folder .DIRECTORY_SEPARATOR. 'file_' . $numero;
				passthru($command);
				$fichier_scanne=true;
				return;
			}
		}
	}

//fonction qui appelle les fonction qui initialise les attribut
	private function CallSet($nodeobject) {
		$this->setPath($nodeobject);						//définition du chemin du fichier
		$this->setFileExtension($nodeobject);						//définition de l'extension du fichier
		$this->setExtensionScan();						//définition des extension à scanner
		$this->setScanDiffFolder ();						//définition du chemin du dossier de scan différé
		$this->setScanMaxSize ();						//définition de la taille max d'un fichier
		$this->setFileSize($nodeobject);							//définition de la taille du fichier
		$this->setScanAll();							//défintion du mode scanner tout les fichiers


		if(self::DEBUG_ON == 1) {
			$debug = 'echo "' . $this->path . "     " . $this->file_extension . "     " . $this->extension_scan . "     " . $this->scan_all . "     " . $this->scan_diff_folder . "     " . $this->scan_max_size . "	" . $this->file_size . '" >> plugins/user-defined.antivirus/debug';
			passthru($debug);
		}
		return ;
	}
//fonction qui initialise l'attribut path
	public function setPath($nodeobject){
		$realpath = $nodeobject->getRealFile();		//on recupere le chemin brute (dans un objet)
		$realpath = realpath($realpath);
		$this->path= $realpath;
		return ;
	}
//fonction qui initialise l'attribut file_extension
	public function setFileExtension ($nodeobject) {
		$realpath = $nodeobject->getRealFile();		//on recupere le chemin brute (dans un objet)
		$realpath = realpath($realpath);
		$realpath = str_replace(" ", "_",$realpath );
		$realpath = strrchr($realpath, DIRECTORY_SEPARATOR);
		$this->file_extension=strrchr($realpath, '.');
		if($this->file_extension == ( "") ){$this->file_extension = ".no_ext";}			//les fichiers sans extension se voient attribuer l'extension .no_ext
		return ;
	}

//fonction qui initialise l'attribut extension_scan
	public function setExtensionScan() {
		$this->extension_scan = $this->pluginConf["EXT"];
		return ;
	}
//fonction qui initialise l'atribut scan_all
	public function setScanAll(){
		$extension = $this->pluginConf["EXT"];
		if(substr($extension, 0, 2) == "*/"){
			$this->scan_all = true;
		}else{
			$this->scan_all = false;
		}
		return ;
	}
//fonction qui initialise l'attribu scan_diff_folder
	public function setScanDiffFolder () {
		$this->scan_diff_folder = $this->pluginConf["PATH"];	
		return ;
	}
//fonction qui initialise l'attribut scan_max-size
	public function setScanMaxSize () {
		$this->scan_max_size = AJXP_Utils::convertBytes($this->pluginConf["SIZE"]);
		return ;
	}
//fonction qui initialise l'attribut file_size
	public function setFileSize ($nodeobject) {
		$realpath = $nodeobject->getRealFile();		//on recupere le chemin brute (dans un objet)
		$realpath = realpath($realpath);
		$this->file_size = filesize($realpath);
		return ;
	}

}
?>
