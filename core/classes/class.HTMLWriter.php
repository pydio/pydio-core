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

/**
 * @package info.ajaxplorer.core
 * @class HTMLWriter
 * Static functions for generating HTML
 */
class HTMLWriter
{

	static function displayMessage($logMessage, $errorMessage)
	{
		$mess = ConfService::getMessages();
		echo "<div title=\"".$mess[98]."\" id=\"message_div\" onclick=\"closeMessageDiv();\" class=\"messageBox ".(isset($logMessage)?"logMessage":"errorMessage")."\"><table width=\"100%\"><tr><td style=\"width: 66%;\">".(isset($logMessage)?$logMessage:$errorMessage)."</td><td style=\"color: #999; text-align: right;padding-right: 10px; width: 30%;\"><i>".$mess[98]."</i></tr></table></div>";
		echo "<script>tempoMessageDivClosing();</script>";
	}
	    
    static function getDocFile($docFileName)
    {
    	$realName = AJXP_DOCS_FOLDER."/".$docFileName.".txt";
    	if(is_file($realName))
    	{
    		$content = implode("<br>", file($realName));
    		$content = preg_replace("(http:\/\/[a-z|.|\/|\-|0-9]*)", "<a target=\"_blank\" href=\"$0\">$0</a>", $content);
    		$content = preg_replace("(\[(.*)\])", "<div class=\"title\">$1</div>", $content);
    		$content = preg_replace("(\+\+ (.*) \+\+)", "<div class=\"subtitle\">$1</div>", $content);
    		$content = str_replace("__AJXP_VERSION__", AJXP_VERSION, $content);
    		$content = str_replace("__AJXP_VERSION_DATE__", AJXP_VERSION_DATE, $content);
    		return $content;
    	}
    	return "File not found : ".$docFileName;
    }
    
    static function repositoryDataAsJS(){
    	if(AuthService::usersEnabled()) return "";
    	require_once(AJXP_BIN_FOLDER."/class.SystemTextEncoding.php");
    	require_once(AJXP_BIN_FOLDER."/class.AJXP_XMLWriter.php");
    	return str_replace("'", "\'", AJXP_XMLWriter::writeRepositoriesData(null));
    }
              
    static function writeI18nMessagesClass($mess)
    {
    	echo "<script language=\"javascript\">\n";
    	echo "if(!MessageHash) window.MessageHash = new Hash();\n";
    	foreach ($mess as $index => $message)
    	{
    		// Make sure \n are double antislashed (\\n).
    		$message = preg_replace("/\n/", "\\\\n", $message);
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
       
    static function charsetHeader($type = 'text/html', $charset='UTF-8'){
    	header("Content-type:$type; charset=$charset");
    }
    
    static function closeBodyAndPage(){
    	print("</body></html>");
    }
    
    static function javascriptErrorHandler($errorType, $errorMessage){    	
    	// Handle "@" case!
    	if(error_reporting() == 0) return ;
    	restore_error_handler();    	
    	die("<script language='javascript'>parent.ajaxplorer.displayMessage('ERROR', '".str_replace("'", "\'", $errorMessage)."');</script>");
    }
    
}

?>
