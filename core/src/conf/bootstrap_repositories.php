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
 * BASIC REPOSITORY CONFIGURATION.
 * Use the GUI to add new repositories to explore!
 *   + Log in as "admin" and open the "Settings" Repository
 */
$REPOSITORIES[0] = array(
	"DISPLAY"		=>	"Default Files",
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
		/*
			"meta.serial"=> array(
				"meta_file_name"	=> ".ajxp_meta",
				"meta_fields"		=> "testKey1,stars_rate,css_label",
				"meta_labels"		=> "Test Key,Rating,Label"
			)
		*/
            "index.lucene" => array(
                "index_meta_fields" => ""
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