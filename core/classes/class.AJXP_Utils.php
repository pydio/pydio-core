<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die('Access not allowed');

define('AJXP_SANITIZE_HTML', 1);
define('AJXP_SANITIZE_HTML_STRICT', 2);
define('AJXP_SANITIZE_ALPHANUM', 3);
define('AJXP_SANITIZE_EMAILCHARS', 4);
/**
 * @package info.ajaxplorer.core
 */
/**
 * Various functions used everywhere, static library
 */
class AJXP_Utils
{

    /**
     * Performs a natural sort on the array keys.
     * Behaves the same as ksort() with natural sorting added.
     *
     * @param Array $array The array to sort
     * @return boolean
     */
    static function natksort(&$array)
    {
        uksort($array, 'strnatcasecmp');
        return true;
    }

    /**
     * Performs a reverse natural sort on the array keys
     * Behaves the same as krsort() with natural sorting added.
     *
     * @param Array $array The array to sort
     * @return boolean
     */
    static function natkrsort(&$array)
    {
        natksort($array);
        $array = array_reverse($array, TRUE);
        return true;
    }

    /**
     * Remove all "../../" tentatives, replace double slashes
     * @static
     * @param string $path
     * @return string
     */
    static function securePath($path)
    {
        if ($path == null) $path = "";
        //
        // REMOVE ALL "../" TENTATIVES
        //
        $dirs = explode('/', $path);
        for ($i = 0; $i < count($dirs); $i++)
        {
            if ($dirs[$i] == '.' or $dirs[$i] == '..') {
                $dirs[$i] = '';
            }
        }
        // rebuild safe directory string
        $path = implode('/', $dirs);

        //
        // REPLACE DOUBLE SLASHES
        //
        while (preg_match('/\/\//', $path))
        {
            $path = str_replace('//', '/', $path);
        }
        return $path;
    }

    /**
     * Function to clean a string from specific characters
     *
     * @static
     * @param string $s
     * @param int $level Can be AJXP_SANITIZE_ALPHANUM, AJXP_SANITIZE_EMAILCHARS, AJXP_SANITIZE_HTML, AJXP_SANITIZE_HTML_STRICT
     * @param string $expand
     * @return mixed|string
     */
    public static function sanitize($s, $level = AJXP_SANITIZE_HTML, $expand = 'script|style|noframes|select|option')
    {
        /**/ //prep the string
        $s = ' ' . $s;
        if ($level == AJXP_SANITIZE_ALPHANUM) {
            return preg_replace("/[^a-zA-Z0-9_\-\.]/", "", $s);
        } else if ($level == AJXP_SANITIZE_EMAILCHARS) {
            return preg_replace("/[^a-zA-Z0-9_\-\.@!%\+=|~\?]/", "", $s);
        }

        //begin removal
        /**/ //remove comment blocks
        while (stripos($s, '<!--') > 0) {
            $pos[1] = stripos($s, '<!--');
            $pos[2] = stripos($s, '-->', $pos[1]);
            $len[1] = $pos[2] - $pos[1] + 3;
            $x = substr($s, $pos[1], $len[1]);
            $s = str_replace($x, '', $s);
        }

        /**/ //remove tags with content between them
        if (strlen($expand) > 0) {
            $e = explode('|', $expand);
            for ($i = 0; $i < count($e); $i++) {
                while (stripos($s, '<' . $e[$i]) > 0) {
                    $len[1] = strlen('<' . $e[$i]);
                    $pos[1] = stripos($s, '<' . $e[$i]);
                    $pos[2] = stripos($s, $e[$i] . '>', $pos[1] + $len[1]);
                    $len[2] = $pos[2] - $pos[1] + $len[1];
                    $x = substr($s, $pos[1], $len[2]);
                    $s = str_replace($x, '', $s);
                }
            }
        }

        $s = strip_tags($s);
        if ($level == AJXP_SANITIZE_HTML_STRICT) {
            $s = preg_replace("/[\",;\/`<>:\*\|\?!\^\\\]/", "", $s);
        } else {
            $s = str_replace(array("<", ">"), array("&lt;", "&gt;"), $s);
        }
        return trim($s);
    }

    /**
     * Perform standard urldecode, sanitization, securepath and magicDequote
     * @static
     * @param $data
     * @param int $sanitizeLevel
     * @return string
     */
    public static function decodeSecureMagic($data, $sanitizeLevel = AJXP_SANITIZE_HTML)
    {
        return SystemTextEncoding::fromUTF8(AJXP_Utils::sanitize(AJXP_Utils::securePath(SystemTextEncoding::magicDequote($data)), $sanitizeLevel));
    }
    /**
     * Try to load the tmp dir from the CoreConf AJXP_TMP_DIR, or the constant AJXP_TMP_DIR,
     * or the sys_get_temp_dir
     * @static
     * @return mixed|null|string
     */
    public static function getAjxpTmpDir()
    {
        if (ConfService::getCoreConf("AJXP_TMP_DIR") != "") {
            return ConfService::getCoreConf("AJXP_TMP_DIR");
        }
        if (defined("AJXP_TMP_DIR") && AJXP_TMP_DIR != "") {
            return AJXP_TMP_DIR;
        }
        return realpath(sys_get_temp_dir());
    }

    /**
     * Parse a Comma-Separated-Line value
     * @static
     * @param $string
     * @param bool $hash
     * @return array
     */
    public static function parseCSL($string, $hash = false)
    {
        $exp = array_map("trim", explode(",", $string));
        if (!$hash) return $exp;
        $assoc = array();
        foreach ($exp as $explVal) {
            $reExp = explode("|", $explVal);
            if (count($reExp) == 1) $assoc[$reExp[0]] = $reExp[0];
            else $assoc[$reExp[0]] = $reExp[1];
        }
        return $assoc;
    }

    /**
     * Parse the $fileVars[] PHP errors
     * @static
     * @param $boxData
     * @return array|null
     */
    static function parseFileDataErrors($boxData)
    {
        $mess = ConfService::getMessages();
        $userfile_error = $boxData["error"];
        $userfile_tmp_name = $boxData["tmp_name"];
        $userfile_size = $boxData["size"];
        if ($userfile_error != UPLOAD_ERR_OK) {
            $errorsArray = array();
            $errorsArray[UPLOAD_ERR_FORM_SIZE] = $errorsArray[UPLOAD_ERR_INI_SIZE] = array(409, "File is too big! Max is" . ini_get("upload_max_filesize"));
            $errorsArray[UPLOAD_ERR_NO_FILE] = array(410, "No file found on server!");
            $errorsArray[UPLOAD_ERR_PARTIAL] = array(410, "File is partial");
            $errorsArray[UPLOAD_ERR_INI_SIZE] = array(410, "No file found on server!");
            if ($userfile_error == UPLOAD_ERR_NO_FILE) {
                // OPERA HACK, do not display "no file found error"
                if (!ereg('Opera', $_SERVER['HTTP_USER_AGENT'])) {
                    return $errorsArray[$userfile_error];
                }
            }
            else
            {
                return $errorsArray[$userfile_error];
            }
        }
        if ($userfile_tmp_name == "none" || $userfile_size == 0) {
            return array(410, $mess[31]);
        }
        return null;
    }

    /**
     * Utilitary to pass some parameters directly at startup :
     * + repository_id / folder
     * + compile & skipDebug
     * + update_i18n, extract, create
     * + external_selector_type
     * + skipIOS
     * + gui
     * @static
     * @param $parameters
     * @param $output
     * @param $session
     * @return void
     */
    public static function parseApplicationGetParameters($parameters, &$output, &$session)
    {
        $output["EXT_REP"] = "/";

        if (isSet($parameters["repository_id"]) && isSet($parameters["folder"])) {
            $repository = ConfService::getRepositoryById($parameters["repository_id"]);
            if ($repository == null) {
                $repository = ConfService::getRepositoryByAlias($parameters["repository_id"]);
                if ($repository != null) {
                    $parameters["repository_id"] = $repository->getId();
                }
            }
            require_once(AJXP_BIN_FOLDER . "/class.SystemTextEncoding.php");
            if (AuthService::usersEnabled()) {
                $loggedUser = AuthService::getLoggedUser();
                if ($loggedUser != null && $loggedUser->canSwitchTo($parameters["repository_id"])) {
                    $output["EXT_REP"] = SystemTextEncoding::toUTF8(urldecode($parameters["folder"]));
                    $loggedUser->setArrayPref("history", "last_repository", $parameters["repository_id"]);
                    $loggedUser->setPref("pending_folder", SystemTextEncoding::toUTF8(AJXP_Utils::decodeSecureMagic($parameters["folder"])));
                    $loggedUser->save();
                    AuthService::updateUser($loggedUser);
                } else {
                    $session["PENDING_REPOSITORY_ID"] = $parameters["repository_id"];
                    $session["PENDING_FOLDER"] = SystemTextEncoding::toUTF8(AJXP_Utils::decodeSecureMagic($parameters["folder"]));
                }
            } else {
                ConfService::switchRootDir($parameters["repository_id"]);
                $output["EXT_REP"] = SystemTextEncoding::toUTF8(urldecode($parameters["folder"]));
            }
        }


        if (isSet($parameters["skipDebug"])) {
            ConfService::setConf("JS_DEBUG", false);
        }
        if (ConfService::getConf("JS_DEBUG") && isSet($parameters["compile"])) {
            require_once(AJXP_BIN_FOLDER . "/class.AJXP_JSPacker.php");
            AJXP_JSPacker::pack();
        }
        if (ConfService::getConf("JS_DEBUG") && isSet($parameters["update_i18n"])) {
            if (isSet($parameters["extract"])) {
                self::extractConfStringsFromManifests();
            }
            self::updateAllI18nLibraries((isSet($parameters["create"]) ? $parameters["create"] : ""));
        }
        if (ConfService::getConf("JS_DEBUG") && isSet($parameters["clear_plugins_cache"])) {
            @unlink(AJXP_PLUGINS_CACHE_FILE);
            @unlink(AJXP_PLUGINS_REQUIRES_FILE);
        }

        if (isSet($parameters["external_selector_type"])) {
            $output["SELECTOR_DATA"] = array("type" => $parameters["external_selector_type"], "data" => $parameters);
        }

        if (isSet($parameters["skipIOS"])) {
            setcookie("SKIP_IOS", "true");
        }
        if (isSet($parameters["gui"])) {
            setcookie("AJXP_GUI", $parameters["gui"]);
            if ($parameters["gui"] == "light") $session["USE_EXISTING_TOKEN_IF_EXISTS"] = true;
        } else {
            if (isSet($session["USE_EXISTING_TOKEN_IF_EXISTS"])) {
                unset($session["USE_EXISTING_TOKEN_IF_EXISTS"]);
            }
            setcookie("AJXP_GUI", null);
        }
    }

    /**
     * Remove windows carriage return
     * @static
     * @param $fileContent
     * @return mixed
     */
    static function removeWinReturn($fileContent)
    {
        $fileContent = str_replace(chr(10), "", $fileContent);
        $fileContent = str_replace(chr(13), "", $fileContent);
        return $fileContent;
    }

    /**
     * Get the filename extensions using ConfService::getRegisteredExtensions()
     * @static
     * @param string $fileName
     * @param string $mode "image" or "text"
     * @param bool $isDir
     * @return string Returns the icon name ("image") or the mime label ("text")
     */
    static function mimetype($fileName, $mode, $isDir)
    {
        $mess = ConfService::getMessages();
        $fileName = strtolower($fileName);
        $EXTENSIONS = ConfService::getRegisteredExtensions();
        if ($isDir) {
            $mime = $EXTENSIONS["folder"];
        } else {
            foreach ($EXTENSIONS as $ext) {
                if (preg_match("/\.$ext[0]$/", $fileName)) {
                    $mime = $ext;
                }
            }
        }
        if (!isSet($mime)) {
            $mime = $EXTENSIONS["unkown"];
        }
        if (is_numeric($mime[2]) || array_key_exists($mime[2], $mess)) {
            $mime[2] = $mess[$mime[2]];
        }
        return (($mode == "image" ? $mime[1] : $mime[2]));
    }
    /**
     * Gather a list of mime that must be treated specially. Used for dynamic replacement in XML mainly.
     * @static
     * @param string $keyword "editable", "image", "audio", "zip"
     * @return string
     */
    static function getAjxpMimes($keyword)
    {
        if ($keyword == "editable") {
            // Gather editors!
            $pServ = AJXP_PluginsService::getInstance();
            $plugs = $pServ->getPluginsByType("editor");
            //$plugin = new AJXP_Plugin();
            $mimes = array();
            foreach ($plugs as $plugin) {
                $node = $plugin->getManifestRawContent("/editor/@mimes", "node");
                $openable = $plugin->getManifestRawContent("/editor/@openable", "node");
                if ($openable->item(0) && $openable->item(0)->value == "true" && $node->item(0)) {
                    $mimestring = $node->item(0)->value;
                    $mimesplit = explode(",", $mimestring);
                    foreach ($mimesplit as $value) {
                        $mimes[$value] = $value;
                    }
                }
            }
            return implode(",", array_values($mimes));
        } else if ($keyword == "image") {
            return "png,bmp,jpg,jpeg,gif";
        } else if ($keyword == "audio") {
            return "mp3";
        } else if ($keyword == "zip") {
            if (ConfService::zipEnabled()) {
                return "zip,ajxp_browsable_archive";
            } else {
                return "none_allowed";
            }
        }
        return "";
    }
    /**
     * Whether a file is to be considered as an image or not
     * @static
     * @param $fileName
     * @return bool
     */
    static function is_image($fileName)
    {
        if (preg_match("/\.png$|\.bmp$|\.jpg$|\.jpeg$|\.gif$/i", $fileName)) {
            return 1;
        }
        return 0;
    }
    /**
     * Whether a file is to be considered as an mp3... Should be DEPRECATED
     * @static
     * @param string $fileName
     * @return bool
     * @deprecated
     */
    static function is_mp3($fileName)
    {
        if (preg_match("/\.mp3$/i", $fileName)) return 1;
        return 0;
    }
    /**
     * Static image mime type headers
     * @static
     * @param $fileName
     * @return string
     */
    static function getImageMimeType($fileName)
    {
        if (preg_match("/\.jpg$|\.jpeg$/i", $fileName)) {
            return "image/jpeg";
        }
        else if (preg_match("/\.png$/i", $fileName)) {
            return "image/png";
        }
        else if (preg_match("/\.bmp$/i", $fileName)) {
            return "image/bmp";
        }
        else if (preg_match("/\.gif$/i", $fileName)) {
            return "image/gif";
        }
    }
    /**
     * Headers to send when streaming
     * @static
     * @param $fileName
     * @return bool|string
     */
    static function getStreamingMimeType($fileName)
    {
        if (preg_match("/\.mp3$/i", $fileName)) {
            return "audio/mp3";
        }
        else if (preg_match("/\.wav$/i", $fileName)) {
            return "audio/wav";
        }
        else if (preg_match("/\.aac$/i", $fileName)) {
            return "audio/aac";
        }
        else if (preg_match("/\.m4a$/i", $fileName)) {
            return "audio/m4a";
        }
        else if (preg_match("/\.aiff$/i", $fileName)) {
            return "audio/aiff";
        }
        else if (preg_match("/\.mp4$/i", $fileName)) {
            return "video/mp4";
        }
        else if (preg_match("/\.mov$/i", $fileName)) {
            return "video/quicktime";
        }
        else if (preg_match("/\.m4v$/i", $fileName)) {
            return "video/m4v";
        }
        else if (preg_match("/\.3gp$/i", $fileName)) {
            return "video/3gpp";
        }
        else if (preg_match("/\.3g2$/i", $fileName)) {
            return "video/3gpp2";
        }
        else return false;
    }

    /**
     * Display a human readable string for a bytesize (1MB, 2,3Go, etc)
     * @static
     * @param $filesize
     * @param bool $phpConfig
     * @return string
     */
    static function roundSize($filesize, $phpConfig = false)
    {
        $mess = ConfService::getMessages();
        $size_unit = $mess["byte_unit_symbol"];
        if ($filesize < 0) {
            $filesize = sprintf("%u", $filesize);
        }
        if ($filesize >= 1073741824) {
            $filesize = round($filesize / 1073741824 * 100) / 100 . ($phpConfig ? "G" : " G" . $size_unit);
        }
        elseif ($filesize >= 1048576) {
            $filesize = round($filesize / 1048576 * 100) / 100 . ($phpConfig ? "M" : " M" . $size_unit);
        }
        elseif ($filesize >= 1024) {
            $filesize = round($filesize / 1024 * 100) / 100 . ($phpConfig ? "K" : " K" . $size_unit);
        }
        else {
            $filesize = $filesize . " " . $size_unit;
        }
        if ($filesize == 0) {
            $filesize = "-";
        }
        return $filesize;
    }

    /**
     * Hidden files start with dot
     * @static
     * @param string $fileName
     * @return bool
     */
    static function isHidden($fileName)
    {
        return (substr($fileName, 0, 1) == ".");
    }

    /**
     * Whether a file is a browsable archive
     * @static
     * @param string $fileName
     * @return int
     */
    static function isBrowsableArchive($fileName)
    {
        return preg_match("/\.zip$/i", $fileName);
    }

    /**
     * Convert a shorthand byte value from a PHP configuration directive to an integer value
     * @param    string   $value
     * @return   int
     */
    static function convertBytes($value)
    {
        if (is_numeric($value)) {
            return $value;
        }
        else
        {
            $value_length = strlen($value);
            $qty = substr($value, 0, $value_length - 1);
            $unit = strtolower(substr($value, $value_length - 1));
            switch ($unit)
            {
                case 'k':
                    $qty *= 1024;
                    break;
                case 'm':
                    $qty *= 1048576;
                    break;
                case 'g':
                    $qty *= 1073741824;
                    break;
            }
            return $qty;
        }
    }

    /**
     * Replace specific chars by their XML Entities, for use inside attributes value
     * @static
     * @param $string
     * @param bool $toUtf8
     * @return mixed|string
     */
    static function xmlEntities($string, $toUtf8 = false)
    {
        $xmlSafe = str_replace(array("&", "<", ">", "\"", "\n", "\r"), array("&amp;", "&lt;", "&gt;", "&quot;", "&#13;", "&#10;"), $string);
        if ($toUtf8) {
            return SystemTextEncoding::toUTF8($xmlSafe);
        } else {
            return $xmlSafe;
        }
    }

    /**
     * Search include path for a given file
     * @static
     * @param string $file
     * @return bool
     */
    static public function searchIncludePath($file)
    {
        $ps = explode(PATH_SEPARATOR, ini_get('include_path'));
        foreach ($ps as $path)
        {
            if (@file_exists($path . DIRECTORY_SEPARATOR . $file)) return true;
        }
        if (@file_exists($file)) return true;
        return false;
    }

    /**
     * Build the current server URL
     * @static
     * @return string
     */
    static function detectServerURL()
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
        $port = (($protocol === 'http' && $_SERVER['SERVER_PORT'] == 80 || $protocol === 'https' && $_SERVER['SERVER_PORT'] == 443)
                ? "" : ":" . $_SERVER['SERVER_PORT']);
        $name = $_SERVER["SERVER_NAME"];
        return "$protocol://$name$port";
    }

    /**
     * Modifies a string to remove all non ASCII characters and spaces.
     * @param string $text
     * @return string
     */
    static public function slugify($text)
    {
        if (empty($text)) return "";
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);

        // trim
        $text = trim($text, '-');

        // transliterate
        if (function_exists('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }

        // lowercase
        $text = strtolower($text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    /**
     * i18n utilitary for extracting the CONF_MESSAGE[] strings out of the XML files
     * @static
     * @return void
     */
    static function extractConfStringsFromManifests()
    {
        $plugins = AJXP_PluginsService::getInstance()->getDetectedPlugins();
        $plug = new AJXP_Plugin("", "");
        foreach ($plugins as $pType => $plugs) {
            foreach ($plugs as $plug) {
                $lib = $plug->getManifestRawContent("//i18n", "nodes");
                if (!$lib->length) continue;
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
     * @return void
     */
    static function updateAllI18nLibraries($createLanguage = "")
    {
        // UPDATE EN => OTHER LANGUAGES
        $nodes = AJXP_PluginsService::getInstance()->searchAllManifests("//i18n", "nodes");
        foreach ($nodes as $node) {
            $nameSpace = $node->getAttribute("namespace");
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
     * @return
     */
    static function updateI18nFiles($baseDir, $detectLanguages = true, $createLanguage = "")
    {
        if (!is_dir($baseDir) || !is_file($baseDir . "/en.php")) return;
        if ($createLanguage != "" && !is_file($baseDir . "/$createLanguage.php")) {
            @copy(AJXP_INSTALL_PATH . "/plugins/core.ajaxplorer/i18n-template.php", $baseDir . "/$createLanguage.php");
        }
        if (!$detectLanguages) {
            $languages = ConfService::listAvailableLanguages();
            $filenames = array();
            foreach ($languages as $key => $value) {
                $filenames[] = $baseDir . "/" . $key . ".php";
            }
        } else {
            $filenames = glob($baseDir . "/*.php");
        }

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
     * @return
     */
    static function updateI18nFromRef($filename, $reference)
    {
        if (!is_file($filename)) return;
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

    /**
     * Generate an HTML table for the tests results. We should use a template somewhere...
     * @static
     * @param $outputArray
     * @param $testedParams
     * @param bool $showSkipLink
     * @return string
     */
    static function testResultsToTable($outputArray, $testedParams, $showSkipLink = true)
    {
        $dumpRows = "";
        $passedRows = array();
        $warnRows = "";
        $errRows = "";
        $errs = $warns = 0;
        $ALL_ROWS =  array(
            "error" => array(),
            "warning" => array(),
            "dump" => array(),
            "passed" => array(),
        );
        $TITLES = array(
            "error" => "Failed Tests",
            "warning" => "Warnings",
            "dump" => "Server Information",
            "passed" => "Other tests passed",
        );
        foreach ($outputArray as $item)
        {

            // A test is output only if it hasn't succeeded (doText returned FALSE)
            $result = $item["result"] ? "passed" : ($item["level"] == "info" ? "dump" : ($item["level"] == "warning"
                    ? "warning" : "error"));
            $success = $result == "passed";
            if($result == "dump") $result = "passed";
            $ALL_ROWS[$result][$item["name"]] = $item["info"];
        }

        include(AJXP_INSTALL_PATH."/core/tests/startup.phtml");
    }

    /**
     * @static
     * @param $outputArray
     * @param $testedParams
     * @return bool
     */
    static function runTests(&$outputArray, &$testedParams)
    {
        // At first, list folder in the tests subfolder
        chdir(AJXP_TESTS_FOLDER);
        $files = glob('*.php');

        $outputArray = array();
        $testedParams = array();
        $passed = true;
        foreach ($files as $file)
        {
            require_once($file);
            // Then create the test class
            $testName = str_replace(".php", "", substr($file, 5));
            $class = new $testName();

            $result = $class->doTest();
            if (!$result && $class->failedLevel != "info") $passed = false;
            $outputArray[] = array(
                "name" => $class->name,
                "result" => $result,
                "level" => $class->failedLevel,
                "info" => $class->failedInfo);
            if (count($class->testedParams)) {
                $testedParams = array_merge($testedParams, $class->testedParams);
            }
        }
        // PREPARE REPOSITORY LISTS
        $repoList = array();
        require_once("../classes/class.ConfService.php");
        require_once("../classes/class.Repository.php");
        include(AJXP_CONF_PATH . "/bootstrap_repositories.php");
        foreach ($REPOSITORIES as $index => $repo) {
            $repoList[] = ConfService::createRepositoryFromArray($index, $repo);
        }
        // Try with the serialized repositories
        if (is_file(AJXP_DATA_PATH . "/plugins/conf.serial/repo.ser")) {
            $fileLines = file(AJXP_DATA_PATH . "/plugins/conf.serial/repo.ser");
            $repos = unserialize($fileLines[0]);
            $repoList = array_merge($repoList, $repos);
        }

        // NOW TRY THE PLUGIN TESTS
        chdir(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER);
        $files = glob('access.*/test.*.php');
        foreach ($files as $file)
        {
            require_once($file);
            // Then create the test class
            list($accessFolder, $testFileName) = explode("/", $file);
            $testName = str_replace(".php", "", substr($testFileName, 5) . "Test");
            $class = new $testName();
            foreach ($repoList as $repository) {
                if($repository->isTemplate || $repository->getParentId() != null) continue;
                $result = $class->doRepositoryTest($repository);
                if ($result === false || $result === true) {
                    if (!$result && $class->failedLevel != "info") {
                        $passed = false;
                    }
                    $outputArray[] = array(
                        "name" => $class->name . "\n Testing repository : " . $repository->getDisplay(),
                        "result" => $result,
                        "level" => $class->failedLevel,
                        "info" => $class->failedInfo);
                    if (count($class->testedParams)) {
                        $testedParams = array_merge($testedParams, $class->testedParams);
                    }
                }
            }
        }

        return $passed;
    }

    /**
     * @static
     * @param $outputArray
     * @param $testedParams
     * @return void
     */
    static function testResultsToFile($outputArray, $testedParams)
    {
        ob_start();
        echo '$diagResults = ';
        var_export($testedParams);
        echo ';';
        echo '$outputArray = ';
        var_export($outputArray);
        echo ';';
        $content = '<?php ' . ob_get_contents() . ' ?>';
        ob_end_clean();
        //print_r($content);
        file_put_contents(TESTS_RESULT_FILE, $content);
    }

    /**
     * Load an array stored serialized inside a file.
     *
     * @param String $filePath Full path to the file
     * @return Array
     */
    static function loadSerialFile($filePath)
    {
        $filePath = AJXP_VarsFilter::filter($filePath);
        $result = array();
        if (is_file($filePath)) {
            $fileLines = file($filePath);
            $result = unserialize(implode("", $fileLines));
        }
        return $result;
    }

    /**
     * Stores an Array as a serialized string inside a file.
     *
     * @param String $filePath Full path to the file
     * @param Array $value The value to store
     * @param Boolean $createDir Whether to create the parent folder or not, if it does not exist.
     * @param bool $silent Silently write the file, are throw an exception on problem.
     */
    static function saveSerialFile($filePath, $value, $createDir = true, $silent = false)
    {
        $filePath = AJXP_VarsFilter::filter($filePath);
        if ($createDir && !is_dir(dirname($filePath))) {
            if (!is_writeable(dirname(dirname($filePath)))) {
                if ($silent) return;
                else throw new Exception("[AJXP_Utils::saveSerialFile] Cannot write into " . dirname(dirname($filePath)));
            }
            mkdir(dirname($filePath));
        }
        try {
            $fp = fopen($filePath, "w");
            fwrite($fp, serialize($value));
            fclose($fp);
        } catch (Exception $e) {
            if ($silent) return;
            else throw $e;
        }
    }

    /**
     * Detect mobile browsers
     * @static
     * @return bool
     */
    public static function userAgentIsMobile()
    {
        $isMobile = false;

        $op = strtolower($_SERVER['HTTP_X_OPERAMINI_PHONE'] OR "");
        $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        $ac = strtolower($_SERVER['HTTP_ACCEPT']);
        $isMobile = strpos($ac, 'application/vnd.wap.xhtml+xml') !== false
                    || $op != ''
                    || strpos($ua, 'sony') !== false
                    || strpos($ua, 'symbian') !== false
                    || strpos($ua, 'nokia') !== false
                    || strpos($ua, 'samsung') !== false
                    || strpos($ua, 'mobile') !== false
                    || strpos($ua, 'windows ce') !== false
                    || strpos($ua, 'epoc') !== false
                    || strpos($ua, 'opera mini') !== false
                    || strpos($ua, 'nitro') !== false
                    || strpos($ua, 'j2me') !== false
                    || strpos($ua, 'midp-') !== false
                    || strpos($ua, 'cldc-') !== false
                    || strpos($ua, 'netfront') !== false
                    || strpos($ua, 'mot') !== false
                    || strpos($ua, 'up.browser') !== false
                    || strpos($ua, 'up.link') !== false
                    || strpos($ua, 'audiovox') !== false
                    || strpos($ua, 'blackberry') !== false
                    || strpos($ua, 'ericsson,') !== false
                    || strpos($ua, 'panasonic') !== false
                    || strpos($ua, 'philips') !== false
                    || strpos($ua, 'sanyo') !== false
                    || strpos($ua, 'sharp') !== false
                    || strpos($ua, 'sie-') !== false
                    || strpos($ua, 'portalmmm') !== false
                    || strpos($ua, 'blazer') !== false
                    || strpos($ua, 'avantgo') !== false
                    || strpos($ua, 'danger') !== false
                    || strpos($ua, 'palm') !== false
                    || strpos($ua, 'series60') !== false
                    || strpos($ua, 'palmsource') !== false
                    || strpos($ua, 'pocketpc') !== false
                    || strpos($ua, 'smartphone') !== false
                    || strpos($ua, 'rover') !== false
                    || strpos($ua, 'ipaq') !== false
                    || strpos($ua, 'au-mic,') !== false
                    || strpos($ua, 'alcatel') !== false
                    || strpos($ua, 'ericy') !== false
                    || strpos($ua, 'up.link') !== false
                    || strpos($ua, 'vodafone/') !== false
                    || strpos($ua, 'wap1.') !== false
                    || strpos($ua, 'wap2.') !== false;
        /*
                  $isBot = false;
                  $ip = $_SERVER['REMOTE_ADDR'];

                  $isBot =  $ip == '66.249.65.39'
                  || strpos($ua, 'googlebot') !== false
                  || strpos($ua, 'mediapartners') !== false
                  || strpos($ua, 'yahooysmcm') !== false
                  || strpos($ua, 'baiduspider') !== false
                  || strpos($ua, 'msnbot') !== false
                  || strpos($ua, 'slurp') !== false
                  || strpos($ua, 'ask') !== false
                  || strpos($ua, 'teoma') !== false
                  || strpos($ua, 'spider') !== false
                  || strpos($ua, 'heritrix') !== false
                  || strpos($ua, 'attentio') !== false
                  || strpos($ua, 'twiceler') !== false
                  || strpos($ua, 'irlbot') !== false
                  || strpos($ua, 'fast crawler') !== false
                  || strpos($ua, 'fastmobilecrawl') !== false
                  || strpos($ua, 'jumpbot') !== false
                  || strpos($ua, 'googlebot-mobile') !== false
                  || strpos($ua, 'yahooseeker') !== false
                  || strpos($ua, 'motionbot') !== false
                  || strpos($ua, 'mediobot') !== false
                  || strpos($ua, 'chtml generic') !== false
                  || strpos($ua, 'nokia6230i/. fast crawler') !== false;
        */
        return $isMobile;
    }
    /**
     * Detect iOS browser
     * @static
     * @return bool
     */
    public static function userAgentIsIOS()
    {
        if (strpos(strtolower($_SERVER["HTTP_USER_AGENT"]), "iphone") !== false) return true;
        if (strpos(strtolower($_SERVER["HTTP_USER_AGENT"]), "ipad") !== false) return true;
        return false;
    }
    /**
     * Try to remove a file without errors
     * @static
     * @param $file
     * @return void
     */
    public static function silentUnlink($file)
    {
        @unlink($file);
    }
    /**
     * Try to set an ini config, without errors
     * @static
     * @param string $paramName
     * @param string $paramValue
     * @return
     */
    public static function safeIniSet($paramName, $paramValue)
    {
        $current = ini_get($paramName);
        if ($current == $paramValue) return;
        @ini_set($paramName, $paramValue);
    }

}

?>