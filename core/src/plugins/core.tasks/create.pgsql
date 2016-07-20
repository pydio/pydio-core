CREATE TABLE ajxp_tasks (
  uid VARCHAR(255) NOT NULL ,
  type INTEGER NOT NULL,
  parent_uid VARCHAR(255) DEFAULT NULL,
  flags INTEGER NOT NULL,
  label VARCHAR(255) NOT NULL,
  userId VARCHAR(255) NOT NULL,
  wsId VARCHAR(32) NOT NULL,
  status INTEGER NOT NULL,
  status_msg VARCHAR(500) NOT NULL,
  progress INTEGER NOT NULL,
  schedule INTEGER NOT NULL,
  schedule_value VARCHAR (255) DEFAULT NULL,
  action VARCHAR(255) NOT NULL,
  parameters VARCHAR(500) NOT NULL,
  nodes VARCHAR(500) NOT NULL,
  creation_date INTEGER NOT NULL DEFAULT '0' COMMENT 'Date of creation of the job',
  status_update INTEGER NOT NULL DEFAULT '0' COMMENT 'Last time the status was updated',
);

CREATE INDEX ajxp_task_idx ON ajxp_tasks ('uid');
CREATE INDEX ajxp_task_usr_idx ON ajxp_tasks ('userId');
CREATE INDEX ajxp_task_status_idx ON ajxp_tasks ('status');
CREATE INDEX ajxp_task_type ON ajxp_tasks ('type');
CREATE INDEX ajxp_task_schedule ON ajxp_tasks ('schedule');
CREATE INDEX ajxp_task_nodes_idx ON ajxp_tasks ('nodes');

