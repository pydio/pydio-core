<?php
/**
 * @package info.ajaxplorer
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
 * Description : Simple interface to the underlaying Logger mechanism.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class AJXP_Node{
	private $url;
	private $metadata = array();
	private $wrapperClassName;
	private $urlParts = array();
	private $realFilePointer;
	
	public function __construct($url, $metadata = array()){
		$this->url = $url;
		$this->metadata = $metadata;
		$this->parseUrl();
	}
	
	public function getRealFile(){
		if(!isset($this->realFilePointer)){
			$this->realFilePointer = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $this->url, true);
			$isRemote = call_user_func(array($this->wrapperClassName, "isRemote"));
			if($isRemote){
				register_shutdown_function(array("AJXP_Utils", "silentUnlink"), $this->realFilePointer);
			}
		}
		return $this->realFilePointer;
	}
	
	public function getUrl(){
		return $this->url;
	}
	
	public function mergeMetadata($metadata){
		$this->metadata = array_merge($this->metadata, $metadata);
	}
	
	public function __get($varName){
		
		if(strtolower($varName) == "wrapperclassname") return $this->wrapperClassName;
		if(strtolower($varName) == "url") return $this->url;
		if(strtolower($varName) == "metadata") return $this->metadata;
		
		if(isSet($this->metadata[$varName])){
			return $this->metadata[$varName];
		}else{
			return null;
		}
	}
	
	public function __set($metaName, $metaValue){
		if(strtolower($metaName) == "metadata"){
			$this->metadata = $metaValue;
			return;
		}
		$this->metadata[$metaName] = $metaValue;
	}
	
	protected function parseUrl(){
		$this->urlParts = parse_url($this->url);
		if(strstr($this->urlParts["scheme"], "ajxp.")!==false){
			$pServ = AJXP_PluginsService::getInstance();
			$this->wrapperClassName = $pServ->getWrapperClassName($this->urlParts["scheme"]);
		}
	}
	
}