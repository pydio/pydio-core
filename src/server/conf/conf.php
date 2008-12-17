<?php
//--------------------------------------------------------
//
//	AjaXplorer 
//
//	Charles du Jeu
//	http://www.ajaxplorer.info/
//
//--------------------------------------------------------

//--------------------------------------------------------
//	CONFIGURATION IS HERE!
//--------------------------------------------------------


/* DEFAULT LANGUAGE */
$dft_langue="en";

define("AJXP_VERSION", "2.3.9");
define("AJXP_VERSION_DATE", "2008/12/17");

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

/* 
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
		"RECYCLE_BIN" 	=> 	'recycle_bin'
	)
);


/**
 * Specific config for wordpress plugin, do not touch if you are not sure!
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

//------------------------
//		UPLOAD CONFIG
//------------------------

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

/* RECYCLE BIN : leave blank if you do not want to use it.
/********************************/
$recycle_bin = "recycle_bin";

/*  HTTPS DOMAIN? (USED TO CORRECT A BUG IN IE)
/**************************************************/
$use_https=false;

// UNITE DE TAILLE DES FICHIER (octets "o", bytes "b")
// (Unit of file size, "o" or "b")
$size_unit="b";

// NOMBRE DE CARACTERES MAXIMUM POUR LES NOMS DE FICHIER
// (max number chars for file and directory names)

$max_caracteres=50;

// AFFICHAGE DES FICHIERS CACHES : oui=1, non=0 (UN FICHIER CACHE COMMENCE PAR UN POINT)
// (Show hidden files, yes=1, no=0)

$showhidden=0;





//------------------------------------------------------
//		DO NOT CHANGE THESE VARIABLES BELOW
//------------------------------------------------------



$installPath = realpath(dirname(__FILE__)."/../..");
define("INSTALL_PATH", $installPath);
define("USERS_DIR", $installPath."/server/users");
define("SERVER_ACCESS", "content.php");
define("ADMIN_ACCESS", "admin.php");
define("IMAGES_FOLDER", "client/images");
define("CLIENT_RESOURCES_FOLDER", "client");
define("SERVER_RESOURCES_FOLDER", "server/classes");
define("DOCS_FOLDER", "client/doc");

define("OLD_USERS_DIR", $installPath."/bookmarks");
define("INITIAL_ADMIN_PASSWORD", "admin");

define("GZIP_DOWNLOAD", false);
define("GZIP_LIMIT", 1*1048576); // Do not Gzip files above 50M

// PAGES D'ENTETE ET DE BAS DE PAGE
// (header and footer files )
$baspage=CLIENT_RESOURCES_FOLDER."/html/bottom.html";


?>
