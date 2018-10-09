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

CREATE INDEX ajxp_feed_repository_id_idx ON ajxp_feed (repository_id);
CREATE INDEX ajxp_feed_user_id_idx ON ajxp_feed (user_id);