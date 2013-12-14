<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 *
 * Core extensions and icons supported. You can add a line in this file to support
 * more extensions.
 * Array is ("extension", "icon_name", "key of the message in the i18n file")
 *
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

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
    array("m4a","sound.png", 59),
    array("aac","sound.png", 59),
    array("xls","spreadsheet.png", 64),
    array("xlsx","spreadsheet.png", 64),
    array("xlt","spreadsheet.png", 64),
    array("xltx","spreadsheet.png", 64),
    array("ods","spreadsheet.png", 64),
    array("sxc","spreadsheet.png", 64),
    array("csv","spreadsheet.png", 64),
    array("tsv","spreadsheet.png", 64),
    array("doc","word.png", 65),
    array("docx","word.png", 65),
    array("dot","word.png", 65),
    array("dotx","word.png", 65),
    array("odt","word.png", 65),
    array("swx","word.png", 65),
    array("rtf","word.png", 65),
    array("ppt","presentation.png", 442),
    array("pps","presentation.png", 442),
    array("odp","presentation.png", 442),
    array("sxi","presentation.png", 442),
    array("pdf","pdf.png", 79),
    array("mov","video.png", 80),
    array("avi","video.png", 81),
    array("mpg","video.png", 82),
    array("mpeg","video.png", 83),
    array("mp4","video.png", 83),
    array("m4v","video.png", 83),
    array("ogv","video.png", "Video"),
    array("webm","video.png", "Video"),
    array("wmv","video.png", 81),
    array("swf","flash.png", 91),
    array("flv","flash.png", 91),
    array("tiff","image.png", "TIFF"),
    array("tif","image.png", "TIFF"),
    array("svg","image.png", "SVG"),
    array("psd","image.png", "Photoshop"),
    array("ers","horo.png", "Timestamp"),
);
