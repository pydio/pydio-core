<?php

class SystemTextEncoding
{
        function getEncoding(){
               // Get the current locale (expecting the filesystem is in the same locale, as the standard says)
               $currentLocale = setlocale(LC_CTYPE, 0);
               $encoding = substr(strrchr($currentLocale, "."), 1);
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

        function fromUTF8($filesystemElement){
        	if(function_exists("iconv")){
               return iconv("UTF-8", SystemTextEncoding::getEncoding(), $filesystemElement);
        	}else{
        		return utf8_decode($filesystemElement);
        	}
        }

        function toUTF8($filesystemElement){
        	if(function_exists("iconv")){
               return iconv(SystemTextEncoding::getEncoding(), "UTF-8", $filesystemElement);
        	}else{
        		return utf8_encode($filesystemElement);
        	}
        }

}

?>
