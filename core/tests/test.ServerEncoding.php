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
defined('AJXP_EXEC') or die( 'Access not allowed');
require_once('../classes/class.AbstractTest.php');

/**
 * @package info.ajaxplorer.test
 * Test Server Encoding
 */
class ServerEncoding extends AbstractTest
{
    function ServerEncoding() { parent::AbstractTest("Server charset encoding", "You must set a correct charset encoding in your locale definition in the form: en_us.UTF-8. Please refer to setlocale man page. If your detected locale is C, please check the <a href=\"http://www.ajaxplorer.info/wordpress/documentation-3/chapter-faq/#toc-i-have-a-warning-concerning-the-character-encoding-when-i-first-start-ajaxplorer-what-should-i-do\">F.A.Q.</a>. "); }
    function doTest() 
    { 
        // Get the locale
        $locale = setlocale(LC_CTYPE, 0);
        if ($locale == 'C') { $this->failedLevel = "warning"; $this->failedInfo .= "Detected locale: $locale (using UTF-8)"; return FALSE; }
        if (strpos($locale, '.') === FALSE) { $this->failedLevel = "warning"; $this->failedInfo .= "Locale doesn't contain encoding: $locale (so using UTF-8)"; return FALSE; }
        // Check if we have iconv
        if (!function_exists("iconv") && floatval(phpversion()) > 5.0) { $this->failedInfo .= "Couldn't find iconv. Please use a PHP version with iconv support"; return FALSE; }
        if (floatval(phpversion) > 5.0)
        {
            // Try converting from a known UTF-8 string to ISO8859-1 string and back to make sure it works.
            $string = "aéàç";
            $iso = iconv("UTF-8", "ISO-8859-1", $string);
            $back = iconv("ISO-8859-1", "UTF-8", $iso);
            if (strlen($iso) != 4 || ord($iso[1]) != 233 || $back != $string) { $this->failedInfo .= "iconv doesn't work on your system: $string $iso $back"; return FALSE; } 
        }
        return TRUE;
    }
};

?>