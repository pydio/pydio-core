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
namespace Pydio\Core\Utils\Vars;

use Pydio\Core\Utils\TextEncoder;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class StringHelper
 * @package Pydio\Core\Utils
 */
class StringHelper
{

    /**
     * Performs a natural sort on the array keys.
     * Behaves the same as ksort() with natural sorting added.
     *
     * @param array $array The array to sort
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
     * @param array $array The array to sort
     * @return boolean
     */
    public static function natkrsort(&$array)
    {
        StringHelper::natksort($array);
        $array = array_reverse($array, TRUE);
        return true;
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
        if ($toUtf8 && TextEncoder::getEncoding() != "UTF-8") {
            return TextEncoder::toUTF8($xmlSafe);
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
            return TextEncoder::toUTF8($xmlSafe);
        } else {
            return $xmlSafe;
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

    /**
     * Indents a flat JSON string to make it more human-readable.
     *
     * @param string $json The original JSON string to process.
     *
     * @return string Indented version of the original JSON string.
     */
    public static function prettyPrintJSON($json)
    {
        $result = '';
        $pos = 0;
        $strLen = strlen($json);
        $indentStr = '  ';
        $newLine = "\n";
        $prevChar = '';
        $outOfQuotes = true;

        for ($i = 0; $i <= $strLen; $i++) {

            // Grab the next character in the string.
            $char = substr($json, $i, 1);

            // Are we inside a quoted string?
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;

                // If this character is the end of an element,
                // output a new line and indent the next line.
            } else if (($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos--;
                for ($j = 0; $j < $pos; $j++) {
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
                    $pos++;
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
     * generates a random password, uses base64: 0-9a-zA-Z
     * @param int [optional] $length length of password, default 24 (144 Bit)
     * @param bool $complexChars
     * @return string password
     */
    public static function generateRandomString($length = 24, $complexChars = false)
    {
        if (function_exists('openssl_random_pseudo_bytes') && USE_OPENSSL_RANDOM && !$complexChars) {
            $password = base64_encode(openssl_random_pseudo_bytes($length, $strong));
            if ($strong == TRUE)
                return substr(str_replace(array("/", "+", "="), "", $password), 0, $length); //base64 is about 33% longer, so we need to truncate the result
        }

        //fallback to mt_rand if php < 5.3 or no openssl available
        $characters = '0123456789';
        $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        if ($complexChars) {
            $characters .= "!@#$%&*?";
        }
        $charactersLength = strlen($characters) - 1;
        $password = '';

        //select some random characters
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[mt_rand(0, $charactersLength)];
        }

        return $password;
    }

    /**
     * @param $regexp
     * @return string
     */
    public static function regexpToLike($regexp)
    {
        $regexp = trim($regexp, '/');
        $left = "~";
        $right = "~";
        if ($regexp[0] == "^") {
            $left = "";
        }
        if ($regexp[strlen($regexp) - 1] == "$") {
            $right = "";
        }
        if ($left == "" && $right == "") {
            return "= %s";
        }
        return "LIKE %" . $left . "like" . $right;
    }

    /**
     * @param $regexp
     * @return string
     */
    public static function cleanRegexp($regexp)
    {
        $regexp = str_replace("\/", "/", trim($regexp, '/'));
        return ltrim(rtrim($regexp, "$"), "^");
    }

    /**
     * @param $regexp
     * @return string
     */
    public static function likeToLike($regexp)
    {
        $left = "";
        $right = "";
        if ($regexp[0] == "%") {
            $left = "~";
        }
        if ($regexp[strlen($regexp) - 1] == "%") {
            $right = "~";
        }
        if ($left == "" && $right == "") {
            return "= %s";
        }
        return "LIKE %" . $left . "like" . $right;
    }

    /**
     * @param $regexp
     * @return string
     */
    public static function cleanLike($regexp)
    {
        return ltrim(rtrim($regexp, "%"), "%");
    }

    /**
     * @param $regexp
     * @return null|string
     */
    public static function regexpToLdap($regexp)
    {
        if (empty($regexp))
            return null;

        // Escape parenthesis for LDAP
        $regexp = str_replace(array("(", ")"), array("\(", "\)"), $regexp);

        $left = "*";
        $right = "*";
        if ($regexp[0] == "^") {
            $regexp = ltrim($regexp, "^");
            $left = "";
        }
        if ($regexp[strlen($regexp) - 1] == "$") {
            $regexp = rtrim($regexp, "$");
            $right = "";
        }
        return $left . $regexp . $right;
    }

    /**
     * Create a unique UID
     * @return string
     */
    public static function createGUID()
    {
        if (function_exists('com_create_guid')) {
            return trim(com_create_guid(), "{}");
        } else {
            mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = ""//chr(123)// "{"
                . substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);
            //.chr(125);// "}"
            return $uuid;
        }
    }


    /**
     * @param $string serialized string
     * @param $allowed_classes mixed
     * @return mixed
     */
    public static function safeUnserialize($string, $allowed_classes = false){
        if ((version_compare(PHP_VERSION, "7.0.0") >= 0) && !empty($allowed_classes)){
            return unserialize($string, ['allowed_classes' => $allowed_classes]);
        } else {
            return unserialize($string);
        }

        //if ($x === null || $x instanceof \__PHP_Incomplete_Class){
        //    return [];
        //}
    }
}