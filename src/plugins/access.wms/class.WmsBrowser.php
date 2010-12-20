<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
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
 * Description : This driver will access a WMS Browser, ask his capabilities and display the layers
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

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