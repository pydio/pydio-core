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
 * Description : Deprecated / Previous bookmarks system, not used anymore.
 */
class BookmarksManager
{
	var $currentUser;
	var $bookmarksFolder = "bookmarks";
	
	function BookmarksManager($user)
	{
		$this->currentUser = $user;
	}
		
	function addBookMark($path)
	{
		$currentBMarks = $this->getBookMarks();
		if(in_array($path, $currentBMarks)) return;
		$fp = fopen($this->bookmarksFolder."/".$this->currentUser.".txt", "a");
		fwrite($fp, ConfService::getCurrentRootDirIndex().":".$path."\n");
		fclose($fp);
	}
	
	function getBookMarks($currentRootOnly = true)
	{
		$bMarks = array();
		if(is_file($this->bookmarksFolder."/".$this->currentUser.".txt"))
		{
			$file = file($this->bookmarksFolder."/".$this->currentUser.".txt");
			foreach ($file as $index => $bookmark)
			{
				$bookmark = str_replace("\n", "", $bookmark);
				if(trim($bookmark) != "")
				{
					$bmRootId = 0;
					$splitTest = split(":", $bookmark);					
					if(is_array($splitTest) && count($splitTest) == 2)
					{
						$bmRootId = intval($splitTest[0]);
						$bookmark = $splitTest[1];
					}
					if(!$currentRootOnly)
					{
						if(!isset($bMarks[$bmRootId])) $bMarks[$bmRootId] = array();
						$bMarks[$bmRootId][] = $bookmark;
					}
					else if($bmRootId == ConfService::getCurrentRootDirIndex())
					{
						$bMarks[] = $bookmark;
					}
				}
			}
		}
		return $bMarks;
	}
	
	function removeBookMark($path)
	{	
		$currentBMarksByRoot = $this->getBookMarks(false);
		$found = false;
		foreach ($currentBMarksByRoot as $currentBMarks)
		{
			if(in_array($path, $currentBMarks)){
				 $found = true;
				 break;
			}
		}
		if(!$found) return ;
		$newBookMarks = array();
		foreach ($currentBMarksByRoot as $crtRoot => $currentBMarks)
		{
			foreach ($currentBMarks as $bookmark)
			{
				if($bookmark == $path || trim($bookmark) == "") continue;
				$newBookMarks[] = $crtRoot.":".$bookmark;
			}
		}
		unlink($this->bookmarksFolder."/".$this->currentUser.".txt");	
		$fp = fopen($this->bookmarksFolder."/".$this->currentUser.".txt", "w");
		fwrite($fp, join("\n", $newBookMarks)."\n");
		fclose($fp);
	}

	function getCurrentUser()
	{
		return $this->currentUser;
	}
	
}

?>