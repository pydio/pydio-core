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
defined('AJXP_EXEC') or die('Access not allowed');

define('AJXP_SANITIZE_HTML', 1);
define('AJXP_SANITIZE_HTML_STRICT', 2);
define('AJXP_SANITIZE_ALPHANUM', 3);
define('AJXP_SANITIZE_EMAILCHARS', 4);
define('AJXP_SANITIZE_FILENAME', 5);

// THESE ARE DEFINED IN bootstrap_context.php
// REPEAT HERE FOR BACKWARD COMPATIBILITY.
if (!defined('PBKDF2_HASH_ALGORITHM')) {

    define("PBKDF2_HASH_ALGORITHM", "sha256");
    define("PBKDF2_ITERATIONS", 1000);
    define("PBKDF2_SALT_BYTE_SIZE", 24);
    define("PBKDF2_HASH_BYTE_SIZE", 24);

    define("HASH_SECTIONS", 4);
    define("HASH_ALGORITHM_INDEX", 0);
    define("HASH_ITERATION_INDEX", 1);
    define("HASH_SALT_INDEX", 2);
    define("HASH_PBKDF2_INDEX", 3);

    define("USE_OPENSSL_RANDOM", false);

}


/**
 * Various functions used everywhere, static library
 * @package Pydio
 * @subpackage Core
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
    public static function natksort(&$array)
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
    public static function natkrsort(&$array)
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
    public static function securePath($path)
    {
        if ($path == null) $path = "";
        //
        // REMOVE ALL "../" TENTATIVES
        //
        $path = str_replace(chr(0), "", $path);
        $dirs = explode('/', $path);
        for ($i = 0; $i < count($dirs); $i++) {
            if ($dirs[$i] == '.' or $dirs[$i] == '..') {
                $dirs[$i] = '';
            }
        }
        // rebuild safe directory string
        $path = implode('/', $dirs);

        //
        // REPLACE DOUBLE SLASHES
        //
        while (preg_match('/\/\//', $path)) {
            $path = str_replace('//', '/', $path);
        }
        return $path;
    }

    public static function safeDirname($path)
    {
        return (DIRECTORY_SEPARATOR === "\\" ? str_replace("\\", "/", dirname($path)): dirname($path));
    }

    public static function safeBasename($path)
    {
        return (DIRECTORY_SEPARATOR === "\\" ? str_replace("\\", "/", basename($path)): basename($path));
    }

    public static function clearHexaCallback($array){
        return chr(hexdec($array[1]));
    }

    /**
     * Given a string, this function will determine if it potentially an
     * XSS attack and return boolean.
     *
     * @param string $string
     *  The string to run XSS detection logic on
     * @return boolean
     *  True if the given `$string` contains XSS, false otherwise.
     */
    public static function detectXSS($string) {
        $contains_xss = FALSE;

        // Skip any null or non string values
        if(is_null($string) || !is_string($string)) {
            return $contains_xss;
        }

        // Keep a copy of the original string before cleaning up
        $orig = $string;

        // Set the patterns we'll test against
        $patterns = array(
            // Match any attribute starting with "on" or xmlns
            '#(<[^>]+[\x00-\x20\"\'\/])(on|xmlns)[^>]*>?#iUu',

            // Match javascript:, livescript:, vbscript: and mocha: protocols
            '!((java|live|vb)script|mocha|feed|data):(\w)*!iUu',
            '#-moz-binding[\x00-\x20]*:#u',

            // Match style attributes
            '#(<[^>]+[\x00-\x20\"\'\/])style=[^>]*>?#iUu',

            // Match unneeded tags
            '#</*(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base)[^>]*>?#i'
        );

        foreach($patterns as $pattern) {
            // Test both the original string and clean string
            if(preg_match($pattern, $string) || preg_match($pattern, $orig)){
                $contains_xss = TRUE;
            }
            if ($contains_xss === TRUE) return TRUE;
        }

        return FALSE;
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
        if ($level == AJXP_SANITIZE_ALPHANUM) {
            return preg_replace("/[^a-zA-Z0-9_\-\.]/", "", $s);
        } else if ($level == AJXP_SANITIZE_EMAILCHARS) {
            return preg_replace("/[^a-zA-Z0-9_\-\.@!%\+=|~\?]/", "", $s);
        } else if ($level == AJXP_SANITIZE_FILENAME) {
            // Convert Hexadecimals
            $s = preg_replace_callback('!(&#|\\\)[xX]([0-9a-fA-F]+);?!', array('AJXP_Utils', 'clearHexaCallback'), $s);
            // Clean up entities
            $s = preg_replace('!(&#0+[0-9]+)!','$1;',$s);
            // Decode entities
            $s = html_entity_decode($s, ENT_NOQUOTES, 'UTF-8');
            // Strip whitespace characters
            $s = trim($s);
            $s = str_replace(chr(0), "", $s);
            $s = preg_replace("/[\"\/\|\?\\\]/", "", $s);
            if(self::detectXSS($s)){
                $s = "XSS Detected - Rename Me";
            }
            return $s;
        }

        /**/ //prep the string
        $s = ' ' . $s;

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
        return ltrim($s);
    }

    /**
     * Perform standard urldecode, sanitization and securepath
     * @static
     * @param $data
     * @param int $sanitizeLevel
     * @return string
     */
    public static function decodeSecureMagic($data, $sanitizeLevel = AJXP_SANITIZE_HTML)
    {
        return SystemTextEncoding::fromUTF8(AJXP_Utils::sanitize(AJXP_Utils::securePath($data), $sanitizeLevel));
    }
    /**
     * Try to load the tmp dir from the CoreConf AJXP_TMP_DIR, or the constant AJXP_TMP_DIR,
     * or the sys_get_temp_dir
     * @static
     * @return mixed|null|string
     */
    public static function getAjxpTmpDir()
    {
        $conf = ConfService::getCoreConf("AJXP_TMP_DIR");
        if (!empty($conf)) {
            return $conf;
        }
        if (defined("AJXP_TMP_DIR") && AJXP_TMP_DIR != "") {
            return AJXP_TMP_DIR;
        }
        return realpath(sys_get_temp_dir());
    }

    public static function detectApplicationFirstRun()
    {
        return !file_exists(AJXP_CACHE_DIR."/first_run_passed");
    }

    public static function setApplicationFirstRunPassed()
    {
        @file_put_contents(AJXP_CACHE_DIR."/first_run_passed", "true");
    }

    public static function forwardSlashDirname($path)
    {
        return (DIRECTORY_SEPARATOR === "\\" ? str_replace("\\", "/", dirname($path)): dirname($path));
    }

    public static function forwardSlashBasename($path)
    {
        return (DIRECTORY_SEPARATOR === "\\" ? str_replace("\\", "/", basename($path)): basename($path));
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
     * @param bool $throwException
     * @return array|null
     * @throws Exception
     */
    public static function parseFileDataErrors($boxData, $throwException=false)
    {
        $mess = ConfService::getMessages();
        $userfile_error = $boxData["error"];
        $userfile_tmp_name = $boxData["tmp_name"];
        $userfile_size = $boxData["size"];
        if ($userfile_error != UPLOAD_ERR_OK) {
            $errorsArray = array();
            $errorsArray[UPLOAD_ERR_FORM_SIZE] = $errorsArray[UPLOAD_ERR_INI_SIZE] = array(409, str_replace("%i", ini_get("upload_max_filesize"), $mess["537"]));
            $errorsArray[UPLOAD_ERR_NO_FILE] = array(410, $mess[538]);
            $errorsArray[UPLOAD_ERR_PARTIAL] = array(410, $mess[539]);
            $errorsArray[UPLOAD_ERR_NO_TMP_DIR] = array(410, $mess[540]);
            $errorsArray[UPLOAD_ERR_CANT_WRITE] = array(411, $mess[541]);
            $errorsArray[UPLOAD_ERR_EXTENSION] = array(410, $mess[542]);
            if ($userfile_error == UPLOAD_ERR_NO_FILE) {
                // OPERA HACK, do not display "no file found error"
                if (!ereg('Opera', $_SERVER['HTTP_USER_AGENT'])) {
                    $data = $errorsArray[$userfile_error];
                    if($throwException) throw new Exception($data[1], $data[0]);
                    return $data;
                }
            } else {
                $data = $errorsArray[$userfile_error];
                if($throwException) throw new Exception($data[1], $data[0]);
                return $data;
            }
        }
        if ($userfile_tmp_name == "none" || $userfile_size == 0) {
            if($throwException) throw new Exception($mess[31], 410);
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

        if (isSet($parameters["repository_id"]) && isSet($parameters["folder"]) || isSet($parameters["goto"])) {
            if (isSet($parameters["goto"])) {
                $repoId = array_shift(explode("/", ltrim($parameters["goto"], "/")));
                $parameters["folder"] = str_replace($repoId, "", ltrim($parameters["goto"], "/"));
            } else {
                $repoId = $parameters["repository_id"];
            }
            $repository = ConfService::getRepositoryById($repoId);
            if ($repository == null) {
                $repository = ConfService::getRepositoryByAlias($repoId);
                if ($repository != null) {
                    $parameters["repository_id"] = $repository->getId();
                }
            } else {
                $parameters["repository_id"] = $repository->getId();
            }
            require_once(AJXP_BIN_FOLDER . "/class.SystemTextEncoding.php");
            if (AuthService::usersEnabled()) {
                $loggedUser = AuthService::getLoggedUser();
                if ($loggedUser != null && $loggedUser->canSwitchTo($parameters["repository_id"])) {
                    $output["FORCE_REGISTRY_RELOAD"] = true;
                    $output["EXT_REP"] = SystemTextEncoding::toUTF8(urldecode($parameters["folder"]));
                    $loggedUser->setArrayPref("history", "last_repository", $parameters["repository_id"]);
                    $loggedUser->setPref("pending_folder", SystemTextEncoding::toUTF8(AJXP_Utils::decodeSecureMagic($parameters["folder"])));
                    $loggedUser->save("user");
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
        if (AJXP_SERVER_DEBUG && isSet($parameters["extract_application_hooks"])) {
            self::extractHooksToDoc();
        }

        if (isSet($parameters["external_selector_type"])) {
            $output["SELECTOR_DATA"] = array("type" => $parameters["external_selector_type"], "data" => $parameters);
        }

        if (isSet($parameters["skipIOS"])) {
            setcookie("SKIP_IOS", "true");
        }
        if (isSet($parameters["skipANDROID"])) {
            setcookie("SKIP_ANDROID", "true");
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
        if (isSet($session["OVERRIDE_GUI_START_PARAMETERS"])) {
            $output = array_merge($output, $session["OVERRIDE_GUI_START_PARAMETERS"]);
        }
    }

    /**
     * Remove windows carriage return
     * @static
     * @param $fileContent
     * @return mixed
     */
    public static function removeWinReturn($fileContent)
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
    public static function mimetype($fileName, $mode, $isDir)
    {
        $mess = ConfService::getMessages();
        $fileName = strtolower($fileName);
        $EXTENSIONS = ConfService::getRegisteredExtensions();
        if ($isDir) {
            $mime = $EXTENSIONS["ajxp_folder"];
        } else {
            foreach ($EXTENSIONS as $ext) {
                if (preg_match("/\.$ext[0]$/", $fileName)) {
                    $mime = $ext;
                    break;
                }
            }
        }
        if (!isSet($mime)) {
            $mime = $EXTENSIONS["ajxp_empty"];
        }
        if (is_numeric($mime[2]) || array_key_exists($mime[2], $mess)) {
            $mime[2] = $mess[$mime[2]];
        }
        return (($mode == "image" ? $mime[1] : $mime[2]));
    }

    public static $registeredExtensions;
    public static function mimeData($fileName, $isDir)
    {
        $fileName = strtolower($fileName);
        if (self::$registeredExtensions == null) {
            self::$registeredExtensions = ConfService::getRegisteredExtensions();
        }
        if ($isDir) {
            $mime = self::$registeredExtensions["ajxp_folder"];
        } else {
            $pos = strrpos($fileName, ".");
            if ($pos !== false) {
                $fileExt = substr($fileName, $pos + 1);
                if (!empty($fileExt) && array_key_exists($fileExt, self::$registeredExtensions) && $fileExt != "ajxp_folder" && $fileExt != "ajxp_empty") {
                    $mime = self::$registeredExtensions[$fileExt];
                }
            }
        }
        if (!isSet($mime)) {
            $mime = self::$registeredExtensions["ajxp_empty"];
        }
        return array($mime[2], $mime[1]);

    }


    /**
     * Gather a list of mime that must be treated specially. Used for dynamic replacement in XML mainly.
     * @static
     * @param string $keyword "editable", "image", "audio", "zip"
     * @return string
     */
    public static function getAjxpMimes($keyword)
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
            if (ConfService::zipBrowsingEnabled()) {
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
    public static function is_image($fileName)
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
    public static function is_mp3($fileName)
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
    public static function getImageMimeType($fileName)
    {
        if (preg_match("/\.jpg$|\.jpeg$/i", $fileName)) {
            return "image/jpeg";
        } else if (preg_match("/\.png$/i", $fileName)) {
            return "image/png";
        } else if (preg_match("/\.bmp$/i", $fileName)) {
            return "image/bmp";
        } else if (preg_match("/\.gif$/i", $fileName)) {
            return "image/gif";
        }
    }
    /**
     * Headers to send when streaming
     * @static
     * @param $fileName
     * @return bool|string
     */
    public static function getStreamingMimeType($fileName)
    {
        if (preg_match("/\.mp3$/i", $fileName)) {
            return "audio/mp3";
        } else if (preg_match("/\.wav$/i", $fileName)) {
            return "audio/wav";
        } else if (preg_match("/\.aac$/i", $fileName)) {
            return "audio/aac";
        } else if (preg_match("/\.m4a$/i", $fileName)) {
            return "audio/m4a";
        } else if (preg_match("/\.aiff$/i", $fileName)) {
            return "audio/aiff";
        } else if (preg_match("/\.mp4$/i", $fileName)) {
            return "video/mp4";
        } else if (preg_match("/\.mov$/i", $fileName)) {
            return "video/quicktime";
        } else if (preg_match("/\.m4v$/i", $fileName)) {
            return "video/x-m4v";
        } else if (preg_match("/\.3gp$/i", $fileName)) {
            return "video/3gpp";
        } else if (preg_match("/\.3g2$/i", $fileName)) {
            return "video/3gpp2";
        } else return false;
    }

    public static $sizeUnit;
    /**
     * Display a human readable string for a bytesize (1MB, 2,3Go, etc)
     * @static
     * @param $filesize
     * @param bool $phpConfig
     * @return string
     */
    public static function roundSize($filesize, $phpConfig = false)
    {
        if (self::$sizeUnit == null) {
            $mess = ConfService::getMessages();
            self::$sizeUnit = $mess["byte_unit_symbol"];
        }
        if ($filesize < 0) {
            $filesize = sprintf("%u", $filesize);
        }
        if ($filesize >= 1073741824) {
            $filesize = round($filesize / 1073741824 * 100) / 100 . ($phpConfig ? "G" : " G" . self::$sizeUnit);
        } elseif ($filesize >= 1048576) {
            $filesize = round($filesize / 1048576 * 100) / 100 . ($phpConfig ? "M" : " M" . self::$sizeUnit);
        } elseif ($filesize >= 1024) {
            $filesize = round($filesize / 1024 * 100) / 100 . ($phpConfig ? "K" : " K" . self::$sizeUnit);
        } else {
            $filesize = $filesize . " " . self::$sizeUnit;
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
    public static function isHidden($fileName)
    {
        return (substr($fileName, 0, 1) == ".");
    }

    /**
     * Whether a file is a browsable archive
     * @static
     * @param string $fileName
     * @return int
     */
    public static function isBrowsableArchive($fileName)
    {
        return preg_match("/\.zip$/i", $fileName);
    }

    /**
     * Convert a shorthand byte value from a PHP configuration directive to an integer value
     * @param    string   $value
     * @return   int
     */
    public static function convertBytes($value)
    {
        if (is_numeric($value)) {
            return intval($value);
        } else {
            $value_length = strlen($value);
            $value = str_replace(",",".", $value);
            $qty = floatval(substr($value, 0, $value_length - 1));
            $unit = strtolower(substr($value, $value_length - 1));
            switch ($unit) {
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


    //Relative Date Function

    public static function relativeDate($time, $messages, $shortestForm = false)
    {
        $crtYear = date('Y');
        $today = strtotime(date('M j, Y'));
        $reldays = ($time - $today)/86400;
        $relTime = date($messages['date_relative_time_format'], $time);

        if ($reldays >= 0 && $reldays < 1) {
            return  str_replace("TIME", $relTime, $messages['date_relative_today']);
        } else if ($reldays >= 1 && $reldays < 2) {
            return  str_replace("TIME", $relTime, $messages['date_relative_tomorrow']);
        } else if ($reldays >= -1 && $reldays < 0) {
            return  str_replace("TIME", $relTime, $messages['date_relative_yesterday']);
        }

        if (abs($reldays) < 7) {

            if ($reldays > 0) {
                $reldays = floor($reldays);
                return  str_replace("%s", $reldays, $messages['date_relative_days_ahead']);
                //return 'In ' . $reldays . ' day' . ($reldays != 1 ? 's' : '');
            } else {
                $reldays = abs(floor($reldays));
                return  str_replace("%s", $reldays, $messages['date_relative_days_ago']);
                //return $reldays . ' day' . ($reldays != 1 ? 's' : '') . ' ago';
            }

        }
        $finalDate = date($messages["date_relative_date_format"], $time ? $time : time());
        if(strpos($messages["date_relative_date_format"], "F") !== false && isSet($messages["date_intl_locale"]) && class_exists("IntlDateFormatter")){
            $intl = IntlDateFormatter::create($messages["date_intl_locale"], IntlDateFormatter::FULL, IntlDateFormatter::FULL, null, null, "MMMM");
            $localizedMonth = $intl->format($time ? $time : time());
            $dateFuncMonth = date("F", $time ? $time : time());
            $finalDate = str_replace($dateFuncMonth, $localizedMonth, $finalDate);
        }
        if(!$shortestForm || strpos($finalDate, $crtYear) !== false){
            $finalDate = str_replace($crtYear, '', $finalDate);
            return str_replace("DATE", $finalDate, $messages["date_relative_date"]);
        }else{
            return $finalDate = date("M Y", $time ? $time : time());
        }

    }


    /**
     * Replace specific chars by their XML Entities, for use inside attributes value
     * @static
     * @param $string
     * @param bool $toUtf8
     * @return mixed|string
     */
    public static function xmlEntities($string, $toUtf8 = false)
    {
        $xmlSafe = str_replace(array("&", "<", ">", "\"", "\n", "\r"), array("&amp;", "&lt;", "&gt;", "&quot;", "&#13;", "&#10;"), $string);
        if ($toUtf8 && SystemTextEncoding::getEncoding() != "UTF-8") {
            return SystemTextEncoding::toUTF8($xmlSafe);
        } else {
            return $xmlSafe;
        }
    }


    /**
     * Replace specific chars by their XML Entities, for use inside attributes value
     * @static
     * @param $string
     * @param bool $toUtf8
     * @return mixed|string
     */
    public static function xmlContentEntities($string, $toUtf8 = false)
    {
        $xmlSafe = str_replace(array("&", "<", ">", "\""), array("&amp;", "&lt;", "&gt;", "&quot;"), $string);
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
        $from     = explode('/', $from);
        $to       = explode('/', $to);
        $relPath  = $to;

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
     * @param bool $withURI
     * @internal param bool $witchURI
     * @static
     * @return string
     */
    public static function detectServerURL($withURI = false)
    {
        $setUrl = ConfService::getCoreConf("SERVER_URL");
        if (!empty($setUrl)) {
            return $setUrl;
        }
        if (php_sapi_name() == "cli") {
            AJXP_Logger::debug("WARNING, THE SERVER_URL IS NOT SET, WE CANNOT BUILD THE MAIL ADRESS WHEN WORKING IN CLI");
        }
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $port = (($protocol === 'http' && $_SERVER['SERVER_PORT'] == 80 || $protocol === 'https' && $_SERVER['SERVER_PORT'] == 443)
                ? "" : ":" . $_SERVER['SERVER_PORT']);
        $name = $_SERVER["SERVER_NAME"];
        if (!$withURI) {
            return "$protocol://$name$port";
        } else {
            $uri = dirname($_SERVER["REQUEST_URI"]);
            $api = ConfService::currentContextIsRestAPI();
            if(!empty($api)){
                // Keep only before api base
                $uri = array_shift(explode("/".$api."/", $uri));
            }
            return "$protocol://$name$port".$uri;
        }
    }

    /**
     * Modifies a string to remove all non ASCII characters and spaces.
     * @param string $text
     * @return string
     */
    public static function slugify($text)
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

    public static function getHooksFile()
    {
        return AJXP_INSTALL_PATH."/".AJXP_DOCS_FOLDER."/hooks.json";
    }

    public static function extractHooksToDoc()
    {
        $docFile = self::getHooksFile();
        if (is_file($docFile)) {
            copy($docFile, $docFile.".bak");
            $existingHooks = json_decode(file_get_contents($docFile), true);
        } else {
            $existingHooks = array();
        }
        $allPhpFiles = self::glob_recursive(AJXP_INSTALL_PATH."/*.php");
        $hooks = array();
        foreach ($allPhpFiles as $phpFile) {
            $fileContent = file($phpFile);
            foreach ($fileContent as $lineNumber => $line) {
                if (preg_match_all('/AJXP_Controller::applyHook\("([^"]+)", (.*)\)/', $line, $matches)) {
                    $names = $matches[1];
                    $params = $matches[2];
                    foreach ($names as $index => $hookName) {
                        if(!isSet($hooks[$hookName])) $hooks[$hookName] = array("TRIGGERS" => array(), "LISTENERS" => array());
                        $hooks[$hookName]["TRIGGERS"][] = array("FILE" => substr($phpFile, strlen(AJXP_INSTALL_PATH)), "LINE" => $lineNumber);
                        $hooks[$hookName]["PARAMETER_SAMPLE"] = $params[$index];
                    }
                }

            }
        }
        $registryHooks = AJXP_PluginsService::getInstance()->searchAllManifests("//hooks/serverCallback", "xml", false, false, true);
        $regHooks = array();
        foreach ($registryHooks as $xmlHook) {
            $name = $xmlHook->getAttribute("hookName");
            $method = $xmlHook->getAttribute("methodName");
            $pluginId = $xmlHook->getAttribute("pluginId");
            if($pluginId == "") $pluginId = $xmlHook->parentNode->parentNode->parentNode->getAttribute("id");
            if(!isSet($regHooks[$name])) $regHooks[$name] = array();
            $regHooks[$name][] = array("PLUGIN_ID" => $pluginId, "METHOD" => $method);
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
        file_put_contents($docFile, self::prettyPrintJSON(json_encode($existingHooks)));

    }

    /**
     * Indents a flat JSON string to make it more human-readable.
     *
     * @param string $json The original JSON string to process.
     *
     * @return string Indented version of the original JSON string.
     */
    public function prettyPrintJSON($json)
    {
        $result      = '';
        $pos         = 0;
        $strLen      = strlen($json);
        $indentStr   = '  ';
        $newLine     = "\n";
        $prevChar    = '';
        $outOfQuotes = true;

        for ($i=0; $i<=$strLen; $i++) {

            // Grab the next character in the string.
            $char = substr($json, $i, 1);

            // Are we inside a quoted string?
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;

                // If this character is the end of an element,
                // output a new line and indent the next line.
            } else if (($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos --;
                for ($j=0; $j<$pos; $j++) {
                    $result .= $indentStr;
                }
            }

            // Add the character to the result string.
            $result .= $char;

            // If the last character was the beginning of an element,
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos ++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            $prevChar = $char;
        }

        return $result;
    }

    /**
     * i18n utilitary for extracting the CONF_MESSAGE[] strings out of the XML files
     * @static
     * @return void
     */
    public static function extractConfStringsFromManifests()
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
    public static function updateAllI18nLibraries($createLanguage = "")
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
    public static function updateI18nFiles($baseDir, $detectLanguages = true, $createLanguage = "")
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
    public static function updateI18nFromRef($filename, $reference)
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
     * @return void
     */
    public static function testResultsToTable($outputArray, $testedParams, $showSkipLink = true)
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
        foreach ($outputArray as $item) {

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
    public static function runTests(&$outputArray, &$testedParams)
    {
        // At first, list folder in the tests subfolder
        chdir(AJXP_TESTS_FOLDER);
        $files = glob('*.php');

        $outputArray = array();
        $testedParams = array();
        $passed = true;
        foreach ($files as $file) {
            require_once($file);
            // Then create the test class
            $testName = str_replace(".php", "", substr($file, 5));
            if(!class_exists($testName)) continue;
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
        foreach ($files as $file) {
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
    public static function testResultsToFile($outputArray, $testedParams)
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

    public static function isStream($path)
    {
        $wrappers = stream_get_wrappers();
        $wrappers_re = '(' . join('|', $wrappers) . ')';
        return preg_match( "!^$wrappers_re://!", $path ) === 1;
    }

    /**
     * Load an array stored serialized inside a file.
     *
     * @param String $filePath Full path to the file
     * @param Boolean $skipCheck do not test for file existence before opening
     * @return Array
     */
    public static function loadSerialFile($filePath, $skipCheck = false, $format="ser")
    {
        $filePath = AJXP_VarsFilter::filter($filePath);
        $result = array();
        if ($skipCheck) {
            $fileLines = @file($filePath);
            if ($fileLines !== false) {
                if($format == "ser") $result = unserialize(implode("", $fileLines));
                else if($format == "json") $result = json_decode(implode("", $fileLines), true);
            }
            return $result;
        }
        if (is_file($filePath)) {
            $fileLines = file($filePath);
            if($format == "ser") $result = unserialize(implode("", $fileLines));
            else if($format == "json") $result = json_decode(implode("", $fileLines), true);
        }
        return $result;
    }

    /**
     * Stores an Array as a serialized string inside a file.
     *
     * @param String $filePath Full path to the file
     * @param Array|Object $value The value to store
     * @param Boolean $createDir Whether to create the parent folder or not, if it does not exist.
     * @param bool $silent Silently write the file, are throw an exception on problem.
     * @param string $format
     * @throws Exception
     */
    public static function saveSerialFile($filePath, $value, $createDir = true, $silent = false, $format="ser", $jsonPrettyPrint = false)
    {
        $filePath = AJXP_VarsFilter::filter($filePath);
        if ($createDir && !is_dir(dirname($filePath))) {
            @mkdir(dirname($filePath), 0755, true);
            if (!is_dir(dirname($filePath))) {
                // Creation failed
                if($silent) return;
                else throw new Exception("[AJXP_Utils::saveSerialFile] Cannot write into " . dirname(dirname($filePath)));
            }
        }
        try {
            $fp = fopen($filePath, "w");
            if($format == "ser") $content = serialize($value);
            else if ($format == "json") {
                $content = json_encode($value);
                if($jsonPrettyPrint) $content = self::prettyPrintJSON($content);
            }
            fwrite($fp, $content);
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
                    || strpos($ua, 'android') !== false
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
        if (stripos($_SERVER["HTTP_USER_AGENT"], "iphone") !== false) return true;
        if (stripos($_SERVER["HTTP_USER_AGENT"], "ipad") !== false) return true;
        if (stripos($_SERVER["HTTP_USER_AGENT"], "ipod") !== false) return true;
        return false;
    }
    /**
     * Detect Android UA
     * @static
     * @return bool
     */
    public static function userAgentIsAndroid()
    {
        return (stripos($_SERVER["HTTP_USER_AGENT"], "android") !== false);
    }

    public static function userAgentIsNativePydioApp(){

        return (stripos($_SERVER["HTTP_USER_AGENT"], "ajaxplorer-ios-client") !== false
                || stripos($_SERVER["HTTP_USER_AGENT"], "Apache-HttpClient") !== false
                || stripos($_SERVER["HTTP_USER_AGENT"], "python-requests") !== false
        );
    }

    public static function osFromUserAgent($useragent = null) {

        $osList = array
        (
            'Windows 10' => 'windows nt 10.0',
            'Windows 8.1' => 'windows nt 6.3',
            'Windows 8' => 'windows nt 6.2',
            'Windows 7' => 'windows nt 6.1',
            'Windows Vista' => 'windows nt 6.0',
            'Windows Server 2003' => 'windows nt 5.2',
            'Windows XP' => 'windows nt 5.1',
            'Windows 2000 sp1' => 'windows nt 5.01',
            'Windows 2000' => 'windows nt 5.0',
            'Windows NT 4.0' => 'windows nt 4.0',
            'Windows Me' => 'win 9x 4.9',
            'Windows 98' => 'windows 98',
            'Windows 95' => 'windows 95',
            'Windows CE' => 'windows ce',
            'Windows (version unknown)' => 'windows',
            'OpenBSD' => 'openbsd',
            'SunOS' => 'sunos',
            'Ubuntu' => 'ubuntu',
            'Linux' => '(linux)|(x11)',
            'Mac OSX Beta (Kodiak)' => 'mac os x beta',
            'Mac OSX Cheetah' => 'mac os x 10.0',
            'Mac OSX Puma' => 'mac os x 10.1',
            'Mac OSX Jaguar' => 'mac os x 10.2',
            'Mac OSX Panther' => 'mac os x 10.3',
            'Mac OSX Tiger' => 'mac os x 10.4',
            'Mac OSX Leopard' => 'mac os x 10.5',
            'Mac OSX Snow Leopard' => 'mac os x 10.6',
            'Mac OSX Lion' => 'mac os x 10.7',
            'Mac OSX Mountain Lion' => 'mac os x 10.8',
            'Mac OSX Mavericks' => 'mac os x 10.9',
            'Mac OSX Yosemite' => 'mac os x 10.10',
            'Mac OS (classic)' => '(mac_powerpc)|(macintosh)',
            'QNX' => 'QNX',
            'BeOS' => 'beos',
            'Apple iPad' => 'iPad',
            'Apple iPhone' => 'iPhone',
            'OS2' => 'os\/2',
            'SearchBot'=>'(nuhk)|(googlebot)|(yammybot)|(openbot)|(slurp)|(msnbot)|(ask jeeves\/teoma)|(ia_archiver)'
        );

        if($useragent == null){
            $useragent = $_SERVER['HTTP_USER_AGENT'];
            $useragent = strtolower($useragent);
        }

        $found = "Not automatically detected.$useragent";
        foreach($osList as $os=>$match) {
            if (preg_match('/' . $match . '/i', $useragent)) {
                $found = $os;
                break;
            }
        }

        return $found;


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
     * @return void
     */
    public static function safeIniSet($paramName, $paramValue)
    {
        $current = ini_get($paramName);
        if ($current == $paramValue) return;
        @ini_set($paramName, $paramValue);
    }

    /**
     * Parse URL ignoring # and ?
     * @param $path
     * @return array
     */
    public static function safeParseUrl($path)
    {
        $parts = parse_url(str_replace(array("#", "?"), array("__AJXP_FRAGMENT__", "__AJXP_MARK__"), $path));
        $parts["path"]= str_replace(array("__AJXP_FRAGMENT__", "__AJXP_MARK__"), array("#", "?"), $parts["path"]);
        return $parts;
    }

    /**
     * @static
     * @param string $url
     * @return bool|mixed|string
     */
    public static function getRemoteContent($url)
    {
        if (ini_get("allow_url_fopen")) {
            return file_get_contents($url);
        } else if (function_exists("curl_init")) {
            $ch = curl_init();
            $timeout = 30; // set to zero for no timeout
            curl_setopt ($ch, CURLOPT_URL, $url);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $return = curl_exec($ch);
            curl_close($ch);
            return $return;
        } else {
            $i = parse_url($url);
            $httpClient = new HttpClient($i["host"]);
            $httpClient->timeout = 30;
            return $httpClient->quickGet($url);
        }
    }

    public static function decypherStandardFormPassword($userId, $password){
        if (function_exists('mcrypt_decrypt')) {
            // We have encoded as base64 so if we need to store the result in a database, it can be stored in text column
            $password = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($userId."\1CDAFx¨op#"), base64_decode($password), MCRYPT_MODE_ECB), "\0");
        }
        return $password;
    }

    public static function parseStandardFormParameters(&$repDef, &$options, $userId = null, $prefix = "DRIVER_OPTION_", $binariesContext = null, $cypheredPassPrefix = "")
    {
        if ($binariesContext === null) {
            $binariesContext = array("USER" => (AuthService::getLoggedUser()!= null)?AuthService::getLoggedUser()->getId():"shared");
        }
        $replicationGroups = array();
        $switchesGroups = array();
        foreach ($repDef as $key => $value) {
            if( ( ( !empty($prefix) &&  strpos($key, $prefix)!== false && strpos($key, $prefix)==0 ) || empty($prefix) )
                && strpos($key, "ajxptype") === false
                && strpos($key, "_original_binary") === false
                && strpos($key, "_replication") === false
                && strpos($key, "_checkbox") === false){
                if (isSet($repDef[$key."_ajxptype"])) {
                    $type = $repDef[$key."_ajxptype"];
                    if ($type == "boolean") {
                        $value = ($value == "true"?true:false);
                    } else if ($type == "integer") {
                        $value = intval($value);
                    } else if ($type == "array") {
                        $value = explode(",", $value);
                    } else if ($type == "password" && $userId!=null) {
                        if (trim($value) != "" && $value != "__AJXP_VALUE_SET__" && function_exists('mcrypt_encrypt')) {
                            // We encode as base64 so if we need to store the result in a database, it can be stored in text column
                            $value = $cypheredPassPrefix . base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256,  md5($userId."\1CDAFx¨op#"), $value, MCRYPT_MODE_ECB));
                        }
                    } else if ($type == "binary" && $binariesContext !== null) {
                        if (!empty($value)) {
                            if ($value == "ajxp-remove-original") {
                                if (!empty($repDef[$key."_original_binary"])) {
                                    ConfService::getConfStorageImpl()->deleteBinary($binariesContext, $repDef[$key."_original_binary"]);
                                }
                                $value = "";
                            } else {
                                $file = AJXP_Utils::getAjxpTmpDir()."/".$value;
                                if (file_exists($file)) {
                                    $id= !empty($repDef[$key."_original_binary"]) ? $repDef[$key."_original_binary"] : null;
                                    $id=ConfService::getConfStorageImpl()->saveBinary($binariesContext, $file, $id);
                                    $value = $id;
                                }
                            }
                        } else if (!empty($repDef[$key."_original_binary"])) {
                            $value = $repDef[$key."_original_binary"];
                        }
                    } else if (strpos($type,"group_switch:") === 0) {
                        $tmp = explode(":", $type);
                        $gSwitchName = $tmp[1];
                        $switchesGroups[substr($key, strlen($prefix))] = $gSwitchName;
                    } else if ($type == "text/json") {
                        $value = json_decode($value, true);
                    }
                    if (!in_array($type, array("textarea", "boolean", "text/json"))) {
                        $value = AJXP_Utils::sanitize($value, AJXP_SANITIZE_HTML);
                    }
                    unset($repDef[$key."_ajxptype"]);
                }
                if (isSet($repDef[$key."_checkbox"])) {
                    $checked = $repDef[$key."_checkbox"] == "checked";
                    unset($repDef[$key."_checkbox"]);
                    if(!$checked) continue;
                }
                if (isSet($repDef[$key."_replication"])) {
                    $repKey = $repDef[$key."_replication"];
                    if(!is_array($replicationGroups[$repKey])) $replicationGroups[$repKey] = array();
                    $replicationGroups[$repKey][] = $key;
                }
                $options[substr($key, strlen($prefix))] = $value;
                unset($repDef[$key]);
            } else {
                $repDef[$key] = $value;
            }
        }
        // DO SOMETHING WITH REPLICATED PARAMETERS?
        if (count($switchesGroups)) {
            foreach ($switchesGroups as $fieldName => $groupName) {
                if (isSet($options[$fieldName])) {
                    $gValues = array();
                    $radic = $groupName."_".$options[$fieldName]."_";
                    foreach ($options as $optN => $optV) {
                        if (strpos($optN, $radic) === 0) {
                            $newName = substr($optN, strlen($radic));
                            $gValues[$newName] = $optV;
                        }
                    }
                }
                $options[$fieldName."_group_switch"] = $options[$fieldName];
                $options[$fieldName] = $gValues;
            }
        }

    }

    private static $_dibiParamClean = array();
    public static function cleanDibiDriverParameters($params)
    {
        if(!is_array($params)) return $params;
        $value = $params["group_switch_value"];
        if (isSet($value)) {
            if(isSet(self::$_dibiParamClean[$value])){
                return self::$_dibiParamClean[$value];
            }
            if ($value == "core") {
                $bootStorage = ConfService::getBootConfStorageImpl();
                $configs = $bootStorage->loadPluginConfig("core", "conf");
                $params = $configs["DIBI_PRECONFIGURATION"];
                if (!is_array($params)) {
                     throw new Exception("Empty SQL default connexion, there is something wrong with your setup! You may have switch to an SQL-based plugin without defining a connexion.");
                }
            } else {
                unset($params["group_switch_value"]);
            }
            foreach ($params as $k => $v) {
                $params[array_pop(explode("_", $k, 2))] = AJXP_VarsFilter::filter($v);
                unset($params[$k]);
            }
        }
        switch ($params["driver"]) {
            case "sqlite":
            case "sqlite3":
                $params["formatDateTime"] = "'Y-m-d H:i:s'";
                $params["formatDate"] = "'Y-m-d'";
                break;
        }
        if(isSet($value)){
            self::$_dibiParamClean[$value] = $params;
        }
        return $params;
    }

    public static function runCreateTablesQuery($p, $file)
    {

        switch ($p["driver"]) {
            case "sqlite":
            case "sqlite3":
                if (!file_exists(dirname($p["database"]))) {
                    @mkdir(dirname($p["database"]), 0755, true);
                }
                $ext = ".sqlite";
                break;
            case "mysql":
                $ext = ".mysql";
                break;
            case "postgre":
                $ext = ".pgsql";
                break;
            default:
                return "ERROR!, DB driver ". $p["driver"] ." not supported yet in __FUNCTION__";
        }

        $result = array();
        $file = dirname($file) ."/". str_replace(".sql", $ext, basename($file) );
        $sql = file_get_contents($file);
        $separators = explode("/** SEPARATOR **/", $sql);

        $allParts = array();

        foreach($separators as $sep){
            $firstLine = array_shift(explode("\n", trim($sep)));
            if($firstLine == "/** BLOCK **/"){
                $allParts[] = $sep;
            }else{
                $parts = explode(";", $sep);
                $remove = array();
                for ($i = 0 ; $i < count($parts); $i++) {
                    $part = $parts[$i];
                    if (strpos($part, "BEGIN") && isSet($parts[$i+1])) {
                        $parts[$i] .= ';'.$parts[$i+1];
                        $remove[] = $i+1;
                    }
                }
                foreach($remove as $rk) unset($parts[$rk]);
                $allParts = array_merge($allParts, $parts);
            }
        }
        dibi::connect($p);
        dibi::begin();
        foreach ($allParts as $createPart) {
            $sqlPart = trim($createPart);
            if (empty($sqlPart)) continue;
            try {
                dibi::nativeQuery($sqlPart);
                $resKey = str_replace("\n", "", substr($sqlPart, 0, 50))."...";
                $result[] = "OK: $resKey executed successfully";
            } catch (DibiException $e) {
                $result[] = "ERROR! $sqlPart failed";
            }
        }
        dibi::commit();
        dibi::disconnect();
        $message = implode("\n", $result);
        if (strpos($message, "ERROR!")) return $message;
        else return "SUCCESS:".$message;
    }


    /*
     * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
     * $algorithm - The hash algorithm to use. Recommended: SHA256
     * $password - The password.
     * $salt - A salt that is unique to the password.
     * $count - Iteration count. Higher is better, but slower. Recommended: At least 1000.
     * $key_length - The length of the derived key in bytes.
     * $raw_output - If true, the key is returned in raw binary format. Hex encoded otherwise.
     * Returns: A $key_length-byte key derived from the password and salt.
     *
     * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
     *
     * This implementation of PBKDF2 was originally created by https://defuse.ca
     * With improvements by http://www.variations-of-shadow.com
     */
    public static function pbkdf2_apply($algorithm, $password, $salt, $count, $key_length, $raw_output = false)
    {
        $algorithm = strtolower($algorithm);

        if(!in_array($algorithm, hash_algos(), true))
            die('PBKDF2 ERROR: Invalid hash algorithm.');
        if($count <= 0 || $key_length <= 0)
            die('PBKDF2 ERROR: Invalid parameters.');

        $hash_length = strlen(hash($algorithm, "", true));
        $block_count = ceil($key_length / $hash_length);

        $output = "";

        for ($i = 1; $i <= $block_count; $i++) {
            // $i encoded as 4 bytes, big endian.
            $last = $salt . pack("N", $i);
            // first iteration
            $last = $xorsum = hash_hmac($algorithm, $last, $password, true);

            // perform the other $count - 1 iterations
            for ($j = 1; $j < $count; $j++) {
                $xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true));
            }

            $output .= $xorsum;
        }

        if($raw_output)
            return substr($output, 0, $key_length);
        else
            return bin2hex(substr($output, 0, $key_length));
    }


    // Compares two strings $a and $b in length-constant time.
    public static function pbkdf2_slow_equals($a, $b)
    {
        $diff = strlen($a) ^ strlen($b);
        for ($i = 0; $i < strlen($a) && $i < strlen($b); $i++) {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }

        return $diff === 0;
    }

    public static function pbkdf2_validate_password($password, $correct_hash)
    {
        $params = explode(":", $correct_hash);

        if (count($params) < HASH_SECTIONS) {
            if (strlen($correct_hash) == 32 && count($params) == 1) {
                return md5($password) == $correct_hash;
            }
            return false;
        }

        $pbkdf2 = base64_decode($params[HASH_PBKDF2_INDEX]);
        return self::pbkdf2_slow_equals(
            $pbkdf2,
             self::pbkdf2_apply(
                $params[HASH_ALGORITHM_INDEX],
                $password,
                $params[HASH_SALT_INDEX],
                (int) $params[HASH_ITERATION_INDEX],
                strlen($pbkdf2),
                true
            )
        );
    }


    public static function pbkdf2_create_hash($password)
    {
        // format: algorithm:iterations:salt:hash
        $salt = base64_encode(mcrypt_create_iv(PBKDF2_SALT_BYTE_SIZE, MCRYPT_DEV_URANDOM));
        return PBKDF2_HASH_ALGORITHM . ":" . PBKDF2_ITERATIONS . ":" .  $salt . ":" .
        base64_encode(self::pbkdf2_apply(
            PBKDF2_HASH_ALGORITHM,
            $password,
            $salt,
            PBKDF2_ITERATIONS,
            PBKDF2_HASH_BYTE_SIZE,
            true
        ));
    }

    /**
     * generates a random password, uses base64: 0-9a-zA-Z
     * @param int [optional] $length length of password, default 24 (144 Bit)
     * @param bool $complexChars
     * @return string password
     */
    public static function generateRandomString($length = 24, $complexChars = false)
    {
        if (function_exists('openssl_random_pseudo_bytes') && USE_OPENSSL_RANDOM && !$complexChars) {
            $password = base64_encode(openssl_random_pseudo_bytes($length, $strong));
            if($strong == TRUE)
                return substr(str_replace(array("/","+","="), "", $password), 0, $length); //base64 is about 33% longer, so we need to truncate the result
        }

        //fallback to mt_rand if php < 5.3 or no openssl available
        $characters = '0123456789';
        $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        if($complexChars){
            $characters .= "!@#$%&*?";
        }
        $charactersLength = strlen($characters)-1;
        $password = '';

        //select some random characters
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[mt_rand(0, $charactersLength)];
        }

        return $password;
    }

    // Does not support flag GLOB_BRACE
    public static function glob_recursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::glob_recursive($dir.'/'.basename($pattern), $flags));
        }
        return $files;
    }

    public static function regexpToLike($regexp)
    {
        $regexp = trim($regexp, '/');
        $left = "~";
        $right = "~";
        if ($regexp[0]=="^") {
            $left = "";
        }
        if ($regexp[strlen($regexp)-1] == "$") {
            $right = "";
        }
        if ($left == "" && $right == "") {
            return "= %s";
        }
        return "LIKE %".$left."like".$right;
    }

    public static function cleanRegexp($regexp)
    {
        $regexp = str_replace("\/", "/", trim($regexp, '/'));
        return ltrim(rtrim($regexp, "$"), "^");
    }

    public static function likeToLike($regexp)
    {
        $left = "";
        $right = "";
        if ($regexp[0]=="%") {
            $left = "~";
        }
        if ($regexp[strlen($regexp)-1] == "%") {
            $right = "~";
        }
        if ($left == "" && $right == "") {
            return "= %s";
        }
        return "LIKE %".$left."like".$right;
    }

    public static function cleanLike($regexp)
    {
        return ltrim(rtrim($regexp, "%"), "%");
    }

    public static function regexpToLdap($regexp)
    {
        if(empty($regexp))
            return null;

        $left = "*";
        $right = "*";
        if ($regexp[0]=="^") {
            $regexp = ltrim($regexp, "^");
            $left = "";
        }
        if ($regexp[strlen($regexp)-1] == "$") {
            $regexp = rtrim($regexp, "$");
            $right = "";
        }
        return $left.$regexp.$right;
    }
    /**
     * Hide file or folder for Windows OS
     * @static
     * @param $path
     * @return void
     */
    public static function winSetHidden($file)
    {
        @shell_exec("attrib +H " . escapeshellarg($file));
    }	
}
