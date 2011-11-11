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
				"meta_fields"		=> "comment",
				"meta_labels"		=> "Comment",
                "meta_visibility"   => "hidden"
			),
            "index.lucene" => array(
                "index_meta_fields" => "comment"
            )
		)
	),

);

$REPOSITORIES[1] = array(
	"DISPLAY"		=>	"My Files",
    "DISPLAY_ID"    =>  432,
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
				"meta_fields"		=> "comment",
				"meta_labels"		=> "Comment",
                "meta_visibility"   => "hidden"
			),
            "index.lucene" => array(
                "index_meta_fields" => "comment"
            )
		)
	),

);

// DO NOT REMOVE THIS!
// SHARE ELEMENTS
$REPOSITORIES["ajxp_shared"] = array(
	"DISPLAY"		=>	"Shared Elements",
	"DISPLAY_ID"		=>	"363",
	"DRIVER"		=>	"ajxp_shared",
	"DRIVER_OPTIONS"=> array(
		"DEFAULT_RIGHTS" => "rw"
	)
);

// ADMIN REPOSITORY
$REPOSITORIES["ajxp_conf"] = array(
	"DISPLAY"		=>	"Settings",
	"DISPLAY_ID"		=>	"165",
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
				"meta_fields"		=> "comment",
				"meta_labels"		=> "Comment",
                "meta_visibility"   => "hidden"
			),
            "index.lucene" => array(
                "index_meta_fields" => "comment"
            )
		)
	),

);
