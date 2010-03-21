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
class AJXP_Plugin{
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
	/**
	 * The manifest.xml loaded
	 *
	 * @var DOMDocument
	 */
	private $manifestDoc;
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
				if(!is_file(INSTALL_PATH."/".$filename)) continue;			
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
		$contribDoc->load($xmlFile);
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
		$this->manifestLoaded = true;
		$this->loadDependencies();
	}
	public function getManifestRawContent($xmlNodeName = ""){
		if($xmlNodeName == ""){
			return $this->manifestDoc->saveXML($this->manifestDoc->documentElement);
		}else{
			$buffer = "";
			$nodes = $this->xPath->query($xmlNodeName);
			foreach ($nodes as $node){
				$buffer .= $this->manifestDoc->saveXML($node);
			}
			return $buffer;
		}
	}
	public function getRegistryContributions(){
		return $this->registryContributions;
	}
	protected function loadDependencies(){
		$depPaths = "dependencies/*/@pluginName";
		$nodes = $this->xPath->query($depPaths);
		foreach ($nodes as $attr){
			$this->dependencies[] = $attr->value;
		}
	}
	public function dependsOn($pluginName){
		return in_array($pluginName, $this->dependencies);
	}
	public function getActiveDependencies(){
		if(!$this->manifestLoaded) return array();
		$deps = array();
		$nodes = $this->xPath->query("dependencies/activePlugin/@pluginName");
		foreach ($nodes as $attr) $deps[] = $attr->value;
		return $deps;
	}
	public function loadConfig($configFile, $format){
		if($format == "inc"){
			if(is_file($configFile)){
				include_once($configFile);
				if(isSet($DRIVER_CONF)) $this->pluginConf = $DRIVER_CONF;
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