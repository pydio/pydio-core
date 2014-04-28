ALTER TABLE ajxp_user_rights DROP COLUMN rid;
ALTER TABLE ajxp_user_rights ADD PRIMARY KEY(login, repo_uuid);

ALTER TABLE ajxp_user_prefs DROP COLUMN rid;
ALTER TABLE ajxp_user_prefs ADD PRIMARY KEY(login, name);

ALTER TABLE ajxp_user_bookmarks DROP COLUMN rid;
ALTER TABLE ajxp_user_bookmarks ADD PRIMARY KEY(login, repo_uuid, title);

ALTER TABLE ajxp_repo_options DROP COLUMN oid;
ALTER TABLE ajxp_repo_options ADD PRIMARY KEY(uuid, name);
