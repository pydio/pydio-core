CREATE TABLE IF NOT EXISTS ajxp_tasks (
    uid VARCHAR(255) NOT NULL ,
    type INTEGER NOT NULL,
    parent_uid VARCHAR(255) DEFAULT NULL,
    flags INTEGER NOT NULL,
    label VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    ws_id VARCHAR(32) NOT NULL,
    status INTEGER NOT NULL,
    status_msg VARCHAR(500) NOT NULL,
    progress INTEGER NOT NULL,
    schedule INTEGER NOT NULL,
    schedule_value VARCHAR(255) DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    parameters BYTEA NOT NULL,
    creation_date INTEGER NOT NULL DEFAULT 0,
    status_update INTEGER NOT NULL DEFAULT 0,

    PRIMARY KEY (uid)
);

CREATE INDEX ajxp_task_usr_idx ON ajxp_tasks (user_id);
CREATE INDEX ajxp_task_status_idx ON ajxp_tasks (status);
CREATE INDEX ajxp_task_type ON ajxp_tasks (type);
CREATE INDEX ajxp_task_schedule ON ajxp_tasks (schedule);

CREATE TABLE IF NOT EXISTS ajxp_tasks_nodes (
  id serial PRIMARY KEY,
  task_uid VARCHAR(40) NOT NULL,
  node_base_url VARCHAR(255) NOT NULL,
  node_path VARCHAR(255) NOT NULL
);


CREATE INDEX ajxp_taskn_tuid_idx ON ajxp_tasks_nodes (task_uid);
CREATE INDEX ajxp_taskn_base_idx ON ajxp_tasks_nodes (node_base_url);
CREATE INDEX ajxp_taskn_path_idx ON ajxp_tasks_nodes (node_path);
