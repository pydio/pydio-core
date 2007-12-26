<?php

class HTMLWriter
{

	function displayMessage($logMessage, $errorMessage)
	{
		$mess = ConfService::getMessages();
		echo "<div title=\"".$mess[98]."\" id=\"message_div\" onclick=\"closeMessageDiv();\" class=\"messageBox ".(isset($logMessage)?"logMessage":"errorMessage")."\"><table width=\"100%\"><tr><td style=\"width: 66%;\">".(isset($logMessage)?$logMessage:$errorMessage)."</td><td style=\"color: #999; text-align: right;padding-right: 10px; width: 30%;\"><i>".$mess[98]."</i></tr></table></div>";
		echo "<script>tempoMessageDivClosing();</script>";
	}
	
    function bookmarkBar($allBookmarks)
    {
    	//echo '<div id="bmbar_title">MyBookmarks&nbsp;&nbsp;</div>';
    	$mess = ConfService::getMessages();
		foreach (array_reverse($allBookmarks) as $path)
		{
			if(is_array($path)) $path = $path["PATH"];
			echo '<div class="bm" onmouseover="this.className=\'bm_hover\';" onmouseout="this.className=\'bm\';"><img width="16" height="16" src="'.CLIENT_RESOURCES_FOLDER.'/images/crystal/mimes/16/folder.png" border="0" align="ABSMIDDLE" style="float:left;"><a href="#" class="disabled" title="'.$mess[146].'" onclick="ajaxplorer.actionBar.removeBookmark(\''.$path.'\'); return false;" onmouseover="$(this).addClassName(\'enabled\');" onmouseout="$(this).removeClassName(\'enabled\');"><img width="16" height="16" src="'.CLIENT_RESOURCES_FOLDER.'/images/crystal/actions/16/delete_bookmark.png" border="0" align="ABSMIDDLE" alt="'.$mess[146].'"></a> <a href="#" onclick="ajaxplorer.goTo(\''.$path.'\'); return false;" class="bookmark_button">'.$path.'</a></div>';
		}
    }	
    
    function getDocFile($docFileName)
    {
    	$realName = INSTALL_PATH."/".DOCS_FOLDER."/".$docFileName.".txt";
    	if(is_file($realName))
    	{
    		$string = "<html><link rel=\"stylesheet\" type=\"text/css\" href=\"".CLIENT_RESOURCES_FOLDER."/css/docs.css\"><body>";
    		$content = implode("<br>", file($realName));
    		$content = preg_replace("(http:\/\/[a-z|.|\/|\-|0-9]*)", "<a target=\"_blank\" href=\"$0\">$0</a>", $content);
    		$content = preg_replace("(\[(.*)\])", "<div class=\"title\">$1</div>", $content);
    		$content = preg_replace("(\+\+ (.*) \+\+)", "<div class=\"subtitle\">$1</div>", $content);
    		$string .=  $content."</body></html>";
    		return $string;
    	}
    	return "File not found : ".$docFileName;
    }
        
    function writeRootDirListAsJsString($rootDirsList)
    {
    	$buffer = "\$H({";
    	foreach ($rootDirsList as $rIndex => $rName)
    	{
    		$buffer .= "$rIndex:'".$rName["DISPLAY"]."'";
    		if($rIndex < count($rootDirsList)-1) $buffer .= ", ";
    	}
    	$buffer .= "})";
    	return $buffer;
    }
    
    function writeI18nMessagesClass($mess)
    {
    	echo "<script language=\"javascript\">\n";
    	echo "var MessageHash = new Hash();\n";
    	foreach ($mess as $index => $message)
    	{
    		if(is_numeric($index))
    		{
    			echo utf8_encode("MessageHash[$index]='".str_replace("'", "\'", $message)."';\n");
    		}
    		else 
    		{
    			echo utf8_encode("MessageHash['$index']='".str_replace("'", "\'", $message)."';\n");
    		}
    			
    	}
    	echo "</script>\n";
    }
    
}

?>