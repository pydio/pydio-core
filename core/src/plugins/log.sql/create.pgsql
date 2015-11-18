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
  params text,
  repository_id VARCHAR(32),
  device VARCHAR(255),
  dirname		VARCHAR(255),
  basename  VARCHAR(255)
);

CREATE INDEX log_date_idx ON ajxp_log(logdate);
CREATE INDEX log_repository_id_idx ON ajxp_log(repository_id);
CREATE INDEX log_dirname_idx ON ajxp_log(dirname);
CREATE INDEX log_basename_idx ON ajxp_log(basename);
CREATE INDEX log_severity_idx ON ajxp_log(severity);
CREATE INDEX log_source_idx ON ajxp_log(source);
