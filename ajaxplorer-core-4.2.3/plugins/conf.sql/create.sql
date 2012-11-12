CREATE TABLE ajxp_user_rights ( 
	rid INTEGER PRIMARY KEY AUTO_INCREMENT, 
	login VARCHAR(255) NOT NULL, 
	repo_uuid VARCHAR(33) NOT NULL, 
	rights VARCHAR(255) NOT NULL
);

CREATE TABLE ajxp_user_prefs ( 
	rid INTEGER PRIMARY KEY AUTO_INCREMENT, 
	login VARCHAR(255) NOT NULL, 
	name VARCHAR(255) NOT NULL, 
	val VARCHAR(2000)
);

CREATE TABLE ajxp_user_bookmarks ( 
	rid INTEGER PRIMARY KEY AUTO_INCREMENT, 
	login VARCHAR(255) NOT NULL, 
	repo_uuid VARCHAR(33) NOT NULL, 
	path VARCHAR(255), 
	title VARCHAR(255)
);

CREATE TABLE ajxp_repo ( 
	uuid VARCHAR(33) PRIMARY KEY, 
	parent_uuid VARCHAR(33) default NULL,
	owner_user_id VARCHAR(50) default NULL,
	child_user_id VARCHAR(50) default NULL,	
 	path VARCHAR(255), 
 	display VARCHAR(255), 
 	accessType VARCHAR(20), 
 	recycle VARCHAR(255), 
 	bcreate BOOLEAN,
 	writeable BOOLEAN, 
 	enabled BOOLEAN ,
 	isTemplate BOOLEAN,
 	inferOptionsFromParent BOOLEAN,
 	slug VARCHAR(255)
);
 
CREATE TABLE ajxp_repo_options ( 
	oid INTEGER PRIMARY KEY AUTO_INCREMENT, 
	uuid VARCHAR(33) NOT NULL, 
	name VARCHAR(50) NOT NULL, 
	val VARCHAR(2000)
);

CREATE TABLE ajxp_roles (
	role_id VARCHAR(50) PRIMARY KEY, 
	serial_role TEXT(500) NOT NULL
);
 
CREATE TABLE ajxp_plugin_configs (
  id VARCHAR(50) NOT NULL,
  configs LONGTEXT NOT NULL,
  PRIMARY KEY (id)
);


CREATE INDEX ajxp_repo_options_uuid_idx ON ajxp_repo_options ( uuid );
