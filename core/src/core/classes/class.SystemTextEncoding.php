<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>, Cyril Russo
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
/**
 * Static utilitaries to encode/decode charset to/from utf8
 * @package Pydio
 * @subpackage Core
 */
class SystemTextEncoding
{
    /**
     * Change the charset of a string from input to output
     * @static
     * @param string $inputCharset
     * @param string $outputCharset
     * @param string $text
     * @return string
     */
    public static function changeCharset($inputCharset, $outputCharset, $text)
    {
        if ($inputCharset == $outputCharset) return $text;
        // Due to iconv bug when dealing with text with non ASCII encoding for last char, we use this workaround http://fr.php.net/manual/fr/function.iconv.php#81494
        if (function_exists("iconv")) {

            return iconv($inputCharset, $outputCharset, $text);
        } else {
            $content = @htmlentities($text, ENT_QUOTES, $inputCharset);
            return @html_entity_decode($content, ENT_QUOTES , $outputCharset);
        }
    }

    public static $currentCharsetValue;
    /**
     * Detect the current charset from the current locale
     * @static
     * @param string $locale
     * @return string
     */
    public static function parseCharset($locale)
    {
        $test = explode("@", $locale);
        $locale = $test[0];
        $encoding = substr(strrchr($locale, "."), 1);
        if (is_numeric($encoding)) {
            if (substr($encoding, 0, 2) == "12") // CP12xx are changed to Windows-12xx to allow PHP4 conversion
                  $encoding = "windows-".$encoding;
              else $encoding = "CP".$encoding; // In other cases, PHP4 won't work anyway, so use CPxxxx encoding (that iconv supports)
        } else if ($locale == "C") {   // Locale not set correctly, most probable error cause is /etc/init.d/apache having "LANG=C" defined
            // In any case, "C" is ASCII-7 bit so it's safe to use the extra bit as if it was UTF-8
            $encoding = "UTF-8";
        }
        if (!strlen($encoding)) $encoding = "UTF-8";
        return $encoding;
    }
    /**
     * Try to detect the current encoding (cached in session)
     * @static
     * @return string
     */
    public static function getEncoding()
    {
           if (self::$currentCharsetValue == null) {
               global $_SESSION;
               if (isset($_SESSION["AJXP_CHARSET"]) && strlen($_SESSION["AJXP_CHARSET"])) {
               // Check if the session get an assigned charset encoding (it's the case for remote SSH for example)
                   self::$currentCharsetValue = $_SESSION["AJXP_CHARSET"];
               } else {
           // Get the current locale (expecting the filesystem is in the same locale, as the standard says)
                   self::$currentCharsetValue = self::parseCharset(setlocale(LC_CTYPE, 0));
               }
           }
           return self::$currentCharsetValue;
    }
    /**
     * Decode a string from UTF8 to current Charset
     * @static
     * @param string $filesystemElement
     * @param bool $test Try to detect if it's really utf8 or not
     * @return string
     */
    public static function fromUTF8($filesystemElement, $test = false)
    {
        if ($test && !SystemTextEncoding::isUtf8($filesystemElement)) {
            return $filesystemElement;
        }
        $enc = SystemTextEncoding::getEncoding();
        return SystemTextEncoding::changeCharset("UTF-8", $enc, $filesystemElement);
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
        return SystemTextEncoding::fromUTF8(SystemTextEncoding::magicDequote($filesystemElement));
    }

    /**
     * Transform a string from current charset to utf8
     * @static
     * @param string $filesystemElement
     * @param bool $test Test if it's already UTF8 or not, to avoid double-encoding
     * @return string
     */
    public static function toUTF8($filesystemElement, $test = true)
    {
        if ($test && SystemTextEncoding::isUtf8($filesystemElement)) {
            return $filesystemElement;
        }
        $enc = SystemTextEncoding::getEncoding();
        return SystemTextEncoding::changeCharset($enc, "UTF-8", $filesystemElement);
    }
    /**
     * Test if a string seem to be already UTF8-encoded
     * @static
     * @param string $string
     * @return bool
     */
    public static function isUtf8($string)
    {
        return preg_match('%^(?:
          [\x09\x0A\x0D\x20-\x7E]            # ASCII
        | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
        | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
        | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
        | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
            )*$%xs', $string);
    }
    /**
     * Transform a string from current Storage charset to utf8
     * @static
     * @param string $filesystemElement
     * @param bool $test Test if it's already UTF8 or not, to avoid double-encoding
     * @return string
     */
    public static function fromStorageEncoding($filesystemElement, $test = true)
    {
        if ($test && SystemTextEncoding::isUtf8($filesystemElement)) {
            return $filesystemElement;
        }
        $enc = SystemTextEncoding::getEncoding();
        return SystemTextEncoding::changeCharset($enc, "UTF-8", $filesystemElement);
    }
    /**
     * Decode a string from UTF8 to current Storage Charset
     * @static
     * @param string $filesystemElement
     * @param bool $test Try to detect if it's really utf8 or not
     * @return string
     */
    public static function toStorageEncoding($filesystemElement, $test = false)
    {
        if ($test && !SystemTextEncoding::isUtf8($filesystemElement)) {
            return $filesystemElement;
        }
        $enc = SystemTextEncoding::getEncoding();
        return SystemTextEncoding::changeCharset("UTF-8", $enc, $filesystemElement);
    }	
}
