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
 * Description : Download counter for publiclets.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class PublicletCounter {
	
	static private $counters;
	
	static function getCount($publiclet){
		$counters = self::loadCounters();
		if(isSet($counters[$publiclet])) return $counters[$publiclet];
		return 0;
	}
	
	static function increment($publiclet){
		if(!self::isActive()) return -1 ;
		$counters = self::loadCounters();
		if(!isSet($counters[$publiclet])){
			$counters[$publiclet]  = 0;
		}
		$counters[$publiclet] ++;
		self::saveCounters($counters);
		return $counters[$publiclet];
	}
	
	static function reset($publiclet){
		if(!self::isActive()) return -1 ;
		$counters = self::loadCounters();
		$counters[$publiclet]  = 0;
		self::saveCounters($counters);
	}
	
	static function delete($publiclet){
		if(!self::isActive()) return -1 ;
		$counters = self::loadCounters();
		if(isSet($counters[$publiclet])){
			unset($counters[$publiclet]);
			self::saveCounters($counters);
		}
	}
	
	static private function isActive(){
		return (is_dir(PUBLIC_DOWNLOAD_FOLDER) && is_writable(PUBLIC_DOWNLOAD_FOLDER));
	}
	
	static private function loadCounters(){
		if(!isSet(self::$counters)){
			self::$counters = AJXP_Utils::loadSerialFile(PUBLIC_DOWNLOAD_FOLDER."/.ajxp_publiclet_counters.ser");			
		}
		return self::$counters;
	}
	
	static private function saveCounters($counters){
		self::$counters = $counters;
		AJXP_Utils::saveSerialFile(PUBLIC_DOWNLOAD_FOLDER."/.ajxp_publiclet_counters.ser", $counters, false);
	}
	
}
?>