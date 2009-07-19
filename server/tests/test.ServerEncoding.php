<?php
/**
 * @package info.ajaxplorer
 *
 * Copyright 2007-2009 Cyril Russo
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 *
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 *
 * The main conditions are as follow :
 * You must conspicuously and appropriately publish on each copy distributed
 * an appropriate copyright notice and disclaimer of warranty and keep intact
 * all the notices that refer to this License and to the absence of any warranty;
 * and give any other recipients of the Program a copy of the GNU Lesser General
 * Public License along with the Program.
 *
 * If you modify your copy or copies of the library or any portion of it, you may
 * distribute the resulting library provided you do so under the GNU Lesser
 * General Public License. However, programs that link to the library may be
 * licensed under terms of your choice, so long as the library itself can be changed.
 * Any translation of the GNU Lesser General Public License must be accompanied by the
 * GNU Lesser General Public License.
 *
 * If you copy or distribute the program, you must accompany it with the complete
 * corresponding machine-readable source code or with a written offer, valid for at
 * least three years, to furnish the complete corresponding machine-readable source code.
 *
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * Description : Abstract representation of an action driver. Must be implemented.
 */
                                 
require_once('../classes/class.AbstractTest.php');

class ServerEncoding extends AbstractTest
{
    function ServerEncoding() { parent::AbstractTest("Server charset encoding", "You must set a correct charset encoding in your locale definition in the form: en_us.UTF-8. Please refer to setlocale man page. If your detected locale is C, please check <a href=\"http://www.ajaxplorer.info/documentation/chapter-8-faq/#c87\">http://www.ajaxplorer.info/documentation/chapter-8-faq/#c87</a>. "); }
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
