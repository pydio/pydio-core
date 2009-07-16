<?php
/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Charles du Jeu
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
 * Description : Static functions for generation HTML.
 */
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
    		$string = "<html><head><link rel=\"stylesheet\" type=\"text/css\" href=\"".CLIENT_RESOURCES_FOLDER."/css/docs.css\"></head><body>";
    		$content = implode("<br>", file($realName));
    		$content = preg_replace("(http:\/\/[a-z|.|\/|\-|0-9]*)", "<a target=\"_blank\" href=\"$0\">$0</a>", $content);
    		$content = preg_replace("(\[(.*)\])", "<div class=\"title\">$1</div>", $content);
    		$content = preg_replace("(\+\+ (.*) \+\+)", "<div class=\"subtitle\">$1</div>", $content);
    		$content = str_replace("__AJXP_VERSION__", AJXP_VERSION, $content);
    		$content = str_replace("__AJXP_VERSION_DATE__", AJXP_VERSION_DATE, $content);
    		$string .=  $content."</body></html>";
    		return $string;
    	}
    	return "File not found : ".$docFileName;
    }
    
    function repositoryDataAsJS(){
    	if(AuthService::usersEnabled()) return "";
    	require_once(INSTALL_PATH."/server/classes/class.SystemTextEncoding.php");
    	require_once(INSTALL_PATH."/server/classes/class.AJXP_XMLWriter.php");
    	return str_replace("'", "\'", AJXP_XMLWriter::writeRepositoriesData(null));
    }
            
    function writeI18nMessagesClass($mess)
    {
    	echo "<script language=\"javascript\">\n";
    	echo "if(!MessageHash) var MessageHash = new Hash();\n";
    	foreach ($mess as $index => $message)
    	{
    		if(is_numeric($index))
    		{
			echo "MessageHash[$index]='".str_replace("'", "\'", $message)."';\n";
    		}
    		else 
    		{
    			echo "MessageHash['$index']='".str_replace("'", "\'", $message)."';\n";
    		}
    			
    	}
    	echo "MessageHash;";
    	echo "</script>\n";
    }
    
    function closeBodyAndPage(){
    	print("</body></html>");
    }
    
    function javascriptErrorHandler($errorType, $errorMessage){    	
    	restore_error_handler();    	
    	die("<script language='javascript'>parent.ajaxplorer.displayMessage('ERROR', '".str_replace("'", "\'", $errorMessage)."');</script>");
    }
    
}

?>
