<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Core parser for loading / serving plugins
 * @package Pydio
 * @subpackage Core
 */
class AJXP_PluginsService
{
    private static $instance;
    private $registry = array();
    private $required_files = array();
    private $activePlugins = array();
    private $streamWrapperPlugins = array();
    private $registeredWrappers = array();
    private $xmlRegistry;
    private $registryVersion;
    private $tmpDeferRegistryBuild = false;
    /**
     * @var AbstractConfDriver
     */
    private $confStorage;
    private $mixinsDoc;
    private $mixinsXPath;


    /**
     * @return bool
     */
    private function loadRegistryFromCache(){

        if((!defined("AJXP_SKIP_CACHE") || AJXP_SKIP_CACHE === false)){
            $reqs = AJXP_Utils::loadSerialFile(AJXP_PLUGINS_REQUIRES_FILE);
            if (count($reqs)) {
                foreach ($reqs as $filename) {
                    if (!is_file($filename)) {
                        // Cache is out of sync
                        return false;
                    }
                    require_once($filename);
                }
                $res = AJXP_Utils::loadSerialFile(AJXP_PLUGINS_CACHE_FILE);
                $this->registry = $res;
                // Refresh streamWrapperPlugins
                foreach ($this->registry as $plugs) {
                    foreach ($plugs as $plugin) {
                        if (method_exists($plugin, "detectStreamWrapper") && $plugin->detectStreamWrapper(false) !== false) {
                            $this->streamWrapperPlugins[] = $plugin->getId();
                        }
                    }
                }
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }

    }

    /**
     * Loads the full registry, from the cache or not
     * @param String $pluginFolder
     * @param AbstractConfDriver $confStorage
     * @param bool $rewriteCache Force a cache rewriting
     */
    public function loadPluginsRegistry($pluginFolder, $confStorage, $rewriteCache = false)
    {
        if(!$rewriteCache){
            if(!empty($this->registry)){
                return;
            }
            if($this->loadRegistryFromCache()){
                return;
            }
        }
        if (is_string($pluginFolder)) {
            $pluginFolder = array($pluginFolder);
        }
        $this->confStorage = $confStorage;
        $pluginsPool = array();

        foreach ($pluginFolder as $sourceFolder) {
            $handler = @opendir($sourceFolder);
            if ($handler) {
                while ( ($item = readdir($handler)) !==false) {
                    if($item == "." || $item == ".." || !@is_dir($sourceFolder."/".$item) || strstr($item,".")===false) continue ;
                    $plugin = new AJXP_Plugin($item, $sourceFolder."/".$item);
                    $plugin->loadManifest();
                    if ($plugin->manifestLoaded()) {
                        $pluginsPool[$plugin->getId()] = $plugin;
                        if (method_exists($plugin, "detectStreamWrapper") && $plugin->detectStreamWrapper(false) !== false) {
                            $this->streamWrapperPlugins[] = $plugin->getId();
                        }
                    }
                }
                closedir($handler);
            }
        }
        if (count($pluginsPool)) {
            $this->checkDependencies($pluginsPool);
            foreach ($pluginsPool as $plugin) {
                $this->recursiveLoadPlugin($plugin, $pluginsPool);
            }
        }
        if (!defined("AJXP_SKIP_CACHE") || AJXP_SKIP_CACHE === false) {
            AJXP_Utils::saveSerialFile(AJXP_PLUGINS_REQUIRES_FILE, $this->required_files, false, false);
            AJXP_Utils::saveSerialFile(AJXP_PLUGINS_CACHE_FILE, $this->registry, false, false);
            if (is_file(AJXP_PLUGINS_QUERIES_CACHE)) {
                @unlink(AJXP_PLUGINS_QUERIES_CACHE);
            }
        }
    }

    /**
     * Load plugin class with dependencies first
     *
     * @param AJXP_Plugin $plugin
     * @param array $pluginsPool
     */
    private function recursiveLoadPlugin($plugin, $pluginsPool)
    {
        if ($plugin->loadingState!="") {
            return ;
        }
        $dependencies = $plugin->getDependencies();
        $plugin->loadingState = "lock";
        foreach ($dependencies as $dependencyId) {
            if (isSet($pluginsPool[$dependencyId])) {
                $this->recursiveLoadPlugin($pluginsPool[$dependencyId], $pluginsPool);
            } else if (strpos($dependencyId, "+") !== false) {
                foreach (array_keys($pluginsPool) as $pId) {
                    if (strpos($pId, str_replace("+", "", $dependencyId)) === 0) {
                        $this->recursiveLoadPlugin($pluginsPool[$pId], $pluginsPool);
                    }
                }
            }
        }
        $plugType = $plugin->getType();
        if (!isSet($this->registry[$plugType])) {
            $this->registry[$plugType] = array();
        }
        $plugin = $this->instanciatePluginClass($plugin);
        $options = $this->confStorage->loadPluginConfig($plugType, $plugin->getName());
        $plugin->loadConfigs($options);
        $this->registry[$plugType][$plugin->getName()] = $plugin;
        $plugin->loadingState = "loaded";
    }

    public function loadFromPluginQueriesCache($key)
    {
        if(AJXP_SKIP_CACHE) return null;
        $test = AJXP_Utils::loadSerialFile(AJXP_PLUGINS_QUERIES_CACHE);
        if (!empty($test) && is_array($test) && isset($test[$key])) {
            return $test[$key];
        }
        return null;
    }

    public function storeToPluginQueriesCache($key, $value)
    {
        if(AJXP_SKIP_CACHE) return;
        $test = AJXP_Utils::loadSerialFile(AJXP_PLUGINS_QUERIES_CACHE);
        if(!is_array($test)) $test = array();
        $test[$key] = $value;
        AJXP_Utils::saveSerialFile(AJXP_PLUGINS_QUERIES_CACHE, $test);
    }

    public static function clearPluginsCache(){
        @unlink(AJXP_PLUGINS_CACHE_FILE);
        @unlink(AJXP_PLUGINS_REQUIRES_FILE);
        @unlink(AJXP_PLUGINS_QUERIES_CACHE);
    }

    /**
     * Simply load a plugin class, without the whole dependencies et.all
     * @param string $pluginId
     * @param array $pluginOptions
     * @return AJXP_Plugin
     */
    public function softLoad($pluginId, $pluginOptions)
    {
        if(empty($this->registry)){
            $this->loadRegistryFromCache();
        }
        // Try to get from cache
        list($type, $name) = explode(".", $pluginId);
        if(!empty($this->registry) && isSet($this->registry[$type][$name])) {
            $plugin = $this->registry[$type][$name];
            $plugin->init($pluginOptions);
            return clone $plugin;
        }


        $plugin = new AJXP_Plugin($pluginId, AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/".$pluginId);
        $plugin->loadManifest();
        $plugin = $this->instanciatePluginClass($plugin);
        $plugin->loadConfigs(array()); // Load default
        $plugin->init($pluginOptions);
        return $plugin;
    }

    /**
     * Find a PHP class and instanciate it to replace the empty AJXP_Plugin
     *
     * @param AJXP_Plugin $plugin
     * @return AJXP_Plugin
     */
    private function instanciatePluginClass($plugin)
    {
        $definition = $plugin->getClassFile();
        if(!$definition) return $plugin;
        $filename = AJXP_INSTALL_PATH."/".$definition["filename"];
        $className = $definition["classname"];
        if (is_file($filename)) {
            require_once($filename);
            $newPlugin = new $className($plugin->getId(), $plugin->getBaseDir());
            $newPlugin->loadManifest();
            $this->required_files[] = $filename;
            return $newPlugin;
        } else {
            return $plugin;
        }
    }
    /**
     * Check that a plugin dependencies are loaded, disable it otherwise.
     * @param $arrayToSort
     * @return
     */
    private function checkDependencies(&$arrayToSort)
    {
        // First make sure that the given dependencies are present
        foreach ($arrayToSort as $plugId => $plugObject) {
            $plugObject->updateDependencies($this);
            if($plugObject->hasMissingExtensions()){
                unset($arrayToSort[$plugId]);
                continue;
            }
            $dependencies = $plugObject->getDependencies();
            if(!count($dependencies)) continue;// return ;
            $found = false;
            foreach ($dependencies as $requiredPlugId) {
                if ( strpos($requiredPlugId, "+") !== false || isSet($arrayToSort[$requiredPlugId])) {
                    $found = true; break;
                }
            }
            if (!$found) {
                unset($arrayToSort[$plugId]);
            }
        }
    }

    /**
     * User function for sorting
     *
     * @param $pluginIdA
     * @param $pluginIdB
     * @internal param String $pluginA
     * @internal param String $pluginB
     * @return integer
     */
    public static function sortByDependencyIds($pluginIdA, $pluginIdB)
    {
        if($pluginIdA == $pluginIdB) return 0;
        $pluginA = self::findPluginById($pluginIdA);
        $pluginB = self::findPluginById($pluginIdB);
        if($pluginA == null || $pluginB == null) return 0;
        if ($pluginA->dependsOn($pluginIdB)) {
            return 1;
        }
        if ($pluginB->dependsOn($pluginIdA)) {
            return -1;
        }
        return 0;
    }

    public function getOrderByDependency($plugins, $withStatus = true)
    {
        $orders = array();
        $keys = array();
        $unkowns = array();
        if ($withStatus) {
            foreach ($plugins as $pid => $status) {
                if($status) $keys[] = $pid;
            }
        } else {
            $keys = array_keys($plugins);
        }
        $result = array();
        while (count($keys) > 0) {
            $test = array_shift($keys);
            $testObject = $this->getPluginById($test);
            $deps = $testObject->getActiveDependencies(self::getInstance());
            if (!count($deps)) {
                $result[] = $test;
                continue;
            }
            $found = false;
            $inOriginalPlugins = false;
            foreach ($deps as $depId) {
                if (in_array($depId, $result)) {
                    $found = true;
                    break;
                }
                if (!$inOriginalPlugins && array_key_exists($depId, $plugins) && (!$withStatus || $plugins[$depId] == true)) {
                    $inOriginalPlugins = true;
                }
            }
            if ($found) {
                $result[] = $test;
            } else {
                if($inOriginalPlugins) $keys[] = $test;
                else {
                    unset($plugins[$test]);
                    $unkowns[] = $test;
                }
            }
        }
        return array_merge($result, $unkowns);
    }




    /**
     * All the plugins of a given type
     * @param string $type
     * @return AJXP_Plugin[]
     */
    public function getPluginsByType($type)
    {
        if(isSet($this->registry[$type])) return $this->registry[$type];
        else return array();
    }

    /**
     * Get a plugin instance
     *
     * @param string $pluginId
     * @return AJXP_Plugin
     */
    public function getPluginById($pluginId)
    {
        $split = explode(".", $pluginId);
        return $this->getPluginByTypeName($split[0], $split[1]);
    }

    /**
     * Remove a plugin
     * @param string $pluginId
     * @return void
     */
    public function removePluginById($pluginId)
    {
        $split = explode(".", $pluginId);
        if (isSet($this->registry[$split[0]]) && isSet($this->registry[$split[0]][$split[1]])) {
            unset($this->registry[$split[0]][$split[1]]);
        }
    }

    /**
     * Load the plugins list, and set active the plugins automatically,
     * except for the specific types that declare a "core.*" plugin. In that case,
     * either the core class has an AUTO_LOAD_TYPE property and all plugins are activated,
     * or it's the task of the core class to load the necessary plugin(s) of this type.
     * @return void
     */
    public function initActivePlugins()
    {
        $detected = $this->getDetectedPlugins();
        $toActivate = array();
        foreach ($detected as $pType => $pObjects) {
            $coreP = $this->findPlugin("core", $pType);
            if($coreP !== false && !isSet($coreP->AUTO_LOAD_TYPE)) continue;
            foreach ($pObjects as $pName => $pObject) {
                $toActivate[$pObject->getId()] = $pObject ;
            }
        }
        $o = $this->getOrderByDependency($toActivate, false);
        foreach ($o as $id) {
            $pObject = $toActivate[$id];
            $pObject->init(array());
            try {
                $pObject->performChecks();
                if(!$pObject->isEnabled() || $pObject->hasMissingExtensions()) continue;
                $this->setPluginActiveInst($pObject->getType(), $pObject->getName(), true);
            } catch (Exception $e) {
                //$this->errors[$pName] = "[$pName] ".$e->getMessage();
            }

        }
    }

    /**
     * Add a plugin to the list of active plugins
     * @static
     * @param string $type
     * @param string $name
     * @param bool $active
     * @param AJXP_Plugin $updateInstance
     * @return void
     */
    public static function setPluginActive($type, $name, $active=true, $updateInstance = null)
    {
        self::getInstance()->setPluginActiveInst($type, $name, $active, $updateInstance);
    }
    /**
     * Instance implementation of the setPluginActive
     * @param $type
     * @param $name
     * @param bool $active
     * @param AJXP_Plugin $updateInstance
     * @return void
     */
    public function setPluginActiveInst($type, $name, $active=true, $updateInstance = null)
    {
        if ($active) {
            // Check active plugin dependencies
            $plug = $this->getPluginById($type.".".$name);
            if(!$plug || !$plug->isEnabled()) return;
            $deps = $plug->getActiveDependencies($this);
            if (count($deps)) {
                $found = false;
                foreach ($deps as $dep) {
                    if (isSet($this->activePlugins[$dep]) && $this->activePlugins[$dep] !== false) {
                        $found = true; break;
                    }
                }
                if (!$found) {
                    $this->activePlugins[$type.".".$name] = false;
                    return ;
                }
            }
        }
        if(isSet($this->activePlugins[$type.".".$name])){
            unset($this->activePlugins[$type.".".$name]);
        }
        $this->activePlugins[$type.".".$name] = $active;
        if (isSet($updateInstance) && isSet($this->registry[$type][$name])) {
            $this->registry[$type][$name] = $updateInstance;
        }
        if (isSet($this->xmlRegistry) && !$this->tmpDeferRegistryBuild) {
            $this->buildXmlRegistry(($this->registryVersion == "extended"));
        }
    }

    public static function deferBuildingRegistry(){
        self::getInstance()->tmpDeferRegistryBuild = true;
    }

    public static function flushDeferredRegistryBuilding(){
        $t = self::getInstance();
        $t->tmpDeferRegistryBuild = false;
        if (isSet($t->xmlRegistry)) {
            $t->buildXmlRegistry(($t->registryVersion == "extended"));
        }
    }

    /**
     * Some type require only one active plugin at a time
     * @param $type
     * @param $name
     * @param AJXP_Plugin $updateInstance
     * @return void
     */
    public function setPluginUniqueActiveForType($type, $name, $updateInstance = null)
    {
        $typePlugs = $this->getPluginsByType($type);
        $originalValue = $this->tmpDeferRegistryBuild;
        $this->tmpDeferRegistryBuild = true;
        foreach ($typePlugs as $plugName => $plugObject) {
            $this->setPluginActiveInst($type, $plugName, false);
        }
        $this->tmpDeferRegistryBuild = $originalValue;
        $this->setPluginActiveInst($type, $name, true, $updateInstance);
    }
    /**
     * Retrieve the whole active plugins list
     * @return array
     */
    public function getActivePlugins()
    {
        return $this->activePlugins;
    }
    /**
     * Retrieve an array of active plugins for type
     * @param string $type
     * @param bool $unique
     * @return AJXP_Plugin[]
     */
    public function getActivePluginsForType($type, $unique = false)
    {
        $acts = array();
        foreach ($this->activePlugins as $plugId => $active) {
            if(!$active) continue;
            list($pT,$pN) = explode(".", $plugId);
            if ($pT == $type && isset($this->registry[$pT][$pN])) {
                if ($unique) {
                    return $this->registry[$pT][$pN];
                    break;
                }
                $acts[$pN] = $this->registry[$pT][$pN];
            }
        }
        if($unique && !count($acts)) return false;
        return $acts;
    }

    /**
     * Return only one of getActivePluginsForType
     * @param $type
     * @return array|bool
     */
    public function getUniqueActivePluginForType($type)
    {
        return $this->getActivePluginsForType($type, true);
    }
    /**
     * All the plugins registry, active or not
     * @return array
     */
    public function getDetectedPlugins()
    {
        return $this->registry;
    }
    /**
     * All the plugins that declare a stream wrapper
     * @return array
     */
    public function getStreamWrapperPlugins()
    {
        return $this->streamWrapperPlugins;
    }
    /**
     * Add the $protocol/$wrapper to an internal cache
     * @param string $protocol
     * @param string $wrapperClassName
     * @return void
     */
    public function registerWrapperClass($protocol, $wrapperClassName)
    {
        $this->registeredWrappers[$protocol] = $wrapperClassName;
    }

    /**
     * Find a classname for a given protocol
     * @param $protocol
     * @return
     */
    public function getWrapperClassName($protocol)
    {
        return $this->registeredWrappers[$protocol];
    }
    /**
     * The protocol/classnames table
     * @return array
     */
    public function getRegisteredWrappers()
    {
        return $this->registeredWrappers;
    }
    /**
     * Go through all plugins and call their getRegistryContributions() method.
     * Add all these contributions to the main XML ajxp_registry document.
     * @param bool $extendedVersion Will be passed to the plugin, for optimization purpose.
     * @return void
     */
    public function buildXmlRegistry($extendedVersion = true)
    {
        $actives = $this->getActivePlugins();
        $reg = new DOMDocument();
        $reg->loadXML("<ajxp_registry></ajxp_registry>");
        foreach ($actives as $activeName=>$status) {
            if($status === false) continue;
            $plug = $this->getPluginById($activeName);
            $contribs = $plug->getRegistryContributions($extendedVersion);
            foreach ($contribs as $contrib) {
                $parent = $contrib->nodeName;
                $nodes = $contrib->childNodes;
                if(!$nodes->length) continue;
                $uuidAttr = $contrib->getAttribute("uuidAttr");
                if($uuidAttr == "") $uuidAttr = "name";
                $this->mergeNodes($reg, $parent, $uuidAttr, $nodes);
            }
        }
        $this->xmlRegistry = $reg;
    }

    /**
     * Build the XML Registry if not already built, and return it.
     * @static
     * @param bool $extendedVersion
     * @return DOMDocument The registry
     */
    public static function getXmlRegistry($extendedVersion = true)
    {
        $self = self::getInstance();
        if (!isSet($self->xmlRegistry) || ($self->registryVersion == "light" && $extendedVersion)) {
            $self->buildXmlRegistry( $extendedVersion );
            $self->registryVersion = ($extendedVersion ? "extended":"light");
        }
        return $self->xmlRegistry;
    }
    /**
     * Replace the current xml registry
     * @static
     * @param $registry
     * @return void
     */
    public static function updateXmlRegistry($registry)
    {
        $self = self::getInstance();
        $self->xmlRegistry = $registry;
    }

    /**
     * Append some predefined XML to a plugin instance
     * @param AJXP_Plugin $plugin
     * @param DOMDocument $manifestDoc
     * @param String $mixinName
     */
    public function patchPluginWithMixin(&$plugin, &$manifestDoc, $mixinName)
    {
        // Load behaviours if not already
        if (!isSet($this->mixinsDoc)) {
            $this->mixinsDoc = new DOMDocument();
            $this->mixinsDoc->load(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/core.ajaxplorer/ajxp_mixins.xml");
            $this->mixinsXPath = new DOMXPath($this->mixinsDoc);
        }
        // Merge into manifestDoc
        $nodeList = $this->mixinsXPath->query($mixinName);
        if(!$nodeList->length) return;
        $mixinNode = $nodeList->item(0);
        foreach ($mixinNode->childNodes as $child) {
            if($child->nodeType != XML_ELEMENT_NODE) continue;
            $uuidAttr = $child->getAttribute("uuidAttr") OR "name";
            $this->mergeNodes($manifestDoc, $child->nodeName, $uuidAttr, $child->childNodes, true);
        }

        // Reload plugin XPath
        $plugin->reloadXPath();
    }

    /**
     * Search all plugins manifest with an XPath query, and return either the Nodes, or directly an XML string.
     * @param string $query
     * @param string $stringOrNodeFormat
     * @param boolean $limitToActivePlugins Whether to search only in active plugins or in all plugins
     * @return DOMNode[]
     */
    public static function searchAllManifests($query, $stringOrNodeFormat = "string", $limitToActivePlugins = false, $limitToEnabledPlugins = false, $loadExternalFiles = false)
    {
        $buffer = "";
        $nodes = array();
        $self = self::getInstance();
        foreach ($self->registry as $plugType) {
            foreach ($plugType as $plugName => $plugObject) {
                if ($limitToActivePlugins) {
                    $plugId = $plugObject->getId();
                    if ($limitToActivePlugins && (!isSet($self->activePlugins[$plugId]) || $self->activePlugins[$plugId] === false)) {
                        continue;
                    }
                }
                if ($limitToEnabledPlugins) {
                    if(!$plugObject->isEnabled()) continue;
                }
                $res = $plugObject->getManifestRawContent($query, $stringOrNodeFormat, $loadExternalFiles);
                if ($stringOrNodeFormat == "string") {
                    $buffer .= $res;
                } else {
                    foreach ($res as $node) {
                        $nodes[] = $node;
                    }
                }
            }
        }
        if($stringOrNodeFormat == "string") return $buffer;
        else return $nodes;
    }

    /**
     * Central function of the registry construction, merges some nodes into the existing registry.
     * @param DOMDocument $original
     * @param $parentName
     * @param $uuidAttr
     * @param $childrenNodes
     * @param bool $doNotOverrideChildren
     * @return void
     */
    protected function mergeNodes(&$original, $parentName, $uuidAttr, $childrenNodes, $doNotOverrideChildren = false)
    {
        // find or create parent
        $parentSelection = $original->getElementsByTagName($parentName);
        if ($parentSelection->length) {
            $parentNode = $parentSelection->item(0);
            $xPath = new DOMXPath($original);
            foreach ($childrenNodes as $child) {
                if($child->nodeType != XML_ELEMENT_NODE) continue;
                if ($child->getAttribute($uuidAttr) == "*") {
                    $query = $parentName.'/'.$child->nodeName;
                } else {
                    $query = $parentName.'/'.$child->nodeName.'[@'.$uuidAttr.' = "'.$child->getAttribute($uuidAttr).'"]';
                }
                $childrenSel = $xPath->query($query);
                if ($childrenSel->length) {
                    if($doNotOverrideChildren) continue;
                    foreach ($childrenSel as $existingNode) {
                        // Clone as many as needed
                        $clone = $original->importNode($child, true);
                        $this->mergeChildByTagName($clone, $existingNode);
                    }
                } else {
                    $clone = $original->importNode($child, true);
                    $parentNode->appendChild($clone);
                }
            }
        } else {
            //create parentNode and append children
            if ($childrenNodes->length) {
                $parentNode = $original->importNode($childrenNodes->item(0)->parentNode, true);
                $original->documentElement->appendChild($parentNode);
            } else {
                $parentNode = $original->createElement($parentName);
                $original->documentElement->appendChild($parentNode);
            }
        }
    }
    /**
     * Utilitary function
     * @param $new
     * @param $old
     * @return
     */
    protected function mergeChildByTagName($new, &$old)
    {
        if (!$this->hasElementChild($new) || !$this->hasElementChild($old)) {
            $old->parentNode->replaceChild($new, $old);
            return;
        }
        foreach ($new->childNodes as $newChild) {
            if($newChild->nodeType != XML_ELEMENT_NODE) continue;

            $found = null;
            foreach ($old->childNodes as $oldChild) {
                if($oldChild->nodeType != XML_ELEMENT_NODE) continue;
                if ($oldChild->nodeName == $newChild->nodeName) {
                    $found = $oldChild;
                }
            }
            if ($found != null) {
                if ($newChild->nodeName == "post_processing" || $newChild->nodeName == "pre_processing") {
                    $old->appendChild($newChild->cloneNode(true));
                } else {
                    $this->mergeChildByTagName($newChild, $found);
                }
            } else {
                // CloneNode or it's messing with the current foreach loop.
                $old->appendChild($newChild->cloneNode(true));
            }
        }
    }

    /**
     * Utilitary
     * @param $node
     * @return bool
     */
    private function hasElementChild($node)
    {
        if(!$node->hasChildNodes()) return false;
        foreach ($node->childNodes as $child) {
            if($child->nodeType == XML_ELEMENT_NODE) return true;
        }
        return false;
    }

    /**
     * @param string $plugType
     * @param string $plugName
     * @return AJXP_Plugin
     */
    public function getPluginByTypeName($plugType, $plugName)
    {
        if (isSet($this->registry[$plugType]) && isSet($this->registry[$plugType][$plugName])) {
            return $this->registry[$plugType][$plugName];
        } else {
            return false;
        }
    }

    /**
     *
     * @param string $type
     * @param string $name
     * @return AJXP_Plugin
     */
    public static function findPlugin($type, $name)
    {
        $instance = self::getInstance();
        return $instance->getPluginByTypeName($type, $name);
    }

    /**
     * Simply find a plugin by its id (type.name)
     * @static
     * @param $id
     * @return AJXP_Plugin
     */
    public static function findPluginById($id)
    {
        return self::getInstance()->getPluginById($id);
    }

    private function __construct()
    {
    }
    /**
     * Singleton method
     *
     * @return AJXP_PluginsService the service instance
     */
    public static function getInstance()
    {
        if (!isSet(self::$instance)) {
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
