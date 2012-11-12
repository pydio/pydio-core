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
 * @class PublicletCounter
 * Download counter for publiclets
 */
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
		return (is_dir(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")) && is_writable(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")));
	}
	
	static private function loadCounters(){
		if(!isSet(self::$counters)){
			self::$counters = AJXP_Utils::loadSerialFile(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")."/.ajxp_publiclet_counters.ser");			
		}
		return self::$counters;
	}
	
	static private function saveCounters($counters){
		self::$counters = $counters;
		AJXP_Utils::saveSerialFile(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")."/.ajxp_publiclet_counters.ser", $counters, false);
	}
	
}
?>