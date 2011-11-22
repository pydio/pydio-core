<?php
/*
* Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
* This file is part of AjaXplorer.
*
* AjaXplorer is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* AjaXplorer is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
*
* The latest code can be found at <http://www.ajaxplorer.info/>.
*/
$mess=array(
"Main"      => "Main Options",
"App Title" => "Application Title",
"Your application title" => "This title will appear as the window title, in the splash screen.",
"Main container for core AjaXplorer settings (application title, sharing, webdav server config, etc...)" => "Main container for core AjaXplorer settings (application title, sharing, webdav server config, etc...)",
"Default Language" => "Default Language",
"Default language when a user does not have set his/her own." => "Default language when a user does not have set his/her own.",
"Sharing" => "Sharing",
"Download Folder" => "Download Folder",
"Absolute path to the public folder where temporary download links will be created. Setting this empty will disable the sharing feature." => "Absolute path to the public folder where temporary download links will be created. Setting this empty will disable the sharing feature.",
"Download URL" => "Download URL",
"If not inferred directly from the current ajaxplorer URI plus the public download folder name, replace the public access URL here." => "If not inferred directly from the current ajaxplorer URI plus the public download folder name, replace the public access URL here.",
"Existing users" => "Existing users",
"Allow the users to pick an existing user when sharing a folder" => "Allow the users to pick an existing user when sharing a folder",
"Compression Features" => "Compression Features",
"Gzip Download" => "Gzip Download",
"Gzip files on-the-fly before downloading. Disabled by default, as it's generally useful only on small files, and decreases performances on big files. This has nothing to see with the Zip Creation feature, it's just a on-the-fly compression applied on a unique file at download." => "Gzip files on-the-fly before downloading. Disabled by default, as it's generally useful only on small files, and decreases performances on big files. This has nothing to see with the Zip Creation feature, it's just a on-the-fly compression applied on a unique file at download.",
"Gzip Limit" => "Gzip Limit",
"If activated, a default limit should be set above when files are no more compressed." => "If activated, a default limit should be set above when files are no more compressed.",
"Zip Creation" => "Zip Creation",
"If you encounter problems with online zip creation or multiple files downloading, you can disable the feature." => "If you encounter problems with online zip creation or multiple files downloading, you can disable the feature.",
"WebDAV Server" => "WebDAV Server",
"Enable WebDAV" => "Enable WebDAV",
"Enable the webDAV support. Please READ THE DOC to safely use this feature." => "Enable the webDAV support. Please READ THE DOC to safely use this feature.",
"Shares URI" => "Shares URI",
"Common URI to access the shares. Please READ THE DOC to safely use this feature." => "Common URI to access the shares. Please READ THE DOC to safely use this feature.",
"Shares Host" => "Shares Host",
"Host used in webDAV protocol. Should be detected by default. Please READ THE DOC to safely use this feature." => "Host used in webDAV protocol. Should be detected by default. Please READ THE DOC to safely use this feature.",
"Digest Realm" => "Digest Realm",
"Default realm for authentication. Please READ THE DOC to safely use this feature." => "Default realm for authentication. Please READ THE DOC to safely use this feature.",
"Miscalleneous" => "Miscalleneous",
"Command-line Active" => "Command-line Active",
"Use AjaXplorer framework via the command line, allowing CRONTAB jobs or background actions." => "Use AjaXplorer framework via the command line, allowing CRONTAB jobs or background actions.",
"Command-line PHP" => "Command-line PHP",
"On specific hosts, you may have to use a specific path to access the php command line" => "On specific hosts, you may have to use a specific path to access the php command line",
"Filename length" => "Filename length",
"Maximum characters length of new files or folders" => "Maximum characters length of new files or folders",
"Temporary Folder" => "Temporary Folder",
"This is necessary only if you have errors concerning the tmp dir access or writeability : most probably, they are due to PHP SAFE MODE (should disappear in php6) or various OPEN_BASEDIR restrictions. In that case, create and set writeable a tmp folder somewhere at the root of your hosting (but above the web/ or www/ or http/ if possible!!) and enter here the full path to this folder" => "This is necessary only if you have errors concerning the tmp dir access or writeability : most probably, they are due to PHP SAFE MODE (should disappear in php6) or various OPEN_BASEDIR restrictions. In that case, create and set writeable a tmp folder somewhere at the root of your hosting (but above the web/ or www/ or http/ if possible!!) and enter here the full path to this folder",
"Admin email" => "Admin email",
"Administrator email, not used for the moment" => "Administrator email, not used for the moment",
"User Credentials" => "User Credentials",
"User" => "User",
"User name - Can be overriden on a per-user basis (see users 'Personal Data' tab)" => "User name - Can be overriden on a per-user basis (see users 'Personal Data' tab)",
"Password" => "Password",
"User password - Can be overriden on a per-user basis." => "User password - Can be overriden on a per-user basis.",
"Session credentials" => "Session credentials",
"Try to use the current AjaXplorer user credentials for connecting. Warning, the AJXP_SESSION_SET_CREDENTIALS config must be set to true!" => "Try to use the current AjaXplorer user credentials for connecting. Warning, the AJXP_SESSION_SET_CREDENTIALS config must be set to true!",
"User name" => "User name",
"User password" => "User password",
"Repository Slug" => "Repository Slug",
"Alias" => "Alias",
"Alias for replacing the generated unique id of the repository" => "Alias for replacing the generated unique id of the repository",
"Template Options" => "Template Options",
"Allow to user" => "Allow to user",
"Allow non-admin users to create a repository from this template." => "Allow non-admin users to create a repository from this template.",
"Default Label" => "Default Label",
"Prefilled label for the new repository, you can use the AJXP_USER keyworkd in it." => "Prefilled label for the new repository, you can use the AJXP_USER keyworkd in it.",
"Small Icon" => "Small Icon",
"16X16 Icon for representing the template" => "16X16 Icon for representing the template",
"Big Icon" => "Big Icon",
"Big Icon for representing the template" => "Big Icon for representing the template",
"Filesystem Commons" => "Filesystem Commons",
"Recycle Bin Folder" => "Recycle Bin Folder",
"Leave empty if you do not want to use a recycle bin." => "Leave empty if you do not want to use a recycle bin.",
"Default Rights" => "Default Rights",
"This right pattern (empty, r, or rw) will be applied at user creation for this repository." => "This right pattern (empty, r, or rw) will be applied at user creation for this repository.",
"Character Encoding" => "Character Encoding",
"If your server does not set correctly its charset, it can be good to specify it here manually." => "If your server does not set correctly its charset, it can be good to specify it here manually.",
"Pagination Threshold" => "Pagination Threshold",
"When a folder will contain more items than this number, display will switch to pagination mode, for better performances." => "When a folder will contain more items than this number, display will switch to pagination mode, for better performances.",
"#Items per page" => "#Items per page",
"Once in pagination mode, number of items to display per page." => "Once in pagination mode, number of items to display per page.",
"Default Metasources" => "Default Metasources",
"Comma separated list of metastore and meta plugins, that will be automatically applied to all repositories created with this driver" => "Comma separated list of metastore and meta plugins, that will be automatically applied to all repositories created with this driver",
"Auth Driver Commons" => "Auth Driver Commons",
"Transmit Clear Pass" => "Transmit Clear Pass",
"Whether the password will be transmitted clear or encoded between the client and the server" => "Whether the password will be transmitted clear or encoded between the client and the server",
"Auto Create User" => "Auto Create User",
"When set to true, the user object is created automatically if the authentication succeed. Used by remote authentication systems." => "When set to true, the user object is created automatically if the authentication succeed. Used by remote authentication systems.",
"Login Redirect" => "Login Redirect",
"If set to a given URL, the login action will not trigger the display of login screen but redirect to this URL." => "If set to a given URL, the login action will not trigger the display of login screen but redirect to this URL.",
"Admin Login" => "Admin Login",
"For exotic auth drivers, an user ID that must be considered as admin by default." => "For exotic auth drivers, an user ID that must be considered as admin by default.",
);
?>