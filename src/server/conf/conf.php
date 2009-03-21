<?php
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
define("AJXP_VERSION", "2.4.rc2");
define("AJXP_VERSION_DATE", "2009/02/17");

define("ENABLE_USERS", 1);
define("ADMIN_PASSWORD", "admin");
define("ALLOW_GUEST_BROWSING", 0);
define("AUTH_MODE", "ajaxplorer"); // "ajaxplorer", "local_http", "remote", "wordpress"

define("AUTH_MODE_REMOTE_SERVER", "www.yourdomain.com"); //
define("AUTH_MODE_REMOTE_URL", "/answering_script.php"); // 
define("AUTH_MODE_REMOTE_USER", ""); // 
define("AUTH_MODE_REMOTE_PASSWORD", ""); // 
define("AUTH_MODE_REMOTE_PORT", 80); // 
define("AUTH_MODE_REMOTE_SESSION_NAME", "session_id"); // 

define("HTTPS_POLICY_FILE", "");

/*********************************************************/
/* BASIC REPOSITORY CONFIGURATION.
/* Use the GUI to add new repositories to explore!
/*   + Log in as "admin" and go to "Settings">"Repositories"
/*********************************************************/
$REPOSITORIES[0] = array(
	"DISPLAY"		=>	"Default Files", 
	"DRIVER"		=>	"fs", 
	"DRIVER_OPTIONS"=> array(
		"PATH"			=>	realpath(dirname(__FILE__)."/../../files"), 
		"CREATE"		=>	true,
		"RECYCLE_BIN" 	=> 	'recycle_bin',
		"CHMOD_VALUE"   =>  '0600'
	)
);


/**
 * Specific config for wordpress plugin, still experimental, do not touch if you are not sure!
 * If you add this and create an "admin" user in ajaxplorer, 
 * you should be able to access your files in the wordpress "admin" section, 
 * in the "Manage" chapter, new tab "Ajaxplorer File Management". Tested on WP 2.1
 */
if(AUTH_MODE == "wordpress"){
	$REPOSITORIES[0] = array(
		"DISPLAY"		=>	"Wordpress", 
		"DRIVER"		=>	"fs", 
		"DRIVER_OPTIONS"=> array(
			"PATH"			=>	realpath(dirname(__FILE__)."/../../../../../wp-content"), 
			"CREATE"		=>	false,
			"RECYCLE_BIN" 	=> 	'recycle_bin'
		)
	);
}

/*********************************************/
/*	DEFAULT LANGUAGE
/*  Check i18n folder for available values.
/*********************************************/
$default_language="en";


/*********************************************/
/*	GLOBAL UPLOAD CONFIG
/*********************************************/
// Maximum number of files for each upload. Leave to 0 for no limit.
$upload_max_number = 16;

// Maximum size per file allowed to upload. By default, this is fixed by php config 'upload_max_filesize'.
// Use this one only if you want to set it smaller than the php config. If you want to increase the php value, 
// please check the PHP documentation for how to set a php config.
//
// Use either the php config syntax with letters for size (e.g. "2M" for 2MegaBytes , "1G" for one gigabyte, etc.) 
// or an integer value like 2097152 for 2 megabytes.
$upload_max_size_per_file = 0;

// Maximum total size (all files size cumulated) by upload.
// Leave to 0 if you do not want any limit.
// See the previous variable for syntax ("2M" or 2097152 )
$upload_max_size_total = 0;

/*********************************************/
/* WEBMASTER EMAIL / NOT USED AT THE MOMENT!!
/*********************************************/
$webmaster_email = "webmaster@yourdomain.com";

/**************************************************/
/*  HTTPS DOMAIN? (USED TO CORRECT A BUG IN IE)
/**************************************************/
$use_https=false;

/**************************************************/
/* MAX NUMBER CHARS FOR FILE AND DIRECTORY NAMES
/**************************************************/
$max_caracteres=50;



/**************************************************/
/*	ADVANCED : DO NOT CHANGE THESE VARIABLES BELOW
/**************************************************/
$installPath = realpath(dirname(__FILE__)."/../..");
define("INSTALL_PATH", $installPath);
define("USERS_DIR", $installPath."/server/users");
define("SERVER_ACCESS", "content.php");
define("ADMIN_ACCESS", "admin.php");
define("IMAGES_FOLDER", "client/images");
define("CLIENT_RESOURCES_FOLDER", "client");
define("SERVER_RESOURCES_FOLDER", "server/classes");
define("DOCS_FOLDER", "client/doc");
define("TESTS_RESULT_FILE", $installPath."/server/conf/diag_result.php");


define("OLD_USERS_DIR", $installPath."/bookmarks");
define("INITIAL_ADMIN_PASSWORD", "admin");

define("GZIP_DOWNLOAD", false);
define("GZIP_LIMIT", 1*1048576); // Do not Gzip files above 1M
?>