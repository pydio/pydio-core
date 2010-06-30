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
 * Description : Config file for the phpMail notifier
 * DROP ME IN server/conf folder
 */
$DRIVER_CONF = array(
	/**
	 * Feed the path to image magick convert
	 * Example is given for windows, it's the unique 
	 * working syntax : backslashes, no spaces in dirs, full path.
	 * Will be more straightforward for linux.
	 */
	"FROM"	=> array("address" => "webmaster@ajaxplorer.install", "name" => "The Webmaster"),
	"TO"	=> array(
		array("address" => "test1@yourdomain.com", "name" => "Name 1"),
		array("address" => "test2@yourdomain.com", "name" => "Name 2"),
	),
	"SUBJECT" => "New upload on server!",
	"BODY" 	  => "A new file is being uploaded on the server by the user <b>%user</b>",	
	"MAILER" => "sendmail" // use "mail" or "sendmail", see PHPMailerLite documentation
);
?>