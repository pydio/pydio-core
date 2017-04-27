CREATE TABLE ajxp_user_prefs_temp (
  rid serial PRIMARY KEY,
  login varchar(255) NOT NULL,
  name varchar(255) NOT NULL,
  val bytea
);
/* SEPARATOR */
CREATE UNIQUE INDEX prefs_login_name ON ajxp_user_prefs_temp(login, name);
/* SEPARATOR */
INSERT INTO ajxp_user_prefs_temp (SELECT * from ajxp_user_prefs
	WHERE NOT EXISTS(
            SELECT 1
            FROM ajxp_user_prefs As S2
            WHERE S2.login = ajxp_user_prefs.login AND S2.name = ajxp_user_prefs.name
                AND S2.rid > ajxp_user_prefs.rid
            HAVING COUNT(*) > 0
    )
);
/* SEPARATOR */
DROP TABLE ajxp_user_prefs;
/* SEPARATOR */
ALTER TABLE ajxp_user_prefs_temp RENAME TO ajxp_user_prefs;