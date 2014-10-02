CREATE TYPE ajxp_change_type AS ENUM ('create','delete','path','content');

CREATE TABLE ajxp_changes (
  seq BIGSERIAL,
  repository_identifier TEXT NOT NULL,
  node_id INTEGER NOT NULL,
  type ajxp_change_type NOT NULL,
  source text NOT NULL,
  target text NOT NULL,
  constraint pk primary key(seq)
);

CREATE INDEX ajxp_changes_node_id ON ajxp_changes (node_id);
CREATE INDEX ajxp_changes_repo_id ON ajxp_changes (repository_identifier);
CREATE INDEX ajxp_changes_type ON ajxp_changes (type);

CREATE TABLE ajxp_index (
  node_id BIGSERIAL ,
  node_path text NOT NULL,
  bytesize INTEGER NOT NULL,
  md5 varchar(32) NOT NULL,
  mtime INTEGER NOT NULL,
  repository_identifier text NOT NULL,
  PRIMARY KEY (node_id)
);

CREATE INDEX ajxp_index_repo_id ON ajxp_index (repository_identifier);
CREATE INDEX ajxp_index_md5 ON ajxp_index (md5);

/** SEPARATOR **/
/** BLOCK **/
CREATE FUNCTION ajxp_index_delete() RETURNS trigger AS $ajxp_index_delete$
    BEGIN
      INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
        VALUES (OLD.repository_identifier, OLD.node_id, OLD.node_path, 'NULL', 'delete');
      RETURN NULL;
    END;
$ajxp_index_delete$ LANGUAGE plpgsql;

/** SEPARATOR **/
/** BLOCK **/
CREATE FUNCTION ajxp_index_insert() RETURNS trigger AS $ajxp_index_insert$
    BEGIN
      INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
        VALUES (NEW.repository_identifier, NEW.node_id, 'NULL', NEW.node_path, 'create');
      RETURN NEW;
    END;
$ajxp_index_insert$ LANGUAGE plpgsql;

/** SEPARATOR **/
/** BLOCK **/
CREATE FUNCTION ajxp_index_update() RETURNS trigger AS $ajxp_index_update$
    BEGIN
      IF OLD.node_path = NEW.node_path THEN
        INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
          VALUES (NEW.repository_identifier, NEW.node_id, OLD.node_path, NEW.node_path, 'content');
      ELSE
        INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
          VALUES (NEW.repository_identifier, NEW.node_id, OLD.node_path, NEW.node_path, 'path');
      END IF;
      RETURN NEW;
    END;
$ajxp_index_update$ LANGUAGE plpgsql;
/** SEPARATOR **/

CREATE TRIGGER LOG_DELETE AFTER DELETE ON ajxp_index
FOR EACH ROW EXECUTE PROCEDURE ajxp_index_delete();

CREATE TRIGGER LOG_INSERT AFTER INSERT ON ajxp_index
FOR EACH ROW EXECUTE PROCEDURE ajxp_index_insert();

CREATE TRIGGER LOG_UPDATE AFTER UPDATE ON ajxp_index
FOR EACH ROW EXECUTE PROCEDURE ajxp_index_update();
