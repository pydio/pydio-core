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
 * @package info.ajaxplorer.core
 * @class AJXP_Node
 * Atomic representation of a data.
 */
class AJXP_Node{
	protected $_url;
	protected $_metadata = array();
	protected $_wrapperClassName;
	protected $urlParts = array();
	protected $realFilePointer;

    protected $nodeInfoLoaded = false;
	
	public function __construct($url, $metadata = array()){
        $this->setUrl($url);
		$this->_metadata = $metadata;
	}

    public function setUrl($url){
        $this->_url = $url;
        // Clean url
        $testExp = explode("//", $url);
        if(count($testExp) > 1){
            $this->_url = array_shift($testExp)."//";
            $this->_url .= implode("/", $testExp);
        }
        $this->parseUrl();
    }

    public function setLeaf($boolean){
        $this->_metadata["is_file"] = $boolean;
    }

    public function isLeaf(){
        return isSet($this->_metadata["is_file"])?$this->_metadata["is_file"]:true;
    }

    public function setLabel($label){
        $this->_metadata["text"] = $label;
    }

    public function getLabel(){
        return isSet($this->_metadata["text"])? $this->_metadata["text"] : basename($this->urlParts["path"]);
    }

    public function loadNodeInfo($forceRefresh = false, $contextNode = false){
        if($this->nodeInfoLoaded && !$forceRefresh) return;
        AJXP_Controller::applyHook("node.info", array(&$this, $contextNode));
        $this->nodeInfoLoaded = true;
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

	public function mergeMetadata($metadata, $mergeValues = false){
        if($mergeValues){
            foreach($metadata as $key => $value){
                if(isSet($this->_metadata[$key])){
                    $existingValue = explode(",", $this->_metadata[$key]);
                    if(!in_array($value, $existingValue)){
                        array_push($existingValue, $value);
                        $this->_metadata[$key] = implode(",", $existingValue);
                    }
                }else{
                    $this->_metadata[$key] = $value;
                }
            }
        }else{
            $this->_metadata = array_merge($this->_metadata, $metadata);
        }
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
        if(strstr($this->_url, "#") !== false){
            $url = str_replace("#", "__HASH__", $this->_url);
            $this->urlParts = parse_url($url);
            foreach($this->urlParts as $partKey => $partValue){
                $this->urlParts[$partKey] = str_replace("__HASH__", "#", $partValue);
            }
        }else{
            $this->urlParts = parse_url($this->_url);
        }

		if(strstr($this->urlParts["scheme"], "ajxp.")!==false){
			$pServ = AJXP_PluginsService::getInstance();
			$this->_wrapperClassName = $pServ->getWrapperClassName($this->urlParts["scheme"]);
		}
	}
	
}