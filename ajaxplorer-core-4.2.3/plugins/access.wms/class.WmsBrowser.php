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
 * AJXP_Plugin to access a WMS Server
 */
class WmsBrowser extends AbstractAccessDriver 
{
	function switchAction($action, $httpVars, $fileVars){
		if(!isSet($this->actions[$action])) return;
		parent::accessPreprocess($action, $httpVars, $fileVars);
		
		switch ($action){
			case "ls":
				$doc = DOMDocument::load($this->repository->getOption("HOST") . "?request=GetCapabilities");		
				$xPath = new DOMXPath($doc);
				$dir = $httpVars["dir"];
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="wms.1" attributeName="ajxp_label" sortType="String"/><column messageId="wms.6" attributeName="srs" sortType="String"/><column messageId="wms.4" attributeName="style" sortType="String"/><column messageId="wms.5" attributeName="keywords" sortType="String"/></columns>');
				
				$layers = $xPath->query("Capability/Layer/Layer");				
				// Detect "levels"
				$levels = array();
				$leafs = array();
				$styleLevels = $prefixLevels = false;
				foreach ($layers as $layer){
					$name = $xPath->evaluate("Name", $layer)->item(0)->nodeValue;
					$stylesList = $xPath->query("Style/Name", $layer);					
					if(strstr($name, ":")!==false){
						$exp = explode(":", $name);
						if(!isSet($levels[$exp[0]]))$levels[$exp[0]] = array();
						$levels[$exp[0]][] = $layer;
						$prefixLevels = true;
					}else if($stylesList->length > 1){
						if(!isSet($levels[$name])) $levels[$name] = array();
						foreach ($stylesList as $style){
							$levels[$name][$style->nodeValue] = $layer;
						}
						$styleLevels = true;
					}else {
						$leafs[] = $layer;
					}
				}					
				if($dir == "/" || $dir == ""){
					$this->listLevels($levels);
					$this->listLayers($leafs, $xPath);
				}else if(isSet($levels[basename($dir)])){					
					$this->listLayers($levels[basename($dir)], $xPath, ($styleLevels?array($this,"replaceStyle"):null));
				}
				AJXP_XMLWriter::close();
			break;
			
			default:
			break;
		}
	}
	
	function listLevels($levels) {
		foreach ($levels as $key => $layers ){
			AJXP_XMLWriter::renderNode("/$key", $key, false, array(
            	"icon"			=> "folder.png",
            	"openicon"		=> "openfolder.png",
            	"parentname"	=> "/",
            	"srs"			=> "-",
            	"keywords"		=> "-",
            	"style"			=> "-"
			));
		}
		
	}
	
	function replaceStyle($key, $metaData){
		if(!is_string($key)) return $metaData ;
		$metaData["name"] = $metaData["name"]."___".$key;
		$metaData["title"] = $metaData["title"]." (".$key.")";
		$metaData["style"] = $key;
		return $metaData;
	}
	
	function listLayers($nodeList, $xPath, $replaceCallback = null){
		foreach ($nodeList as  $key => $node){
			$name = $xPath->evaluate("Name", $node)->item(0)->nodeValue;
			$title =$xPath->evaluate("Title", $node)->item(0)->nodeValue;
			$srs =$xPath->evaluate("SRS", $node)->item(0)->nodeValue;
            $metaData = array(
            	"icon"			=> "wms_images/mimes/ICON_SIZE/domtreeviewer.png",
            	"parentname"	=> "/",
            	"name"			=> $name,
            	"title"			=> $title,
				"ajxp_mime" 	=> "wms_layer",
				"srs"			=> $srs,
				"wms_url"		=> $this->repository->getOption("HOST")
            );
			$style = $xPath->query("Style/Name", $node)->item(0)->nodeValue;
			$metaData["style"] = $style;
			$keys = array();
			$keywordList = $xPath->query("KeywordList/Keyword", $node);
			if($keywordList->length){
				foreach ($keywordList as $keyword){
					$keys[] = $keyword->nodeValue;							
				}
			}
			$metaData["keywords"] = implode(",",$keys);
			$metaData["queryable"] = ($node->attributes->item(0)->value == "1"?"True":"False");
			$bBoxAttributes = array();
			try{
				$bBoxAttributes = $xPath->query("LatLonBoundingBox", $node)->item(0)->attributes;
				$attributes = $xPath->query("BoundingBox", $node)->item(0)->attributes;
				if(isSet($attributes)){
					$bBoxAttributes = $attributes;
				}
			}catch(Exception $e){}
			foreach ($bBoxAttributes as $domAttr){
				$metaData["bbox_".$domAttr->name] = $domAttr->value;
			}
			
			if($replaceCallback != null){
				$metaData = call_user_func($replaceCallback, $key, $metaData);
			}
			
            AJXP_XMLWriter::renderNode("/".$metaData["name"], $title, true, $metaData);
		}		
	}
	
	
}

?>