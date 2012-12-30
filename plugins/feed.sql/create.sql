CREATE TABLE ajxp_feed (
  id int(11) NOT NULL AUTO_INCREMENT,
  edate int(11) NOT NULL,
  etype varchar(50) NOT NULL,
  htype varchar(50) NOT NULL,
  user_id varchar(255) NOT NULL,
  repository_id varchar(255) NOT NULL,
  user_group varchar(500) NOT NULL,
  repository_scope varchar(50) NOT NULL,
  content longtext NOT NULL,
  PRIMARY KEY (id),
  KEY edate (edate,etype,htype,user_id,repository_id,repository_scope)
)
