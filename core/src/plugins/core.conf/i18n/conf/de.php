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
"Generic Conf Features" => "Standard Konfiguration",
"Let user create repositories" => "Benutzer dürfen Arbeitsumgebungen erstellen",
"Remember guest preferences" => "Einstellungen von 'Gast' beibehalten",
"If the 'guest' user is enabled, remember her preferences accross sessions." => "Wenn der Benutzer 'Gast' aktiv ist kann festgelegt werden, ob geänderte Einstellungen über mehrere Sitzungen gelten.",
"Configurations Management" => "Konfigurationsmanagement",
"Sets how the application core data (users,roles,etc) is stored." => "Festlegen wie Core-Daten (Benutzer, Rollen, etc) gespeichert werden.",
"Default start repository" => "Standard Start Arbeitsumgebung",
"Default repository" => "Standard Arbeitsumgebung",
"Maximum number of shared users per user" => "Maximale Anzahl an Freigabenbenutzer pro Benutzer",
"Shared users limit" => "Begrenzung der Freigabenbenutzer",
"Core SQL Connexion" => "SQL-Verbindung der Kernanwendung",
"SQL Connexion" => "SQL-Verbindung",
"Simple SQL Connexion definition that can be used by other sql-based plugins" => "Definition der SQL-Verbindung, auf die andere SQL-Basierte Plugins zugreifen können",
"Preferences Saving" => "Voreinstellungen",
"Skip user history" => "Oberflächeneinstellungen nicht beibehalten",
"Use this option to avoid automatic reloading of the interface state (last folder, opened tabs, etc)" => "Automatisches Laden der zuletzt geöffneten Programmoberfläche unterbinden. (zuletzt geöffneter Ordner, offene Tabs, etc)",
"Internal / External Users" => "Interne / Externe Benutzer",
"Maximum number of users displayed in the users autocompleter" => "Maximale Anzahl der Benutzern, die in der Autovervollständigung angezeigt werden",
"Users completer limit" => "Anzahl Benutzer in Autovervollständigung",
"Minimum number of characters to trigger the auto completion feature" => "Minimale Anzahl an Zeichen, bevor die Autovervollständigung angezeigt wird",
"Users completer min chars" => "Autovervollständigung ab Zeichen",
"Do not display real login in parenthesis" => "Login-Namen nicht in Klammern anzeigen",
"Hide real login" => "Anmelde-Namen verstecken",
"See existing users" => "Bestehende Benutzer anzeigen",
"Allow the users to pick an existing user when sharing a folder" => "Benutzer dürfen beim Teilen von Elementen andere Benutzer auswählen.",
"Create external users" => "Benutzer für Freigaben erstellen",
"Allow the users to create a new user when sharing a folder" => "Benutzer dürfen beim Teilen von Elementen weitere Benutzer anlegen.",
"External users parameters" => "Parameter bei der Analge von Freigabebenutzern",
"List of parameters to be edited when creating a new shared user." => "Liste mit Parametern, die beim Anlegen eines neuen Benutzers, während der Freigabe von Elementen, festgelegt werden müssen.",
"Configuration Store Instance" => "Erweiterung für den Konfigurationsspeicher",
"Instance" => "Erweiterung",
"Choose the configuration plugin" => "Erweiterung für den Konfigurationsspeicher auswählen",
"Name" => "Name",
"Full name displayed to others" => "Für andere sichtbarer Name",
"Avatar" => "Avatar",
"Image displayed next to the user name" => "Bild, welches neben dem Benutzer angezeigt wird",
"Email" => "Email",
"Address used for notifications" => "Adresse wird für Benachrichtigungen verwendet",
"Country" => "Land",
"Language" => "Sprache",
"User Language" => "Benutzer Sprache",
"Role Label" => "Rollenname",
"Users Lock Action" => "Anmelde-Aktion",
"If set, this action will be triggered automatically at users login. Can be logout (to lock out the users), pass_change (to force password change), or anything else" => "Dieser Befehl wir automatisch bei der Benutzeranmeldung ausgeführt. (z.B. Benutzer sperren, Passwortänderung, etc.)",
"Worskpace creation delegation" => "Datenübertragung beim Erstellen von Arbeitsumgebungen",
"Let user create repositories from templates" => "Arbeitsumgebungen können aus Vorlagen erstellt werden",
"Whether users can create their own repositories, based on predefined templates." => "Benutzer können aus Vorlagen ihre eigenen Arbeitsumgebungen erstellen.",
"Users Directory Listing" => "Abfrage von Benutzerdaten",
"Share with existing users from all groups" => "Mit Benutzern aller Gruppen teilen",
"Allow to search users from other groups through auto completer (can be handy if previous option is set to false) and share workspaces with them" => "Benutzer anderer Gruppen bei der Autovervollständigung anzeigen, wenn Ordner freigegeben werden.",
"List existing from all groups" => "Benutzer aller Gruppen anzeigen",
"If previous option is set to True, directly display a full list of users from all groups" => "Wenn die vorherige Einstellung aktiv ist kann hiermit festgelegt werden, ob die Benutzer anderer Gruppen direkt angezeigt werden sollen.",
"Roles / Groups Directory Listing" => "Abfrage von Gruppen und Rollen",
"Display roles and/or groups" => "Anzeige von Rollen oder Gruppen",
"Users only (do not list groups nor roles)" => "Nur Benutzer (weder Gruppen noch Rollen)",
"Allow Group Listing" => "Gruppen anzeigen",
"Allow Role Listing" => "Rollen anzeigen",
"Role/Group Listing" => "Gruppen und Rollen anzeigen",
"List Roles By" => "Rollen anzeigen",
"All roles" => "Alle Rollen",
"User roles only" => "Nur Rollen des Benutzers",
"role prefix" => "Rollen-Präfix",
"Excluded Roles" => "Ausgeschlossene Rollen",
"Included Roles" => "Eingeschlossene Rollen",
"Some roles should be disappered in the list.  list separated by ',' or start with 'preg:' for regex." => "Rollen die nicht angezeigt werden sollen. Mit Komma getrennt oder beginnend mit 'preg:' für regex.",
"Some roles should be shown in the list.  list separated by ',' or start with 'preg:' for regex." => "Rollen die angezeigt werden sollen. Mit Komma getrennt oder beginnend mit 'preg:' für regex.",
"External Users Creation" => "Anlage von Benutzern während der Freigabe von Elementen",
);
