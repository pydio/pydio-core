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
	protected $_url;
	protected $_metadata = array();
	protected $_wrapperClassName;
	protected $urlParts = array();
	protected $realFilePointer;
	
	public function __construct($url, $metadata = array()){
		$this->_url = $url;
        // Clean url
        $testExp = explode("//", $url);
        if(count($testExp) > 1){
            $this->_url = array_shift($testExp)."//";
            $this->_url .= implode("/", $testExp);
        }

		$this->_metadata = $metadata;
		$this->parseUrl();
	}
	
	public function getRealFile(){
		if(!isset($this->realFilePointer)){
			$this->realFilePointer = call_user_func(array($this->_wrapperClassName, "getRealFSReference"), $this->_url, true);
			$isRemote = call_user_func(array($this->_wrapperClassName, "isRemote"));
			if($isRemote){
				register_shutdown_function(array("AJXP_Utils", "silentUnlink"), $this->realFilePointer);
			}
		}
		return $this->realFilePointer;
	}
	
	public function getUrl(){
		return $this->_url;
	}
	
	public function getPath(){
		return $this->urlParts["path"];
	}

	public function mergeMetadata($metadata){
		$this->_metadata = array_merge($this->_metadata, $metadata);
	}
	
	public function __get($varName){
		
		if(strtolower($varName) == "wrapperclassname") return $this->_wrapperClassName;
		if(strtolower($varName) == "url") return $this->_url;
		if(strtolower($varName) == "metadata") return $this->_metadata;
		
		if(isSet($this->_metadata[$varName])){
			return $this->_metadata[$varName];
		}else{
			return null;
		}
	}
	
	public function __set($metaName, $metaValue){
		if(strtolower($metaName) == "metadata"){
			$this->_metadata = $metaValue;
			return;
		}
		$this->_metadata[$metaName] = $metaValue;
	}
	
	protected function parseUrl(){
		$this->urlParts = parse_url($this->_url);
		if(strstr($this->urlParts["scheme"], "ajxp.")!==false){
			$pServ = AJXP_PluginsService::getInstance();
			$this->_wrapperClassName = $pServ->getWrapperClassName($this->urlParts["scheme"]);
		}
	}
	
}