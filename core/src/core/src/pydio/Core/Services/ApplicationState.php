<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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

use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Utils\Vars\PathUtils;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Various helpers to get application state, global folders, etc.
 * @package Pydio\Core\Utils
 */
class ApplicationState
{
    /**
     * @var null|string
     */
    private static $restBase = null;

    /**
     * @var string
     */
    private static $sapiType = "session";

    /**
     * @var string
     */
    private static $minisiteHash = null;

    /**
     * @var bool
     */
    public static $silenceInstantMessages = false;

    /**
     * @var bool
     */
    private static $isAdminMode = false;

    /**
     * @param string $restBase
     */
    public static function setSapiRestBase($restBase){
        self::$sapiType = "rest";
        self::$restBase = $restBase;
    }

    public static function setSapiTypeCLI(){
        self::$sapiType = "cli";
    }

    /**
     * @return bool
     */
    public static function sapiIsCli(){
        return php_sapi_name() === "cli" || self::$sapiType === "cli";
    }

    /**
     * @return null|string
     */
    public static function getSapiRestBase(){
        return self::$restBase;
    }

    /**
     * @return bool
     */
    public static function sapiUsesSession(){
        return !self::sapiIsCli() && self::$restBase === null;
    }

    /**
     * @param $hash
     */
    public static function setStateMinisite($hash){
        self::$minisiteHash = $hash;
    }

    /**
     * @return bool
     */
    public static function hasMinisiteHash(){
        return self::$minisiteHash !== null;
    }

    /**
     * @return string
     */
    public static function getMinisiteHash(){
        return self::$minisiteHash;
    }

    /**
     * Set application in admin only mode
     */
    public static function setAdminMode(){
        self::$isAdminMode = true;
    }

    /**
     * @return bool
     */
    public static function isAdminMode(){
        return self::$isAdminMode;
    }

    /**
     * Check if data/cache/first_run_passed file exists or not
     * @return bool
     */
    public static function detectApplicationFirstRun()
    {
        return !file_exists(AJXP_CACHE_DIR . "/first_run_passed") &&  !file_exists(AJXP_DATA_PATH . "/plugins/boot.conf/first_run_passed");
    }

    /**
     * Touch data/cache/first_run_passed file
     */
    public static function setApplicationFirstRunPassed()
    {
        if(!is_file(AJXP_DATA_PATH . "/plugins/boot.conf")){
            @mkdir(AJXP_DATA_PATH . "/plugins/boot.conf", 0666, true);
        }
        @file_put_contents(AJXP_DATA_PATH . "/plugins/boot.conf/first_run_passed", "true");
    }

    /**
     * Search include path for a given file
     * @static
     * @param string $file
     * @return bool
     */
    public static function searchIncludePath($file)
    {
        $ps = explode(PATH_SEPARATOR, ini_get('include_path'));
        foreach ($ps as $path) {
            if (@file_exists($path . DIRECTORY_SEPARATOR . $file)) return true;
        }
        if (@file_exists($file)) return true;
        return false;
    }

    /**
     * @static
     * @param $from
     * @param $to
     * @return string
     */
    public static function getTravelPath($from, $to)
    {
        $from = explode('/', $from);
        $to = explode('/', $to);
        $relPath = $to;

        foreach ($from as $depth => $dir) {
            // find first non-matching dir
            if ($dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if ($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                } else {
                    $relPath[0] = './' . $relPath[0];
                }
            }
        }
        return implode('/', $relPath);
    }

    /**
     * Build the current server URL
     * @param bool $withURI Wether to return the "path" part in scheme://host[/path]
     * @param bool $forceInternal Wether to force server local IP detection, or to use external URL if it is set in the configuration
     * @static
     * @return string
     */
    public static function detectServerURL($withURI = false, $forceInternal = false)
    {
        $setUrl = ConfService::getGlobalConf("SERVER_URL");
        if (!empty($setUrl) && !$forceInternal) {
            return (string)$setUrl;
        }
        if (self::sapiIsCli()) {
            Logger::debug("WARNING, THE SERVER_URL IS NOT SET, WE CANNOT BUILD IT WHEN WORKING IN CLI");
            return "";
        }
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $port = (($protocol === 'http' && $_SERVER['SERVER_PORT'] == 80 || $protocol === 'https' && $_SERVER['SERVER_PORT'] == 443)
            ? "" : ":" . $_SERVER['SERVER_PORT']);
        $name = $_SERVER["SERVER_NAME"];
        if (!$withURI) {
            return "$protocol://$name$port";
        } else {
            $uri = dirname($_SERVER["REQUEST_URI"]);
            $apiBase = self::getSapiRestBase();
            if (!empty($apiBase)) {
                // apiBase must be /path/to/pydio/endpoint => remove endpoint
                $uri = PathUtils::forwardSlashDirname(rtrim($apiBase));
                if(empty($uri)) $uri = "/";
            }
            return "$protocol://$name$port" . $uri;
        }
    }

    /**
     * @param RepositoryInterface $repository
     * @return string
     */
    public static function getWorkspaceShortcutURL($repository)
    {
        if (empty($repository)) {
            return "";
        }
        $repoSlug = $repository->getSlug();
        $skipHistory = ConfService::getGlobalConf("SKIP_USER_HISTORY", "conf");
        if ($skipHistory) {
            $prefix = "/ws-";
        } else {
            $prefix = "?goto=";
        }
        return trim(self::detectServerURL(true), "/") . $prefix . $repoSlug;
    }

    /**
     * Try to load the tmp dir from the CoreConf AJXP_TMP_DIR, or the constant AJXP_TMP_DIR,
     * or the sys_get_temp_dir
     * @static
     * @return mixed|null|string
     */
    public static function getTemporaryFolder()
    {
        $conf = ConfService::getGlobalConf("AJXP_TMP_DIR");
        if (!empty($conf)) {
            return $conf;
        }
        if (defined("AJXP_TMP_DIR") && AJXP_TMP_DIR != "") {
            return AJXP_TMP_DIR;
        }
        return realpath(sys_get_temp_dir());
    }

    /**
     * Try to set an ini config, without errors
     * @static
     * @param string $paramName
     * @param string $paramValue
     * @return void
     */
    public static function safeIniSet($paramName, $paramValue)
    {
        $current = ini_get($paramName);
        if ($current == $paramValue) return;
        @ini_set($paramName, $paramValue);
    }
}