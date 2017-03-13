<?php
/*
 * Copyright 2007-2016 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Core\PluginFramework;

use DOMXPath;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\Repository;
use Pydio\Cache\Core\AbstractCacheDriver;
use Pydio\Conf\Core\AbstractConfDriver;
use Pydio\Conf\Core\CoreConfLoader;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Exception\PydioUserAlertException;
use Pydio\Core\Exception\RepositoryLoadException;
use Pydio\Core\Http\TopLevelRouter;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;


use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Services\CacheService;

use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\FileHelper;
use Pydio\Log\Core\Logger;
use Pydio\Access\Meta\Core\AbstractMetaSource;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Core parser for loading / serving plugins
 * @package Pydio
 * @subpackage Core
 */
class PluginsService
{
    /**
     * @var PluginsService[]
     */
    private static $instances = [];

    /**
     * All detected plugins
     * @var array
     */
    private $detectedPlugins = [];

    /**
     * @var ContextInterface
     */
    private $context;

    /**
     * @var string
     */
    private $pluginsDir;

    private $required_files = [];
    private $activePlugins = [];
    private $streamWrapperPlugins = [];
    private $registeredWrappers = [];
    private $xmlRegistry;
    private $registryVersion;
    private $tmpDeferRegistryBuild = false;

    private $mixinsDoc;
    private $mixinsXPath;

    /*********************************/
    /*         STATIC FUNCTIONS      */
    /*********************************/

    /**
     * Load registry either from cache or from plugins folder.
     * @throws PydioException
     */
    public static function initCoreRegistry(){

        $coreInstance = self::getInstance(Context::emptyContext());
        $coreInstance->getDetectedPlugins();

    }

    /**
     * Clear the cached files with the plugins
     */
    public static function clearPluginsCache(){
        @unlink(AJXP_PLUGINS_CACHE_FILE);
        @unlink(AJXP_PLUGINS_REQUIRES_FILE);
        @unlink(AJXP_PLUGINS_QUERIES_CACHE);
        @unlink(AJXP_PLUGINS_BOOTSTRAP_CACHE);
        @unlink(AJXP_CACHE_DIR."/".TopLevelRouter::ROUTE_CACHE_FILENAME);
        if(@unlink(AJXP_PLUGINS_REPOSITORIES_CACHE)){
            $content = "<?php \n";
            $boots = glob(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/*/repositories.php");
            if($boots !== false){
                foreach($boots as $b){
                    $content .= 'require_once("'.$b.'");'."\n";
                }
            }
            @file_put_contents(AJXP_PLUGINS_REPOSITORIES_CACHE, $content);
        }
    }

    /**
     * Clear the cached registries
     */
    public static function clearRegistryCaches(){
        foreach(self::$instances as $instance){
            CacheService::delete(AJXP_CACHE_SERVICE_NS_SHARED, $instance->getRegistryCacheKey(true));
            CacheService::delete(AJXP_CACHE_SERVICE_NS_SHARED, $instance->getRegistryCacheKey(false));
        }
    }

    /**
     * @return AbstractConfDriver
     */
    public static function confPluginSoftLoad()
    {
        $ctx = Context::emptyContext();
        /** @var AbstractConfDriver $booter */
        $booter = PluginsService::getInstance($ctx)->softLoad("boot.conf", []);
        $coreConfigs = $booter->loadPluginConfig("core", "conf");
        $corePlug = PluginsService::getInstance($ctx)->softLoad("core.conf", []);
        $corePlug->loadConfigs($coreConfigs);
        return $corePlug->getImplementation();

    }

    /**
     * @return AbstractCacheDriver
     */
    public static function cachePluginSoftLoad()
    {
        $ctx = Context::emptyContext();
        $coreConfigs = [];
        $corePlug = PluginsService::getInstance($ctx)->softLoad("core.cache", []);
        /** @var CoreConfLoader $coreConf */
        $coreConf = PluginsService::getInstance($ctx)->softLoad("core.conf", []);
        $coreConf->loadBootstrapConfForPlugin("core.cache", $coreConfigs);
        if (!empty($coreConfigs)) $corePlug->loadConfigs($coreConfigs);
        return $corePlug->getImplementation();
    }

    /**
     * Find a plugin by its type/name
     * @param string $type
     * @param string $name
     * @return Plugin
     */
    public static function findPluginWithoutCtxt($type, $name)
    {
        return self::getInstance()->getPluginByTypeName($type, $name);
    }

    /**
     * Singleton method
     * @param ContextInterface|null $ctx
     * @return PluginsService the service instance
     */
    public static function getInstance($ctx = null)
    {
        if(empty($ctx)){
            $ctx = Context::emptyContext();
        }
        $identifier = $ctx->getStringIdentifier();
        if (!isSet(self::$instances[$identifier])) {
            $c = __CLASS__;
            /**
             * @var PluginsService $pServ
             */
            self::$instances[$identifier] = $pServ = new $c($ctx);
            if(!$ctx->isEmpty()) {
                $emptyInstance = self::getInstance(Context::contextWithObjects(null, null));
                //$pServ->getDetectedPlugins();
                $pServ->cloneDetectedPluginsFromCoreInstance($emptyInstance);
                $pServ->copyCorePluginFromService($emptyInstance, "conf");
                $pServ->copyCorePluginFromService($emptyInstance, "cache");
                $pServ->copyCorePluginFromService($emptyInstance, "auth");
                if($ctx->hasRepository()){
                    $pServ->initRepositoryPlugins($ctx);
                }
                $pServ->initActivePlugins();
            }else{
                $pServ->getDetectedPlugins();
            }
        }
        return self::$instances[$identifier];
    }

    /**
     * @param ContextInterface $ctx
     */
    public static function clearInstance($ctx){
        $identifier = $ctx->getStringIdentifier();
        if(isSet(self::$instances[$identifier])){
            unset(self::$instances[$identifier]);
            gc_collect_cycles();
        }
    }

    /**
     * @param ContextInterface $ctx
     * @throws RepositoryLoadException
     * @throws PydioUserAlertException
     */
    private function initRepositoryPlugins($ctx){

        $errors = [];
        $repository = $ctx->getRepository();
        if(empty($repository)){
            throw new RepositoryLoadException($repository, []);
        }
        $accessType = $repository->getAccessType();
        /** @var AbstractAccessDriver $plugInstance */
        $plugInstance = $this->getPluginByTypeName("access", $accessType);
        if(!$plugInstance instanceof AbstractAccessDriver){
            throw new RepositoryLoadException($repository, ["Could not load plugin as an accessDriver"]);
        }

        // TRIGGER BEFORE INIT META
        $metaSources = $repository->getContextOption($ctx, "META_SOURCES");
        if (isSet($metaSources) && is_array($metaSources) && count($metaSources)) {
            $keys = array_keys($metaSources);
            foreach ($keys as $plugId) {
                if($plugId == "") continue;
                /** @var AbstractMetaSource $instance */
                $instance = $this->getPluginById($plugId);
                if (!is_object($instance)) {
                    continue;
                }
                if (!method_exists($instance, "beforeInitMeta")) {
                    continue;
                }
                try {
                    $options = $metaSources[$plugId];
                    if($ctx->hasUser()) {
                        $options = $ctx->getUser()->getMergedRole()->filterPluginConfigs($plugId, $options, $repository->getId());
                    }
                    $instance->init($ctx, $options);
                    $instance->beforeInitMeta($ctx, $plugInstance);
                } catch (\Exception $e) {
                    Logger::error(__CLASS__, 'Meta plugin', 'Cannot instanciate Meta plugin, reason : '.$e->getMessage());
                    $errors[] = $e->getMessage();
                }
            }
        }

        // INIT MAIN DRIVER
        try {
            $plugInstance->init($ctx);
        }catch (PydioUserAlertException $ua){
            throw $ua;
        }catch (\Exception $e){
            new RepositoryLoadException($repository, [$e->getMessage()]);
        }
        $repository->setDriverInstance($plugInstance);

        $this->deferBuildingRegistry();
        $this->setPluginUniqueActiveForType("access", $accessType);

        // TRIGGER INIT META
        $metaSources = $repository->getContextOption($ctx, "META_SOURCES");
        if (isSet($metaSources) && is_array($metaSources) && count($metaSources)) {
            $keys = array_keys($metaSources);
            foreach ($keys as $plugId) {
                if($plugId == "") continue;
                $split = explode(".", $plugId);
                /** @var AbstractMetaSource $instance */
                $instance = $this->getPluginById($plugId);
                if (!is_object($instance)) {
                    continue;
                }
                try {
                    $options = $metaSources[$plugId];
                    if($ctx->hasUser()) {
                        $options = $ctx->getUser()->getMergedRole()->filterPluginConfigs($plugId, $options, $repository->getId());
                    }
                    $instance->init($ctx, $options);
                    if(!method_exists($instance, "initMeta")) {
                        throw new \Exception("Meta Source $plugId does not implement the initMeta method.");
                    }
                    $instance->initMeta($ctx, $plugInstance);
                } catch (\Exception $e) {
                    Logger::error(__CLASS__, 'Meta plugin', 'Cannot instanciate Meta plugin, reason : '.$e->getMessage());
                    $errors[] = $e->getMessage();
                }
                $this->setPluginActive($split[0], $split[1]);
            }
        }
        $this->flushDeferredRegistryBuilding();
        if(count($errors)){
            throw new RepositoryLoadException($repository, $errors);
        }

    }

    /**
     * @param PluginsService $source
     * @param string $type
     */
    private function copyCorePluginFromService($source, $type){

        /**
         * @var CoreInstanceProvider $corePlugin
         */
        $corePlugin = $source->getPluginByTypeName("core", $type);
        $this->setPluginActive("core", $corePlugin->getName(), true, $corePlugin);
        $implementation = $corePlugin->getImplementation();
        if(!empty($implementation)){
            $this->setPluginUniqueActiveForType($type, $implementation->getName(), $implementation);
        }

    }

    /**
     * Search in all plugins (enabled / active or not) and store result in cache
     * @param string $query
     * @param callable $typeChecker
     * @param callable $callback
     * @return mixed
     */
    public static function searchManifestsWithCache($query, $callback, $typeChecker = null){
        $coreInstance = self::getInstance(Context::emptyContext());
        $result = $coreInstance->loadFromPluginQueriesCache($query);
        if(empty($typeChecker)){
            $typeChecker = function($test){
                return ($test !== null && is_array($test));
            };
        }
        if($typeChecker($result)){
            return $result;
        }
        $nodes = $coreInstance->searchAllManifests($query, "node", false, false, true);
        $result = $callback($nodes);
        $coreInstance->storeToPluginQueriesCache($query, $result);
        return $result;
    }

    /**
     * Search all plugins manifest with an XPath query, and return either the Nodes, or directly an XML string.
     * @param string $query
     * @param string $stringOrNodeFormat
     * @param boolean $limitToActivePlugins Whether to search only in active plugins or in all plugins
     * @param bool $limitToEnabledPlugins
     * @param bool $loadExternalFiles
     * @return \DOMNode[]
     */
    public function searchAllManifests($query, $stringOrNodeFormat = "string", $limitToActivePlugins = false, $limitToEnabledPlugins = false, $loadExternalFiles = false)
    {
        $buffer = "";
        $nodes = [];
        $detectedPlugins = $this->getDetectedPlugins();
        foreach ($detectedPlugins as $plugType) {
            /** @var Plugin $plugObject */
            foreach ($plugType as $plugName => $plugObject) {
                if ($limitToActivePlugins) {
                    $plugId = $plugObject->getId();
                    if ($limitToActivePlugins && (!isSet($this->activePlugins[$plugId]) || $this->activePlugins[$plugId] === false)) {
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
     * Gather stream data from repositories driver, without loading the whole context.
     *
     * @param RepositoryInterface[] $repositories
     * @return array
     */
    public static function detectRepositoriesStreams($repositories){
        $streams = [];
        foreach ($repositories as $repository) {
            $accessType = $repository->getAccessType();
            // Find access driver from base plugins
            $plugin = self::findPluginWithoutCtxt("access", $accessType);
            if($plugin instanceof AbstractAccessDriver){
                $streamData = $plugin->detectStreamWrapper(false);
                if($streamData !== false) $streams[$streamData["protocol"]] = $accessType;
            }
        }
        return $streams;
    }

    /*********************************/
    /*        PUBLIC FUNCTIONS       */
    /*********************************/

    /**
     * Get publicable XML Registry, filtered by roles
     * @param bool $extendedVersion
     * @param bool $clone
     * @param bool $useCache
     * @return \DOMDocument|\DOMNode
     */
    public function getFilteredXMLRegistry($extendedVersion = true, $clone = false, $useCache = false)
    {
        if ($useCache) {
            $cacheKey = $this->getRegistryCacheKey($extendedVersion);
            $tStamps = [];
            if($this->context->hasUser()) $tStamps[] = "pydio:user:".$this->context->getUser()->getId();
            if($this->context->hasRepository()) $tStamps[] = "pydio:repository:".$this->context->getRepositoryId();
            $cachedXml = CacheService::fetchWithTimestamps(AJXP_CACHE_SERVICE_NS_SHARED, $cacheKey, $tStamps);
            if ($cachedXml !== false) {
                $registry = new \DOMDocument("1.0", "utf-8");
                $registry->loadXML($cachedXml);
                $this->updateXmlRegistry($registry, $extendedVersion);
                if ($clone) {
                    return $registry->cloneNode(true);
                } else {
                    return $registry;
                }
            }
        }

        $registry = $this->getXmlRegistry($extendedVersion);
        if(UsersService::usersEnabled()){
            $changes = $this->filterRegistryFromRole($registry, $this->context);
            if ($changes) {
                $this->updateXmlRegistry($registry, $extendedVersion);
            }
        }

        if ($useCache && isSet($cacheKey)) {
            CacheService::saveWithTimestamp(AJXP_CACHE_SERVICE_NS_SHARED, $cacheKey, $registry->saveXML());
        }

        if ($clone) {
            $cloneDoc = $registry->cloneNode(true);
            $registry = $cloneDoc;
        }
        return $registry;

    }

    /**
     * @param string $plugType
     * @param string $plugName
     * @return Plugin
     */
    public function getPluginByTypeName($plugType, $plugName)
    {
        if (isSet($this->detectedPlugins[$plugType]) && isSet($this->detectedPlugins[$plugType][$plugName])) {
            return $this->detectedPlugins[$plugType][$plugName];
        } else {
            return false;
        }
    }

    /**
     * Loads the full registry, from the cache only
     * @param AbstractCacheDriver
     * @return bool
     */
    private function loadPluginsFromCache($cacheStorage) {

        if(!empty($this->detectedPlugins)){
            return true;
        }
        if($this->_loadDetectedPluginsFromCache($cacheStorage)){
            return true;
        }

        return false;
    }

    /**
     * Loads the full registry, from the cache or not
     * @param String $pluginFolder
     * @param AbstractConfDriver $confStorage
     * @param AbstractCacheDriver|null $cacheStorage
     */
    private function loadPlugins($pluginFolder, $confStorage, $cacheStorage)
    {
        if (!empty($cacheStorage) && $this->loadPluginsFromCache($cacheStorage)) {
            return;
        }

        if (is_string($pluginFolder)) {
            $pluginFolder = [$pluginFolder];
        }

        $pluginsPool = [];
        foreach ($pluginFolder as $sourceFolder) {
            $handler = @opendir($sourceFolder);
            if ($handler) {
                while ( ($item = readdir($handler)) !==false) {
                    if($item == "." || $item == ".." || !@is_dir($sourceFolder."/".$item) || strstr($item,".")===false) continue ;
                    $plugin = new Plugin($item, $sourceFolder."/".$item);
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
                $this->recursiveLoadPlugin($confStorage, $plugin, $pluginsPool);
            }
        }

        if (!defined("AJXP_SKIP_CACHE") || AJXP_SKIP_CACHE === false) {
            FileHelper::saveSerialFile(AJXP_PLUGINS_REQUIRES_FILE, $this->required_files, false, false);
            FileHelper::saveSerialFile(AJXP_PLUGINS_CACHE_FILE, $this->detectedPlugins, false, false);
            if (is_file(AJXP_PLUGINS_QUERIES_CACHE)) {
                @unlink(AJXP_PLUGINS_QUERIES_CACHE);
            }

            $this->savePluginsRegistryToCache($cacheStorage);
        }
    }

    /**
     * Simply load a plugin class, without the whole dependencies et.all
     * @param string $pluginId
     * @param array $pluginOptions
     * @return Plugin|CoreInstanceProvider
     */
    public function softLoad($pluginId, $pluginOptions)
    {
        // Try to get from cache
        list($type, $name) = explode(".", $pluginId);
        if(!empty($this->detectedPlugins) && isSet($this->detectedPlugins[$type][$name])) {
            /**
             * @var Plugin $plugin
             */
            $plugin = $this->detectedPlugins[$type][$name];
            $plugin->init($this->context, $pluginOptions);
            return clone $plugin;
        }


        $plugin = new Plugin($pluginId, AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/".$pluginId);
        $plugin->loadManifest();
        $plugin = $this->instanciatePluginClass($plugin);
        $plugin->loadConfigs([]); // Load default
        $plugin->init($this->context, $pluginOptions);
        return $plugin;
    }

    /**
     * All the plugins of a given type
     * @param string $type
     * @return Plugin[]
     */
    public function getPluginsByType($type)
    {
        if(isSet($this->detectedPlugins[$type])) return $this->detectedPlugins[$type];
        else return [];
    }

    /**
     * Get a plugin instance
     *
     * @param string $pluginId
     * @return Plugin
     */
    public function getPluginById($pluginId)
    {
        $split = explode(".", $pluginId);
        return $this->getPluginByTypeName($split[0], $split[1]);
    }

    /**
     * Load data from cache
     * @param $key
     * @return mixed|null
     */
    public function loadFromPluginQueriesCache($key)
    {
        if(AJXP_SKIP_CACHE) return null;
        $test = FileHelper::loadSerialFile(AJXP_PLUGINS_QUERIES_CACHE);
        if (!empty($test) && is_array($test) && isset($test[$key])) {
            return $test[$key];
        }
        return null;
    }

    /**
     * Copy data to cache
     * @param $key
     * @param $value
     * @throws \Exception
     */
    public function storeToPluginQueriesCache($key, $value)
    {
        if(AJXP_SKIP_CACHE) return;
        $test = FileHelper::loadSerialFile(AJXP_PLUGINS_QUERIES_CACHE);
        if(!is_array($test)) $test = [];
        $test[$key] = $value;
        FileHelper::saveSerialFile(AJXP_PLUGINS_QUERIES_CACHE, $test);
    }

    /*********************************/
    /*    PUBLIC: ACTIVE PLUGINS     */
    /*********************************/

    /**
     * Set the service in defer mode : do not rebuild
     * registry on each plugin activation
     */
    public function deferBuildingRegistry(){
        $this->tmpDeferRegistryBuild = true;
    }

    /**
     * If service was in defer mode, now build the registry
     */
    public function flushDeferredRegistryBuilding(){
        $this->tmpDeferRegistryBuild = false;
        if (isSet($this->xmlRegistry)) {
            $this->buildXmlRegistry(($this->registryVersion == "extended"));
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
        $this->deferBuildingRegistry();
        /**
         * @var Plugin $pObject
         */
        $toActivate = [];
        foreach ($detected as $pType => $pObjects) {
            $coreP = $this->getPluginByTypeName("core", $pType);
            if($coreP !== false && !isSet($coreP->AUTO_LOAD_TYPE)) continue;
            foreach ($pObjects as $pName => $pObject) {
                $toActivate[$pObject->getId()] = $pObject ;
            }
        }
        $o = $this->getOrderByDependency($toActivate, false);
        foreach ($o as $id) {
            $pObject = $toActivate[$id];
            $pObject->init($this->context, []);
            try {
                $pObject->performChecks();
                if(!$pObject->isEnabled($this->context) || $pObject->hasMissingExtensions()) continue;
                $this->setPluginActive($pObject->getType(), $pObject->getName(), true);
            } catch (\Exception $e) {
                //$this->errors[$pName] = "[$pName] ".$e->getMessage();
            }

        }
        $this->flushDeferredRegistryBuilding();
    }

    /**
     * Add a plugin to the list of active plugins
     * @param $type
     * @param $name
     * @param bool $active
     * @param Plugin $updateInstance
     * @return void
     */
    public function setPluginActive($type, $name, $active=true, $updateInstance = null)
    {
        if ($active) {
            // Check active plugin dependencies
            $plug = $this->getPluginById($type.".".$name);
            if(!$plug || !$plug->isEnabled($this->context)) return;
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
        if (isSet($updateInstance) && isSet($this->detectedPlugins[$type][$name])) {
            $this->detectedPlugins[$type][$name] = $updateInstance;
        }
        if (isSet($this->xmlRegistry) && !$this->tmpDeferRegistryBuild) {
            $this->buildXmlRegistry(($this->registryVersion == "extended"));
        }
    }

    /**
     * Some type require only one active plugin at a time
     * @param $type
     * @param $name
     * @param Plugin $updateInstance
     * @return void
     */
    public function setPluginUniqueActiveForType($type, $name, $updateInstance = null)
    {
        $typePlugs = $this->getPluginsByType($type);
        $originalValue = $this->tmpDeferRegistryBuild;
        $this->tmpDeferRegistryBuild = true;
        foreach ($typePlugs as $plugName => $plugObject) {
            $this->setPluginActive($type, $plugName, false);
        }
        $this->tmpDeferRegistryBuild = $originalValue;
        $this->setPluginActive($type, $name, true, $updateInstance);
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
     * @return Plugin[]
     */
    public function getActivePluginsForType($type, $unique = false)
    {
        $detectedPlugins = $this->getDetectedPlugins();
        $acts = [];
        foreach ($this->activePlugins as $plugId => $active) {
            if(!$active) continue;
            list($pT,$pN) = explode(".", $plugId);
            if ($pT == $type && isset($detectedPlugins[$pT][$pN])) {
                if ($unique) {
                    return $detectedPlugins[$pT][$pN];
                    break;
                }
                $acts[$pN] = $detectedPlugins[$pT][$pN];
            }
        }
        if($unique && !count($acts)) return false;
        return $acts;
    }

    /**
     * Return only one of getActivePluginsForType
     * @param $type
     * @return Plugin
     */
    public function getUniqueActivePluginForType($type)
    {
        return $this->getActivePluginsForType($type, true);
    }

    /**
     * All the plugins registry, active or not
     * @param AbstractConfDriver $confPlugin
     * @param AbstractCacheDriver $cachePlugin
     * @return array
     * @throws PydioException
     */
    public function getDetectedPlugins($confPlugin = null, $cachePlugin = null)
    {
        if(empty($this->detectedPlugins)){

            if($cachePlugin === null){
                $cachePlugin = self::cachePluginSoftLoad();
            }
            if (!$this->loadPluginsFromCache($cachePlugin)) {
                // Load from conf
                try {
                    if($confPlugin === null){
                        $confPlugin = self::confPluginSoftLoad();
                    }
                    $this->loadPlugins($this->pluginsDir, $confPlugin, $cachePlugin);
                } catch (\Exception $e) {
                    throw new PydioException("Severe error while loading plugins registry : ".$e->getMessage());
                }
            }
        }
        return $this->detectedPlugins;
    }

    /**
     * @param $emptyInstance PluginsService
     */
    public function cloneDetectedPluginsFromCoreInstance($emptyInstance){
        $detected = $emptyInstance->getDetectedPlugins();
        $this->detectedPlugins = [];
        $this->streamWrapperPlugins = $emptyInstance->streamWrapperPlugins;
        foreach($detected as $type => $plugins){
            $this->detectedPlugins[$type] = [];
            /**
             * @var string $name
             * @var Plugin $plugin
             */
            foreach($plugins as $name => $plugin){
                $cloned = clone $plugin;
                $this->detectedPlugins[$type][$name] = $cloned;
            }
        }
    }

    /*********************************/
    /*    PUBLIC: WRAPPERS METHODS   */
    /*********************************/
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
     * @return string
     */
    public function getWrapperClassName($protocol, $register = false)
    {
        if(isSet($this->registeredWrappers[$protocol])){
            return $this->registeredWrappers[$protocol];
        }
        /** @var AbstractAccessDriver $access */
        $access = $this->getActivePluginsForType("access", true);
        if($access === false){
            return null;
        }
        $data = $access->detectStreamWrapper($register);
        if($data !== null && $data["protocol"] == $protocol){
            return $data["classname"];
        }
        return null;
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
     * Append some predefined XML to a plugin instance
     * @param Plugin $plugin
     * @param \DOMDocument $manifestDoc
     * @param String $mixinName
     */
    public function patchPluginWithMixin(&$plugin, &$manifestDoc, $mixinName)
    {
        // Load behaviours if not already
        if (!isSet($this->mixinsDoc)) {
            $this->mixinsDoc = new \DOMDocument();
            $this->mixinsDoc->load(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/core.ajaxplorer/ajxp_mixins.xml");
            $this->mixinsXPath = new \DOMXPath($this->mixinsDoc);
        }
        // Merge into manifestDoc
        $nodeList = $this->mixinsXPath->query($mixinName);
        if(!$nodeList->length) return;
        $mixinNode = $nodeList->item(0);
        /** @var \DOMElement $child */
        foreach ($mixinNode->childNodes as $child) {
            if($child->nodeType != XML_ELEMENT_NODE) continue;
            $uuidAttr = $child->getAttribute("uuidAttr") OR "name";
            $this->mergeNodes($manifestDoc, $child->nodeName, $uuidAttr, $child->childNodes, true);
        }

        // Reload plugin XPath
        $plugin->reloadXPath();
    }


    /*********************************/
    /*         PRIVATE FUNCTIONS     */
    /*********************************/
    /**
     * Save plugin registry to cache
     * @param AbstractCacheDriver|null $cacheStorage
     */
    private function savePluginsRegistryToCache($cacheStorage) {
        if (!empty ($cacheStorage)) {
            $cacheStorage->save(AJXP_CACHE_SERVICE_NS_SHARED, "plugins_registry", $this->detectedPlugins);
        }
    }

    /**
     * Go through all plugins and call their getRegistryContributions() method.
     * Add all these contributions to the main XML ajxp_registry document.
     * @param bool $extendedVersion Will be passed to the plugin, for optimization purpose.
     * @return void
     */
    private function buildXmlRegistry($extendedVersion = true)
    {
        $actives = $this->getActivePlugins();
        $reg = new \DOMDocument();
        $reg->loadXML("<ajxp_registry></ajxp_registry>");
        foreach ($actives as $activeName=>$status) {
            if($status === false) continue;
            $plug = $this->getPluginById($activeName);
            $contribs = $plug->getRegistryContributions($this->context, $extendedVersion);
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
     * Load plugin class with dependencies first
     *
     * @param AbstractConfDriver $confStorage
     * @param Plugin $plugin
     * @param array $pluginsPool
     */
    private function recursiveLoadPlugin(AbstractConfDriver $confStorage, $plugin, $pluginsPool)
    {
        if ($plugin->loadingState!="") {
            return ;
        }
        $dependencies = $plugin->getDependencies();
        $plugin->loadingState = "lock";
        foreach ($dependencies as $dependencyId) {
            if (isSet($pluginsPool[$dependencyId])) {
                $this->recursiveLoadPlugin($confStorage, $pluginsPool[$dependencyId], $pluginsPool);
            } else if (strpos($dependencyId, "+") !== false) {
                foreach (array_keys($pluginsPool) as $pId) {
                    if (strpos($pId, str_replace("+", "", $dependencyId)) === 0) {
                        $this->recursiveLoadPlugin($confStorage, $pluginsPool[$pId], $pluginsPool);
                    }
                }
            }
        }
        $plugType = $plugin->getType();
        if (!isSet($this->detectedPlugins[$plugType])) {
            $this->detectedPlugins[$plugType] = [];
        }
        $options = $confStorage->loadPluginConfig($plugType, $plugin->getName());
        if($plugin->isEnabled($this->context) || (isSet($options["AJXP_PLUGIN_ENABLED"]) && $options["AJXP_PLUGIN_ENABLED"] === true)){
            $plugin = $this->instanciatePluginClass($plugin);
        }
        $plugin->loadConfigs($options);
        $this->detectedPlugins[$plugType][$plugin->getName()] = $plugin;
        $plugin->loadingState = "loaded";
    }

    /**
     * @param AbstractCacheDriver $cacheStorage
     * @return bool
     */
    private function _loadDetectedPluginsFromCache($cacheStorage){

        if((!defined("AJXP_SKIP_CACHE") || AJXP_SKIP_CACHE === false)){
            $reqs = FileHelper::loadSerialFile(AJXP_PLUGINS_REQUIRES_FILE);
            if (count($reqs)) {
                foreach ($reqs as $fileName) {
                    if (!is_file($fileName)) {
                        // Cache is out of sync
                        return false;
                    }
                    require_once($fileName);
                }

                $res = null;

                // Retrieving Registry from Server Cache
                if (!empty($cacheStorage)) {
                    $res = $cacheStorage->fetch(AJXP_CACHE_SERVICE_NS_SHARED, 'plugins_registry');
                    if(is_array($res)){
                        $this->detectedPlugins=$res;
                    }
                }

                // Retrieving Registry from files cache
                if (empty($res)) {
                    $res = FileHelper::loadSerialFile(AJXP_PLUGINS_CACHE_FILE);
                    $this->detectedPlugins=$res;
                    $this->savePluginsRegistryToCache($cacheStorage);
                }

                // Refresh streamWrapperPlugins
                foreach ($this->detectedPlugins as $plugs) {
                    /** @var Plugin $plugin */
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
     * Find a PHP class and instanciate it to replace the empty Plugin
     *
     * @param Plugin $plugin
     * @return Plugin
     */
    private function instanciatePluginClass($plugin)
    {
        $definition = $plugin->getClassFile();
        if(!$definition) return $plugin;
        $filename = AJXP_INSTALL_PATH."/".$definition["filename"];
        $className = $definition["classname"];
        if (is_file($filename)) {
            /**
             * @var Plugin $newPlugin
             */
            require_once($filename);
            $newPlugin = new $className($plugin->getId(), $plugin->getBaseDir());
            $newPlugin->loadManifest();
            $this->required_files[$filename] = $filename;
            return $newPlugin;
        } else {
            return $plugin;
        }
    }

    /**
     * Check that a plugin dependencies are loaded, disable it otherwise.
     * @param Plugin[] $arrayToSort
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
     * @param $plugins
     * @param bool $withStatus
     * @return array
     */
    private function getOrderByDependency($plugins, $withStatus = true)
    {
        $keys = [];
        $unkowns = [];
        if ($withStatus) {
            foreach ($plugins as $pid => $status) {
                if($status) $keys[] = $pid;
            }
        } else {
            $keys = array_keys($plugins);
        }
        $result = [];
        while (count($keys) > 0) {
            $test = array_shift($keys);
            $testObject = $this->getPluginById($test);
            $deps = $testObject->getActiveDependencies($this);
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
     * Check the current user "specificActionsRights" and filter the full registry actions with these.
     * @static
     * @param \DOMDocument $registry
     * @param ContextInterface $ctx
     * @return bool
     */
    private function filterRegistryFromRole(&$registry, ContextInterface $ctx)
    {
        $loggedUser = $ctx->getUser();
        if ($loggedUser == null) return false;
        $crtRepo = $ctx->getRepository();
        $crtRepoId = AJXP_REPO_SCOPE_ALL; // "ajxp.all";
        if ($crtRepo != null && $crtRepo instanceof Repository) {
            $crtRepoId = $crtRepo->getId();
        }
        $actionRights = $loggedUser->getMergedRole()->listActionsStatesFor($crtRepo);
        $changes = false;
        $xPath = new DOMXPath($registry);
        foreach ($actionRights as $pluginName => $actions) {
            foreach ($actions as $actionName => $enabled) {
                if ($enabled !== false) continue;
                $actions = $xPath->query("actions/action[@name='$actionName']");
                if (!$actions->length) {
                    continue;
                }
                $action = $actions->item(0);
                $action->parentNode->removeChild($action);
                $changes = true;
            }
        }
        $parameters = $loggedUser->getMergedRole()->listParameters();
        foreach ($parameters as $scope => $paramsPlugs) {
            if ($scope === AJXP_REPO_SCOPE_ALL || $scope === $crtRepoId || ($crtRepo != null && $crtRepo->hasParent() && $scope === AJXP_REPO_SCOPE_SHARED)) {
                foreach ($paramsPlugs as $plugId => $params) {
                    foreach ($params as $name => $value) {
                        // Search exposed plugin_configs, replace if necessary.
                        $searchparams = $xPath->query("plugins/*[@id='$plugId']/plugin_configs/property[@name='$name']");
                        if (!$searchparams->length) continue;
                        $param = $searchparams->item(0);
                        $newCdata = $registry->createCDATASection(json_encode($value));
                        $param->removeChild($param->firstChild);
                        $param->appendChild($newCdata);
                    }
                }
            }
        }
        return $changes;
    }

    /**
     * Build the XML Registry if not already built, and return it.
     * @static
     * @param bool $extendedVersion
     * @return \DOMDocument The registry
     */
    private function getXmlRegistry($extendedVersion = true)
    {
        if (!isSet($this->xmlRegistry) || ($this->registryVersion == "light" && $extendedVersion)) {
            $this->buildXmlRegistry( $extendedVersion );
            $this->registryVersion = ($extendedVersion ? "extended":"light");
        }
        return $this->xmlRegistry;
    }

    /**
     * Replace the current xml registry
     * @static
     * @param $registry
     * @param bool $extendedVersion
     */
    private function updateXmlRegistry($registry, $extendedVersion = true)
    {
        $this->xmlRegistry = $registry;
        $this->registryVersion = ($extendedVersion? "extended" : "light");
    }

    /**
     * Get a unique string identifier for caching purpose
     * @param bool $extendedVersion
     * @return string
     */
    private function getRegistryCacheKey($extendedVersion = true)
    {
        $phpContext = 'session';
        if(ApplicationState::getSapiRestBase() !== null){
            $phpContext = 'rest';
        }else if(ApplicationState::sapiIsCli()){
            $phpContext = 'cli';
        }
        $v = $extendedVersion ? "extended" : "light";
        return "xml_registry:". $phpContext . ":" . $v . ":" . $this->context->getStringIdentifier();
    }

    /**
     * Central function of the registry construction, merges some nodes into the existing registry.
     * @param \DOMDocument $original
     * @param $parentName
     * @param $uuidAttr
     * @param $childrenNodes
     * @param bool $doNotOverrideChildren
     * @return void
     */
    private function mergeNodes(&$original, $parentName, $uuidAttr, $childrenNodes, $doNotOverrideChildren = false)
    {
        // find or create parent
        $parentSelection = $original->getElementsByTagName($parentName);
        if ($parentSelection->length) {
            $parentNode = $parentSelection->item(0);
            $xPath = new \DOMXPath($original);
            /** @var \DOMElement $child */
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
                    /** @var \DOMElement $existingNode */
                    foreach ($childrenSel as $existingNode) {
                        if($existingNode->getAttribute("forbidOverride") == "true"){
                            continue;
                        }
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
     * @param \DOMNode $new
     * @param \DOMNode $old
     */
    private function mergeChildByTagName($new, &$old)
    {
        if (!$this->hasElementChild($new) || !$this->hasElementChild($old)) {
            $old->parentNode->replaceChild($new, $old);
            return;
        }
        /** @var \DOMElement $newChild */
        foreach ($new->childNodes as $newChild) {
            if($newChild->nodeType != XML_ELEMENT_NODE) continue;

            $found = null;
            /** @var \DOMElement $oldChild */
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
                    if($found->getAttribute("forbidOverride") == "true") {
                        continue;
                    }
                    $this->mergeChildByTagName($newChild->cloneNode(true), $found);
                }
            } else {
                // CloneNode or it's messing with the current foreach loop.
                $old->appendChild($newChild->cloneNode(true));
            }
        }
    }

    /**
     * Utilitary
     * @param \DOMNode $node
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
     * PluginsService constructor.
     * @param ContextInterface|null $ctx
     * @param string|null $pluginsDir
     */
    private function __construct($ctx = null, $pluginsDir = null)
    {
        $this->context = $ctx;
        if(empty($pluginsDir)){
            $pluginsDir = AJXP_INSTALL_PATH.DIRECTORY_SEPARATOR.AJXP_PLUGINS_FOLDER;
        }
        $this->pluginsDir = $pluginsDir;
    }

    public function __clone()
    {
        trigger_error("Cannot clone me, i'm a singleton!", E_USER_ERROR);
    }
}
