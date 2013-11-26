CREATE TABLE ajxp_feed (
  id serial PRIMARY KEY,
  edate integer NOT NULL,
  etype varchar(12) NOT NULL,
  htype varchar(32) NOT NULL,
  index_path text,
  user_id varchar(255) NOT NULL,
  repository_id varchar(33) NOT NULL,
  user_group varchar(500),
  repository_scope varchar(50),
  repository_owner varchar(255),
  content bytea NOT NULL
);

CREATE UNIQUE INDEX ajxp_feed_edate_idx ON ajxp_feed (
  edate,
  etype,
  htype,
  user_id,
  repository_id
);

CREATE INDEX ajxp_feed_index_path_idx ON ajxp_feed (index_path);
