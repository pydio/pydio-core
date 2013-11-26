CREATE TABLE ajxp_simple_store (
  object_id varchar(255) NOT NULL,
  store_id varchar(50) NOT NULL,
  serialized_data bytea,
  binary_data bytea,
  related_object_id varchar(255),
  PRIMARY KEY(object_id, store_id)
);
