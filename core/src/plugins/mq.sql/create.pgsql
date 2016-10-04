CREATE TABLE IF NOT EXISTS ajxp_mq_queues (
  channel_name varchar(255) NOT NULL,
  content BLOB NOT NULL,
  constraint pk primary key(channel_name)
);