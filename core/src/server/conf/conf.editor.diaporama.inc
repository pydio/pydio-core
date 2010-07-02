<?php
/**
 * @package info.ajaxplorer.plugins
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
 * Description : Config file for the editor.pdf plugin.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

$DRIVER_CONF = array(
	/************************************/
	/* THUMBNAIL MANAGEMENT				*/
	/************************************/	
	"GENERATE_THUMBNAIL" => true,
	// "Quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.
	// Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
	// 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
	// 2 = Up to 95 times faster.  Images appear a little sharp, some prefer this over a quality of 3.
	// 3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled, just faster.
	// 4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
	// 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.
	"THUMBNAIL_QUALITY" => 1,
	// Whether they are stored in a cache dir. At the moment, there is no mechanism for clearing the cache
	// So if you use it, it will be faster for the users, but the cache repository will increase size without stoping.
	"USE_THUMBNAIL_CACHE" => false,
	"THUMBNAIL_CACHE_DIR" => INSTALL_PATH."/server/tmp/" // Must end with a slash! Should be placed outside the distribution, since it can grow heavily!
);
?>