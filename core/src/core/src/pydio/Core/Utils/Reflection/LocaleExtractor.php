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
namespace Pydio\Core\Utils\Reflection;

use Pydio\Core\Model\Context;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\LocaleService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class LocaleExtractor
 * @package Pydio\Core\Utils
 */
class LocaleExtractor
{

    /**
     * i18n utilitary for extracting the CONF_MESSAGE[] strings out of the XML files
     * @static
     * @return void
     */
    public static function extractConfStringsFromManifests()
    {
        $plugins = PluginsService::getInstance(Context::emptyContext())->getDetectedPlugins();
        /**
         * @var Plugin $plug
         */
        foreach ($plugins as $pType => $plugs) {
            foreach ($plugs as $plug) {
                $lib = $plug->getManifestRawContent("//i18n", "nodes");
                if (!$lib->length) continue;
                /** @var \DOMElement $library */
                $library = $lib->item(0);
                $namespace = $library->getAttribute("namespace");
                $path = $library->getAttribute("path");
                $xml = $plug->getManifestRawContent();
                // for core, also load mixins
                $refFile = AJXP_INSTALL_PATH . "/" . $path . "/conf/en.php";
                $reference = array();
                if (preg_match_all("/CONF_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $match[1] = str_replace(array("[", "]"), "", $match[1]);
                        $reference[$match[1]] = $match[1];
                    }
                }
                if ($namespace == "") {
                    $mixXml = file_get_contents(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/core.ajaxplorer/ajxp_mixins.xml");
                    if (preg_match_all("/MIXIN_MESSAGE(\[.*?\])/", $mixXml, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $match) {
                            $match[1] = str_replace(array("[", "]"), "", $match[1]);
                            $reference[$match[1]] = $match[1];
                        }
                    }
                }
                if (count($reference)) {
                    self::updateI18nFromRef($refFile, $reference);
                }
            }
        }
    }

    /**
     * Browse the i18n libraries and update the languages with the strings missing
     * @static
     * @param string $createLanguage
     * @param string $pluginId
     * @return void
     */
    public static function updateAllI18nLibraries($createLanguage = "", $pluginId = "")
    {
        // UPDATE EN => OTHER LANGUAGES
        $nodes = PluginsService::getInstance(Context::emptyContext())->searchAllManifests("//i18n", "nodes");
        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            $nameSpace = $node->getAttribute("namespace");
            if(!empty($pluginId)){
                $plug = $node->parentNode->parentNode->parentNode->getAttribute("id");
                if($plug !== $pluginId) continue;
            }
            $path = AJXP_INSTALL_PATH . "/" . $node->getAttribute("path");
            if ($nameSpace == "") {
                self::updateI18nFiles($path, false, $createLanguage);
                self::updateI18nFiles($path . "/conf", true, $createLanguage);
            } else {
                self::updateI18nFiles($path, true, $createLanguage);
                self::updateI18nFiles($path . "/conf", true, $createLanguage);
            }
        }
    }

    /**
     * Patch the languages files of an i18n library with the references strings from the "en" file.
     * @static
     * @param $baseDir
     * @param bool $detectLanguages
     * @param string $createLanguage
     */
    public static function updateI18nFiles($baseDir, $detectLanguages = true, $createLanguage = "")
    {
        if (!is_dir($baseDir) || !is_file($baseDir . "/en.php")) return;
        if ($createLanguage != "" && !is_file($baseDir . "/$createLanguage.php")) {
            @copy(AJXP_INSTALL_PATH . "/plugins/core.ajaxplorer/i18n-template.php", $baseDir . "/$createLanguage.php");
        }
        if (!$detectLanguages) {
            $languages = LocaleService::listAvailableLanguages();
            $filenames = array();
            foreach ($languages as $key => $value) {
                $filenames[] = $baseDir . "/" . $key . ".php";
            }
        } else {
            $filenames = glob($baseDir . "/*.php");
        }

        $mess = array();
        include($baseDir . "/en.php");
        $reference = $mess;

        foreach ($filenames as $filename) {
            self::updateI18nFromRef($filename, $reference);
        }
    }

    /**
     * i18n Utilitary
     * @static
     * @param $filename
     * @param $reference
     */
    public static function updateI18nFromRef($filename, $reference)
    {
        if (!is_file($filename)) return;
        $mess = array();
        include($filename);
        $missing = array();
        foreach ($reference as $messKey => $message) {
            if (!array_key_exists($messKey, $mess)) {
                $missing[] = "\"$messKey\" => \"$message\",";
            }
        }
        //print_r($missing);
        if (count($missing)) {
            $header = array();
            $currentMessages = array();
            $footer = array();
            $fileLines = file($filename);
            $insideArray = false;
            foreach ($fileLines as $line) {
                if (strstr($line, "\"") !== false) {
                    $currentMessages[] = trim($line);
                    $insideArray = true;
                } else {
                    if (!$insideArray && strstr($line, ");") !== false) $insideArray = true;
                    if (!$insideArray) {
                        $header[] = trim($line);
                    } else {
                        $footer[] = trim($line);
                    }
                }
            }
            $currentMessages = array_merge($header, $currentMessages, $missing, $footer);
            file_put_contents($filename, join("\n", $currentMessages));
        }
    }
}