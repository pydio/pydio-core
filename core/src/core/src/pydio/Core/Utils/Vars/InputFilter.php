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
namespace Pydio\Core\Utils\Vars;

use Psr\Http\Message\UploadedFileInterface;
use Pydio\Core\Exception\ForbiddenCharacterException;
use Pydio\Core\Services\LocaleService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Tools to clean inputs
 * @package Pydio\Core\Utils
 */
class InputFilter
{

    const SANITIZE_HTML = 1;
    const SANITIZE_HTML_STRICT = 2;
    const SANITIZE_ALPHANUM = 3;
    const SANITIZE_EMAILCHARS = 4;
    const SANITIZE_FILENAME = 5;
    const SANITIZE_DIRNAME = 6;

    /**
     * Remove all "../../" tentatives, replace double slashes
     * @static
     * @param string $path
     * @return string
     */
    public static function securePath($path)
    {
        if ($path == null) {
            return "";
        }
        //
        // REMOVE ALL "../" TENTATIVES
        //
        $path = str_replace(chr(0), "", $path);
        $dirs = self::safeExplode($path);
        $count = count($dirs);
        for ($i = 0; $i < $count; $i++) {
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

    /**
     * @param $path
     * @return array
     */
    public static function safeExplode($path) {
        return (DIRECTORY_SEPARATOR === "\\" ? preg_split('/(\\\|\\/)/', $path) : explode('/', $path));
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
    public static function detectXSS($string)
    {
        $contains_xss = FALSE;

        // Skip any null or non string values
        if (is_null($string) || !is_string($string)) {
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
            '#</*(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base|svg)[^>]*>?#i'
        );

        foreach ($patterns as $pattern) {
            // Test both the original string and clean string
            if (preg_match($pattern, $string) || preg_match($pattern, $orig)) {
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
     * @param int $level Can be InputFilter::SANITIZE_ALPHANUM, InputFilter::SANITIZE_EMAILCHARS, InputFilter::SANITIZE_HTML, InputFilter::SANITIZE_HTML_STRICT
     * @param string $expand
     * @param bool $throwException
     * @return mixed|string
     * @throws ForbiddenCharacterException
     */
    public static function sanitize($s, $level = InputFilter::SANITIZE_HTML, $throwException = false, $expand = 'script|style|noframes|select|option')
    {
        $original = $s;
        if ($level == InputFilter::SANITIZE_ALPHANUM) {
            $s =  preg_replace("/[^a-zA-Z0-9_\-\.]/", "", $s);
            if($throwException && $original !== $s){
                throw new ForbiddenCharacterException($original);
            }
            return $s;
        } else if ($level == InputFilter::SANITIZE_EMAILCHARS) {
            $s = preg_replace("/[^a-zA-Z0-9_\-\.@!%\+=|~\?]/", "", $s);
            if($throwException && $original !== $s){
                throw new ForbiddenCharacterException($original);
            }
            return $s;
        } else if ($level == InputFilter::SANITIZE_FILENAME || $level == InputFilter::SANITIZE_DIRNAME) {
            // Convert Hexadecimals
            $s = preg_replace_callback('!(&#|\\\)[xX]([0-9a-fA-F]+);?!', function($array){
                return chr(hexdec($array[1]));
            }, $s);
            // Clean up entities
            $s = preg_replace('!(&#0+[0-9]+)!', '$1;', $s);
            // Decode entities
            $s = html_entity_decode($s, ENT_NOQUOTES, 'UTF-8');
            // Strip whitespace characters
            $s = ltrim($s);
            $s = str_replace(chr(0), "", $s);
            if ($level == InputFilter::SANITIZE_FILENAME) {
                $s = preg_replace("/[\"\/\|\?\\\]/", "", $s);
            } else {
                $s = preg_replace("/[\"\|\?\\\]/", "", $s);
            }
            if (self::detectXSS($s)) {
                if (strpos($s, "/") === 0) $s = "/XSS Detected - Rename Me";
                else $s = "XSS Detected - Rename Me";
            }
            if($throwException && $original !== $s){
                throw new ForbiddenCharacterException($original);
            }
            return $s;
        }

        /**/ //prep the string
        $s = ' ' . $s;

        //begin removal
        //remove comment blocks
        $pos = []; $len = [];
        while (stripos($s, '<!--') > 0) {
            $pos[1] = stripos($s, '<!--');
            $pos[2] = stripos($s, '-->', $pos[1]);
            $len[1] = $pos[2] - $pos[1] + 3;
            $x = substr($s, $pos[1], $len[1]);
            $s = str_replace($x, '', $s);
        }

        //remove tags with content between them
        if (strlen($expand) > 0) {
            $e = explode('|', $expand);
            $pos = []; $len = [];
            $eLength = count($e);
            for ($i = 0; $i < $eLength; $i++) {
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
        if ($level == InputFilter::SANITIZE_HTML_STRICT) {
            $s = preg_replace("/[\",;\/`<>:\*\|\?!\^\\\]/", "", $s);
        } else {
            $s = str_replace(array("<", ">"), array("&lt;", "&gt;"), $s);
        }
        if($throwException && trim($original) !== trim($s)){
            throw new ForbiddenCharacterException($original);
        }
        return ltrim($s);
    }

    /**
     * Perform standard urldecode, sanitization and securepath
     * @static
     * @param $data
     * @param int $sanitizeLevel
     * @return string
     * @throws ForbiddenCharacterException
     */
    public static function decodeSecureMagic($data, $sanitizeLevel = InputFilter::SANITIZE_DIRNAME)
    {
        return InputFilter::sanitize(InputFilter::securePath($data), $sanitizeLevel, true);
    }

    /**
     * Parse the $fileVars[] PHP errors
     * @static
     * @param array|UploadedFileInterface $boxData
     * @param bool $throwException
     * @return array|null
     * @throws \Exception
     */
    public static function parseFileDataErrors($boxData, $throwException = false)
    {
        $mess = LocaleService::getMessages();
        if (is_array($boxData)) {
            $userfile_error = $boxData["error"];
            $userfile_tmp_name = $boxData["tmp_name"];
            $userfile_size = $boxData["size"];
        } else {
            $userfile_error = $boxData->getError();
            $userfile_size = $boxData->getSize();
            $userfile_tmp_name = "";
        }
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
                if (strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') === false) {
                    $data = $errorsArray[$userfile_error];
                    if ($throwException) throw new \Exception($data[1], $data[0]);
                    return $data;
                }
            } else {
                $data = $errorsArray[$userfile_error];
                if ($throwException) throw new \Exception($data[1], $data[0]);
                return $data;
            }
        }
        if ($userfile_tmp_name == "none" || $userfile_size == 0) {
            if ($throwException) throw new \Exception($mess[31], 410);
            return array(410, $mess[31]);
        }
        return null;
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
     * This function is used when the server's PHP configuration is using magic quote
     * @param string $text
     * @return string
     */
    public static function magicDequote($text)
    {
        // If the PHP server enables magic quotes, remove them
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
            return stripslashes($text);
        return $text;
    }

    /**
     * call fromUTF8
     * @static
     * @param string $filesystemElement
     * @return string
     */
    public static function fromPostedFileName($filesystemElement)
    {
        return InputFilter::magicDequote($filesystemElement);
    }


}