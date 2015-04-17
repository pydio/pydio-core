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

if (!defined('LOG_LEVEL_DEBUG')) {
    define("LOG_LEVEL_DEBUG", "Debug");
    define("LOG_LEVEL_INFO", "Info");
    define("LOG_LEVEL_NOTICE", "Notice");
    define("LOG_LEVEL_WARNING", "Warning");
    define("LOG_LEVEL_ERROR", "Error");
}

/**
 * The basic concept of plugin. Only needs a manifest.xml file.
 * @package Pydio
 * @subpackage Core
 */
class AJXP_Plugin implements Serializable
{
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
    protected $externalFilesAppended = false;
    protected $enabled;
    protected $actions;
    protected $registryContributions = array();
    protected $contributionsLoaded = false;
    protected $options; // can be passed at init time
    protected $pluginConf; // can be passed at load time
    protected $pluginConfDefinition;
    protected $dependencies;
    protected $extensionsDependencies;
    protected $streamData;
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
        "externalFilesAppended",
        "enabled",
        "actions",
        "registryContributions",
        "contributionsLoaded",
        "mixins",
        "streamData",
        "options", "pluginConf", "pluginConfDefinition", "dependencies", "extensionsDependencies", "loadingState", "manifestXML");

    /**
     * Construction method
     *
     * @param string $id
     * @param string $baseDir
     */
    public function __construct($id, $baseDir)
    {
        $this->baseDir = $baseDir;
        $this->id = $id;
        $split = explode(".", $id);
        $this->type = $split[0];
        $this->name = $split[1];
        $this->actions = array();
        $this->dependencies = array();
        $this->extensionsDependencies = array();
    }

    protected function getPluginWorkDir($check = false)
    {
        $d = AJXP_DATA_PATH.DIRECTORY_SEPARATOR."plugins".DIRECTORY_SEPARATOR.$this->getId();
        if(!$check) return $d;
        if (!is_dir($d)) {
            $res = @mkdir($d, 0755, true);
            if(!$res) throw new Exception("Error while creating plugin directory for ".$this->getId());
        }
        return $d;
    }

    protected function getPluginCacheDir($shared = false, $check = false)
    {
        $d = ($shared ? AJXP_SHARED_CACHE_DIR : AJXP_CACHE_DIR).DIRECTORY_SEPARATOR."plugins".DIRECTORY_SEPARATOR.$this->getId();
        if(!$check) return $d;
        if (!is_dir($d)) {
            $res = @mkdir($d, 0755, true);
            if(!$res) throw new Exception("Error while creating plugin cache directory for ".$this->getId());
        }
        return $d;
    }

    /**
     * @param $options
     * @return void
     */
    public function init($options)
    {
        $this->options = array_merge($this->loadOptionsDefaults(), $options);
    }

    protected function getFilteredOption($optionName, $repositoryScope = AJXP_REPO_SCOPE_ALL, $userObject = null)
    {
        if(!is_array($this->options)) $this->options = array();
        $merged = $this->options;
        if(is_array($this->pluginConf)) $merged = array_merge($merged, $this->pluginConf);
        $loggedUser = $userObject;
        if($loggedUser == null){
            $loggedUser = AuthService::getLoggedUser();
        }
        if ($loggedUser != null) {
            if ($repositoryScope == AJXP_REPO_SCOPE_ALL) {
                $repo = ConfService::getRepository();
                if($repo != null) $repositoryScope = $repo->getId();
            }
            $test = $loggedUser->mergedRole->filterParameterValue(
                $this->getId(),
                $optionName,
                $repositoryScope,
                isSet($merged[$optionName]) ? $merged[$optionName] : null
            );
            if (isSet($repo) && $repo != null && $repo->hasParent()) {
                $retest = $loggedUser->mergedRole->filterParameterValue(
                    $this->getId(),
                    $optionName,
                    $repo->getParentId(),
                    isSet($merged[$optionName]) ? $merged[$optionName] : null
                );
                if($retest != null) $test = $retest;
            }
            return $test;
        } else {
            return isSet($merged[$optionName]) ? $merged[$optionName] : null;
        }
    }
    /**
     * Perform initialization checks, and throw exception if problems found.
     * @throws Exception
     */
    public function performChecks()
    {
    }
    /**
     * @return bool
     */
    public function isEnabled()
    {
        if(isSet($this->enabled)) return $this->enabled;
        $this->enabled = true;
        if ($this->manifestLoaded) {
            $l = $this->xPath->query("@enabled", $this->manifestDoc->documentElement);
            if ($l->length && $l->item(0)->nodeValue === "false") {
                $this->enabled = false;
            }
        }
        return $this->enabled;
    }

    /**
     * Main function for loading all the nodes under registry_contributions.
     * @return void
     */
    protected function loadRegistryContributions($dry = false)
    {
        $regNodes = $this->xPath->query("registry_contributions/*");
        for ($i=0;$i<$regNodes->length;$i++) {
            $regNode = $regNodes->item($i);
            if($regNode->nodeType != XML_ELEMENT_NODE) continue;
            if ($regNode->nodeName == "external_file" && !$this->externalFilesAppended) {
                $data = $this->nodeAttrToHash($regNode);
                $filename = $data["filename"] OR "";
                $include = $data["include"] OR "*";
                $exclude = $data["exclude"] OR "";
                if(!is_file(AJXP_INSTALL_PATH."/".$filename)) continue;
                if ($include != "*") {
                    $include = explode(",", $include);
                } else {
                    $include = array("*");
                }
                if ($exclude != "") {
                    $exclude = explode(",", $exclude);
                } else {
                    $exclude = array();
                }
                $this->initXmlContributionFile($filename, $include, $exclude, $dry);
            } else {
                if (!$dry) {
                    $this->registryContributions[]=$regNode;
                    $this->parseSpecificContributions($regNode);
                }
            }
        }
        // add manifest as a "plugins" (remove parsed contrib)
        $pluginContrib = new DOMDocument();
        $pluginContrib->loadXML("<plugins uuidAttr='name'></plugins>");
        $manifestNode = $pluginContrib->importNode($this->manifestDoc->documentElement, true);
        $pluginContrib->documentElement->appendChild($manifestNode);
        $xP=new DOMXPath($pluginContrib);
        $regNodeParent = $xP->query("registry_contributions", $manifestNode);
        if ($regNodeParent->length) {
            $manifestNode->removeChild($regNodeParent->item(0));
        }
        if (!$dry) {
            $this->registryContributions[]=$pluginContrib->documentElement;
            $this->parseSpecificContributions($pluginContrib->documentElement);
            $this->contributionsLoaded = true;
        }
    }

    /**
     * Load an external XML file and include/exclude its nodes as contributions.
     * @param string $xmlFile Path to the file from the base install path
     * @param array $include XPath query for XML Nodes to include
     * @param array $exclude XPath query for XML Nodes to exclude from the included ones.
     * @return
     */
    protected function initXmlContributionFile($xmlFile, $include=array("*"), $exclude=array(), $dry = false)
    {
        $contribDoc = new DOMDocument();
        $contribDoc->load(AJXP_INSTALL_PATH."/".$xmlFile);
        if (!is_array($include) && !is_array($exclude)) {
            if (!$dry) {
                $this->registryContributions[] = $contribDoc->documentElement;
                $this->parseSpecificContributions($contribDoc->documentElement);
            }
            return;
        }
        $xPath = new DOMXPath($contribDoc);
        $excluded = array();
        foreach ($exclude as $excludePath) {
            $children = $xPath->query($excludePath);
            foreach ($children as $child) {
                $excluded[] = $child;
            }
        }
        $selected = array();
        foreach ($include as $includePath) {
            $incChildren = $xPath->query($includePath);
            if(!$incChildren->length) continue;
            $parentNode = $incChildren->item(0)->parentNode;
            if (!isSet($selected[$parentNode->nodeName])) {
                $selected[$parentNode->nodeName]=array("parent"=>$parentNode, "nodes"=>array());
            }
            foreach ($incChildren as $incChild) {
                $foundEx = false;
                foreach ($excluded as $exChild) {
                    if ($this->nodesEqual($exChild, $incChild)) {
                        $foundEx = true;break;
                    }
                }
                if($foundEx) continue;
                $selected[$parentNode->nodeName]["nodes"][] = $incChild;
            }
            if (!count($selected[$parentNode->nodeName]["nodes"])) {
                unset($selected[$parentNode->nodeName]);
            }
        }
        if(!count($selected)) return;
        $originalRegContrib = $this->xPath->query("registry_contributions")->item(0);
        foreach ($selected as $parentNodeName => $data) {
            $node = $data["parent"]->cloneNode(false);
            //$newNode = $originalRegContrib->ownerDocument->importNode($node, false);
            if ($dry) {
                $localRegParent = $this->xPath->query("registry_contributions/".$parentNodeName);
                if ($localRegParent->length) {
                    $localRegParent = $localRegParent->item(0);
                } else {
                    $localRegParent = $originalRegContrib->ownerDocument->createElement($parentNodeName);
                    $originalRegContrib->appendChild($localRegParent);
                }
            }
            foreach ($data["nodes"] as $childNode) {
                $node->appendChild($childNode);
                if($dry) $localRegParent->appendChild($localRegParent->ownerDocument->importNode($childNode, true));
            }
            if (!$dry) {
                $this->registryContributions[] = $node;
                $this->parseSpecificContributions($node);
            } else {
                $this->reloadXPath();
            }
        }
    }
    /**
     * Dynamically modify some registry contributions nodes. Can be easily derivated to enable/disable
     * some features dynamically during plugin initialization.
     * @param DOMNode $contribNode
     * @return void
     */
    protected function parseSpecificContributions(&$contribNode)
    {
        //Append plugin id to callback tags
        $callbacks = $contribNode->getElementsByTagName("serverCallback");
        foreach ($callbacks as $callback) {
            $attr = $callback->ownerDocument->createAttribute("pluginId");
            $attr->value = $this->id;
            $callback->appendChild($attr);
        }
        if ($contribNode->nodeName == "actions") {
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            foreach ($contribNode->childNodes as $actionNode) {
                if($actionNode->nodeType!=XML_ELEMENT_NODE) continue;
                $names = $actionXpath->query("@name", $actionNode);
                $name = $names->item(0)->value;
                $this->actions[$name] = $name;
                continue;
            }
        }
    }

    /**
     * Load the main manifest.xml file of the plugni
     * @throws Exception
     * @return void
     */
    public function loadManifest()
    {
        $file = $this->baseDir."/manifest.xml";
        if (!is_file($file)) {
            return;
        }
        $this->manifestDoc = new DOMDocument();
        try {
            $this->manifestDoc->load($file);
        } catch (Exception $e) {
            throw $e;
        }
        $id = $this->manifestDoc->documentElement->getAttribute("id");
        if (empty($id) || $id != $this->getId()) {
            $this->manifestDoc->documentElement->setAttribute("id", $this->getId());
        }
        $this->xPath = new DOMXPath($this->manifestDoc);
        $this->loadMixins();
        $this->detectStreamWrapper();
        $this->manifestLoaded = true;
        $this->loadDependencies();
    }

    /**
     * Get the plugin label as defined in the manifest file (label attribute)
     * @return mixed|string
     */
    public function getManifestLabel()
    {
        $l = $this->xPath->query("@label", $this->manifestDoc->documentElement);
        if($l->length) return AJXP_XMLWriter::replaceAjxpXmlKeywords($l->item(0)->nodeValue);
        else return $this->id;
    }
    /**
     * Get the plugin description as defined in the manifest file (description attribute)
     * @return mixed|string
     */
    public function getManifestDescription()
    {
        $l = $this->xPath->query("@description", $this->manifestDoc->documentElement);
        if($l->length) return AJXP_XMLWriter::replaceAjxpXmlKeywords($l->item(0)->nodeValue);
        else return "";
    }

    /**
     * Serialized all declared attributes and return a serialized representation of this plugin.
     * The XML Manifest is base64 encoded before serialization.
     * @return string
     */
    public function serialize()
    {
        if ($this->manifestDoc != null) {
            $this->manifestXML = serialize(base64_encode($this->manifestDoc->saveXML()));
        }
        $serialArray = array();
        foreach ($this->serializableAttributes as $attr) {
            $serialArray[$attr] = $this->$attr;
        }
        return serialize($serialArray);
    }

    /**
     * Load this plugin from its serialized reprensation. The manifest XML is base64 decoded.
     * @param $string
     * @return void
     */
    public function unserialize($string)
    {
        $serialArray = unserialize($string);
        foreach ($serialArray as $key => $value) {
            $this->$key = $value;
        }
        if ($this->manifestXML != NULL) {
            $this->manifestDoc = new DOMDocument(1.0, "UTF-8");
            $this->manifestDoc->loadXML(base64_decode(unserialize($this->manifestXML)));
            $this->reloadXPath();
            unset($this->manifestXML);
        }
    }

    /**
     * Legacy function, should better be used to return XML Nodes than string. Perform a query
     * on the manifest.
     * @param string $xmlNodeName
     * @param string $format
     * @param bool $externalFiles
     * @return DOMElement|DOMNodeList|string
     */
    public function getManifestRawContent($xmlNodeName = "", $format = "string", $externalFiles = false)
    {
        if ($externalFiles && !$this->externalFilesAppended) {
            $this->loadRegistryContributions(true);
            $this->externalFilesAppended = true;
        }
        if ($xmlNodeName == "") {
            if ($format == "string") {
                return $this->manifestDoc->saveXML($this->manifestDoc->documentElement);
            } else {
                return $this->manifestDoc->documentElement;
            }
        } else {
            $nodes = $this->xPath->query($xmlNodeName);
            if ($format == "string") {
                $buffer = "";
                foreach ($nodes as $node) {
                    $buffer .= $this->manifestDoc->saveXML($node);
                }
                return $buffer;
            } else {
                return $nodes;
            }
        }
    }
    /**
     * Return the registry contributions. The parameter can be used by subclasses to optimize the size of the XML returned :
     * the extended version is called when sending to the client, whereas the "small" version is loaded to find and apply actions.
     * @param bool $extendedVersion Can be used by subclasses to optimize the size of the XML returned.
     * @return array
     */
    public function getRegistryContributions($extendedVersion = true)
    {
        if (!$this->contributionsLoaded) {
            $this->loadRegistryContributions();
        }
        return $this->registryContributions;
    }
    /**
     * Load the declared dependant plugins
     * @return void
     */
    protected function loadDependencies()
    {
        $depPaths = "dependencies/*/@pluginName";
        $nodes = $this->xPath->query($depPaths);
        foreach ($nodes as $attr) {
            $value = $attr->value;
            $this->dependencies = array_merge($this->dependencies, explode("|", $value));
        }
        $extPaths = "dependencies/phpExtension/@name";
        $nodes = $this->xPath->query($extPaths);
        foreach ($nodes as $attr) {
            $this->extensionsDependencies[] = $attr->value;
        }
    }
    /**
     * Update dependencies dynamically
     * @param AJXP_PluginsService $pluginService
     * @return void
     */
    public function updateDependencies($pluginService)
    {
        $append = false;
        foreach ($this->dependencies as $index => $dependency) {
            if ($dependency == "access.AJXP_STREAM_PROVIDER") {
                unset($this->dependencies[$index]);
                $append = true;
            }
        }
        if ($append) {
            $this->dependencies = array_merge($this->dependencies, $pluginService->getStreamWrapperPlugins());
        }
    }
    /**
     * Check if this plugin depends on another one.
     * @param string $pluginName
     * @return bool
     */
    public function dependsOn($pluginName)
    {
        return (in_array($pluginName, $this->dependencies)
            || in_array(substr($pluginName, 0, strpos($pluginName, "."))."+", $this->dependencies));
    }
    /**
     * Get dependencies
     *
     * @param AJXP_PluginsService $pluginService
     * @return array
     */
    public function getActiveDependencies($pluginService)
    {
        if(!$this->manifestLoaded) return array();
        $deps = array();
        $nodes = $this->xPath->query("dependencies/activePlugin/@pluginName");
        foreach ($nodes as $attr) {
            $value = $attr->value;
            $parts = explode("|", $value);
            foreach ($parts as $depName) {
                if ($depName == "access.AJXP_STREAM_PROVIDER") {
                    $deps = array_merge($deps, $pluginService->getStreamWrapperPlugins());
                } else if (strpos($depName, "+") !== false) {
                    $typed = $pluginService->getPluginsByType(substr($depName, 0, strlen($depName)-1));
                    foreach($typed as $typPlug) $deps[] = $typPlug->getId();
                } else {
                    $deps[] = $depName;// array_merge($deps, explode("|", $value));
                }
            }
        }
        return $deps;
    }
    /**
     * Load the global parameters for this plugin
     * @return void
     */
    protected function loadConfigsDefinitions()
    {
        $params = $this->xPath->query("//server_settings/global_param|//server_settings/param");
        $this->pluginConf = array();
        foreach ($params as $xmlNode) {
            $global = ($xmlNode->nodeName == "global_param");
            $paramNode = $this->nodeAttrToHash($xmlNode);
            $this->pluginConfDefinition[$paramNode["name"]] = $paramNode;
            if ($global && isset($paramNode["default"])) {
                if ($paramNode["type"] == "boolean") {
                    $paramNode["default"] = ($paramNode["default"] === "true" ? true: false);
                } else if ($paramNode["type"] == "integer") {
                    $paramNode["default"] = intval($paramNode["default"]);
                }
                $this->pluginConf[$paramNode["name"]] = $paramNode["default"];
            }
        }
    }
    /**
     * Load the default values for this plugin options
     * @return array
     */
    protected function loadOptionsDefaults()
    {
        $optionsDefaults = array();
        $params = $this->xPath->query("//server_settings/param[@default]");
        foreach ($params as $xmlNode) {
            $default = $xmlNode->getAttribute("default");
            if ($xmlNode->getAttribute("type") == "boolean") {
                $default = ($default === "true" ? true: false);
            } else if ($xmlNode->getAttribute("type") == "integer") {
                $default = intval($default);
            }
            $optionsDefaults[$xmlNode->getAttribute("name")] = $default;
        }
        return $optionsDefaults;
    }
    /**
     * @return array
     */
    public function getConfigsDefinitions()
    {
        return $this->pluginConfDefinition;
    }
    /**
     * Load the configs passed as parameter. This method will
     * + Parse the config definitions and load the default values
     * + Merge these values with the $configData parameter
     * + Publish their value in the manifest if the global_param is "exposed" to the client.
     * @param array $configData
     * @return void
     */
    public function loadConfigs($configData)
    {
        // PARSE DEFINITIONS AND LOAD DEFAULT VALUES
        if (!isSet($this->pluginConf)) {
            $this->loadConfigsDefinitions();
        }
        // MERGE WITH PASSED CONFIGS
        $this->pluginConf = array_merge($this->pluginConf, $configData);

        // PUBLISH IF NECESSARY
        foreach ($this->pluginConf as $key => $value) {
            if (isSet($this->pluginConfDefinition[$key]) && isSet($this->pluginConfDefinition[$key]["expose"]) && $this->pluginConfDefinition[$key]["expose"] == "true") {
                $this->exposeConfigInManifest($key, $value);
            }
        }

        // ASSIGN SPECIFIC OPTIONS TO PLUGIN KEY
        if (isSet($this->pluginConf["AJXP_PLUGIN_ENABLED"])) {
            $this->enabled = $this->pluginConf["AJXP_PLUGIN_ENABLED"];
        }
    }
    /**
     * Return this plugin configs, merged with its associated "core" configs.
     * @return array
     */
    public function getConfigs()
    {
        $core = AJXP_PluginsService::getInstance()->findPlugin("core", $this->type);
        if (!empty($core)) {
            $coreConfs = $core->getConfigs();
            return array_merge($coreConfs, $this->pluginConf);
        } else {
            return $this->pluginConf;
        }
    }
    /**
     * Return the file path of the specific class to load
     * @return array|bool
     */
    public function getClassFile()
    {
        $files = $this->xPath->query("class_definition");
        if(!$files->length) return false;
        return $this->nodeAttrToHash($files->item(0));
    }

    public function missingExtensions(){
        $missing = array();
        if(count($this->extensionsDependencies)){
            foreach($this->extensionsDependencies as $ext){
                if (!extension_loaded($ext)) {
                    $missing[] = $ext;
                }
            }
        }
        return $missing;
    }

    public function hasMissingExtensions(){
        return count($this->missingExtensions()) > 0;
    }

    /**
     * @return bool
     */
    public function manifestLoaded()
    {
        return $this->manifestLoaded;
    }
    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
    /**
     * @return string
     */
    public function getBaseDir()
    {
        return $this->baseDir;
    }
    /**
     * @return array
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }
    /**
     * Write a debug log with the plugin id as source
     * @param string $prefix  A quick description or action
     * @param array|string $message Variable number of message args (string or array)
     * @return void
     */
    public function logDebug($prefix, $message = "")
    {
        if(!class_exists("ConfService")) return ;
        if(!ConfService::getConf("SERVER_DEBUG")) return ;
        $args = func_get_args();
        array_shift($args);
        AJXP_Logger::log2(LOG_LEVEL_DEBUG, $this->getId(), $prefix, $args);
    }

    /**
     * Write an info log with the plugin id as source
     * @param string $prefix  A quick description or action
     * @param array|string $message Variable number of message args (string or array)
     * @return void
     */
    public function logInfo($prefix, $message)
    {
        $args = func_get_args();
        array_shift($args);
        AJXP_Logger::log2(LOG_LEVEL_INFO, $this->getId(), $prefix, $args);
    }

    /**
     * Write a notice log with the plugin id as source
     * @param string $prefix  A quick description or action
     * @param array|string $message Variable number of message args (string or array)
     * @return void
     */
    public function logNotice($prefix, $message)
    {
        $args = func_get_args();
        array_shift($args);
        AJXP_Logger::log2(LOG_LEVEL_NOTICE, $this->getId(), $prefix, $args);
    }

    /**
     * Write a warning log with the plugin id as source
     * @param string $prefix  A quick description or action
     * @param array|string $message Variable number of message args (string or array)
     * @return void
     */
    public function logWarning($prefix, $message)
    {
        $args = func_get_args();
        array_shift($args);
        AJXP_Logger::log2(LOG_LEVEL_WARNING, $this->getId(), $prefix, $args);
    }

    /**
     * Write an error log with the plugin id as source
     * @param string $prefix  A quick description or action
     * @param array|string $message Variable number of message args (string or array)
     * @return void
     */
    public function logError($prefix, $message)
    {
        $args = func_get_args();
        array_shift($args);
        AJXP_Logger::log2(LOG_LEVEL_ERROR, $this->getId(), $prefix, $args);
    }

    /**
     * Detect if this plugin declares a StreamWrapper, and if yes loads it and register the stream.
     * @param bool $register
     * @return array|bool
     */
    public function detectStreamWrapper($register = false)
    {
        if (isSet($this->streamData)) {
            if($this->streamData === false) return false;
            $streamData = $this->streamData;
            // include wrapper, no other checks needed.
            include_once(AJXP_INSTALL_PATH."/".$streamData["filename"]);
        } else {
            $files = $this->xPath->query("class_stream_wrapper");
            if (!$files->length) {
                $this->streamData = false;
                return false;
            }
            $streamData = $this->nodeAttrToHash($files->item(0));
            if (!is_file(AJXP_INSTALL_PATH."/".$streamData["filename"])) {
                $this->streamData = false;
                return false;
            }
            include_once(AJXP_INSTALL_PATH."/".$streamData["filename"]);
            if (!class_exists($streamData["classname"])) {
                $this->streamData = false;
                return false;
            }
            $this->streamData = $streamData;
        }
        if ($register) {
            $pServ = AJXP_PluginsService::getInstance();
            if (!in_array($streamData["protocol"], stream_get_wrappers())) {
                stream_wrapper_register($streamData["protocol"], $streamData["classname"]);
                $pServ->registerWrapperClass($streamData["protocol"], $streamData["classname"]);
            }
        }
        return $streamData;
    }
    /**
     * Add a name/value pair in the manifest to be published to the world.
     * @param $configName
     * @param $configValue
     * @return void
     */
    protected function exposeConfigInManifest($configName, $configValue)
    {
        $confBranch = $this->xPath->query("plugin_configs");
        if (!$confBranch->length) {
            $configNode = $this->manifestDoc->importNode(new DOMElement("plugin_configs", ""));
            $this->manifestDoc->documentElement->appendChild($configNode);
        } else {
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

    public function getPluginInformation()
    {
        $info = array(
            "plugin_author" => "",
            "plugin_uri"   => "",
            "core_packaged" => true,
            "plugin_version"=> "follow",
            "core_version" => AJXP_VERSION,
        );
        $infoBranch = $this->xPath->query("plugin_info");
        if ($infoBranch->length) {
            foreach ($infoBranch->item(0)->childNodes as $child) {
                if($child->nodeType != 1) continue;
                if ($child->nodeName != "core_relation") {
                    $info[$child->nodeName] = $child->nodeValue;
                } else {
                    if($child->getAttribute("packaged") == "false") $info["core_packaged"] = false;
                    $coreV = $child->getAttribute("tested_version");
                    if($coreV == "follow") $coreV = AJXP_VERSION;
                    $info["core_version"] = $coreV;
                }
            }
        }
        return $info;
    }

    public function getPluginInformationHTML($defaultAuthor, $defaultUriBase)
    {
        $pInfo = $this->getPluginInformation();
        $pInfoString = "";
        if (empty($pInfo["plugin_uri"])) {
            if($pInfo["core_packaged"]) $pInfo["plugin_uri"] = $defaultUriBase.$this->getType()."/".$this->getName()."/";
            else $pInfo["plugin_uri"] = "N/A";
        }
        if ($pInfo["plugin_uri"] != "N/A") {
            $pInfo["plugin_uri"] = "<a href='".$pInfo["plugin_uri"]."' title='".$pInfo["plugin_uri"]."' target='_blank'>".$pInfo["plugin_uri"]."</a>";
        }
        if ($pInfo["core_packaged"]) {
            unset($pInfo["plugin_version"]);
            unset($pInfo["core_version"]);
        }
        $pInfo["core_packaged"] = ($pInfo["core_packaged"] === true ? "Yes": "No");
        $humanKeys = array(
            "plugin_author" => "Author",
            "plugin_version" => "Version",
            "plugin_uri"    => "Url",
            "core_packaged" => "Core distrib.",
            "core_version"   => "Core version"
        );
        if(empty($pInfo["plugin_author"])) $pInfo["plugin_author"] = $defaultAuthor;
        foreach ($pInfo as $k => $v) {
            $pInfoString .= "<li><span class='pluginfo_key'>".$humanKeys[$k]."</span><span class='pluginfo_value'>$v</span></li>";
        }
        return "<ul class='pluginfo_list'>$pInfoString</ul>";
    }

    /**
     * @return void
     */
    public function reloadXPath()
    {
        // Relaunch xpath
        $this->xPath = new DOMXPath($this->manifestDoc);
    }
    /**
     * @param $mixinName
     * @return bool
     */
    public function hasMixin($mixinName)
    {
        return (in_array($mixinName, $this->mixins));
    }
    /**
     * Check if the plugin declares mixins, and load them using AJXP_PluginsService::patchPluginWithMixin method
     * @return void
     */
    protected function loadMixins()
    {
        $attr = $this->manifestDoc->documentElement->getAttribute("mixins");
        if ($attr != "") {
            $this->mixins = explode(",", $attr);
            foreach ($this->mixins as $mixin) {
                AJXP_PluginsService::getInstance()->patchPluginWithMixin($this, $this->manifestDoc, $mixin);
            }
        }
    }

    /**
     * Transform a simple node and its attributes to a HashTable
     *
     * @param DOMNode $node
     * @return array
     */
    protected function nodeAttrToHash($node)
    {
        $hash = array();
        $attributes  = $node->attributes;
        if ($attributes!=null) {
            foreach ($attributes as $domAttr) {
                $hash[$domAttr->name] = $domAttr->value;
            }
        }
        return $hash;
    }
    /**
     * Compare two nodes at first level (nodename and attributes)
     *
     * @param DOMNode $node1
     * @param DOMNode $node2
     * @return bool
     */
    protected function nodesEqual($node1, $node2)
    {
        if($node1->nodeName != $node2->nodeName) return false;
        $hash1 = $this->nodeAttrToHash($node1);
        $hash2 = $this->nodeAttrToHash($node2);
        foreach ($hash1 as $name=>$value) {
            if(!isSet($hash2[$name]) || $hash2[$name] != $value) return false;
        }
        return true;
    }
}
