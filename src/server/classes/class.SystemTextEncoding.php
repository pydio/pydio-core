<?php

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
			$encoding = "windows-".$encoding;
		} else if ($currentLocale == "C")
		{   // Locale not set correctly, most probable error cause is /etc/init.d/apache having "LANG=C" defined
			// In any case, "C" is ASCII-7 bit so it's safe to use the extra bit as if it was UTF-8 
			$encoding = "UTF-8";
		}
		return $encoding;
	}
	
	function getEncoding(){
	       global $_SESSION;
	       // Check if the session get an assigned charset encoding (it's the case for remote SSH for example)
	       if (isset($_SESSION["charset"])) return $_SESSION["charset"];
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
	    if (get_magic_quotes_gpc() == 1)
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
