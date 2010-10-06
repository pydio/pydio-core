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
 * Description : Exif extractor
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class ExifMetaManager extends AJXP_Plugin {
	
	private static $currentMetaName;
	private static $metaCache;
	
	protected $accessDriver;
	
	public function init($options){
		$this->options = $options;		
		// Do nothing
	}
	
	public function initMeta($accessDriver){
		$this->accessDriver = $accessDriver;		
		
		$messages = ConfService::getMessages();
		$def = $this->getMetaDefinition();
		$cdataHead = '<div>
						<div class="panelHeader infoPanelGroup" colspan="2">'.$messages["meta.serial.1"].'</div>
						<table class="infoPanelTable" cellspacing="0" border="0" cellpadding="0">';
		$cdataFoot = '</table></div>';
		$cdataParts = "";
		
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
		
	public function extractMeta($currentFile, &$metadata, $wrapperClassName, &$realFile){
		//$metadata["passed_file"] = $currentFile;
		if(is_dir($currentFile) || preg_match("/\.zip\//",$currentFile)) return ;
		if(!exif_imagetype($currentFile)) return ;
		if(!isset($realFile)){
			$realFile = call_user_func(array($wrapperClassName, "getRealFSReference"), $currentFile);
		}
		$exif = exif_read_data($realFile, 0, TRUE);
		if($exif === false || !isSet($exif['GPS'])) return ;
		require_once(INSTALL_PATH."/plugins/meta.exif/class.GeoConversion.php");
		$converter = new GeoConversion();
		$latDeg=$this->parseGPSValue($exif["GPS"]["GPSLatitude"][0]);
		$latMin=$this->parseGPSValue($exif["GPS"]["GPSLatitude"][1]);
		$latSec=$this->parseGPSValue($exif["GPS"]["GPSLatitude"][2]);
		$latHem=$exif["GPS"]["GPSLatitudeRef"];
		$longDeg=$this->parseGPSValue($exif["GPS"]["GPSLongitude"][0]);
		$longMin=$this->parseGPSValue($exif["GPS"]["GPSLongitude"][1]);
		$longSec=$this->parseGPSValue($exif["GPS"]["GPSLongitude"][2]);
		$longRef=$exif["GPS"]["GPSLongitudeRef"];
		$gpsData = array(
			"GPS_Latitude"=>"$latDeg deg $latMin' $latSec $latHem--".$converter->DMS2Dd($latDeg."o$latMin'$latSec"),
			"GPS_Longitude"=>"$longDeg deg $longMin' $longSec $longRef--".$converter->DMS2Dd($longDeg."o$longMin'$longSec"),
			"GPS_Altitude"=> $exif["GPS"]["GPSAltitude"][0]
		);
		$metadata = array_merge($metadata, $gpsData);
		// NOT OPTIMAL AT ALL 
		$metadata["meta_fields"] = $this->options["meta_fields"];
		$metadata["meta_labels"] = $this->options["meta_labels"];
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