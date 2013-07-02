ALTER TABLE `ajxp_user_rights` CHANGE `rights` `rights` VARCHAR( 255 ) NOT NULL;

CREATE TABLE ajxp_roles (
	role_id VARCHAR(50) PRIMARY KEY, 
	serial_role TEXT(500) NOT NULL
);
