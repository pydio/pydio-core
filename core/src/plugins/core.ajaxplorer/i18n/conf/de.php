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
"Your application title" => "Dieser Title wird auf dem Begrüssungsbildschirm als Fenstertitel angezeigt",
"Main container for core Pydio settings (application title, sharing, webdav server config, etc...)" => "Haupteinstellungen für Pydio (Applikations-Titel, Freigaben, webDAV-Server Konfiguration, usw.)",
"Default Language" => "Standardsprache",
"Default language when a user does not have set his/her own." => "Sprache die vorausgewählt ist, wenn der Benutzer keine abweichende konfiguriert.",
"Sharing" => "Freigaben",
"Download Folder" => "Download-Ordner",
"Absolute path to the public folder where temporary download links will be created. Setting this empty will disable the sharing feature." => "Absoluter Pfad zu einem Ordner für temporäre Freigaben und Download-Links. Leer lassen, um die Freigabe-Funktion zu deaktivieren.",
"Download URL" => "Download-URL",
"If not inferred directly from the current ajaxplorer URI plus the public download folder name, replace the public access URL here." => "Wenn nicht 'Server-URL' + Ordnername aus 'Download-Ordner' muss hier die URL angegeben werden.",
"Existing users" => "Bestehende Benutzer",
"Allow the users to pick an existing user when sharing a folder" => "Erlaubt den Benutzern bei der Freigabe von Ordnern einen anderen Benutzer auszuwählen",
"Compression Features" => "Komprimierungsfunktionen",
"Gzip Download" => "Gzip-Komprimierung",
"Gzip files on-the-fly before downloading. Disabled by default, as it's generally useful only on small files, and decreases performances on big files. This has nothing to see with the Zip Creation feature, it's just a on-the-fly compression applied on a unique file at download." => "Dateien vor dem Herunterladen mit Gzip komprimieren. Standardmäßig deaktiviert, da die Performance zwar bei kleinen Dateien besser, dafür aber bei großen Dateien schlechter wird. Steht nicht im Zugammenhang mit der Zip-Komprimierung beim Herunterladen mehrerer Dateien sondern gilt nur für einzelne Dateien.",
"Gzip Limit" => "Gzip-Komprimierung bis Dateigröße",
"If activated, a default limit should be set above when files are no more compressed." => "Dateien die größer sind werden nicht mehr komprimiert. Einschränkung sollte für gute Geschwindigkeit verwendet werden.",
"Zip Creation" => "Erstellung von Zip-Dateien",
"If you encounter problems with online zip creation or multiple files downloading, you can disable the feature." => "Deaktivieren, wenn es Probleme beim Online-Erstellen von ZIP-Dateien oder dem Download mehrerer Dateien gibt.",
"WebDAV Server" => "WebDAV-Server",
"Enable WebDAV" => "WebDAV aktivieren",
"Enable the webDAV support. Please READ THE DOC to safely use this feature." => "WebDAV-Unterstützung aktivieren. Bitte lesen Sie die Dokumentation, bevor diese Funktion aktiviert wird.",
"Shares URI" => "Freigabe-URI",
"Common URI to access the shares. Please READ THE DOC to safely use this feature." => "URI für den Zugriff auf Freigaben. Bitte lesen Sie die Dokumentation, bevor diese Funktion aktiviert wird.",
"Shares Host" => "Freigabe-Host",
"Host used in webDAV protocol. Should be detected by default. Please READ THE DOC to safely use this feature." => "Host für WebDAV. Sollte automatisch erkannt werden. Bitte lesen Sie die Dokumentation, bevor diese Funktion aktiviert wird.",
"Digest Realm" => "Authentifizierungs-Realm",
"Default realm for authentication. Please READ THE DOC to safely use this feature." => "Standard  Authentifizierungs-Realm. Bitte lesen Sie die Dokumentation, bevor diese Funktion aktiviert wird.",
"Miscellaneous" => "Verschiedenes",
"Command-line Active" => "Kommandozeile aktiv",
"Use Pydio framework via the command line, allowing CRONTAB jobs or background actions." => "Pydio kann über die Kommandozeile aufgerufen werden. (z.B. für CRONTAB oder Hintergrund-Aktionen)",
"Command-line PHP" => "Pfad zur PHP-Kommandozeile",
"On specific hosts, you may have to use a specific path to access the php command line" => "Wenn PHP nicht über die Umgebungsvariablen gefunden wird muss hier der Pfad angegeben werden.",
"Filename length" => "Dateinamen länge",
"Maximum characters length of new files or folders" => "Anzahl der Zeichen für neue Ordner und Dateien",
"Temporary Folder" => "Ordner für temporäre Daten",
"This is necessary only if you have errors concerning the tmp dir access or writeability : most probably, they are due to PHP SAFE MODE (should disappear in php6) or various OPEN_BASEDIR restrictions. In that case, create and set writeable a tmp folder somewhere at the root of your hosting (but above the web/ or www/ or http/ if possible!!) and enter here the full path to this folder" => "Nur nötig, wenn es Problem beim (Schreib-)zugriff auf das Tmp-Verzeichnis gibt. Meistens verursacht durch den PHP SAFE MODE (in php6 nicht mehr vorhanden) oder durch verschiedene OPEN_BASEDIR-Einschränkungen. In diesem Fall kann ein eigener tmp-Ordner angelegt (wenn möglich außerhalb von web/, www/ oder http/) und hier eingetragen werden.",
"Admin email" => "Admin email",
"Administrator email, not used for the moment" => "Administrator email, not used for the moment",
"User Credentials" => "Anmeldeinformationen",
"User" => "Benutzer",
"User name - Can be overriden on a per-user basis (see users 'Personal Data' tab)" => "Benutzername, der pro Benutzer überschrieben werden kann. (siehe Tab 'Benutzerdaten')",
"Password" => "Passwort",
"User password - Can be overriden on a per-user basis." => "Passwort, das pro Benutzer überschrieben werden kann.",
"Session credentials" => "Anmeldeinformationen aus Sitzung",
"Try to use the current Pydio user credentials for connecting. Warning, the AJXP_SESSION_SET_CREDENTIALS config must be set to true!" => "Verwendet die Pydio Anmeldeinformationen des Benutzers. Achtung, dafür muss die Einstellung AJXP_SESSION_SET_CREDENTIALS aktiviert sein.",
"User name" => "Benutzername",
"User password" => "Benutzer Passwort",
"Repository Slug" => "Repository Slug",
"Alias" => "Alias",
"Alias for replacing the generated unique id of the repository" => "Alias, der statt der automatisch generierten Id dieser Arbeitsumgebung angezeigt wird.",
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
"Leave empty if you do not want to use a recycle bin." => "Leer lassen, um keinen Papierkorb zu verwenden.",
"Default Rights" => "Standardrechte",
"This right pattern (empty, r, or rw) will be applied at user creation for this repository." => "Rechte die auf Benutzer angewendet werden, wenn Sie für diesen Arbeitsbereich angelegt werden. (leer, r, oder rw)",
"Character Encoding" => "Zeichenkodierung",
"If your server does not set correctly its charset, it can be good to specify it here manually." => "Wenn der Zeichensatz vom Server nicht korrekt erkannt wurde kann er hier manuell gesetzt werden.",
"Pagination Threshold" => "Seitenumbruch ab",
"When a folder will contain more items than this number, display will switch to pagination mode, for better performances." => "Ordner mehrseitig anzeigen, wenn die angegebene Anzahl an Einträgen überschritten ist, um die Geschwindigkeit zu verbessern.",
"#Items per page" => "Einträge pro Seite",
"Once in pagination mode, number of items to display per page." => "Wenn der Ordner mehrseitig angezeigt wird kann hier angegeben werden, wie viele Elemente sich auf einer Seite befinden.",
"Default Metasources" => "Standard-Quellen für Metadaten",
"Comma separated list of metastore and meta plugins, that will be automatically applied to all repositories created with this driver" => "Kommagetrennte Liste mit Metadaten- und Metadatenspeicher-Erweiterungen, die automatisch beim Anlegen einer neuen Arbeitsumgebung hinzugefügt werden, wenn dieser Treiber verwendet wird.",
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
"Show files beginning with a ." => "Dateien, die mit einem '.' beginnen anzeigen.",
"Hide recycle bin" => "Papierkorb verstecken",
"Whether to show the recycle bin folder. Unlike in the following options, the folder will be hidden but still writeable." => "Soll ein Papierkorb angezeigt werden. Auch wenn der Papierkorb nicht angezeigt wird ist der Ordner trotzdem beschreibbar.",
"Hide extensions" => "Datei-Erweiterungen verstecken",
"Comma-separated list of extensions to hide. Extensions, files and folders that are hidden are also access forbidden." => "Kommagetrennte Liste mit Datei-Erweiterungen, die versteckt werden. Der Zugriff auf versteckte Dateien wird ebenfalls verboten.",
"Hide folders" => "Ordner verstecken",
"Comma-separated list of specific folders to hide" => "Kommagetrennte Liste mit Ordnern, die versteckt werden.",
"Hide files" => "Dateien verstecken",
"Comma-separated list of specific files to hide" => "Kommagetrennte Liste mit Dateien, die versteckt werden. Der Zugriff auf versteckte Dateien wird ebenfalls verboten.",
"Metadata and indexation" => "Metadaten und Indizierung",
"Pydio Main Options" => "Pydio-Haupteinstellungen",
"Server URL" => "Server-URL",
"Server URL used to build share links and notifications. It will be detected if empty." => "Wird für Freigabe-Links und Benachrichtigungen verwendet. Wert wird automatisch ermittelt, wenn leer.",
"Force Basic Auth" => "Standard-Authentifizierung erzwingen",
"This authentication mechanism is less secure, but will avoid the users having to re-enter a password in some case." => "Bietet weniger Sicherheit und die Benutzer müssen in manchen Fällen das Passwort nicht erneut eingeben.",
"Browser Access" => "Zugriff über den Browser",
"Display the list of files and folder when accessing through the browser" => "Die Dateiliste kann über den Browser abgerufen werden.",
"Command Line" => "Kommandozeile",
"Use COM class" => " COM-Klasse benutzen",
"On Windows running IIS, set this option to true if the COM extension is loaded, this may enable the use of the php command line." => "Auf Windows-Servern mit IIS setzen, wenn die COM-Erweiterung geladen ist. Damit ist es möglich, die PHP-Kommandozeile zu nutzen.",
"Disable Zip browsing" => "Direktes Öffnen von ZIP-Dateien deaktivieren",
"Disable Zip files inline browsing. This can be necessary if you always store huge zip archives: it can have some impact on performance." => "Direktes Öffnen von ZIP-Dateien deaktivieren. Dies kann nötig sein, wenn mit großen ZIP-Dateien gearbeitet wird, da dies Auswirkungen auf die Geschwindigkeit des Systems hat.",
"Zip Encoding" => "Zip-Encoding",
"Set up a specific encoding (try IBM850 or CP437) for filenames to fix characters problems during Zip creation. This may create OS-incompatible archives (Win/Mac)." => "Bestimmtes Encoding für Dateinamen verwenden um Probleme beim Erstellen der Zip-Dateien zu vermeiden. (z.B. IBM850 oder CP437) Es ist möglich, inkompatible Archive zu erstellen (Win/Mac).",
"Repository Commons" => "Arbeitsumgebung Allgemein",
"Description" => "Beschreibung",
"A user-defined description of the content of this workspace" => "Benutzerdefinierte Beschreibung des Inhalts dieser Arbeitsumgebung",
"Group Path" => "Gruppe",
"Set this repository group owner : only users of this group will see it" => "Gruppe, der diese Arbeitsumgebung gehört. Nur Benutzer dieser Gruppe können die Arbeitsumgebung sehen.",
"Disable WebDAV" => "WebDAV deaktivieren",
"Explicitly disable WebDAV access for this repository." => "WebDAV-Zugriff für diese Arbeitsumgebung gezielt deaktivieren.",
"Allow to group admins" => "Allow to group admins",
"Allow group administrators to create a repository from this template." => "Allow group administrators to create a repository from this template.",
"Skip auto-update admin rights" => "Automatische Aktualisierung der Admin-Berechtigungen",
"If you have tons of workspaces (which is not recommanded), admin users login can take a long time while updating admin access to all repositories. Use this option to disable this step, admin will always have access to the Settings." => "Wenn Sie sehr viele Arbeitsbereiche haben (was nicht empfohlen ist), kann die Anmeldung als Administrator sehr lange dauern, da die Rechte aller Arbeitsumgebungen aktualisiert werden. Diese Einstellung deaktiviert die Aktualisierung. Der Administrator hat aber immer Zugriff auf die Einstellungen.",
);
