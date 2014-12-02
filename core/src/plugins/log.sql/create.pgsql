CREATE TYPE ajxp_log_severity AS ENUM (
  'DEBUG',
  'INFO',
  'NOTICE',
  'WARNING',
  'ERROR'
);

CREATE TABLE ajxp_log (
  id serial PRIMARY KEY,
  logdate timestamp,
  remote_ip varchar(45),
  severity ajxp_log_severity,
  "user" varchar(255),
  source varchar(255),
  message text,
  params text
);
