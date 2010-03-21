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
 * Description : A manager for the various recycle bin actions.
 */
 class AJXP_PluginsService{
 	private static $instance;
 	private $registry = array();
 	private $tmpDependencies = array();
 	private $activePlugins = array();
 	private $xmlRegistry;
 	private $pluginFolder;
 	private $confFolder;
 	
 	public function loadPluginsRegistry($pluginFolder, $confFolder){
 		$this->pluginFolder = $pluginFolder;
 		$this->confFolder = $confFolder;
 		$handler = opendir($pluginFolder);
 		$beforeSort = array();
 		if($handler){
 			while ( ($item = readdir($handler)) !==false) {
 				if($item == "." || $item == ".." || !is_dir($pluginFolder."/".$item) || strstr($item,".")===false) continue ;
 				$plugin = new AJXP_Plugin($item, $pluginFolder."/".$item);				
				$plugin->loadManifest();
				if($plugin->manifestLoaded()){
					$beforeSort[$plugin->getId()] = $plugin;					
				}
 			}
 			closedir($handler);
 		}
 		if(count($beforeSort)){
 			$this->checkDependencies($beforeSort);
			usort($beforeSort, array($this, "sortByDependency"));				
 			foreach ($beforeSort as $plugin){
				$plugType = $plugin->getType();
				if(!isSet($this->registry[$plugType])){
					$this->registry[$plugType] = array();
				}
				$plugin = $this->instanciatePluginClass($plugin);
				if(is_file($this->confFolder."/conf.".$plugin->getId().".inc")){
					$plugin->loadConfig($this->confFolder."/conf.".$plugin->getId().".inc", "inc");
				}
				$this->registry[$plugType][$plugin->getName()] = $plugin;
 			}
 		}
 	}
 	
 	/**
 	 * Find a PHP class and instanciate it to replace the empty AJXP_Plugin
 	 *
 	 * @param AJXP_Plugin $plugin
 	 * @return AJXP_Plugin
 	 */
 	private function instanciatePluginClass($plugin){
 		$definition = $plugin->getClassFile();
 		if(!$definition) return $plugin;
 		$filename = INSTALL_PATH."/".$definition["filename"];
 		$className = $definition["classname"];
 		if(is_file($filename)){
 			require_once($filename);
 			$newPlugin = new $className($plugin->getId(), $plugin->getBaseDir());
 			$newPlugin->loadManifest();
	 		return $newPlugin;
 		}else{
 			return $plugin;
 		}
 	}
 	
 	private function checkDependencies(&$arrayToSort){
 		// First make sure that the given dependencies are present
 		foreach ($arrayToSort as $plugId => $plugObject){
 			foreach ($plugObject->getDependencies() as $requiredPlugId){
 				if(!isSet($arrayToSort[$requiredPlugId])){
 					unset($arrayToSort[$plugId]);
 					break;
 				}
 			}
 		}
 	}
 	/**
 	 * User function for sorting
 	 *
 	 * @param AJXP_Plugin $pluginA
 	 * @param AJXP_Plugin $pluginB
 	 */
 	private function sortByDependency($pluginA, $pluginB){
 		if($pluginA->dependsOn($pluginB->getId())) return 1;
 		if($pluginB->dependsOn($pluginA->getId())) return -1;
 		return 0;
 	}
 	
 	public function getPluginsByType($type){
 		if(isSet($this->registry[$type])) return $this->registry[$type];
 		else return array();
 	}
 	
 	public function getPluginById($pluginId){
 		$split = explode(".", $pluginId);
 		return $this->getPluginByTypeName($split[0], $split[1]);
 	}
 	
 	public function removePluginById($pluginId){
 		$split = explode(".", $pluginId);
 		if(isSet($this->registry[$split[0]]) && isSet($this->registry[$split[0]][$split[1]])){
 			unset($this->registry[$split[0]][$split[1]]);
 		}
 	}
 	
 	public static function setPluginActive($type, $name, $active=true){
 		self::getInstance()->setPluginActiveInst($type, $name, $active);
 	}
 	
 	public function setPluginActiveInst($type, $name, $active=true){
 		$this->activePlugins[$type.".".$name] = $active;
 		if(isSet($this->xmlRegistry)){
 			$this->buildXmlRegistry();
 		}
 	}
 	
 	public function setPluginUniqueActiveForType($type, $name){
 		$typePlugs = $this->getPluginsByType($type);
 		foreach($typePlugs as $plugName => $plugObject){
 			$this->setPluginActiveInst($type, $plugName, false);
 		}
 		$this->setPluginActiveInst($type, $name, true);
 	}
 	
 	public function getActivePlugins(){
 		return $this->activePlugins;	
 	}
 	
 	public function buildXmlRegistry(){
 		$actives = $this->getActivePlugins();
 		$reg = DOMDocument::loadXML("<ajxp_registry></ajxp_registry>");
 		foreach($actives as $activeName=>$status){
 			if($status === false) continue; 			
 			$plug = $this->getPluginById($activeName);
 			$contribs = $plug->getRegistryContributions();
 			foreach($contribs as $contrib){
 				$parent = $contrib->nodeName;
 				$nodes = $contrib->childNodes;
 				if(!$nodes->length) continue;
 				//$uuidAttr = $contrib->attributes["uuidAttr"] OR "name";
 				$uuidAttr = "name";
 				$this->mergeNodes($reg, $parent, $uuidAttr, $nodes);
	 		}
 		}
 		$this->xmlRegistry = $reg;
 	}
 	
 	public static function getXmlRegistry(){
 		$self = self::getInstance();
 		if(!isSet($self->xmlRegistry)){
 			$self->buildXmlRegistry();
 		}
 		return $self->xmlRegistry;
 	}
 	
 	protected function mergeNodes(&$original, $parentName, $uuidAttr, $childrenNodes){
 		// find or create parent
 		$parentSelection = $original->getElementsByTagName($parentName);
 		if($parentSelection->length){
 			$parentNode = $parentSelection->item(0);
 			$xPath = new DOMXPath($original);
 			foreach($childrenNodes as $child){
 				if($child->nodeType != XML_ELEMENT_NODE) continue;
 				$child = $original->importNode($child, true);
 				$query = $parentName.'/'.$child->nodeName.'[@'.$uuidAttr.' = "'.$child->getAttribute($uuidAttr).'"]';
 				$childrenSel = $xPath->query($query);
 				if($childrenSel->length){
 					$existingNode = $childrenSel->item(0);
 					$this->mergeChildByTagName($child, $existingNode);
 				}else{
 					$parentNode->appendChild($child);
 				}
 			} 			
 		}else{
 			//create parentNode and append children
 			if($childrenNodes->length){
 				$parentNode = $original->importNode($childrenNodes->item(0)->parentNode, true);
 				$original->documentElement->appendChild($parentNode);
 			}else{
	 			$parentNode = $original->createElement($parentName);
	 			$original->documentElement->appendChild($parentNode);
 			}
 		}
 	}
 	
 	protected function mergeChildByTagName(&$new, &$old){
 		if(!$this->hasElementChild($new) || !$this->hasElementChild($old)){
 			$old->parentNode->replaceChild($new, $old);
 			return;
 		}
 		foreach($new->childNodes as $newChild){
 			if($newChild->nodeType != XML_ELEMENT_NODE) continue;
 			$found = null;
 			foreach($old->childNodes as $oldChild){
 				if($oldChild->nodeType != XML_ELEMENT_NODE) continue;
 				if($oldChild->nodeName == $newChild->nodeName){
 					$found = $oldChild;
 				}
 			}
 			if($found != null){
 				$this->mergeChildByTagName($newChild, $found);
 			}else{
 				$import = $old->ownerDocument->importNode($newChild);
 				$old->appendChild($import);
 			}
 		}
 	}
 	
 	private function hasElementChild($node){
 		if(!$node->hasChildNodes()) return false;
 		foreach($node->childNodes as $child){
 			if($child->nodeType == XML_ELEMENT_NODE) return true;
 		}
 		return false;
 	}
 	
 	public function getPluginByTypeName($plugType, $plugName){
 		if(isSet($this->registry[$plugType]) && isSet($this->registry[$plugType][$plugName])){
 			return $this->registry[$plugType][$plugName];
 		}else{
 			return false;
 		} 		
 	}
 	
 	public static function findPlugin($type, $name){
 		$instance = self::getInstance();
 		return $instance->getPluginByTypeName($type, $name);
 	}
 	
 	public static function findPluginById($id){
 		return self::getInstance()->getPluginById($id);
 	}
 	
 	private function __construct(){ 		
 	}
 	/**
 	 * Singleton method
 	 *
 	 * @return AJXP_PluginsService the service instance
 	 */
 	public static function getInstance()
 	{
 		if(!isSet(self::$instance)){
 			$c = __CLASS__;
 			self::$instance = new $c;
 		}
 		return self::$instance;
 	}
    public function __clone()
    {
        trigger_error("Cannot clone me, i'm a singleton!", E_USER_ERROR);
    } 	
 }
 ?>