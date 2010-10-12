<?php

defined('AJXP_EXEC') or die( 'Access not allowed');

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
 * Description : configuration file
 */
$RESERVED_EXTENSIONS = array(
	"folder"	=> array("ajxp_folder", "folder.png", 8),
	"unkown" 	=> array("ajxp_empty", "mime_empty.png", 23)
);
$EXTENSIONS = array(
	// ALL FILES TYPES
	array("mid", "midi.png", 9),	
	array("txt", "txt2.png", 10),
	array("sql","txt2.png", 10),
	array("js","javascript.png", 11),
	array("gif","image.png", 12),
	array("jpg","image.png", 13),
	array("html","html.png", 14),
	array("htm","html.png", 15),
	array("rar","archive.png", 60),
	array("gz","zip.png", 61),
	array("tgz","archive.png", 61),
	array("z","archive.png", 61),
	array("ra","video.png", 16),
	array("ram","video.png", 17),
	array("rm","video.png", 17),
	array("pl","source_pl.png", 18),
	array("zip","zip.png", 19),
	array("wav","sound.png", 20),
	array("php","php.png", 21),
	array("php3","php.png", 22),
	array("phtml","php.png", 22),
	array("exe","exe.png", 50),
	array("bmp","image.png", 56),
	array("png","image.png", 57),
	array("css","css.png", 58),
	array("mp3","sound.png", 59),
	array("xls","spreadsheet.png", 64),
	array("doc","document.png", 65),
	array("pdf","pdf.png", 79),
	array("mov","video.png", 80),
	array("avi","video.png", 81),
	array("mpg","video.png", 82),
	array("mpeg","video.png", 83),
	array("wmv","video.png", 81),
	array("swf","flash.png", 91),
	array("flv","flash.png", 91),
	array("tiff","image.png", "TIFF"),
	array("tif","image.png", "TIFF"),
	array("svg","image.png", "SVG"),
	array("psd","image.png", "Photoshop"),
);
?>