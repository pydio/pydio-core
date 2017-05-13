<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 *
 * Core extensions and icons supported. You can add a line in this file to support
 * more extensions.
 * Array is ("extension", "icon_name", "key of the message in the i18n file")
 *
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

$RESERVED_EXTENSIONS = array(
    "folder"	=> array("ajxp_folder", "folder.png", "folder", 8),
    "unkown" 	=> array("ajxp_empty", "mime_empty.png", "file", 23)
);
$EXTENSIONS = array(
    // ALL FILES TYPES
    array("mid", "midi.png", "file-music", 9),
    array("txt", "txt2.png", "file-document", 10),
    array("sql","txt2.png", "file-document", 10),
    array("js","javascript.png", "language-javascript", 11),
    array("gif","image.png", "file-image", 12),
    array("jpg","image.png", "file-image", 13),
    array("html","html.png", "language-html5", 14),
    array("htm","html.png", "language-html5", 15),
    array("rar","archive.png", "archive", 60),
    array("gz","zip.png", "archive", 61),
    array("tgz","archive.png", "archive", 61),
    array("z","archive.png", "archive", 61),
    array("ra","video.png", "file-video", 16),
    array("ram","video.png", "file-video",  17),
    array("rm","video.png", "file-video",  17),
    array("pl","source_pl.png", "file-xml", 18),
    array("java","source_pl.png", "file-xml", 18),
    array("zip","zip.png", "archive", 19),
    array("wav","sound.png", "file-music", 20),
    array("php","php.png", "language-php",  21),
    array("php3","php.png", "language-php", 22),
    array("php4","php.png", "language-php", 22),
    array("phar","php.png", "language-php", 22),
    array("phtml","php.png", "language-php", 22),
    array("exe","exe.png", "application", 50),
    array("bmp","image.png", "file-image", 56),
    array("png","image.png", "file-image", 57),
    array("css","css.png", "language-css3", 58),
    array("mp3","sound.png", "file-music", 59),
    array("m4a","sound.png", "file-music",  59),
    array("aac","sound.png", "file-music", 59),
    array("xls","spreadsheet.png", "file-excel", 64),
    array("xlsx","spreadsheet.png", "file-excel", 64),
    array("xlt","spreadsheet.png", "file-excel", 64),
    array("xltx","spreadsheet.png", "file-excel", 64),
    array("ods","spreadsheet.png", "file-excel", 64),
    array("sxc","spreadsheet.png", "file-excel", 64),
    array("csv","spreadsheet.png", "file-excel", 64),
    array("tsv","spreadsheet.png", "file-excel", 64),
    array("doc","word.png", "file-word", 65),
    array("docx","word.png", "file-word", 65),
    array("dot","word.png", "file-word", 65),
    array("dotx","word.png", "file-word", 65),
    array("odt","word.png", "file-word", 65),
    array("swx","word.png", "file-word", 65),
    array("rtf","word.png", "file-word", 65),
    array("ppt","presentation.png", "file-powerpoint", 446),
    array("pps","presentation.png", "file-powerpoint", 446),
    array("odp","presentation.png", "file-powerpoint", 446),
    array("sxi","presentation.png", "file-powerpoint", 446),
    array("key","presentation.png", "file-powerpoint", 446),
    array("pdf","pdf.png", "file-pdf", 79),
    array("mov","video.png", "file-video",  80),
    array("avi","video.png", "file-video",  81),
    array("mpg","video.png",  "file-video", 82),
    array("mpeg","video.png", "file-video",  83),
    array("mp4","video.png", "file-video",  83),
    array("m4v","video.png", "file-video",  83),
    array("ogv","video.png", "file-video",  "Video"),
    array("webm","video.png", "file-video",  "Video"),
    array("wmv","video.png", "file-video",  81),
    array("swf","flash.png", "movie", 91),
    array("flv","flash.png", "movie", 91),
    array("tiff","image.png", "file-image", "TIFF"),
    array("tif","image.png", "file-image", "TIFF"),
    array("svg","image.png", "file-image", "SVG"),
    array("psd","image.png", "file-image", "Photoshop"),
    array("ers","horo.png",  "timer", "Timestamp"),
    array("dwg","dwg.png", "cube-outline", "DWG"),
);
