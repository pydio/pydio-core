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
/****************************/
/* USERS MANAGEMENT 
/****************************/
// Whether the users system should be active or not.
// If set to FALSE or 0, all files and settings will be
// accessible by everybody
define("ENABLE_USERS", 1);

// Startup admin password (used at first creation). Once
// The admin password is created and his password is changed, 
// this config has no more impact.
define("ADMIN_PASSWORD", "admin");

// Whether a "guest" user should be enabled or not : this guest
// will appear in the list of the users and you can assign rights to him
// like a normal user. If no user is logged, the guest user is logged.
define("ALLOW_GUEST_BROWSING", 0);

// Minimal length required for password. For better security, you 
// should set this to at least 8 characters.
define("AJXP_PASSWORD_MINLENGTH", 8);

// If you want to allow public URL to uploaded file please do the following : 
// + Create this folder.
// + Set it writeable by the server and accessible to public (see next variable).
// + Create an empty index.html file inside it, to be sure that the files listing is not directly browsable.
//
// This is the absolute path of the folder on the server, The default value expects 
// a "public" folder at the root of your ajaxplorer directory.
define("PUBLIC_DOWNLOAD_FOLDER", realpath(dirname(__FILE__)."/../../public")); // Set to '' to disable

// By default, the public download url will be "your ajaxplorer root install"/PUBLIC_DOWNLOAD_FOLDER.
// If you want to set this to another value, use the variable below; otherwise leave empty.
// Example : http://www.mypublicdomain.com/publicdata [NO TRAILING SLASH!]
define("PUBLIC_DOWNLOAD_URL", "");


define("HTTPS_POLICY_FILE", "");

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
			"REPOSITORIES_FILEPATH"	=> "AJXP_INSTALL_PATH/server/conf/repo.ser",
			"ROLES_FILEPATH"		=> "AJXP_INSTALL_PATH/server/users/roles.ser",
			"USERS_DIRPATH"			=> "AJXP_INSTALL_PATH/server/users",
			/*
			"CUSTOM_DATA"			=> array(
				"email"	=> "Email", 
				"country" => "Country"
			)
			*/
			)
	),
	"AUTH_DRIVER" => array(
		"NAME"		=> "serial",
		"OPTIONS"	=> array(
			"LOGIN_REDIRECT"		=> false,
			"USERS_FILEPATH"		=> "AJXP_INSTALL_PATH/server/users/users.ser",
			"AUTOCREATE_AJXPUSER" 	=> false, 
			"TRANSMIT_CLEAR_PASS"	=> false )
	),
	/*
	"AUTH_DRIVER" => array(
		"NAME"		=> "multi",
		"OPTIONS"	=>	array(
			"DRIVERS"	=> array(
				"serial" => array(
					"NAME"		=> "serial",
					"OPTIONS"	=> array(
						"USERS_FILEPATH"		=> "AJXP_INSTALL_PATH/server/users/users.ser",
						"AUTOCREATE_AJXPUSER" 	=> false
					)
				),
				"ftp" => array(
					"NAME"		=> "ftp",
					"OPTIONS"	=> array(
						"REPOSITORY_ID"			=> "dyna_ftp",
						"ADMIN_USER"			=> "admin",
						"FTP_LOGIN_SCREEN" 		=> false
				 	)
				)				
			),
			"MASTER_DRIVER"			=> "serial",
			"TRANSMIT_CLEAR_PASS"	=> true,
			"LOGIN_REDIRECT"		=> false,
		)
	),
	"AUTH_DRIVER" => array(
		"NAME"		=> "ftp",
			"OPTIONS"	=> array(
				"REPOSITORY_ID"			=> "dyna_ftp",
				"ADMIN_USER"			=> "admin",
				"FTP_LOGIN_SCREEN" 		=> false,
				"TRANSMIT_CLEAR_PASS"	=> true,
				"LOGIN_REDIRECT"		=> false,
		)
	),
	*/
	"LOG_DRIVER" => array(
	 	"NAME" => "text",
	 	"OPTIONS" => array( 
	 		"LOG_PATH" => "AJXP_INSTALL_PATH/server/logs/",
	 		"LOG_FILE_NAME" => 'log_' . date('m-d-y') . '.txt',
	 		"LOG_CHMOD" => 0770
	 	)
	),
	// Do not use wildcard for uploader, to keep them in a given order
	// Warning, do not add the "meta." plugins, they are automatically
	// detected and activated by the application.
	"ACTIVE_PLUGINS" => array("editor.*", "uploader.flex", "uploader.html", "gui.ajax", "hook.*", "downloader.http")
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
		"PATH"			=>	realpath(dirname(__FILE__)."/../../files"), 
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
		)
	),
	
);

$REPOSITORIES["dyna_ftp"] = array(
	"DISPLAY"		=>	"FTP", 
	"AJXP_SLUG"		=>  "ftp",
	"DRIVER"		=>	"ftp", 
	"DRIVER_OPTIONS"=> array(
		"FTP_HOST"		=>	"ftp.ajaxplorer.info", 
		"FTP_PORT"		=>	"21",
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

/*********************************************/
/* DEBUG MODES, for client (JS_DEBUG) 
/* and server (SERVER_DEBUG).
/* Warning : this has to be set to true
/* to be able to both make modifications to 
/* the css or the js files, and to compile 
/* these into bundled file.
/*********************************************/
$AJXP_JS_DEBUG = true;
$AJXP_SERVER_DEBUG = true;

/*********************************************/
/* SESSION CREDENTIALS
/* If set to true, user credentials (username and password) are saved in
/* session to be later used. This can be used by FTP repositories to login
/* using username and password from axajplorer's login, very helpful when
/* FTP uses same ldap authentication as ajaxplorer.
/*********************************************/
$AJXP_SESSION_SET_CREDENTIALS = false;

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

/********************************************/
/* DOWNLOAD OPTIONS
/*********************************************/
// Gzip files on-the-fly before download (unique files)
define("GZIP_DOWNLOAD", false);
define("GZIP_LIMIT", 1*1048576); // Do not Gzip files above 1M

// Disable multiple files and folders download by creating a zip archive.
// If enabled, it uses PclZip php library, based on the gz php functions.
// If you are encountering problems with this, set this to true. A "multiple downloader"
// will appear instead.
define("DISABLE_ZIP_CREATION", false);

/***********************************************/
/* CLIENT SESSION OPTIONS
/*********************************************/
// The length of the client session in SECONDS. By default, it's copying
// the server session length. In most PHP installation, it will be 1440, ie 24minutes.
// You can set this value to 0, this will make the client session "infinite" by 
// pinging the server at regular occasions (thus keeping the PHP session alive). 
// This is not a recommanded setting for evident security reasons.
//define("AJXP_CLIENT_TIMEOUT_TIME", intval(ini_get("session.gc_maxlifetime")));
define("AJXP_CLIENT_TIMEOUT_TIME", intval(ini_get("session.gc_maxlifetime")));
// The number of MINUTES before the session expiration
// where the client issues a warning.
define("AJXP_CLIENT_TIMEOUT_WARN_BEFORE", 3);

/**********************************************/
/* GOOGLE ANALYTICS SETUP
/**********************************************/
// Set this is to you GA id : something like UE-XXXXX-Y
// This will append the Async Loading code to ajaxplorer.
define("GOOGLE_ANALYTICS_ID", "");
// Add this if you want to set the domain artificially.
// If you don't know what it's about, you should probably
// leave it empty!
define("GOOGLE_ANALYTICS_DOMAIN", "");
// If this is set to true and your ID is not empty,
// this will log various actions as Events in your 
// analytics report. Only some of them are implemented
// at the moment.
define("GOOGLE_ANALYTICS_EVENT", false);

/***********************************************/
/* CUSTOM MESSAGE IN START UP SCREEN
/***********************************************/
// Your app title, AjaXplorer by default
$customTitle = "";
// Font size for the start up screen title, "35px" by default
$customTitleFontSize = "";
// A custom icon, path must be from the root of the ajaxplorer installation
$customIcon = "";
// Space to leave for the icon, most probably the real image width plus a couple
// of pixels. By default, "35px" 
$customIconWidth = "";
// An additional welcome message appearing in the start up screen
$welcomeCustomMessage = "";

/***************************************************/
/*   WEBDAV SERVER CONFIGS
 * If you enable this configuration, you will be able to mount your repositories as webDAV 
 * folders. Please read carefully the documentation below.
 * 
 * HTACCESS & REWRITE RULE
 * Please open and edit the necessary values in the root .htaccess file
 * of the distribution. The concepts are to redirect automatically all
 * requests sent on your_install_path/shares to your_install_path/dav.php
 * 
 * AUTHENTICATION
 * Many clients can handle basic and digest authentication, but
 * windows client forces the use of digest authentication. Thus
 * we have to ask the user to manually re-enter his/her password
 * to be able to store it in the right form... Not very handy, but 
 * necessary though. This "Webdav password" is stored encrypted inside 
 * the user's wallet. 
 * 
 ***************************************************/
// Put this to true or false
define("AJXP_WEBDAV_ENABLE", true);

// WEBDAV_BASEURI must be correctly set in order to make webdav work
// Warning, you also must ENABLE APACHE REWRITE ENGINE 
// and edit the .htaccess file at the root of the distribution 
// Put here the path to the virtual folder defined in your
// .htaccess, that will redirect to dav.php, NO TRAILING SLASH. 
// Clients will then access the shares with the following 
// combination : http[s]://yourdomain/BASEURI/repositoryID.
define("AJXP_WEBDAV_BASEURI", "/ajaxplorer/shares");

// By default, the beginning of the url will be automatically detected
// from the request data (protocol / host), but if you want you can 
// uncomment this line and set it to force the value. This can be useful
// for tests, where you can access your server via http://localhost, and 
// want the webDAV tests to pass through https://your_ip_adress ... 
// define("AJXP_WEBDAV_BASEHOST", "http://192.168.0.11");

// Here is the problematic part : windows recent webdav client only
// accept HTTP "Digest" Auth, which imply storing the password either clear, 
// or with an algorithm different of the one currently used in AjaXplorer. 
// Thus, after you have enabled AJXP_WEBDAV, the users will have to update their
// password at least once, to make sure to store the new hashed form in their wallet.
// This will be necessary before they can access the repositories via webDAV, otherwise
// you'll have to enable GUEST browsing.
define("AJXP_WEBDAV_DIGESTREALM", "ajxp_webdav_realm");

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
$max_caracteres=255;

/*************************************************/
/* WHEN SET, USE SYSTEM CODE TO GET FILESIZE. 
/* Enable this on 32bits machine, to overcome PHP 
/* 4GB limit on file size. This requires shell_exec 
/* permission on linux, and fork permission on 
/* windows. Under Windows, it's faster to install 
/* COM PHP Extension.
/*************************************************/
$allowRealSizeProbing=false;
?>
