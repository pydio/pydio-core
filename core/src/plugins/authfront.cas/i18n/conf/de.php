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

$mess = array(
    "CAS FrontEnd" => "CAS-Server",
    "Authentication by CAS" => "Über einen CAS-Server (Central Authentication Service) anmelden",
    "Order" => "Reihenfolge der Erweiterung",
    "Order this plugin with other auth frontends" => "Diese Erweiterung unter den anderen aktiven Authentifizierungs-Erweiterungen einordnen",
    "Create User" => "Benutzer erstellen",
    "Automatically create user if it does not already exists" => "Benutzer automatisch erstellen, wenn dieser noch nicht existiert",
    "General" =>"Allgemein" ,
    "Protocol Type" => "Aktivieren für",
    "Enable/disable automatically based on the protocol used" => "Erweiterung abhängig vom verwendeten Protokoll aktivieren oder deaktivieren",
    "Sessions Only" => "Web-Sitzungen",
    "CAS server address" => "Adresse des CAS-Servers",
    "CAS Server" => "CAS-Server",
    "CAS Port" => "CAS-Port" ,
    "Port where CAS server is running on. Default: 443" => "Port auf dem der CAS-Server läuft. Standard: 443",
    "CAS URI" => "CAS-URI" ,
    "URI for CAS service (without / at the end). Default: /" => "URI des CAS service (ohne / am Ende). Standard: /",
    "Logout URL" => "Logout-URL",
    "Redirect to the given URL on loggin out" => "Nach dem Ausloggen auf eine bestimmte URL weiterleiten",
    "Modify login page" => "Anmeldeseite anpassen" ,
    "Login page will be modified to give user a link to authenticate via CAS manually. Otherwise Pydio will redirect automatically to CAS login page." => "Auf der Anmeldeseite einen Link für die manuelle Anmeldung per CAS anzeigen. Andernfalls wird Pydio automatisch zur CAS Anmeldeseite weiterleiten.",
    "Certificate path" => "Pfad zum Zertifikat" ,
    "Path to the ca chain that issued the cas server certificate" => "Pfad zur CA-Chain, die das CAS-Zertifikat des Servers ausstellte",
    "Debug mode" => "Debug-Modus" ,
    "Debug file" => "Debug-Datei" ,
    "Set phpCAS in debug mode" => "Aktiviert in phpCAS den Debug-Modus" ,
    "Log to file. If null, use yyyy-mm-dd.txt" => "In Datei Protokollieren. Bei null nutze yyyy-mm-dd.txt",
    "String for CAS auth" => "Text bei CAS-Authentifizierung",
    "This message will be appeared in login page. Ex: Use CAS credential" => "Dieser Text wird bei CAS-Authentifizierung auf der Anmelde-Seite angezeigt.",
    "String for Pydio auth" => "Text bei Pydio-Authentifizierung",
    "This message will be appeared in login page. Ex: Use Pydio credential" => "Dieser Text wird bei Pydio-Authentifizierung auf der Anmelde-Seite angezeigt.",
    "String for button click here" => "Text für Klick auf Button",
    "Additional roles for user logged in by CAS" => "Zusätzliche Rolle für Benutzer die durch CAS angemeldet werden",
    "phpCAS mode" => "phpCAS-Modus" ,
    "In mode proxy, phpCAS works as a CAS Proxy who provides Proxy ticket for others services such as SMB, IMAP" => "Im Proxy Modus arbeitet phpCAS  als CAS Proxy, der Proxy-Tickets für andere Dienste wie z.B. SMB oder IMAP anbietet.",
    "Client" => "Client" ,
    "Proxy" => "Proxy" ,
    "Proxied Service" => "Service über Proxy" ,
    "Proxied service who uses Proxy Ticket provided by this CAS Proxy.Ex smb://pydio.com" => "Benutzt ein Proxy-Ticket vom CAS Proxy. (z.B. smb://pydio.com)",
    "PTG store mode" => "PTG Speicher-Modus" ,
    "Config for Proxy Granting Ticket Storage. If is file option, location for storate is session_save_path()" => "Proxy stellt einen Ticketspeicher bereit. Bei der 'Datei'-Option ist der Speicherort session_save_path().",
    "mySQL Tables" => "MySQL-Tabellen",
    "Install SQL Table (support only mysql)" => "SQL-Tabellen installieren (nur MySQL)",
    "Set Fixed Callback Url" => "Feste Callback-Url",
    "CAS will call this url to pass pgtID and pgtIOU. It's very useful when you deploy Pydio in several nodes" => "CAS verwendet diese URL um pgtID und pgtIOU zu übertragen. Dies ist nötig, wenn es mehrere Instanzen von Pydio gibt.",
);
