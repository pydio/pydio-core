<?php

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