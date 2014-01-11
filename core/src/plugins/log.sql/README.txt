log.sql plugin documentation
-----------------------------

This is the log.sql plugin. It stores Pydio logging
information in an SQL database.

configuring the plugin
----------------------

Below is an example configuration. Tweak this to suit your needs and
then insert it into conf.php.
You will need to comment out or remove the existing serial driver in
order for this to work properly.

	"LOG_DRIVER" => array(
		"NAME" => "sql",
		"OPTIONS" => array(
		        "SQL_DRIVER" => array(
                                "driver" => "mysql",
                                "host" => "localhost",
                                "database" => "ajxp",
                                "user" => "ajxp",
                                "password" => "ajxp"
                        )
		)
	),

When you are happy with the configuration, run the create.sql script
located inside the same directory as the plugin
to create the database schema for the log.sql plugin.

Note that the performance of Pydio is slightly degraded by the
overhead of the plugin - but that's what you sacrifice for the
flexibility.


table structure
---------------
+-----------+-------------------------------------------------+------+-----+---------+----------------+
| Field     | Type                                            | Null | Key | Default | Extra          |
+-----------+-------------------------------------------------+------+-----+---------+----------------+
| id        | int(11)                                         | NO   | PRI | NULL    | auto_increment | 
| logdate   | datetime                                        | YES  |     | NULL    |                | 
| remote_ip | VARCHAR(32)                                     | YES  |     | NULL    |                | 
| severity  | enum('DEBUG','INFO','NOTICE','WARNING','ERROR') | YES  |     | NULL    |                | 
| user      | varchar(255)                                    | YES  |     | NULL    |                | 
| message   | TEXT                                            | YES  |     | NULL    |                | 
| params    | TEXT                                            | YES  |     | NULL    |                | 
+-----------+-------------------------------------------------+------+-----+---------+----------------+

// ORIGINAL 
The remote_ip is reduced to decimal format, this can cater for IPv4 and IPv6 in future versions. See inet_ptod() and inet_dtop() in the source.
The severity is normalised to ensure we only record valid log severity levels. This might be turned into varchar if we find this too limiting.
// AT THE MOMENT THE REMOTE_IP IS STORED AS IS IN THE DB


This file is part of the Pydio distribution.


Contribution by Mosen : greetings!
