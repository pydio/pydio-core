<?php

class AJXP_XMLWriter
{
	
	function header()
	{
		header('Content-Type: text/xml; charset=UTF-8');
		header('Cache-Control: no-cache');
		print('<?xml version="1.0" encoding="UTF-8"?>');
		print("<tree>");
		
	}
	
	function close()
	{
		print("</tree>");
	}
	
	function reloadCurrentNode()
	{
		print("<reload_instruction object=\"tree\"/>");
	}
	
	function reloadNode($nodeName)
	{
		print("<reload_instruction object=\"tree\" node=\"$nodeName\"/>");
	}
	
	function requireAuth()
	{
		print("<require_auth/>");
	}
	
	function reloadFileList($fileOrBool)
	{
		if(is_string($fileOrBool)) print "<reload_instruction object=\"list\" file=\"".utf8_encode($fileOrBool)."\"/>";
		else print "<reload_instruction object=\"list\"/>";
	}
	
	function writeBookmarks($allBookmarks)
	{
		foreach ($allBookmarks as $bookmark)
		{
			$path = ""; $title = "";
			if(is_array($bookmark)){
				$path = $bookmark["PATH"];
				$title = $bookmark["TITLE"];
			}else if(is_string($bookmark)){
				$path = $bookmark;
				$title = basename($bookmark);
			}
			print("<bookmark path=\"".$path."\" title=\"".$title."\"/>");
		}
	}
	
	function sendMessage($logMessage, $errorMessage)
	{
		$messageType = ""; 
		$message = "";
		if($errorMessage == null)
		{
			$messageType = "SUCCESS";
			$message = $logMessage;
		}
		else
		{
			$messageType = "ERROR";
			$message = $errorMessage;
		}
		print("<message type=\"$messageType\">".utf8_encode($message)."</message>");
	}
	
	function sendUserData()
	{
		$loggedUser = AuthService::getLoggedUser();
		if($loggedUser != null)
		{
			print("<user id=\"".$loggedUser->id."\">");
			print("<active_repo id=\"".ConfService::getCurrentRootDirIndex()."\" write=\"".($loggedUser->canWrite(ConfService::getCurrentRootDirIndex())?"1":"0")."\" read=\"".($loggedUser->canRead(ConfService::getCurrentRootDirIndex())?"1":"0")."\"/>");
			print("<repositories>");
			foreach (ConfService::getRootDirsList() as $rootDirIndex => $rootDirData)
			{
				if($loggedUser->canRead($rootDirIndex)) print("<repo id=\"".$rootDirIndex."\">".utf8_encode($rootDirData["DISPLAY"])."</repo>");
			}
			print("</repositories>");
			print("<preferences>");
			print("<pref name=\"display\" value=\"".$loggedUser->getPref("display")."\"/>");
			print("<pref name=\"lang\" value=\"".$loggedUser->getPref("lang")."\"/>");
			print("</preferences>");
			print("</user>");
		}		
	}
	
	function loggingResult($result)
	{
		print("<logging_result value=\"$result\"/>");
	}
	
    function bookmarkBar($currentUser, $allBookmarks)
    {
		echo '<div id="bm_bar_cont"><div id="bookmarks_bar">';
		foreach ($allBookmarks as $path)
		{
			echo '<div><img src="images/foldericon.png" border="0" align="ABSMIDDLE"><a class="delete_bm_button" href="content.php?action=display_bookmark_bar&user='.$currentUser.'&bm_action=delete_bookmark&bm_path='.$path.'"><img src="images/crystal/delete_bookmark.png" border="0" align="ABSMIDDLE" alt="Delete Bookmark" title="Delete Bookmark"></a> <a href="#" onclick="getFrame(\'actionbar\').ActionBar.locationBarSubmit(\''.$path.'\'); return false;" class="bookmark_button">'.$path.'</a></div>';
		}
		echo '</div></div>';		
    }	
    
    function writeRootDirChooser($rootDirsList, $crtIndex)
    {
    	echo '<select id="rootDirChooser" onChange="document.location.href=\'content.php?action=root_tree&root_dir_index=\'+this.options[this.selectedIndex].value;">';
    	foreach ($rootDirsList as $rIndex => $rName)
    	{
    		$selected = "";
    		if($rIndex == $crtIndex) $selected = " selected";
    		echo '<option value="'.$rIndex.'"'.$selected.'>'.$rName["DISPLAY"].' ('.$rName["PATH"].')</option>';
    	}
    	echo '</select>';
    }
    
    function writeSessionDataForJs()
    {
    	if(session_id()!= "")
    	{
    		echo "<script language=\"javascript\">document.PHPSESSID='".session_id()."';</script>\n";
    	}
    }
    
    function writeI18nMessagesClass($mess)
    {
    	echo "<script language=\"javascript\">";
    	echo "var MessageClass = {askOverwrite:'".str_replace("'", "\'", $mess[124])."', filenameExists:'".str_replace("'", "\'", $mess[125])."'};";
    	echo "</script>";
    }
    
}

?>