<?php

class SerialMetaManager extends AJXP_Plugin {
	
	private static $currentMetaName;
	private static $metaCache;
	
	protected $accessDriver;
	
	public function init($options, $accessDriver){
		$this->accessDriver = $accessDriver;		
		$this->options = $options;
		
		$def = $this->getMetaDefinition();
		$dynaContrib = '<client_configs>
			<component_config className="FilesList">
				<columns>';
		foreach ($def as $key=>$label){
			$dynaContrib .= '<additional_column messageString="'.$label.'" attributeName="'.$key.'" sortType="String"/>';
		}
		$dynaContrib .=	'</columns>
			</component_config>
		</client_configs>	
		';
		$dom = new DOMDocument();
		$dom->loadXML($dynaContrib);
		$imported = $this->manifestDoc->importNode($dom->documentElement, true);
		$selection = $this->xPath->query("registry_contributions");
		$contrib = $selection->item(0);		
		$contrib->appendChild($imported);

		parent::init($options);
	
	}
		
	protected function getMetaDefinition(){
		$fields = $this->options["meta_fields"];
		$arrF = explode(",", $fields);
		$labels = $this->options["meta_labels"];
		$arrL = explode(",", $labels);
		$result = array();
		foreach ($arrF as $index => $value){
			if(isSet($arrL[$index])){
				$result[$value] = $arrL[$index];
			}else{
				$result[$value] = $value;
			}
		}
		return $result;		
	}
	
	public function editMeta($actionName, $httpVars, $fileVars){
		if(!isSet($this->actions[$actionName])) return;
		$selection = new UserSelection();
		$selection->initFromHttpVars();
		$currentFile = $selection->getUniqueFile();
		
		$newValues = array();
		$def = $this->getMetaDefinition();
		foreach ($def as $key => $label){
			$newValues[$key] = AJXP_Utils::decodeSecureMagic($httpVars[$key]);
		}		
		$wrapperData = $this->accessDriver->detectStreamWrapper(false);
		$urlBase = $wrapperData["protocol"]."://".$this->accessDriver->repository->getId();
		$this->addMeta($urlBase.$currentFile, $newValues);	
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::reloadDataNode("", SystemTextEncoding::toUTF8($currentFile), true);	
		AJXP_XMLWriter::close();
	}
	
	public function extractMeta($currentFile, &$metadata, $wrapperClassName, &$realFile){
		$base = basename($currentFile);
		$this->loadMetaFileData($currentFile);		
		if(is_array(self::$metaCache) && array_key_exists($base, self::$metaCache)){
			$metadata = array_merge($metadata, self::$metaCache[$base]);
		}
		// NOT OPTIMAL AT ALL 
		$metadata["meta_fields"] = $this->options["meta_fields"];
		$metadata["meta_labels"] = $this->options["meta_labels"];
	}
	
	public function updateMetaLocation($oldFile, $newFile = null, $copy = false){
		$this->loadMetaFileData($oldFile);
		$oldKey = basename($oldFile);
		if(!array_key_exists($oldKey, self::$metaCache)){
			return;
		}
		$oldData = self::$metaCache[$oldKey];
		// If it's a move or a delete, delete old data
		if(!$copy){
			unset(self::$metaCache[$oldKey]);
			$this->saveMetaFileData($oldFile);
		}
		// If copy or move, copy data.
		if($newFile != null){
			$this->addMeta($newFile, $oldData);
		}
	}
	
	public function addMeta($currentFile, $dataArray){
		$this->loadMetaFileData($currentFile);
		self::$metaCache[basename($currentFile)] = $dataArray;
		$this->saveMetaFileData($currentFile);
	}
	
	protected function loadMetaFileData($currentFile){
		$metaFile = dirname($currentFile)."/".$this->options["meta_file_name"];
		if(self::$currentMetaName == $metaFile && is_array(self::$metaCache)){
			return;
		}
		if(is_file($metaFile) && is_readable($metaFile)){
			$rawData = file_get_contents($metaFile);
			self::$metaCache = unserialize($rawData);
		}else{
			self::$metaCache = array();
		}
	}
	
	protected function saveMetaFileData($currentFile){
		$metaFile = dirname($currentFile)."/".$this->options["meta_file_name"];
		if((is_file($metaFile) && is_writable($metaFile)) || is_writable(dirname($metaFile))){
			$fp = fopen($metaFile, "w");
			fwrite($fp, serialize(self::$metaCache), strlen(serialize(self::$metaCache)));
			fclose($fp);
		}
	}
	
}

?>