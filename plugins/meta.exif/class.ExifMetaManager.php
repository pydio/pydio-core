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

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * Extract Exif data from JPEG IMAGES
 */
class ExifMetaManager extends AJXP_Plugin {
	
	protected $accessDriver;
	protected $metaDefinitions;
	
	public function init($options){
		$this->options = $options;		
	}
	
	public function performChecks(){
		if(!function_exists("exif_imagetype")){
			throw new Exception("Exif PHP extension does not seem to be installed!");
		}
	}
	
	public function initMeta($accessDriver){		
		$this->accessDriver = $accessDriver;	
		if(!function_exists("exif_read_data")) return ;		
		//$messages = ConfService::getMessages();
		$def = $this->getMetaDefinition();
		if(!count($def)){
			return ;
		}
		$cdataHead = '<div>
						<div class="panelHeader infoPanelGroup" colspan="2">AJXP_MESSAGE[meta.exif.1]</div>
						<table class="infoPanelTable" cellspacing="0" border="0" cellpadding="0">';
		$cdataFoot = '</table></div>';
		$cdataParts = "";
		$even = false;
		foreach ($def as $key=>$label){
			$trClass = ($even?" class=\"even\"":"");
			$even = !$even;
			$cdataParts .= '<tr'.$trClass.'><td class="infoPanelLabel">'.$label.'</td><td class="infoPanelValue" id="ip_'.$key.'">#{'.$key.'}</td></tr>';
		}
		
		$selection = $this->xPath->query('registry_contributions/client_configs/component_config[@className="InfoPanel"]/infoPanelExtension');
		$contrib = $selection->item(0);
		$contrib->setAttribute("attributes", implode(",", array_keys($def)));		
		$contrib->setAttribute("modifier", "ExifCellRenderer.prototype.infoPanelModifier");
		$htmlSel = $this->xPath->query('html', $contrib);
		$html = $htmlSel->item(0);
		$cdata = $this->manifestDoc->createCDATASection($cdataHead . $cdataParts . $cdataFoot);
		$html->appendChild($cdata);
				
		parent::init($this->options);
	
	}
	
	protected function getMetaDefinition(){
		if(isSet($this->metaDefinitions)){
			return $this->metaDefinitions;
		}
		$fields = $this->options["meta_fields"];
		$arrF = explode(",", $fields);		
		$labels = $this->options["meta_labels"];
		$arrL = explode(",", $labels);
		$result = array();
		foreach ($arrF as $index => $value){
			$value = str_replace(".", "-", $value);
			if(isSet($arrL[$index])){
				$result[$value] = $arrL[$index];
			}else{
				$result[$value] = $value;
			}
		}
		$this->metaDefinitions = $result;		
		return $result;		
	}	
	
	public function extractExif($actionName, $httpVars, $fileVars){
		$userSelection = new UserSelection();
		$userSelection->initFromHttpVars($httpVars);
		$repo = ConfService::getRepository();
		$repo->detectStreamWrapper();
		$wrapperData = $repo->streamData;
		$urlBase = $wrapperData["protocol"]."://".$repo->getId();		
		$decoded = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
		$realFile = call_user_func(array($wrapperData["classname"], "getRealFSReference"), $urlBase.$decoded);
		AJXP_Utils::safeIniSet('exif.encode_unicode', 'UTF-8');
		$exifData = @exif_read_data($realFile, 0, TRUE);
        if($exifData === false || !is_array($exifData)) return;
		if($exifData !== false && isSet($exifData["GPS"])){
			$exifData["COMPUTED_GPS"] = $this->convertGPSData($exifData);
		}
		$excludeTags = array("componentsconfiguration", "filesource", "scenetype", "makernote");
		AJXP_XMLWriter::header("metadata", array("file" => $httpVars["file"], "type" => "EXIF"));		
		foreach ($exifData as $section => $data){
			print("<exifSection name='$section'>");			
			foreach ($data as $key => $value){
                if(is_array($value)) {
                    $value = implode(",", $value);
                }
				if(in_array(strtolower($key), $excludeTags)) continue;
				if(!is_numeric($value)) $value = $this->string_format($value);
				print("<exifTag name=\"$key\">".SystemTextEncoding::toUTF8($value)."</exifTag>");
			}
			print("</exifSection>");
		}
		AJXP_XMLWriter::close("metadata");
	}

	function string_format($str) {
		$tmpStr = "";

		for($i=0;$i<strlen($str);$i++) {
			if(ord($str[$i]) !=0) {
				$tmpStr .= $str[$i];
			}
		}
		return $tmpStr;
	}
	
	/**
	 * @param AJXP_Node $ajxpNode
	 */
	public function extractMeta(&$ajxpNode){
		$currentFile = $ajxpNode->getUrl();
		$definitions = $this->getMetaDefinition();
		if(!count($definitions)) return ;
		if(!function_exists("exif_read_data")) return ;
		if(is_dir($currentFile) || preg_match("/\.zip\//",$currentFile)) return ;
		if(!preg_match("/\.jpg$|\.jpeg$|\.tif$|\.tiff$/i",$currentFile)) return ;
		if(!exif_imagetype($currentFile)) return ;
		$realFile = $ajxpNode->getRealFile();
		$exif = @exif_read_data($realFile, 0, TRUE);
		if($exif === false) return ;
		$additionalMeta = array();
		foreach ($definitions as $def => $label){
			list($exifSection, $exifName) = explode("-", $def);
			if($exifSection == "COMPUTED_GPS" && !isSet($exif["COMPUTED_GPS"])){
				$exif['COMPUTED_GPS'] = $this->convertGPSData($exif);
			}
			if(isSet($exif[$exifSection]) && isSet($exif[$exifSection][$exifName])){						
				$additionalMeta[$def] = $exif[$exifSection][$exifName];
			}
		}
		$ajxpNode->mergeMetadata($additionalMeta);
	}

	private function convertGPSData($exif){
		if(!isSet($exif["GPS"])) return array();
		require_once(AJXP_INSTALL_PATH."/plugins/meta.exif/class.GeoConversion.php");
		$converter = new GeoConversion();
		$latDeg=$this->parseGPSValue($exif["GPS"]["GPSLatitude"][0]);
		$latMin=$this->parseGPSValue($exif["GPS"]["GPSLatitude"][1]);
		$latSec=$this->parseGPSValue($exif["GPS"]["GPSLatitude"][2]);
		$latHem=$exif["GPS"]["GPSLatitudeRef"];
		$longDeg=$this->parseGPSValue($exif["GPS"]["GPSLongitude"][0]);
		$longMin=$this->parseGPSValue($exif["GPS"]["GPSLongitude"][1]);
		$longSec=$this->parseGPSValue($exif["GPS"]["GPSLongitude"][2]);
		$longRef=$exif["GPS"]["GPSLongitudeRef"];
		$latSign = ($latHem == "S" ? "-":"");
		$longSign = ($longRef == "W" ? "-":"");
		$gpsData = array(
			"GPS_Latitude"=>"$latDeg deg $latMin' $latSec $latHem--".$converter->DMS2Dd($latSign.$latDeg."o$latMin'$latSec"),
			"GPS_Longitude"=>"$longDeg deg $longMin' $longSec $longRef--".$converter->DMS2Dd($longSign.$longDeg."o$longMin'$longSec"),
			"GPS_Altitude"=> $exif["GPS"]["GPSAltitude"][0]
		);
		return $gpsData;
	}
	
	private function parseGPSValue($value){
		if(strstr($value, "/") === false){
			return floatval($value);
		}else{
			$exp = explode("/", $value);
			return round(intval($exp[0])/intval($exp[1]), 4);
		}
	}
	
}

?>