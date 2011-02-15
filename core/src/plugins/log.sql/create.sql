CREATE TABLE `ajxp_log` (
	`id`		INT	PRIMARY KEY	AUTO_INCREMENT,
	`logdate`	DATETIME,
	`remote_ip`	VARCHAR(32),
	`severity`	ENUM('DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR'),
	`user`		VARCHAR(255),
	`message`	VARCHAR(255),
	`params`	VARCHAR(255)
)