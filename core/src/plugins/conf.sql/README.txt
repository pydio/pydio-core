conf.sql plugin documentation
-----------------------------

This is the conf.sql plugin. It stores AjaXplorer configuration
information in an SQL database,
it is responsible for storing the following information in the
AjaXplorer application:

- Repository definitions
- User bookmarks
- User preferences
- User rights

configuring the plugin
----------------------

Below is an example configuration. Tweak this to suit your needs and
then insert it into conf.php.
You will need to comment out or remove the existing serial driver in
order for this to work properly.


$CONF_STORAGE = array(
	"NAME"		=> "sql",
	"OPTIONS"	=> array(
		"SQL_DRIVER" => array(
			"driver" => "mysql",
			"host"	=> "localhost",
			"database" => "ajxp",
			"user"	=> "ajxp_database_user",
			"password" => "ajxp_database_password"
		)
	)
);

When you are happy with the configuration, run the create.sql script
located inside the same directory as the plugin
to create the database schema for the conf.sql plugin.

Note that the performance of AjaXplorer is slightly degraded by the
overhead of the plugin - but that's what you sacrifice for the
flexibility.

This file is part of the AjaXplorer distribution.
Contribution by Mosen : greetings!