CREATE TABLE ajxp_users (
  login varchar(255) PRIMARY KEY,
  password varchar(255) NOT NULL,
  "groupPath" varchar(255),
  failedLogins int NOT NULL,
  lastChange date NOT NULL
);
CREATE TABLE ajxp_users_passwords (
  login varchar(255) PRIMARY KEY,
  "password" varchar(255) NOT NULL,
  "date" varchar(25) NOT NULL
 );
