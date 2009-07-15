<?php
/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Charles du Jeu, Cyril Russo
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
 * Description : static methods for handling charset conversions.
 */
class SystemTextEncoding
{
	function changeCharset($inputCharset, $outputCharset, $text)
	{
	    if ($inputCharset == $outputCharset) return $text;
		// Due to iconv bug when dealing with text with non ASCII encoding for last char, we use this workaround http://fr.php.net/manual/fr/function.iconv.php#81494
		if(function_exists("iconv"))
		{
		    
			return iconv($inputCharset, $outputCharset, $text);
		}else
		{
			$content = @htmlentities($text, ENT_QUOTES, $inputCharset);  
			return @html_entity_decode($content, ENT_QUOTES , $outputCharset);
		}
	}
	
	function parseCharset($locale)
	{
		$encoding = substr(strrchr($locale, "."), 1);
		if (is_numeric($encoding))
		{
		    if (substr($encoding, 0, 2) == "12") // CP12xx are changed to Windows-12xx to allow PHP4 conversion
  			    $encoding = "windows-".$encoding;
  			else $encoding = "CP".$encoding; // In other cases, PHP4 won't work anyway, so use CPxxxx encoding (that iconv supports)
		} else if ($locale == "C")
		{   // Locale not set correctly, most probable error cause is /etc/init.d/apache having "LANG=C" defined
			// In any case, "C" is ASCII-7 bit so it's safe to use the extra bit as if it was UTF-8 
			$encoding = "UTF-8";
		}
		if (!strlen($encoding)) $encoding = "UTF-8";
		return $encoding;
	}
	
	function getEncoding(){
	       global $_SESSION;
	       // Check if the session get an assigned charset encoding (it's the case for remote SSH for example)
	       if (isset($_SESSION["AJXP_CHARSET"]) && strlen($_SESSION["AJXP_CHARSET"])) return $_SESSION["AJXP_CHARSET"];
	       // Get the current locale (expecting the filesystem is in the same locale, as the standard says)
	       return SystemTextEncoding::parseCharset(setlocale(LC_CTYPE, 0));
	}
	
	function fromUTF8($filesystemElement){
		$enc = SystemTextEncoding::getEncoding();
	    return SystemTextEncoding::changeCharset("UTF-8", $enc, $filesystemElement);
	}
  
	/** This function is used when the server's PHP configuration is using magic quote */
    function magicDequote($text)
    {
	    // If the PHP server enables magic quotes, remove them
	    if (get_magic_quotes_gpc())
	        return stripslashes($text);
	    return $text;  
    }
	                         
    function fromPostedFileName($filesystemElement)
    {
	    return SystemTextEncoding::fromUTF8(SystemTextEncoding::magicDequote($filesystemElement));
	}
	
	function toUTF8($filesystemElement){
		$enc = SystemTextEncoding::getEncoding();
		return SystemTextEncoding::changeCharset($enc, "UTF-8", $filesystemElement);
	}
}

?>
