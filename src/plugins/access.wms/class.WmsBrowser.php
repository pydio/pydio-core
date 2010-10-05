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
				$doc = DOMDocument::load($this->repository->getOption("HOST") . "?request=getCapabilities");
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendFilesListComponentConfig('<columns switchGridMode="filelist"><column messageId="wms.2" attributeName="ajxp_label" sortType="String"/><column messageId="wms.1" attributeName="name" sortType="String"/><column messageId="wms.3" attributeName="queryable" sortType="String"/><column messageId="wms.4" attributeName="style" sortType="String"/><column messageId="wms.5" attributeName="keywords" sortType="String"/></columns>');		
				$xPath = new DOMXPath($doc);
				$nodeList = $xPath->query("Capability/Layer/Layer");				
				foreach ($nodeList as $node){
					$name = $xPath->evaluate("Name", $node)->item(0)->nodeValue;
					$title =$xPath->evaluate("Title", $node)->item(0)->nodeValue;
		            $metaData = array(
		            	"icon"			=> "wms_images/mimes/ICON_SIZE/domtreeviewer.png",
		            	"parentname"	=> "/",
		            	"name"			=> $name,
		            	"title"			=> $title,
						"ajxp_mime" 	=> "layer",
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
		            AJXP_XMLWriter::renderNode("/$name", $title, true, $metaData);
				}
				AJXP_XMLWriter::close();
			break;
			
			default:
			break;
		}
	}
	
	
}

?>