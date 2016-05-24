CREATE TABLE ajxp_tasks (
  uid VARCHAR(255) NOT NULL ,
  flags INTEGER NOT NULL,
  label VARCHAR(255) NOT NULL,
  userId VARCHAR(255) NOT NULL,
  wsId VARCHAR(32) NOT NULL,
  status INTEGER NOT NULL,
  status_msg VARCHAR(500) NOT NULL,
  progress INTEGER NOT NULL,
  schedule VARCHAR(500) NOT NULL,
  action VARCHAR(255) NOT NULL,
  parameters VARCHAR(500) NOT NULL,
  nodes VARCHAR(500) NOT NULL
);

CREATE INDEX ajxp_task_idx ON ajxp_tasks ('uid');
CREATE INDEX ajxp_task_usr_idx ON ajxp_tasks ('userId');
CREATE INDEX ajxp_task_status_idx ON ajxp_tasks ('status');
CREATE INDEX ajxp_task_nodes_idx ON ajxp_tasks ('nodes');

