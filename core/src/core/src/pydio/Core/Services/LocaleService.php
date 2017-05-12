<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Services;

use Pydio\Core\Model\Context;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Utils\Vars\VarsFilter;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class LocaleService
 * @package Pydio\Core\Services
 */
class LocaleService
{
    /**
     * @var LocaleService
     */
    private static $instance;

    private $cache = [];

    private $currentLanguage;

    /**
     * Singleton method
     *
     * @return LocaleService the service instance
     */
    public static function getInstance()
    {
        if (!isSet(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }
        return self::$instance;
    }

    /**
     * LocaleService constructor.
     */
    private function __construct(){
        $this->cache["AVAILABLE_LANG"] = LocaleService::listAvailableLanguages();
    }
    
    /**
     * PUBLIC STATIC METHODS
     */
    /**
     * Set the language in the session
     * @static
     * @param string $lang
     * @return void
     */
    public static function setLanguage($lang)
    {
        $inst = self::getInstance();
        if (array_key_exists($lang, $inst->cache["AVAILABLE_LANG"])) {
            if($lang !== $inst->currentLanguage && isSet($inst->cache["MESSAGES"])){
                $inst->cache["MESSAGES"] = null;
            }
            $inst->currentLanguage = $lang;
            SessionService::setLanguage($lang);
        }
    }

    /**
     * Get the language from the session
     * @static
     * @return string
     */
    public static function getLanguage()
    {
        $lang = self::getInstance()->currentLanguage;
        if ($lang == null) {
            $lang = ConfService::getInstance()->getGlobalConf("DEFAULT_LANGUAGE");
        }
        if (empty($lang)) return "en";
        return $lang;
    }

    /**
     * Get the list of all "conf" messages
     * @static
     * @param bool $forceRefresh Refresh the list
     * @return array
     */
    public static function getConfigMessages($forceRefresh = false)
    {
        return self::getInstance()->getMessagesInstConf($forceRefresh);
    }

    /**
     * Get all i18n message
     * @static
     * @param bool $forceRefresh
     * @return array
     */
    public static function getMessages($forceRefresh = false)
    {
        return self::getInstance()->getMessagesInst($forceRefresh);
    }

    /**
     * Detect available languages from the core i18n library
     * @static
     * @return array
     */
    public static function listAvailableLanguages()
    {
        // Cache in session!
        if (SessionService::has(SessionService::LANGUAGES_KEY) && !isSet($_GET["refresh_langs"])) {
            return SessionService::fetch(SessionService::LANGUAGES_KEY);
        }
        $langDir = AJXP_COREI18N_FOLDER;
        $languages = array();
        if (($dh = opendir($langDir)) !== FALSE) {
            while (($file = readdir($dh)) !== false) {
                $matches = array();
                if (preg_match("/(.*)\.php/", $file, $matches) == 1) {
                    $fRadical = $matches[1];
                    include($langDir . "/" . $fRadical . ".php");
                    $langName = isSet($mess["languageLabel"]) ? $mess["languageLabel"] : "Not Found";
                    $languages[$fRadical] = $langName;
                }
            }
            closedir($dh);
        }
        if (count($languages)) {
            SessionService::save(SessionService::LANGUAGES_KEY, $languages);
        }
        return $languages;
    }

    /**
     * Clear the messages cache
     */
    public static function clearMessagesCache()
    {
        $i18nFiles = glob(dirname(AJXP_PLUGINS_MESSAGES_FILE) . "/i18n/*.ser");
        if (is_array($i18nFiles)) {
            foreach ($i18nFiles as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * @param $array
     */
    public static function contributeMessages($array){
        $inst = self::getInstance();
        if(!isSet($inst->cache["ADDITIONAL_MESSAGES"])){
            $inst->cache["ADDITIONAL_MESSAGES"] = [];
        }
        $inst->cache["ADDITIONAL_MESSAGES"] = $inst->cache["ADDITIONAL_MESSAGES"] + $array;
        if(isset($inst->cache["MESSAGES"])){
            unset($inst->cache["MESSAGES"]);
        }
    }

    /**
     * PRIVATE INSTANCE METHODS
     */
    /**
     * See static method
     * @param bool $forceRefresh
     * @return array
     */
    private function getMessagesInstConf($forceRefresh = false)
    {
        // make sure they are loaded
        $this->getMessagesInst($forceRefresh);
        return $this->cache["CONF_MESSAGES"];
    }

    /**
     * Get i18n messages
     * @param bool $forceRefresh
     * @return
     */
    private function getMessagesInst($forceRefresh = false)
    {
        $crtLang = self::getLanguage();
        $messageCacheDir = dirname(AJXP_PLUGINS_MESSAGES_FILE)."/i18n";
        $messageFile = $messageCacheDir."/".$crtLang."_".basename(AJXP_PLUGINS_MESSAGES_FILE);
        if (isSet($this->cache["MESSAGES"]) && !$forceRefresh) {
            return $this->cache["MESSAGES"];
        }
        if (!isset($this->cache["MESSAGES"]) && is_file($messageFile)) {
            include($messageFile);
            if (isSet($MESSAGES)) {
                $this->cache["MESSAGES"] = $MESSAGES;
            }
            if (isSet($CONF_MESSAGES)) {
                $this->cache["CONF_MESSAGES"] = $CONF_MESSAGES;
            }
        } else {
            $this->cache["MESSAGES"] = array();
            $this->cache["CONF_MESSAGES"] = array();
            $nodes = PluginsService::getInstance(Context::emptyContext())->searchAllManifests("//i18n", "nodes");
            /** @var \DOMElement $node */
            foreach ($nodes as $node) {
                $nameSpace = $node->getAttribute("namespace");
                $path = AJXP_INSTALL_PATH."/".$node->getAttribute("path");
                $lang = $crtLang;
                if (!is_file($path."/".$crtLang.".php")) {
                    $lang = "en"; // Default language, minimum required.
                }
                if (is_file($path."/".$lang.".php")) {
                    require($path."/".$lang.".php");
                    if (isSet($mess)) {
                        foreach ($mess as $key => $message) {
                            $this->cache["MESSAGES"][(empty($nameSpace)?"":$nameSpace.".").$key] = $message;
                        }
                    }
                }
                $lang = $crtLang;
                if (!is_file($path."/conf/".$crtLang.".php")) {
                    $lang = "en";
                }
                if (is_file($path."/conf/".$lang.".php")) {
                    $mess = array();
                    require($path."/conf/".$lang.".php");
                    $this->cache["CONF_MESSAGES"] = array_merge($this->cache["CONF_MESSAGES"], $mess);
                }
            }
            if(!is_dir($messageCacheDir)) mkdir($messageCacheDir);
            VarsFilter::filterI18nStrings($this->cache["MESSAGES"]);
            VarsFilter::filterI18nStrings($this->cache["CONF_MESSAGES"]);
            @file_put_contents($messageFile, "<?php \$MESSAGES = ".var_export($this->cache["MESSAGES"], true) ." ; \$CONF_MESSAGES = ".var_export($this->cache["CONF_MESSAGES"], true) ." ; ");
        }
        if(isSet($this->cache["ADDITIONAL_MESSAGES"])){
            $this->cache["MESSAGES"] = $this->cache["MESSAGES"] + $this->cache["ADDITIONAL_MESSAGES"];
        }

        return $this->cache["MESSAGES"];
    }


}