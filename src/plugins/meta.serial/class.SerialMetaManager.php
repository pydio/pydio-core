<?php

class SerialMetaManager extends AJXP_Plugin {
	
	private static $currentMetaName;
	private static $metaCache;
	
	protected $accessDriver;
	
	public function init($options, $accessDriver){
		$this->accessDriver = $accessDriver;		
		$this->options = $options;
		
		$def = $this->getMetaDefinition();
		$cdataHead = '<div>
						<div class="panelHeader infoPanelGroup" colspan="2">Meta Data</div>
						<table class="infoPanelTable" cellspacing="0" border="0" cellpadding="0">';
		$cdataFoot = '</table></div>';
		$cdataParts = "";
		
		$selection = $this->xPath->query('registry_contributions/client_configs/component_config[@className="FilesList"]/columns');
		$contrib = $selection->item(0);		
		$even = false;
		foreach ($def as $key=>$label){
			$col = $this->manifestDoc->createElement("additional_column");			
			$col->setAttribute("messageString", $label);
			$col->setAttribute("attributeName", $key);
			$col->setAttribute("sortType", "String");
			$contrib->appendChild($col);
			
			$trClass = ($even?" class=\"even\"":"");
			$even = !$even;
			$cdataParts .= '<tr'.$trClass.'><td class="infoPanelLabel">'.$label.'</td><td class="infoPanelValue">#{'.$key.'}</td></tr>';
		}
		
		$selection = $this->xPath->query('registry_contributions/client_configs/component_config[@className="InfoPanel"]/infoPanelExtension');
		$contrib = $selection->item(0);
		$contrib->setAttribute("attributes", implode(",", array_keys($def)));		
		$htmlSel = $this->xPath->query('html', $contrib);
		$html = $htmlSel->item(0);
		$cdata = $this->manifestDoc->createCDATASection($cdataHead . $cdataParts . $cdataFoot);
		$html->appendChild($cdata);
		
		$selection = $this->xPath->query('registry_contributions/client_configs/template_part[@ajxpClass="SearchEngine"]');
		$tag = $selection->item(0);
		$tag->setAttribute("ajxpOptions", json_encode(array("metaColumns"=>$def)));
		
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
			$encodedMeta = array_map(array("SystemTextEncoding", "toUTF8"), self::$metaCache[$base]);
			$metadata = array_merge($metadata, $encodedMeta);
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