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
 * Simple metadata implementation, stored in hidden files inside the
 * folders
 */
class UserMetaManager extends AJXP_Plugin {
	
	protected $accessDriver;
    /**
     * @var SerialMetaStore
     */
    protected $metaStore;

	public function init($options){
		$this->options = $options;
		// Do nothing
	}

	public function initMeta($accessDriver){
		$this->accessDriver = $accessDriver;
        $store = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if($store === false){
            throw new Exception("The 'meta.user' plugin requires at least one active 'metastore' plugin");
        }
        $this->metaStore = $store;
        $this->metaStore->accessDriver = $accessDriver;

		//$messages = ConfService::getMessages();
		$def = $this->getMetaDefinition();
        if(!isSet($this->options["meta_visibility"])) $visibilities = array("visible");
        else $visibilities = explode(",", $this->options["meta_visibility"]);
		$cdataHead = '<div>
						<div class="panelHeader infoPanelGroup" colspan="2">AJXP_MESSAGE[meta.user.1]</div>
						<table class="infoPanelTable" cellspacing="0" border="0" cellpadding="0">';
		$cdataFoot = '</table></div>';
		$cdataParts = "";
		
		$selection = $this->xPath->query('registry_contributions/client_configs/component_config[@className="FilesList"]/columns');
		$contrib = $selection->item(0);		
		$even = false;
		$searchables = array();
        $index = 0;
        $fieldType = "text";
		foreach ($def as $key=>$label){
            if(isSet($visibilities[$index])){
                $lastVisibility = $visibilities[$index];
            }
            $index ++;
			$col = $this->manifestDoc->createElement("additional_column");
			$col->setAttribute("messageString", $label);
			$col->setAttribute("attributeName", $key);
			$col->setAttribute("sortType", "String");
            $col->setAttribute("defaultVisibilty", $lastVisibility);
			if($key == "stars_rate"){
				$col->setAttribute("modifier", "MetaCellRenderer.prototype.starsRateFilter");
				$col->setAttribute("sortType", "CellSorterValue");
                $fieldType = "stars_rate";
			}else if($key == "css_label"){
				$col->setAttribute("modifier", "MetaCellRenderer.prototype.cssLabelsFilter");
				$col->setAttribute("sortType", "CellSorterValue");
                $fieldType = "css_label";
            }else if(substr($key,0,5) == "area_"){
                $searchables[$key] = $label;
                $fieldType = "textarea";
			}else{
				$searchables[$key] = $label;
                $fieldType = "text";
			}
			$contrib->appendChild($col);
			
			$trClass = ($even?" class=\"even\"":"");
			$even = !$even;
			$cdataParts .= '<tr'.$trClass.'><td class="infoPanelLabel">'.$label.'</td><td class="infoPanelValue" data-metaType="'.$fieldType.'" id="ip_'.$key.'">#{'.$key.'}</td></tr>';
		}
		
		$selection = $this->xPath->query('registry_contributions/client_configs/component_config[@className="InfoPanel"]/infoPanelExtension');
		$contrib = $selection->item(0);
		$contrib->setAttribute("attributes", implode(",", array_keys($def)));		
		if(isset($def["stars_rate"]) || isSet($def["css_label"])){
			$contrib->setAttribute("modifier", "MetaCellRenderer.prototype.infoPanelModifier");
		}
		$htmlSel = $this->xPath->query('html', $contrib);
		$html = $htmlSel->item(0);
		$cdata = $this->manifestDoc->createCDATASection($cdataHead . $cdataParts . $cdataFoot);
		$html->appendChild($cdata);
		
		$selection = $this->xPath->query('registry_contributions/client_configs/template_part[@ajxpClass="SearchEngine"]');
		$tag = $selection->item(0);
		$tag->setAttribute("ajxpOptions", json_encode((count($searchables)?array("metaColumns"=>$searchables):array())));
		
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
	
	public function editMeta($actionName, $httpVars, $fileVars){
		if(!isSet($this->actions[$actionName])) return;
		if(is_a($this->accessDriver, "demoAccessDriver")){
			throw new Exception("Write actions are disabled in demo mode!");
		}
		$repo = $this->accessDriver->repository;
		$user = AuthService::getLoggedUser();
		if(!AuthService::usersEnabled() && $user!=null && !$user->canWrite($repo->getId())){
			throw new Exception("You have no right on this action.");
		}
		$selection = new UserSelection();
		$selection->initFromHttpVars();
		$currentFile = $selection->getUniqueFile();
		$wrapperData = $this->accessDriver->detectStreamWrapper(false);
		$urlBase = $wrapperData["protocol"]."://".$this->accessDriver->repository->getId();

		
		$newValues = array();
		$def = $this->getMetaDefinition();
        $ajxpNode = new AJXP_Node($urlBase.$currentFile);
		foreach ($def as $key => $label){
			if(isSet($httpVars[$key])){
				$newValues[$key] = AJXP_Utils::decodeSecureMagic($httpVars[$key]);
			}else{
				if(!isset($original)){
                    $original = $this->metaStore->retrieveMetadata($ajxpNode, "users_meta", false, AJXP_METADATA_SCOPE_GLOBAL);
				}
				if(isSet($original) && isset($original[$key])){
					$newValues[$key] = $original[$key];
				}
			}
		}		
        $this->metaStore->setMetadata($ajxpNode, "users_meta", $newValues, false, AJXP_METADATA_SCOPE_GLOBAL);
        AJXP_Controller::applyHook("node.change", array(null, &$ajxpNode));
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::reloadDataNode("", SystemTextEncoding::toUTF8($currentFile), true);	
		AJXP_XMLWriter::close();
	}
	
	/**
	 * 
	 * @param AJXP_Node $ajxpNode
	 */
	public function extractMeta(&$ajxpNode){
		$currentFile = $ajxpNode->getUrl();

        $metadata = $this->metaStore->retrieveMetadata($ajxpNode, "users_meta", false, AJXP_METADATA_SCOPE_GLOBAL);
        if(count($metadata)){
            // @todo : Should be UTF8-IZED at output only !!??
            // array_map(array("SystemTextEncoding", "toUTF8"), $metadata);
        }
        $metadata["meta_fields"] = $this->options["meta_fields"];
        $metadata["meta_labels"] = $this->options["meta_labels"];
        $ajxpNode->mergeMetadata($metadata);
        
	}
	
	/**
	 * 
	 * @param AJXP_Node $oldFile
	 * @param AJXP_Node $newFile
	 * @param Boolean $copy
	 */
	public function updateMetaLocation($oldFile, $newFile = null, $copy = false){
		if($oldFile == null) return;
		
		$oldMeta = $this->metaStore->retrieveMetadata($oldFile, "users_meta", false, AJXP_METADATA_SCOPE_GLOBAL);
		if(!count($oldMeta)){
			return;
		}
		// If it's a move or a delete, delete old data
		if(!$copy){
            $this->metaStore->removeMetadata($oldFile, "users_meta", false, AJXP_METADATA_SCOPE_GLOBAL);
		}
		// If copy or move, copy data.
		if($newFile != null){
            $this->metaStore->setMetadata($newFile, "users_meta", $oldMeta, false, AJXP_METADATA_SCOPE_GLOBAL);
		}
	}
	
}

?>