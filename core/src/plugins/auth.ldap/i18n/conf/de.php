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
"LDAP Directory" => "LDAP Directory", /* not used anymore? */
"Authentication datas are stored on the LDAP server." => "Authentication datas are stored on the LDAP server.", /* not used anymore? */
"LDAP URL" => "LDAP-URL",
"LDAP Server URL (IP or name)" => "URL des LDAP-Servers (IP oder Name)",
"LDAP Port" => "LDAP-Port",
"LDAP Server Port (leave blank for default)" => "Port auf dem LDAP-Server (für Standardwert leer lassen)",
"LDAP bind username" => "LDAP-Benutzername",
"Username (uid + dn) of LDAP bind user" => "Benutzername (uid + dn) für LDAP-Verbindung",
"LDAP bind password" => "LDAP-Passwort",
"Password of LDAP bind user" => "Passwort für LDAP-Verbindung",
"People DN" => "Benutzer DN",
"DN where the users are stored" => "DN der die Benutzer enthält",
"LDAP Filter" => "Benutzer-Filter",
"Filter which users to fetch." => "Schränkt die Benutzer ein, die geladen werden können.",
"User attribute" => "Benutzer-Attribut",
"Username attribute" => "Attribut, welches den Namen der Benutzer beinhaltet",
"LDAP/AD Directory" => "LDAP/Active-Directory",
"Authentication datas are stored in an LDAP/AD directory." => "Authentifizierung über einen LDAP/Active-Directory Server.",
"Protocol" => "Protokoll",
"Connect through ldap or ldaps" => "Verbindung per LDAP oder LDAPS herstellen",
"Groups DN" => "Gruppen DN",
"DN where the groups are stored. Must be used in cunjonction with a group parameter mapping, generally using the memberOf feature." => "DN der die Gruppen beinhaltet. Must be used in cunjonction with a group parameter mapping, generally using the memberOf feature.",
"LDAP Groups Filter" => "Gruppen-Filter",
"Filter which groups to fetch." => "Schränkt die Gruppen ein, die geladen werden können.",
"Group attribute" => "Gruppen-Attribut",
"Group main attribute to be used as a label" => "Attribut, welches den Namen der Gruppe beinhaltet",
"LDAP attribute" => "LDAP-Attribut",
"Name of the LDAP attribute to read" => "Name des zu lesenden LDAP-Attributs",
"Mapping Type" => "Art des Mappings",
"Determine the type of mapping" => "Art des Mappings festlegen",
"Plugin parameter" => "Benutzerdefinierte Parameter",
"Name of the custom local parameter to set" => "Name benutzerdefinierter Parameter, die gesetzt werden.",
"Test User" => "Testbenutzer",
"Use the Test Connexion button to check if this user is correctly found in your LDAP directory." => "Mit dem Button Verbindung zum LDAP-Server testen wird geprüft, ob der Testbenutzer auf dem LDAP-Server gefunden wird.",
"Test Connexion" => "Verbindung testen",
"Try to connect to LDAP" => "Verbindung zum LDAP-Server testen",
"LDAP Server page size" => "LDAP-Server Anzahl Objekte",
"Page size of LDAP Server" => "Anahl an Objekten, die bei einer Abfrage vom LDAP-Server geladen werden (PageSize)",
"Search Users by Attribute" => "Attribut für Suche",
"When looking for a user through autocomplete, search on a specific parameter instead of user ID" => "Benutzer über die Autovervollständigung anhand eines bestimmten Attributs suchen, statt über die Benutzer-ID.",
"Fake Member from..." => "Fake Member from...",
"If there is no memberOf attribute/overlay, use this option to create additional memberOf attribute. Enter the groups attribute storing the members ids, can be generally either memberUid or member, depending on the schema." => "If there is no memberOf attribute/overlay, use this option to create additional memberOf attribute. Enter the groups attribute storing the members ids, can be generally either memberUid or member, depending on the schema.",
"Role Prefix (for memberof)" => "Role Prefix (for memberof)",
"Role prefix when you mapping memberof => roleID" => "Role prefix when you mapping memberof => roleID",
);
