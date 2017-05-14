<?php
/*
* Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
* The latest code can be found at <https://pydio.com>.
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
"Use the Test Connexion button to check if this user is correctly found in your LDAP directory." => "Mit dem Button \"Verbindung zum LDAP-Server testen\" wird geprüft, ob der Testbenutzer auf dem LDAP-Server gefunden wird.",
"Test Connexion" => "Verbindung testen",
"Try to connect to LDAP" => "Verbindung zum LDAP-Server testen",
"LDAP Server page size" => "LDAP-Server Anzahl Objekte",
"Page size of LDAP Server" => "Anahl an Objekten, die bei einer Abfrage vom LDAP-Server geladen werden (PageSize)",
"Search Users by Attribute" => "Attribut für Suche",
"When looking for a user through autocomplete, search on a specific parameter instead of user ID" => "Benutzer über die Autovervollständigung anhand eines bestimmten Attributs suchen, statt über die Benutzer-ID.",
"Fake Member from..." => "Fake-Mitglied von...",
"If there is no memberOf attribute/overlay, use this option to create additional memberOf attribute. Enter the groups attribute storing the members ids, can be generally either memberUid or member, depending on the schema." => "Falls es kein 'memberOf'-Attribut gibt kann es mit dieser Einstellung ein Attribut festgelegt werden. Geben Sie den Namen des Attributs ein, das abhängig vom Schema entweder MemberId, MemberUid oder das Member enthält.",
"Role Prefix (for memberof)" => "Rollen-Prefix (von memberOf)",
"Role prefix when you mapping memberof => roleID" => "Rollen-Prefix beim Mapping von memberof auf roleID",
"Server Connection" => "Server-Verbindung",
"Set up main connection to server. Use the button to test that your parameters are correct." => "Konfigurieren Sie hier die Verbindung zum LDAP-Server.",
"Users Schema" => "Benutzer-Schema",
"These parameters will describe how the users will be loaded/filtered from the directory." => "Hier können Sie einstellen, wie Benutzer aus dem Verzeichnis geladen/gefiltert werden.",
"Groups Schema" => "Gruppen-Schema",
"These parameters will describe how groups will optionally be loaded/filtered from the directory." => "Hier können Sie einstellen, wie Gruppen aus dem Verzeichnis geladen/gefiltert werden. (Optional)",
"Role prefix when you mapping memberof =&gt; roleID" => "Rollen-Prefix beim Mapping von memberof =&gt; roleID",
"Attributes Mapping" => "Attribut-Mapping",
"Use this section to automatically map some LDAP attributes to Pydio plugins parameters values." => "In diesem Bereich können LDAP-Attribute automatisch auf Pydio-Parameter gemappt werden.",
"Advanced Parameters" => "Parameter für fortgeschrittene Benutzer",
"More advanced settings for LDAP/AD" => "LDAP/AD-Einstellungen für fortgeschrittene Benutzer",
"Fake MemberOf. value of member/memberUid attribute of group" => "Fake MemberOf. Wert des member/memberUid Attributs der Gruppe",
"value of member/memberUid attribute of group: can be user DN or user CN. Use with Fake memberOf enabled. YES use DN, otherwise CN" => "Wert des member/memberUid Attributes der Gruppe: Kann DN oder CN sein. Wird mit aktivem Fake memberOf verwendet. Verwenden Sie DN bei Ja, sonst CN",
"Cache User Count (hours)" => "Benutzeranzahl zwischenspeichern für (Stunden)",
"Locally cache the total number of users during X hours. Can be handy for huge directories." => "Die Benutzeranzahl lokal für x Stunden zwischenspeichern. Für große Verzeichnisse empfehlenswert.",
);
