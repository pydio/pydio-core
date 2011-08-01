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
 * Description : Basic plugin, defined by it's manifest.xml
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class AJXP_Plugin implements Serializable{
	protected $baseDir;
	protected $id;
	protected $name;
	protected $type;
	/**
	 * XPath query
	 *
	 * @var DOMXPath
	 */
	protected $xPath;
	protected $manifestLoaded = false;
	protected $actions;
	protected $registryContributions = array();
	protected $options; // can be passed at init time
	protected $pluginConf; // can be passed at load time
	protected $dependencies;
	protected $mixins = array();
	public $loadingState = "";
	/**
	 * The manifest.xml loaded
	 *
	 * @var DOMDocument
	 */
	protected $manifestDoc;
	
	/**
	 * Internally store XML during serialization state.
	 *
	 * @var string
	 */
	private $manifestXML;
	
	private $serializableAttributes = array(
		"baseDir", 
		"id", 
		"name", 
		"type", 
		"manifestLoaded", 
		"actions", 
		"registryContributions", 
		"mixins",
		"options", "pluginConf", "dependencies", "loadingState", "manifestXML");
	
	/**
	 * Construction method
	 *
	 * @param string $id
	 * @param string $baseDir
	 */	
	public function __construct($id, $baseDir){
		$this->baseDir = $baseDir;
		$this->id = $id;
		$split = explode(".", $id);
		$this->type = $split[0];
		$this->name = $split[1];
		$this->actions = array();
		$this->dependencies = array();
	}
	public function init($options){
		$this->options = $options;
		$this->loadRegistryContributions();
	}
	/**
	 * Perform initialization checks, and throw exception if problems found.
	 * @throws Exception
	 */
	public function performChecks(){
		
	}
	protected function loadRegistryContributions(){		
		$regNodes = $this->xPath->query("registry_contributions/*");
		for($i=0;$i<$regNodes->length;$i++){
			$regNode = $regNodes->item($i);
			if($regNode->nodeType != XML_ELEMENT_NODE) continue;
			if($regNode->nodeName == "external_file"){
				$data = $this->nodeAttrToHash($regNode);
				$filename = $data["filename"] OR "";
				$include = $data["include"] OR "*";
				$exclude = $data["exclude"] OR "";			
				if(!is_file(AJXP_INSTALL_PATH."/".$filename)) continue;			
				if($include != "*") {
					$include = explode(",", $include);
				}else{
					$include = array("*");
				}
				if($exclude != "") {
					$exclude = explode(",", $exclude);			
				}else{
					$exclude = array();
				}
				$this->initXmlContributionFile($filename, $include, $exclude);
			}else{
				$this->registryContributions[]=$regNode;
				$this->parseSpecificContributions($regNode);
			}
		}
		// add manifest as a "plugins" (remove parsed contrib)
		$pluginContrib = new DOMDocument();
		$pluginContrib->loadXML("<plugins uuidAttr='name'></plugins>");
		$manifestNode = $pluginContrib->importNode($this->manifestDoc->documentElement, true);
		$pluginContrib->documentElement->appendChild($manifestNode);
		$xP=new DOMXPath($pluginContrib);
		$regNodeParent = $xP->query("registry_contributions", $manifestNode);
		if($regNodeParent->length){
			$manifestNode->removeChild($regNodeParent->item(0));
		}
		$this->registryContributions[]=$pluginContrib->documentElement;
		$this->parseSpecificContributions($pluginContrib->documentElement);
	}
	protected function initXmlContributionFile($xmlFile, $include=array("*"), $exclude=array()){
		$contribDoc = new DOMDocument();
		$contribDoc->load(AJXP_INSTALL_PATH."/".$xmlFile);
		if(!is_array($include) && !is_array($exclude)){
			$this->registryContributions[] = $contribDoc->documentElement;
			$this->parseSpecificContributions($contribDoc->documentElement);
			return;
		}
		$xPath = new DOMXPath($contribDoc);
		$excluded = array();
		foreach($exclude as $excludePath){
			$children = $xPath->query($excludePath);
			foreach($children as $child){
				$excluded[] = $child;
			}
		}
		$selected = array();
		foreach($include as $includePath){
			$incChildren = $xPath->query($includePath);
			if(!$incChildren->length) continue;
			$parentNode = $incChildren->item(0)->parentNode;
			if(!isSet($selected[$parentNode->nodeName])){
				$selected[$parentNode->nodeName]=array("parent"=>$parentNode, "nodes"=>array());
			}
			foreach($incChildren as $incChild){
				$foundEx = false;
				foreach($excluded as $exChild){
					if($this->nodesEqual($exChild, $incChild)) {
						$foundEx = true;break;
					}
				}
				if($foundEx) continue;
				$selected[$parentNode->nodeName]["nodes"][] = $incChild;
			}
			if(!count($selected[$parentNode->nodeName]["nodes"])){
				unset($selected[$parentNode->nodeName]);
			}
		}
		if(!count($selected)) return;
		foreach($selected as $parentNodeName => $data){
			$node = $data["parent"]->cloneNode(false);
			foreach($data["nodes"] as $childNode){
				$node->appendChild($childNode);
			}
			$this->registryContributions[] = $node;
			$this->parseSpecificContributions($node);			
		}		
	}
	protected function parseSpecificContributions(&$contribNode){
		//Append plugin id to callback tags
		$callbacks = $contribNode->getElementsByTagName("serverCallback");
		foreach($callbacks as $callback){
			$attr = $callback->ownerDocument->createAttribute("pluginId");
			$attr->value = $this->id;
			$callback->appendChild($attr);
		}
		if($contribNode->nodeName == "actions"){
			$actionXpath=new DOMXPath($contribNode->ownerDocument);
			foreach($contribNode->childNodes as $actionNode){
				if($actionNode->nodeType!=XML_ELEMENT_NODE) continue;
				$actionData=array();
				$actionData["XML"] = $contribNode->ownerDocument->saveXML($actionNode);			
				$names = $actionXpath->query("@name", $actionNode);
				$callbacks = $actionXpath->query("processing/serverCallback/@methodName", $actionNode);
				if($callbacks->length){
					$actionData["callback"] = $callbacks->item(0)->value;
				}
				$rightContextNodes = $actionXpath->query("rightsContext",$actionNode);
				if($rightContextNodes->length){
					$rightContext = $rightContextNodes->item(0);
					$actionData["rights"] = $this->nodeAttrToHash($rightContext);
				}
				$actionData["node"] = $actionNode;
				$names = $actionXpath->query("@name", $actionNode);
				$name = $names->item(0)->value;
				$this->actions[$name] = $actionData;
			}
		}
	}
	public function loadManifest(){
		$file = $this->baseDir."/manifest.xml";
		if(!is_file($file)) {
			return;
		}
		$this->manifestDoc = new DOMDocument();
		try{
			$this->manifestDoc->load($file);
		}catch (Exception $e){
			throw $e;
		}
		$this->xPath = new DOMXPath($this->manifestDoc);
		$this->loadMixins();
		$this->manifestLoaded = true;
		$this->loadDependencies();
	}
	
	public function serialize(){
		if($this->manifestDoc != null){
			$this->manifestXML = base64_encode($this->manifestDoc->saveXML());
		}
		$serialArray = array();
		foreach ($this->serializableAttributes as $attr){
			$serialArray[$attr] = serialize($this->$attr);
		}
		return serialize($serialArray);
	}
	
	public function unserialize($string){
		$serialArray = unserialize($string);
		foreach ($serialArray as $key => $value){
			$this->$key = unserialize($value);
		}
		if($this->manifestXML != NULL){			
			$this->manifestDoc = DOMDocument::loadXML(base64_decode($this->manifestXML));
			$this->reloadXPath();			
			unset($this->manifestXML);
		}
		//var_dump($this);
	}
	
	public function getManifestRawContent($xmlNodeName = "", $format = "string"){
		if($xmlNodeName == ""){
			if($format == "string"){
				return $this->manifestDoc->saveXML($this->manifestDoc->documentElement);
			}else{
				return $this->manifestDoc->documentElement;
			}
		}else{
			$nodes = $this->xPath->query($xmlNodeName);
			if($format == "string"){
				$buffer = "";
				foreach ($nodes as $node){
					$buffer .= $this->manifestDoc->saveXML($node);
				}
				return $buffer;
			}else{
				return $nodes;
			}
		}
	}
	public function getRegistryContributions(){
		return $this->registryContributions;
	}
	protected function loadDependencies(){
		$depPaths = "dependencies/*/@pluginName";
		$nodes = $this->xPath->query($depPaths);
		foreach ($nodes as $attr){
			$value = $attr->value;
			$this->dependencies = array_merge($this->dependencies, explode("|", $value));
		}
	}
	public function updateDependencies($pluginService){
		$append = false;
		foreach ($this->dependencies as $index => $dependency){
			if($dependency == "access.AJXP_STREAM_PROVIDER"){
				unset($this->dependencies[$index]);
				$append = true;
			}
		}
		if($append){
			$this->dependencies = array_merge($this->dependencies, $pluginService->getStreamWrapperPlugins());
		}
	}
	
	public function dependsOn($pluginName){
		return in_array($pluginName, $this->dependencies);
	}
	/**
	 * Get dependencies
	 *
	 * @param AJXP_PluginsService $pluginService
	 * @return array
	 */
	public function getActiveDependencies($pluginService){
		if(!$this->manifestLoaded) return array();
		$deps = array();
		$nodes = $this->xPath->query("dependencies/activePlugin/@pluginName");
		foreach ($nodes as $attr) {
			$value = $attr->value;
			if($value == "access.AJXP_STREAM_PROVIDER"){
				$deps = array_merge($deps, $pluginService->getStreamWrapperPlugins());
			}else{
				$deps = array_merge($deps, explode("|", $value));
			}
		}
		return $deps;
	}
	public function loadConfig($configFile, $format){
		if($format == "inc"){
			if(is_file($configFile)){
				include($configFile);
				if(isSet($DRIVER_CONF)) {
					if(isSet($this->pluginConf) && is_array($this->pluginConf)){
						$this->pluginConf = array_merge($this->pluginConf, $DRIVER_CONF);
					}else{
						$this->pluginConf = $DRIVER_CONF;
					}
				}
				if(isSet($CLIENT_EXPOSED_CONFIGS)){
					foreach ($CLIENT_EXPOSED_CONFIGS as $configName){
						if(!array_key_exists($configName, $this->pluginConf)) continue;
						$this->exposeConfigInManifest($configName, $this->pluginConf[$configName]);
					}
				}
			}
		}
	}
	public function getClassFile(){
		$files = $this->xPath->query("class_definition");
		if(!$files->length) return false;
		return $this->nodeAttrToHash($files->item(0));
	}
	public function manifestLoaded(){
		return $this->manifestLoaded;
	}
	public function getId(){
		return $this->id;
	}
	public function getName(){
		return $this->name;
	}
	public function getType(){
		return $this->type;
	}
	public function getBaseDir(){
		return $this->baseDir;
	}
	public function getDependencies(){
		return $this->dependencies;
	}
	
	public function detectStreamWrapper($register = false){
		$files = $this->xPath->query("class_stream_wrapper");
		if(!$files->length) return false;
		$streamData = $this->nodeAttrToHash($files->item(0));
		if(!is_file(AJXP_INSTALL_PATH."/".$streamData["filename"])){
			return false;
		}
		include_once(AJXP_INSTALL_PATH."/".$streamData["filename"]);
		if(!class_exists($streamData["classname"])){
			return false;
		}
		if($register){
			$pServ = AJXP_PluginsService::getInstance();
			if(!in_array($streamData["protocol"], stream_get_wrappers())){
				stream_wrapper_register($streamData["protocol"], $streamData["classname"]);
				$pServ->registerWrapperClass($streamData["protocol"], $streamData["classname"]);
			}
		}
		return $streamData;
	}
	    
	protected function exposeConfigInManifest($configName, $configValue){
		$confBranch = $this->xPath->query("plugin_configs");		
		if(!$confBranch->length){			
			$configNode = $this->manifestDoc->importNode(new DOMElement("plugin_configs", ""));
			$this->manifestDoc->documentElement->appendChild($configNode);			
		}else{
			$configNode = $confBranch->item(0);
		}
		$prop = $this->manifestDoc->createElement("property");
		$propValue = $this->manifestDoc->createCDATASection(json_encode($configValue));
		$prop->appendChild($propValue);
		$attName = $this->manifestDoc->createAttribute("name");
		$attValue = $this->manifestDoc->createTextNode($configName);
		$attName->appendChild($attValue);
		$prop->appendChild($attName);
		$configNode->appendChild($prop);
		$this->reloadXPath();
	}
	
	public function reloadXPath(){
		// Relaunch xpath
		$this->xPath = new DOMXPath($this->manifestDoc);		
	}
	
	public function hasMixin($mixinName){
		return (in_array($mixinName, $this->mixins));
	}
	
	protected function loadMixins(){
		
		$attr = $this->manifestDoc->documentElement->getAttribute("mixins");
		if($attr != ""){
			$this->mixins = explode(",", $attr);
			foreach ($this->mixins as $mixin){
				AJXP_PluginsService::getInstance()->patchPluginWithMixin($this, $this->manifestDoc, $mixin);
			}
		}
	}
	
	/**
	 * Transform a simple node and its attributes to a hash
	 *
	 * @param DOMNode $node
	 */
	protected function nodeAttrToHash($node){
		$hash = array();
		$attributes  = $node->attributes;
		if($attributes!=null){
			foreach ($attributes as $domAttr){
				$hash[$domAttr->name] = $domAttr->value;
			}
		}
		return $hash;
	}
	/**
	 * Compare two nodes at first level (nodename and attributes)
	 *
	 * @param DOMNode $node
	 */
	protected function nodesEqual($node1, $node2){
		if($node1->nodeName != $node2->nodeName) return false;
		$hash1 = $this->nodeAttrToHash($node1);
		$hash2 = $this->nodeAttrToHash($node2);
		foreach($hash1 as $name=>$value){
			if(!isSet($hash2[$name]) || $hash2[$name] != $value) return false;
		}
		return true;
	}
}
?>