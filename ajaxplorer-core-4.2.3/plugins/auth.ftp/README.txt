This plugin allows you to dynamically check credentials against an FTP server.

Basically, you must define a repository using access.ftp inside your config file, 
and make a reference to this repository in your auth_driver config. You have two
usecases available : 
1/ You want direct connexion to a given server : define the repository with all "standard"
ftp options, and leave the AUTH option "FTP_LOGIN_SCREEN" to "False".
2/ You want to make a simple ftp proxy, and enter at login time the ftp data (host, port, etc) : 
define the repository option "DYNAMIC_FTP" to "TRUE", and do not enter all ftp options. Set the 
AUTH option "FTP_LOGIN_SCREEN" to "TRUE".

Aslo, be sure you have an existing admin user, defined with the standard auth.serial.


EXAMPLE : 

1/ Switch auth.serial to auth.ftp in the conf.php file
$PLUGINS = array(
[...]
	"AUTH_DRIVER" => array(
		"NAME"		=> "ftp",
		"OPTIONS"	=> array(
			/* Id of the repository defined below */
			"REPOSITORY_ID" => "dynamic_ftp",
						
			/* Existing admin in the sense of ajaxplorer admin, to avoid alert at startup */
			"ADMIN_USER"	=> "admin",
			
			/* If you want your use to connect any FTP they want, set this to TRUE */			
			"FTP_LOGIN_SCREEN" => "TRUE", 
			
			"TRANSMIT_CLEAR_PASS"	=> true)
	),
[...]
);

2/ Still in the conf.php, define manually a repository using access.ftp driver, and make it
"rw" (read/write) by default (or read, if you prefer!).

$REPOSITORIES["dynamic_ftp"] = array(
	"DISPLAY"	=> "FTP Host",
	"DRIVER"	=> "ftp",
	"DRIVER_OPTIONS" => array(
		/* SEE access.ftp/manifest.xml for all available FTP parameters */
		"FTP_HOST"		=> "ftp.yoursverer.net",
		"FTP_PORT"		=> "21",
		"PATH"		=> "/",

		/* If you want your use to connect any FTP they want, 
			forget options above, and set this one to true*/	
		"DYNAMIC_FTP"	=> "TRUE"
		
		/* Set this to "rw", this will be the default rights for users */
		"DEFAULT_RIGHTS" => "rw"	
	)
);