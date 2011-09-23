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
 * Description : Config file for the editor.pdf plugin.
 * DROP ME IN conf folder
 */
$DRIVER_CONF = array(
	/**
	 * Feed the path to image magick convert
	 * Example is given for windows, it's the unique 
	 * working syntax : backslashes, no spaces in dirs, full path.
	 * Will be more straightforward for linux.
	 */
	"IMAGE_MAGICK_CONVERT"	=> "C:\PROGRA~1\ImageMagick-6.6.0-Q16\convert.exe",
	"IM_THUMB_QUALITY"		=> 65,
	"IM_VIEWER_QUALITY"		=> 90
);
?>