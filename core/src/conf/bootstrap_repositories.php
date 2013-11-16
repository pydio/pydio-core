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
 * Description : configuration file
 * BASIC REPOSITORY CONFIGURATION.
 * The standard repository will point to the data path (ajaxplorer/data by default), folder "files"
 * Use the GUI to add new repositories.
 *   + Log in as "admin" and open the "Settings" Repository
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

$REPOSITORIES[0] = array(
    "DISPLAY"		=>	"Default Files",
    "DISPLAY_ID"    =>  430,
    "DESCRIPTION_ID"=>  475,
    "AJXP_SLUG"		=>  "default",
    "DRIVER"		=>	"fs",
    "DRIVER_OPTIONS"=> array(
        "PATH"			=>	"AJXP_DATA_PATH/files",
        "CREATE"		=>	true,
        "RECYCLE_BIN" 	=> 	'recycle_bin',
        "CHMOD_VALUE"   =>  '0600',
        "DEFAULT_RIGHTS"=>  "",
        "PAGINATION_THRESHOLD" => 500,
        "PAGINATION_NUMBER" => 200,
        "META_SOURCES"		=> array(
            "metastore.serial"=> array(
                "METADATA_FILE"	=> ".ajxp_meta",
                "METADATA_FILE_LOCATION" => "infolders"
            ),
            "meta.user"     => array(
                "meta_fields"		=> "tags",
                "meta_labels"		=> "Tags",
                "meta_visibility"   => "hidden"
            ),
            "meta.filehasher"   => array(),
            "meta.watch"        => array(),
            "meta.exif"   => array(
                "meta_fields" => "COMPUTED_GPS.GPS_Latitude,COMPUTED_GPS.GPS_Longitude",
                "meta_labels" => "Latitude,Longitude"
            ),
            "index.lucene" => array(
                "index_meta_fields" => "tags"
            ),
        )
    ),

);

$REPOSITORIES[1] = array(
    "DISPLAY"		=>	"My Files",
    "DISPLAY_ID"    =>  432,
    "DESCRIPTION_ID"=>  476,
    "AJXP_SLUG"		=>  "my-files",
    "DRIVER"		=>	"fs",
    "DRIVER_OPTIONS"=> array(
        "PATH"			=>	"AJXP_DATA_PATH/personal/AJXP_USER",
        "CREATE"		=>	true,
        "RECYCLE_BIN" 	=> 	'recycle_bin',
        "CHMOD_VALUE"   =>  '0600',
        "DEFAULT_RIGHTS"=>  "rw",
        "PAGINATION_THRESHOLD" => 500,
        "PAGINATION_NUMBER" => 200,
        "META_SOURCES"		=> array(
            "metastore.serial"=> array(
                "METADATA_FILE"	=> ".ajxp_meta",
                "METADATA_FILE_LOCATION" => "infolders"
            ),
            "meta.user"     => array(
                "meta_fields"		=> "tags",
                "meta_labels"		=> "Tags",
                "meta_visibility"   => "hidden"
            ),
            "meta.filehasher"   => array(),
            "meta.watch"        => array(),
            "meta.exif"   => array(
                "meta_fields" => "COMPUTED_GPS.GPS_Latitude,COMPUTED_GPS.GPS_Longitude",
                "meta_labels" => "Latitude,Longitude"
            ),
            "index.lucene" => array(
                "index_meta_fields" => "tags",
                "repository_specific_keywords" => "AJXP_USER",
            )
        )
    ),

);

// DO NOT REMOVE THIS!
// USER DASHBOARD
$REPOSITORIES["ajxp_user"] = array(
    "DISPLAY"		    =>	"My Dashboard",
    "DISPLAY_ID"		=>	"user_dash.title",
    "DESCRIPTION_ID"	=>	"user_dash.desc",
    "DRIVER"		    =>	"ajxp_user",
    "DRIVER_OPTIONS"    => array(
        "DEFAULT_RIGHTS" => "rw"
    )
);

// ADMIN REPOSITORY
$REPOSITORIES["ajxp_conf"] = array(
    "DISPLAY"		=>	"Settings",
    "DISPLAY_ID"		=>	"165",
    "DESCRIPTION_ID"	=>	"506",
    "DRIVER"		=>	"ajxp_conf",
    "DRIVER_OPTIONS"=> array()
);

$REPOSITORIES["fs_template"] = array(
    "DISPLAY"		=>	"Sample Template",
    "DISPLAY_ID"    =>  431,
    "IS_TEMPLATE"	=>  true,
    "DRIVER"		=>	"fs",
    "DRIVER_OPTIONS"=> array(
        "CREATE"		=>	true,
        "RECYCLE_BIN" 	=> 	'recycle_bin',
        "CHMOD_VALUE"   =>  '0600',
        "PAGINATION_THRESHOLD" => 500,
        "PAGINATION_NUMBER" => 200,
        "PURGE_AFTER"       => 0,
        "CHARSET"           => "",
        "META_SOURCES"		=> array(
            "metastore.serial"=> array(
                "METADATA_FILE"	=> ".ajxp_meta",
                "METADATA_FILE_LOCATION" => "infolders"
            ),
            "meta.user"     => array(
                "meta_fields"		=> "tags",
                "meta_labels"		=> "Tags",
                "meta_visibility"   => "hidden"
            ),
            "meta.filehasher"   => array(),
            "meta.watch"        => array(),
            "meta.exif"   => array(
                "meta_fields" => "COMPUTED_GPS.GPS_Latitude,COMPUTED_GPS.GPS_Longitude",
                "meta_labels" => "Latitude,Longitude"
            ),
            "index.lucene" => array(
                "index_meta_fields" => "tags"
            )
        )
    ),

);
