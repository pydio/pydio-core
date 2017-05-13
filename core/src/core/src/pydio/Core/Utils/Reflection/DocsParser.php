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
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Helper methods to parse Pydio methods / hooks and generate documentation
 * @package Pydio\Core\Utils
 */
class DocsParser
{
    /**
     * File where to store JSON
     * @return string
     */
    public static function getHooksFile()
    {
        return AJXP_INSTALL_PATH . "/" . AJXP_DOCS_FOLDER . "/hooks.json";
    }

    /**
     * Browse application registered hooks and send their definition to Hooks File.
     */
    public static function extractHooksToDoc()
    {
        $docFile = self::getHooksFile();
        if (is_file($docFile)) {
            copy($docFile, $docFile . ".bak");
            $existingHooks = json_decode(file_get_contents($docFile), true);
        } else {
            $existingHooks = array();
        }
        $allPhpFiles1 = self::glob_recursive(AJXP_BIN_FOLDER . "/*.php");
        $allPhpFiles2 = self::glob_recursive(AJXP_INSTALL_PATH . "/plugins/*.php");
        $allPhpFiles3 = self::glob_recursive(AJXP_INSTALL_PATH . "/conf/*.php");
        $allPhpFiles = array_merge(array_merge($allPhpFiles1, $allPhpFiles2), $allPhpFiles3);
        $hooks = array();
        foreach ($allPhpFiles as $phpFile) {
            $fileContent = file($phpFile);
            foreach ($fileContent as $lineNumber => $line) {
                if (preg_match_all('/Controller::applyHook\("([^"]+)", (.*)\)/', $line, $matches)) {
                    $names = $matches[1];
                    $params = $matches[2];
                    foreach ($names as $index => $hookName) {
                        if (!isSet($hooks[$hookName])) $hooks[$hookName] = array("TRIGGERS" => array(), "LISTENERS" => array());
                        $filename = substr($phpFile, strlen(AJXP_INSTALL_PATH));
                        if (strpos($filename, "/plugins") === 0) {
                            $source = explode("/", $filename)[2];
                        } else {
                            $parts = explode("/", $filename);
                            $source = str_replace(array("class.", ".php"), "", array_pop($parts));
                        }
                        if (!isSet($hooks[$hookName]["TRIGGERS"][$source])) {
                            $hooks[$hookName]["TRIGGERS"][$source] = array();
                        }
                        $hooks[$hookName]["TRIGGERS"][$source][] = array(
                            "FILE" => $filename,
                            "LINE" => $lineNumber
                        );
                        $hooks[$hookName]["PARAMETER_SAMPLE"] = $params[$index];
                    }
                }

            }
        }
        $registryHooks = PluginsService::getInstance(Context::emptyContext())->searchAllManifests("//hooks/serverCallback", "xml", false, false, true);
        $regHooks = array();
        /** @var \DOMElement $xmlHook */
        foreach ($registryHooks as $xmlHook) {
            $name = $xmlHook->getAttribute("hookName");
            $method = $xmlHook->getAttribute("methodName");
            $pluginId = $xmlHook->getAttribute("pluginId");
            $deferred = $xmlHook->getAttribute("defer") === "true";
            if ($pluginId == "") $pluginId = $xmlHook->parentNode->parentNode->parentNode->getAttribute("id");
            if (!isSet($regHooks[$name])) $regHooks[$name] = array();
            $data = array("PLUGIN_ID" => $pluginId, "METHOD" => $method);
            if ($deferred) $data["DEFERRED"] = true;
            $regHooks[$name][] = $data;
        }

        foreach ($hooks as $h => $data) {

            if (isSet($regHooks[$h])) {
                $data["LISTENERS"] = $regHooks[$h];
            }
            if (isSet($existingHooks[$h])) {
                $existingHooks[$h]["TRIGGERS"] = $data["TRIGGERS"];
                $existingHooks[$h]["LISTENERS"] = $data["LISTENERS"];
                $existingHooks[$h]["PARAMETER_SAMPLE"] = $data["PARAMETER_SAMPLE"];
            } else {
                $existingHooks[$h] = $data;
            }
        }
        file_put_contents($docFile, StringHelper::prettyPrintJSON(json_encode($existingHooks)));

    }

    /**
     * Does not support flag GLOB_BRACE
     * @param $pattern
     * @param int $flags
     * @return array
     */
    private static function glob_recursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::glob_recursive($dir.'/'.basename($pattern), $flags));
        }
        return $files;
    }


}