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
 */
/**
 * Atomic representation of a data. This the basic node of the hierarchical data.
 * Encapsulates the path and url, the nature (leaf or not) and the metadata of the node.
 */
class AJXP_Node{
    /**
     * @var string URL of the node in the form ajxp.protocol://repository_id/path/to/node
     */
	protected $_url;
    /**
     * @var array The node metadata
     */
	protected $_metadata = array();
    /**
     * @var string Associated wrapper
     */
	protected $_wrapperClassName;
    /**
     * @var array Parsed url fragments
     */
	protected $urlParts = array();
    /**
     * @var string A local representation of a real file, if possible
     */
	protected $realFilePointer;
    /**
     * @var bool Whether the core information of the node is already loaded or not
     */
    protected $nodeInfoLoaded = false;

    /**
     * @param string $url URL of the node in the form ajxp.protocol://repository_id/path/to/node
     * @param array $metadata Node metadata
     */
	public function __construct($url, $metadata = array()){
        $this->setUrl($url);
		$this->_metadata = $metadata;
	}

    /**
     * @param $url URL of the node in the form ajxp.protocol://repository_id/path/to/node
     * @return void
     */
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

    /**
     * @param $boolean Leaf or Collection?
     * @return void
     */
    public function setLeaf($boolean){
        $this->_metadata["is_file"] = $boolean;
    }

    /**
     * @return bool
     */
    public function isLeaf(){
        return isSet($this->_metadata["is_file"])?$this->_metadata["is_file"]:true;
    }

    /**
     * @param $label Main label, will set the metadata "text" key.
     * @return void
     */
    public function setLabel($label){
        $this->_metadata["text"] = $label;
    }

    /**
     * @return string Try to get the metadata "text" key, or the basename of the node path.
     */
    public function getLabel(){
        return isSet($this->_metadata["text"])? $this->_metadata["text"] : basename($this->urlParts["path"]);
    }

    /**
     * Applies the "node.info" hook, thus going through the plugins that have registered this node, and loading
     * all metadata at once.
     * @param bool $forceRefresh
     * @param bool $contextNode The parent node, if it can be useful for the hooks callbacks
     * @return
     */
    public function loadNodeInfo($forceRefresh = false, $contextNode = false){
        if($this->nodeInfoLoaded && !$forceRefresh) return;
        AJXP_Controller::applyHook("node.info", array(&$this, $contextNode));
        $this->nodeInfoLoaded = true;
    }

    /**
     * Get a real reference to the filesystem. Remote wrappers will copy the file locally.
     * This will last the time of the script and will be removed afterward.
     * @return string
     */
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

    /**
     * @return string URL of the node in the form ajxp.protocol://repository_id/path/to/node
     */
	public function getUrl(){
		return $this->_url;
	}

    /**
     * @return string The path from the root of the repository
     */
	public function getPath(){
		return $this->urlParts["path"];
	}

    /**
     * @return string The repository identifer
     */
    public function getRepositoryId(){
        return $this->urlParts["host"];
    }

    /**
     * Pass an array of metadata and merge its content with the current metadata.
     * @param array $metadata
     * @param bool $mergeValues
     * @return void
     */
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

    /**
     * Magic getter for metadata
     * @param $varName
     * @return array|null|string
     */
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

    /**
     * Magic setter for metadata
     * @param $metaName
     * @param $metaValue
     * @return
     */
	public function __set($metaName, $metaValue){
		if(strtolower($metaName) == "metadata"){
			$this->_metadata = $metaValue;
			return;
		}
		$this->_metadata[$metaName] = $metaValue;
	}

    /**
     * Safe parseUrl implementation 
     * @return void
     */
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