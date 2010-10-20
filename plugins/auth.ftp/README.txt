This plugin allows you to dynamically check credentials against an FTP server.
For the moment you cannot define the server "on the fly", you must define a repository
using access.ftp inside your config file, and make a reference to this repository in your auth_driver config.

You must also make sure that you have an existing admin user, defined at startup with the standard auth.serial.

1/ Switch auth.serial to auth.ftp in the conf.php file

$PLUGINS = array(
	
[...]

	"AUTH_DRIVER" => array(
		"NAME"		=> "ftp",
		"OPTIONS"	=> array(
			/* ID OF THE REPOSITORY DEFINED BELOW */
			"REPOSITORY_ID" => "dynamic_ftp",
			/* EXISTING ADMIN IN THE SENSE OF AJAXPLORER ADMIN, TO AVOID ALERT AT STARTUP */
			"ADMIN_USER"	=> "admin",
			"TRANSMIT_CLEAR_PASS"	=> true)
	),

	
[...]

);

2/ Still in the conf.php, define manually a repository using access.ftp driver, and make it
"rw" (read/write) by default (or read, if you prefer!).

$REPOSITORIES["dynamic_ftp"] = array(
	"DISPLAY"	=> "FTP Server",
	"DRIVER"	=> "ftp",
	"DRIVER_OPTIONS" => array(
		/* SEE access.ftp/manifest.xml for all available FTP parameters */
		"FTP_HOST"		=> "ftp.yoursverer.net",
		"FTP_PORT"		=> "21",
		"PATH"		=> "/",
				
		/* Set this to "rw" */
		"DEFAULT_RIGHTS" => "rw"	
	)
);