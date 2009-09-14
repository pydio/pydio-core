CREATE TABLE ajxp_user_rights ( 
	rid INTEGER PRIMARY KEY, 
	login VARCHAR(255), 
	repo_uuid VARCHAR(33), 
	rights VARCHAR(20)
);

CREATE TABLE ajxp_user_prefs ( 
	rid INTEGER PRIMARY KEY, 
	login VARCHAR(255), 
	key VARCHAR(255), 
	value VARCHAR(255)
);

CREATE TABLE ajxp_user_bookmarks ( 
	rid INTEGER PRIMARY KEY, 
	login VARCHAR(255), 
	repo_uuid VARCHAR(33), 
	path VARCHAR(255), 
	title VARCHAR(255)
);

CREATE TABLE ajxp_repo ( 
	uuid VARCHAR(33) PRIMARY KEY, 
 	path VARCHAR(255), 
 	display VARCHAR(255), 
 	accessType VARCHAR(20), 
 	recycle VARCHAR(255) , 
 	bcreate BOOLEAN,
 	writeable BOOLEAN, 
 	enabled BOOLEAN 
);
 
CREATE TABLE ajxp_repo_options ( 
	oid INTEGER PRIMARY KEY, 
	uuid VARCHAR(33), 
	key VARCHAR(50), 
	value VARCHAR(255) 
);
 
CREATE INDEX ajxp_repo_options_uuid_idx ON ajxp_repo_options ( uuid );