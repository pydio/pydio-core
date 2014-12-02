<?php
/*
* Copyright 2007-2014 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
* This file is part of Pydio.
*
* Pydio is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Pydio is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
*
* The latest code can be found at <http://pyd.io/>.
*/

/*******************************************************************************
* German translation:
*   + update: Axel Otterstätter <axel.otterstaetter@googlemail.com>
*   + update: Stefan Huber <sh@signalwerk.ch>
*   + update: Martin Schaible <martin@martinschaible.ch>
*******************************************************************************/  

/* Do not use HTML entities! It would mess up everything */ 
$mess=array(
"Main"      => "Haupteinstellungen",
"App Title" => "Anwendungsname",
"Your application title" => "Dieser Title wird auf den Begrüssungsbildschirm als Fenstertitel angezeigt",
"Main container for core Pydio settings (application title, sharing, webdav server config, etc...)" => "Haupteinstellungen für Pydio (Applikations-Titel, Freigaben, webDAV-Server Konfiguration, usw.)",
"Default Language" => "Standardsprache",
"Default language when a user does not have set his/her own." => "Standardsprache, wenn der Benutzer keine Sprache eingestellt hat.",
"Sharing" => "Freigaben",
"Download Folder" => "Download-Ordner",
"Absolute path to the public folder where temporary download links will be created. Setting this empty will disable the sharing feature." => "Absoluter Pfad zu dem Ordner in welchem die Download-Links für temporäre Freigaben erstellt werden. Wenn diese Einstellung leer ist, werden die Freigaben deaktiviert.",
"Download URL" => "Download-URL",
"If not inferred directly from the current ajaxplorer URI plus the public download folder name, replace the public access URL here." => "If not inferred directly from the current ajaxplorer URI plus the public download folder name, replace the public access URL here.",
"Existing users" => "Bestehende Benutzer",
"Allow the users to pick an existing user when sharing a folder" => "Erlaubt den Benutzern bei der Freigabe von Ordnern einen anderen Benutzer auszuwählen",
"Compression Features" => "Komprimierungsfunktionen",
"Gzip Download" => "Gzip Download",
"Gzip files on-the-fly before downloading. Disabled by default, as it's generally useful only on small files, and decreases performances on big files. This has nothing to see with the Zip Creation feature, it's just a on-the-fly compression applied on a unique file at download." => "Gzip files on-the-fly before downloading. Disabled by default, as it's generally useful only on small files, and decreases performances on big files. This has nothing to see with the Zip Creation feature, it's just a on-the-fly compression applied on a unique file at download.",
"Gzip Limit" => "Gzip Limit",
"If activated, a default limit should be set above when files are no more compressed." => "If activated, a default limit should be set above when files are no more compressed.",
"Zip Creation" => "Zip Creation",
"If you encounter problems with online zip creation or multiple files downloading, you can disable the feature." => "If you encounter problems with online zip creation or multiple files downloading, you can disable the feature.",
"WebDAV Server" => "WebDAV-Server",
"Enable WebDAV" => "WebDAV aktivieren",
"Enable the webDAV support. Please READ THE DOC to safely use this feature." => "Enable the webDAV support. Please READ THE DOC to safely use this feature.",
"Shares URI" => "Freigaben URI",
"Common URI to access the shares. Please READ THE DOC to safely use this feature." => "Common URI to access the shares. Please READ THE DOC to safely use this feature.",
"Shares Host" => "Shares Host",
"Host used in webDAV protocol. Should be detected by default. Please READ THE DOC to safely use this feature." => "Host used in webDAV protocol. Should be detected by default. Please READ THE DOC to safely use this feature.",
"Digest Realm" => "Digest Realm",
"Default realm for authentication. Please READ THE DOC to safely use this feature." => "Default realm for authentication. Please READ THE DOC to safely use this feature.",
"Miscellaneous" => "Verschiedenes",
"Command-line Active" => "Kommando-Zeile aktiv",
"Use Pydio framework via the command line, allowing CRONTAB jobs or background actions." => "Use Pydio framework via the command line, allowing CRONTAB jobs or background actions.",
"Command-line PHP" => "PHP-Kommando-Zeile",
"On specific hosts, you may have to use a specific path to access the php command line" => "On specific hosts, you may have to use a specific path to access the php command line",
"Filename length" => "Dateinamen länge",
"Maximum characters length of new files or folders" => "Anzahl der Zeichen für neue Ordner und Dateien",
"Temporary Folder" => "Ordner für temporäre Daten",
"This is necessary only if you have errors concerning the tmp dir access or writeability : most probably, they are due to PHP SAFE MODE (should disappear in php6) or various OPEN_BASEDIR restrictions. In that case, create and set writeable a tmp folder somewhere at the root of your hosting (but above the web/ or www/ or http/ if possible!!) and enter here the full path to this folder" => "This is necessary only if you have errors concerning the tmp dir access or writeability : most probably, they are due to PHP SAFE MODE (should disappear in php6) or various OPEN_BASEDIR restrictions. In that case, create and set writeable a tmp folder somewhere at the root of your hosting (but above the web/ or www/ or http/ if possible!!) and enter here the full path to this folder",
"Admin email" => "Admin email",
"Administrator email, not used for the moment" => "Administrator email, not used for the moment",
"User Credentials" => "User Credentials",
"User" => "Benutzer",
"User name - Can be overriden on a per-user basis (see users 'Personal Data' tab)" => "User name - Can be overriden on a per-user basis (see users 'Personal Data' tab)",
"Password" => "Passwort",
"User password - Can be overriden on a per-user basis." => "User password - Can be overriden on a per-user basis.",
"Session credentials" => "Session credentials",
"Try to use the current Pydio user credentials for connecting. Warning, the AJXP_SESSION_SET_CREDENTIALS config must be set to true!" => "Try to use the current Pydio user credentials for connecting. Warning, the AJXP_SESSION_SET_CREDENTIALS config must be set to true!",
"User name" => "Benutzername",
"User password" => "Benutzer Passwort",
"Repository Slug" => "Repository Slug",
"Alias" => "Alias",
"Alias for replacing the generated unique id of the repository" => "Alias for replacing the generated unique id of the workspace",
"Template Options" => "Template Options",
"Allow to user" => "Allow to user",
"Allow non-admin users to create a repository from this template." => "Allow non-admin users to create a workspace from this template.",
"Default Label" => "Standard Beschreibung",
"Prefilled label for the new repository, you can use the AJXP_USER keyworkd in it." => "Prefilled label for the new workspace, you can use the AJXP_USER keyworkd in it.",
"Small Icon" => "Kleines Symbol",
"16X16 Icon for representing the template" => "16X16 Icon for representing the template",
"Big Icon" => "Großes Symbol",
"Big Icon for representing the template" => "Big Icon for representing the template",
"Filesystem Commons" => "Dateisystem Allgemein",
"Recycle Bin Folder" => "Papierkorb-Verzeichnis",
"Leave empty if you do not want to use a recycle bin." => "Leave empty if you do not want to use a recycle bin.",
"Default Rights" => "Standardrechte",
"This right pattern (empty, r, or rw) will be applied at user creation for this repository." => "This right pattern (empty, r, or rw) will be applied at user creation for this workspace.",
"Character Encoding" => "Zeichenkodierung",
"If your server does not set correctly its charset, it can be good to specify it here manually." => "If your server does not set correctly its charset, it can be good to specify it here manually.",
"Pagination Threshold" => "Pagination Threshold",
"When a folder will contain more items than this number, display will switch to pagination mode, for better performances." => "When a folder will contain more items than this number, display will switch to pagination mode, for better performances.",
"#Items per page" => "#Items per page",
"Once in pagination mode, number of items to display per page." => "Once in pagination mode, number of items to display per page.",
"Default Metasources" => "Default Metasources",
"Comma separated list of metastore and meta plugins, that will be automatically applied to all repositories created with this driver" => "Comma separated list of metastore and meta plugins, that will be automatically applied to all workspaces created with this driver",
"Auth Driver Commons" => "Auth. Treiber Allgemein",
"Transmit Clear Pass" => "Transmit Clear Pass",
"Whether the password will be transmitted clear or encoded between the client and the server" => "Whether the password will be transmitted clear or encoded between the client and the server",
"Auto Create User" => "Auto Create User",
"When set to true, the user object is created automatically if the authentication succeed. Used by remote authentication systems." => "When set to true, the user object is created automatically if the authentication succeed. Used by remote authentication systems.",
"Login Redirect" => "Login Redirect",
"If set to a given URL, the login action will not trigger the display of login screen but redirect to this URL." => "If set to a given URL, the login action will not trigger the display of login screen but redirect to this URL.",
"Admin Login" => "Admin Login",
"For exotic auth drivers, an user ID that must be considered as admin by default." => "For exotic auth drivers, an user ID that must be considered as admin by default.",
"Show hidden files" => "Versteckte Dateien anzeigen",
"Show files beginning with a ." => "Show files beginning with a .",
"Hide recycle bin" => "Papierkorb verstecken",
"Whether to show the recycle bin folder. Unlike in the following options, the folder will be hidden but still writeable." => "Whether to show the recycle bin folder. Unlike in the following options, the folder will be hidden but still writeable.",
"Hide extensions" => "Hide extensions",
"Comma-separated list of extensions to hide. Extensions, files and folders that are hidden are also access forbidden." => "Comma-separated list of extensions to hide. Extensions, files and folders that are hidden are also access forbidden.",
"Hide folders" => "Ordner verstecken",
"Comma-separated list of specific folders to hide" => "Comma-separated list of specific folders to hide",
"Hide files" => "Dateien verstecken",
"Comma-separated list of specific files to hide" => "Comma-separated list of specific files to hide",
"Metadata and indexation" => "Metadata and indexation",
"Default Metasources" => "Default Metasources",
"Comma-separated list of metastore and meta plugins, that will be automatically applied to all repositories created with this driver" => "Comma-separated list of metastore and meta plugins, that will be automatically applied to all workspaces created with this driver",
"Pydio Main Options" => "Pydio Haupteinstellungen",
"Server URL" => "Server-URL",
"Server URL used to build share links and notifications. It will be detected if empty." => "Server URL used to build share links and notifications. It will be detected if empty.",
"Force Basic Auth" => "Basic Auth forcieren",
"This authentication mechanism is less secure, but will avoid the users having to re-enter a password in some case." => "This authentication mechanism is less secure, but will avoid the users having to re-enter a password in some case.",
"Browser Access" => "Browser Access",
"Display the list of files and folder when accessing through the browser" => "Display the list of files and folder when accessing through the browser",
"Command Line" => "Kommando-Zeile",
"Use COM class" => " COM-Klasse benutzen",
"On Windows running IIS, set this option to true if the COM extension is loaded, this may enable the use of the php command line." => "On Windows running IIS, set this option to true if the COM extension is loaded, this may enable the use of the php command line.",
"Disable Zip browsing" => "Disable Zip browsing",
"Disable Zip files inline browsing. This can be necessary if you always store huge zip archives: it can have some impact on performance." => "Disable Zip files inline browsing. This can be necessary if you always store huge zip archives: it can have some impact on performance.",
"Zip Encoding" => "Zip Encoding",
"Set up a specific encoding (try IBM850 or CP437) for filenames to fix characters problems during Zip creation. This may create OS-incompatible archives (Win/Mac)." => "Set up a specific encoding (try IBM850 or CP437) for filenames to fix characters problems during Zip creation. This may create OS-incompatible archives (Win/Mac).",
"Repository Commons" => "Arbeitsumgebung Allgemein",
"Description" => "Beschreibung",
"A user-defined description of the content of this workspace" => "A user-defined description of the content of this workspace",
"Group Path" => "Group Path",
"Set this repository group owner : only users of this group will see it" => "Set this repository group owner : only users of this group will see it",
"Disable WebDAV" => "WebDAV deaktivieren",
"Explicitly disable WebDAV access for this repository." => "Explicitly disable WebDAV access for this repository.",
"Allow to group admins" => "Allow to group admins",
"Allow group administrators to create a repository from this template." => "Allow group administrators to create a repository from this template.",
);
