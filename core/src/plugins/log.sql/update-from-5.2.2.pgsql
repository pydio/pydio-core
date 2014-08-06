CREATE TABLE ajxp_log2 AS TABLE ajxp_log;

ALTER TABLE  ajxp_log2
  ADD source VARCHAR( 255 )
;
ALTER TABLE  ajxp_log2
  ADD primary key (id)
;

UPDATE ajxp_log2 SET
  source = ajxp_log.message,
  message = split_part(ajxp_log.params,'\t', 1),
  params = split_part(ajxp_log.params,'\t', 2)
FROM ajxp_log
WHERE ajxp_log2.id = ajxp_log.id
;

DROP TABLE ajxp_log;
ALTER TABLE ajxp_log2 RENAME TO ajxp_log;