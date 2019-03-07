<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */

defined('AJXP_EXEC') or die('Access not allowed');

// This is used to manually configure the absolute path to php binary on the local machine
// Remember to clear the plugins cache after changing this value.
$DRIVER_CONF = [
    //Full path on the server to the 'convert' binary
    "IMAGE_MAGICK_CONVERT" => "/opt/local/bin/convert",
    //Additional path to put in the environment PATH variable before calling the command. Can be necessary for Ghostscript on Mac for example.
    "ADDITIONAL_ENV_PATH" => "",
    //Full path on the server to the 'unoconv' binary (see docs)
    "UNOCONV" => "",
    // Imagick Options used for generating the thumbnails
    "IM_THUMB_QUALITY" => "-quality 65 -density 150 -resize 250 -flatten",
    // Imagick Options used for generation the high scale images
    "IM_VIEWER_QUALITY" => "-quality 90 -density 250",
    // You can add any Image Magick options you want here, applied to both thumbs and big images
    "IM_CUSTOM_OPTIONS" => ""

];
