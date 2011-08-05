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

// Startup admin password (used at first creation). Once
// The admin password is created and his password is changed, 
// this config has no more impact.
define("ADMIN_PASSWORD", "admin");


/************************************************
 * TEMPORARY DIR : NOT NECESSARY IN MOST CASES
 ************************************************/
/**
 * This is necessary only if you have errors concerning
 * the tmp dir access or writeability : most probably, 
 * they are due to PHP SAFE MODE (should disappear in php6) or
 * various OPEN_BASEDIR restrictions.
 * In that case, create and set writeable a tmp folder somewhere
 * at the root of your hosting (but above the web/ or www/ or http/ 
 * if possible!!) and enter here the full path to this folder.
 * For example : define("AJXP_TMP_DIR", "/server/root/path/to/user/ajxp_tmp"));
 */
define("AJXP_TMP_DIR", "");

/********************************************
 * CUSTOM VARIABLES HOOK
 ********************************************/
/**
 * This is a sample "hard" hook, directly included. See directly the HookDemo class
 * for more explanation.
 */
//require_once AJXP_INSTALL_PATH."/plugins/hook.demo/class.HookDemo.php";
//AJXP_Controller::registerIncludeHook("vars.filter", array("HookDemo", "filterVars"));

/*********************************************************/
/* PLUGINS DEFINITIONS
/* Drivers will define how the application will work. For 
/* each type of operation, there are multiple implementation
/* possible. Check the content of the plugins folder.
/* CONF = users and repositories definition, 
/* AUTH = users authentification mechanism,
/* LOG = logs of the application.
/*
/* By default, the three are all based on files.
/*
/* ACTIVE_PLUGINS adds other type of plugins to the application.
/* If you are developping your own plugin, do not forget to declare
/* it here.
/*********************************************************/
$PLUGINS = array(
	"CONF_DRIVER" => array(
		"NAME"		=> "serial",
		"OPTIONS"	=> array(
			"REPOSITORIES_FILEPATH"	=> "AJXP_DATA_PATH/plugins/conf.serial/repo.ser",
			"ROLES_FILEPATH"		=> "AJXP_DATA_PATH/plugins/auth.serial/roles.ser",
			"USERS_DIRPATH"			=> "AJXP_DATA_PATH/plugins/auth.serial",
			/*"CUSTOM_DATA"			=> array(
					"email"	=> "Email", 
					"country" => "Country"
				)*/
			)
	),
	"AUTH_DRIVER" => array(
		"NAME"		=> "serial",
		"OPTIONS"	=> array(
			"LOGIN_REDIRECT"		=> false,
			"USERS_FILEPATH"		=> "AJXP_DATA_PATH/plugins/auth.serial/users.ser",
			"AUTOCREATE_AJXPUSER" 	=> false, 
			"TRANSMIT_CLEAR_PASS"	=> false )
	),
	/*
	"AUTH_DRIVER" => array(
		"NAME"		=> "remote",
		"OPTIONS"	=> array(
			"SLAVE_MODE"  => true,
			"USERS_FILEPATH" => "AJXP_INSTALL_PATH/data/users/users.ser",
			"MASTER_AUTH_FUNCTION" => "joomla_remote_auth",
			"MASTER_HOST"		=> "localhost",
			"MASTER_URI"		=> "/joomla/",
			"LOGIN_URL" => "/joomla/",  // The URL to redirect (or call) upon login (typically if one of your user type: http://yourserver/path/to/ajxp, he will get redirected to this url to login into your frontend
			"LOGOUT_URL" => "/joomla/",  // The URL to redirect upon login out (see above)
			"SECRET" => "myprivatesecret",// the same as the one you put in the WP plugin option.
			"TRANSMIT_CLEAR_PASS"   => true // Don't touch this. It's unsafe (and useless here) to transmit clear password.
		)
	),
	*/
	"LOG_DRIVER" => array(
	 	"NAME" => "text",
	 	"OPTIONS" => array( 
	 		"LOG_PATH" => "AJXP_INSTALL_PATH/data/logs/",
	 		"LOG_FILE_NAME" => 'log_' . date('m-d-y') . '.txt',
	 		"LOG_CHMOD" => 0770
	 	)
	),
	// Do not use wildcard for uploader, to keep them in a given order
	// Warning, do not add the "meta." plugins, they are automatically
	// detected and activated by the application.
	"ACTIVE_PLUGINS" => array("core.*", "editor.*", "uploader.flex", "uploader.html", "gui.ajax", "hook.*", "downloader.*", "shorten.*")
);
if(AJXP_Utils::userAgentIsMobile()){
	$PLUGINS["ACTIVE_PLUGINS"][] = "gui.mobile";
	if(AJXP_Utils::userAgentIsIOS() && !isSet($_GET["skipIOS"]) && !isSet($_COOKIE["SKIP_IOS"])){
		$PLUGINS["ACTIVE_PLUGINS"][] = "gui.ios";
	}
}
if(isSet($_COOKIE["AJXP_GUI"])){
	$PLUGINS["ACTIVE_PLUGINS"][] = "gui.".$_COOKIE["AJXP_GUI"];
}
/*********************************************************/
/* BASIC REPOSITORY CONFIGURATION.
/* Use the GUI to add new repositories to explore!
/*   + Log in as "admin" and open the "Settings" Repository
/*********************************************************/
$REPOSITORIES[0] = array(
	"DISPLAY"		=>	"Default Files", 
	"AJXP_SLUG"		=>  "default",
	"DRIVER"		=>	"fs", 
	"DRIVER_OPTIONS"=> array(
		"PATH"			=>	"AJXP_INSTALL_PATH/data/files", 
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
$REPOSITORIES[1] = array(
	"DISPLAY"		=>	"Settings", 
	"DISPLAY_ID"		=>	"165", 
	"DRIVER"		=>	"ajxp_conf", 
	"DRIVER_OPTIONS"=> array()	
);

/*********************************************/
/*	DEFAULT LANGUAGE
/*  Check i18n folder for available values.
/*********************************************/
$default_language="en";

?>