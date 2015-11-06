/* SEPARATOR */
ALTER TABLE  ajxp_log
  ADD COLUMN repository_id VARCHAR( 32 ) DEFAULT NULL ,
  ADD COLUMN device VARCHAR( 255 ) DEFAULT NULL ,
  ADD COLUMN dirname	VARCHAR(255),
  ADD COLUMN basename  VARCHAR(255)
;
/* SEPARATOR */
CREATE INDEX log_date_idx ON ajxp_log(logdate);
/* SEPARATOR */
CREATE INDEX log_severity_idx ON ajxp_log(severity);
/* SEPARATOR */
CREATE INDEX log_repository_id_idx ON ajxp_log(repository_id);
/* SEPARATOR */
CREATE INDEX log_dirname_idx ON ajxp_log(dirname);
/* SEPARATOR */
CREATE INDEX log_basename_idx ON ajxp_log(basename);
/* SEPARATOR */
CREATE INDEX log_source_idx ON ajxp_log(source);

/* SEPARATOR */
ALTER TABLE ajxp_roles ADD COLUMN last_updated INTEGER NOT NULL DEFAULT 0;
/* SEPARATOR */
CREATE INDEX roles_updated_idx ON ajxp_roles(last_updated);
