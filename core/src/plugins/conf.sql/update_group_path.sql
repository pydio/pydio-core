DELETE FROM ajxp_user_rights WHERE repo_uuid = 'ajxp.group_path';

INSERT INTO ajxp_user_rights (login, repo_uuid, rights)
    (SELECT DISTINCT u.login,'ajxp.group_path',"groupPath"
     FROM ajxp_user_rights AS r,
          ajxp_users AS u
     WHERE u.login = r.login);

INSERT INTO ajxp_user_rights (login, repo_uuid, rights)
    (SELECT DISTINCT login,'ajxp.group_path','/'
     FROM ajxp_user_rights
     WHERE login NOT IN (SELECT login FROM ajxp_users));
