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
 * Description : Still experimental, browse a local SVN repository.
 */
require_once("svn_lib.inc.php");

class svnAccessDriver extends AbstractAccessDriver {
	
	var $listingParser;
	var $xmlOutput = "";
	var $cdataBuffer = "";
	var $crtListingMode = "";
	var $listElements = array();
	var $crtElement = array();
	var $crtPath;
	
	function svnAccessDriver($driverName, $filePath, $repository, $optOptions = NULL){
		parent::AbstractAccessDriver($driverName, $filePath, $repository);
	}
	
	function switchAction($action, $httpVars, $fileVars){
		
		if($action == "ls"){
			$this->crtPath = "";
			if(isSet($httpVars["dir"]) && $httpVars["dir"] != ""){
				$this->crtPath = $httpVars["dir"];
			}			
			$this->crtListingMode = (isSet($httpVars["mode"])?$httpVars["mode"]:"complete");
			$res = ListRepository($this->repository, $this->crtPath);
			//$res[IDX_STDOUT] = "";
			//header("content-type:text/xml");
			//print_r($res[IDX_STDOUT]);
			$this->parseListing($res[IDX_STDOUT]);
			//print_r($this->listElements);
			//exit();
			AJXP_XMLWriter::header();
			// FIRST PASS FOR FOLDERS;
			foreach ($this->listElements as $element){
				if($element["is_file"] == "1") continue;
				print("<tree ");
				foreach ($element as $attName => $attValue){					
					print "$attName=\"".$attValue."\" ";
				}
				print("/>");
			}
			if($this->crtListingMode != "complete"){
				foreach ($this->listElements as $element){
					if($element["is_file"] == "0") continue;
					print("<tree ");
					foreach ($element as $attName => $attValue){					
						print "$attName=\"".$attValue."\" ";
					}
					print("/>");
				}
			}
			AJXP_XMLWriter::close();
			exit(1);
		}else if($action == "log" && isSet($httpVars["file"])){
			
			$res = GetWebspaceLog($this->repository, $httpVars["file"]);
			//print_r($res);
			AJXP_XMLWriter::header();
			if(is_array($res)) print(ereg_replace("[\r\n]","",str_replace('<?xml version="1.0"?>', "", $res[IDX_STDOUT])));
			AJXP_XMLWriter::close();
			exit(1);
		}
	}
	
	function parseListing($xmlContent){
		$this->listElements = array();
	    $this->listingParser = xml_parser_create( "UTF-8" );
	
	    //xml_parser_set_option( $this->xml_parser, XML_OPTION_CASE_FOLDING, false );
	    xml_set_object( $this->listingParser, $this );
	    xml_set_element_handler( $this->listingParser, "listingStartElement", "listingEndElement");
	    xml_set_character_data_handler( $this->listingParser, "listingCData" );
	    xml_parse( $this->listingParser, $xmlContent, true );
	    xml_parser_free( $this->listingParser);
		return $this->listElements;
	}
	
	function listingStartElement($parser, $tag, $attributeList){
		if($tag == "ENTRY"){
			$this->crtElement = array();
			if(isSet($attributeList["KIND"]) && $attributeList["KIND"] == "file"){
				$this->crtElement["is_file"] = true;
			}
			else{
				$this->crtElement["is_file"] = false;
			}
		}else if($tag == "NAME" || $tag == "AUTHOR" || $tag == "DATE" || $tag == "SIZE"){
			$this->cdataBuffer = "";
		}else if($tag == "COMMIT" && $this->crtListingMode == "file_list"){
			$this->crtElement["revision"] = $attributeList["REVISION"];
		}
	}
	
	function listingEndElement($parser, $tag){
		if($tag == "ENTRY"){
			if($this->crtListingMode == "file_list" || $this->crtListingMode == "search"){
				if(!$this->crtElement["is_file"]){
					$this->crtElement["filesize"] = "-";
				}
				$this->crtElement["mimestring"] = Utils::mimetype($this->crtElement["filename"],"text",!$this->crtElement["is_file"]);
				$this->crtElement["icon"] = Utils::mimetype($this->crtElement["filename"], "image",!$this->crtElement["is_file"]);
				$this->crtElement["is_file"] = $this->crtElement["is_file"]?"1":"0";
				$this->listElements[] = $this->crtElement;
			}else {
				if(!$this->crtElement["is_file"]){
					$this->crtElement["icon"] = CLIENT_RESOURCES_FOLDER."/images/foldericon.png";
					$this->crtElement["openicon"] = CLIENT_RESOURCES_FOLDER."/images/openfoldericon.png";
					$this->crtElement["src"] = SERVER_ACCESS."?dir=".$this->crtPath."/".$this->crtElement["text"];
					$this->crtElement["parentname"] = ($this->crtPath == "/"?"":$this->crtPath);
					$this->crtElement["action"] = "javascript:ajaxplorer.getFoldersTree().clickNode(CURRENT_ID)";
					$this->crtElement["is_file"] = $this->crtElement["is_file"]?"1":"0";
					$this->listElements[] = $this->crtElement;
				}
			}			
		}else if($tag == "NAME"){
			$this->crtElement["filename"] = $this->cdataBuffer;
			$this->crtElement["text"] = basename($this->cdataBuffer);
		}else if($tag == "AUTHOR" && $this->crtListingMode == "file_list"){
			$this->crtElement["author"] = $this->cdataBuffer;
		}else if($tag == "SIZE" && $this->crtListingMode == "file_list"){
			$this->crtElement["filesize"] = Utils::roundSize(intval($this->cdataBuffer));
		}else if($tag == "DATE" && $this->crtListingMode == "file_list"){
			$date = $this->cdataBuffer;
			$split = split("T",$date);
			$realDate = $split[0];
			$split = split("\.", $split[1]);
			$realTime = $split[0];
			$this->crtElement["modiftime"] = date("d/m/Y H:i", strtotime($realDate." ".$realTime));
		}
	}
	
	function listingCData($parser, $cData){
		$this->cdataBuffer .= $cData;
	}
	
}

?>
