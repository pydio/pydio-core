<?php
/*
* Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
$mess=array(
"FS Mount" => "Dateisystem einbinden",
"Mount a remote location on the file system before accessing the repository" => "Dateisystem einbinden, bevor auf die Arbeitsumgebung zugegriffen wird",
"User quota" => "Speicherlimit pro Benutzer",
"Maintain the size of a given directory for each user" => "Maximaler Speicherplatz, den ein Benutzer belegen darf.",
"Usage scope" => "Betrachtung",
"Define if usage must be computed for this repository only, or cumulated on all the repositories of the user" => "Legt fest ob das Speicherlimit pro Arbeitsumgebung oder für alle Arbeitsumgebungen zusammen gilt",
"Repository" => "Pro Arbeitsumgebung",
"Cumulate repositories" => "Für alle Arbeitsumgebungen",
"User Quota" => "Speicherlimit pro Benutzer",
"Authorized quota. Use php.ini like values (20M, 2G), etc." => "Erlaubtes Speicherlimit pro Benutzer. Werte wie in php.ini verwenden (20M, 2G), etc.",
"Cache value" => "Wert zwischenspeichern",
"Store computed quota value in the user preferences, to avoid triggering computation each time it is requested. This may introduce some lag if the repository is shared by many users." => "Speicherverbrauch in den Einstellungen des Benutzers zwischenspeichern um die erneute Berechnung beim Zugriff zu vermeiden. Dies kann zu Zeitverzögerungen führen, wenn der Arbeitsbereich freigegeben ist.",
"Soft Limit (%)" => "Dynamische Begrenzung (%)",
"Custom Field (Deprecated)" => "Benutzerdefiniertes Feld (veraltet)",
"If you want to define quotas for each user, define a custom field in the CUSTOM_DATA parameter of the conf plugin, and declare this field name here." => "Bei einem Speicherlimit pro Benutzer muss in der 'Conf'-Erweiterung ein CUSTOM_DATA-Parameter definiert sein, dessen Feldname hier eingetragen wird.",
);
