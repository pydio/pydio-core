<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
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
 *
 * Description : Config file for the phpMail notifier
 * DROP ME IN conf folder
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
    // you can use AJXP_USER, AJXP_FILE, AJXP_FOLDER and AJXP_ACTION keywords.
	"BODY" 	  => "A new file is being uploaded on the server by the user <b>AJXP_USER</b>",
	"MAILER" => "sendmail" // use "mail" or "sendmail", see PHPMailerLite documentation
);
?>