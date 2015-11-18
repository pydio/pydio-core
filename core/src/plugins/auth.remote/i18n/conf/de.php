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
"Remote authentication" => "Remote-Authentifizierung",
"Authentication is done remotely (useful in CMS system)." => "Remote-Authentifizierung (nützlich für CMS-Systeme).",
"Login URL" => "Login URL",
"When not in slave mode, AJXP calls the given URL as URL?name=XXX&amp;pass=XXX&amp;key=XXX. Else it redirect to the given URL" => "When not in slave mode, AJXP calls the given URL as URL?name=XXX&amp;pass=XXX&amp;key=XXX. Else it redirect to the given URL",
"Logout URL" => "Logout-URL",
"Redirect to the given URL on loggin out" => "Beim Abmelden auf die angegebene URL weiterleiten.",
"Secret key" => "Secret-Key",
"This key must only be known by remote end" => "Secret-Key, der nur im entfernten System bekannt ist",
"Users" => "Benutzer",
"The users list" => "Eine Liste mit Benutzern",
"Master Auth Function" => "Master Auth Function",
"User-defined function for performing real password check, necessary for REST API (including iOS client)" => "User-defined function for performing real password check, necessary for REST API (including iOS client)",
"Master Host" => "Master Host",
"Host used to negociated the master authentication, if not set will be detected" => "Host used to negociated the master authentication, if not set will be detected",
"Master Base URI" => "Master Base URI",
"URI to access the base of the CMS installation. Used by the master auth function, this page must contain the login form!" => "URI to access the base of the CMS installation. Used by the master auth function, this page must contain the login form!",
"Auth Form ID" => "Formular-Id des Anmelde-Formulars",
"The HTML ID of the form tag displayed for login on the page defined previously. Not necessary for WP, login-form by default for Joomla, and user-login-form for Drupal" => "Die HTML-Formular-Id des Anmelde-Formulars auf der zuvor festgelegten Seite. Für WP nicht nötig. Bei Joomla standardmäßig 'login-form' und bei Drupal 'user-login-form'.",
"CMS Type" => "CMS-System",
"Choose a predefined CMS or define your custom values" => "Ein definiertes CMS-System auswählen oder eigene Werte festlegen.",
"Local Prefix" => "Benutzer-Präfix",
"The users created with this prefix in their identifier will be stored and handled in the local filesystem. This can be handy for managing the temporary users." => "Benutzer mit diesem Präfix im Benutzernamen werden im Dateisystem gespeichert. Einfach zum verwalten temporärer Benutzer.",
"Roles Map" => "Rollen-Mapping",
"Define a map of key/values for automatically mapping roles from the CMS to Pydio." => "Eine Key/Value-Liste um Rollen des CMS-Systems mit Pydio zu mappen.",
"Wordpress URL" => "Wordpress-URL",
"URL of your WP installation, either http://host/path or simply /path if it's on the same host" => "URL der WP-Installation. Absoluter Pfad (http://host/path) oder relati (/path), wenn auf dem gleichen Server.",
"Login URI" => "Login-URI",
"Exit Action" => "Aktion beim Verlassen",
"Choose the action performed when the user wants to quit Pydio : either trigger a Joomla! logout, or simply go back to the main page." => "Aktion auswählen, die ausgeführt wird, wenn der Benutzer Pydio beenden möchte. Möglich ist entweder ein Logout aus dem CMS oder eine Weiterleitung zur Haupt-Seite.",
"Joomla! URL" => "Joomla-URL",
"Full path to Joomla! installation, either in the form of http://localhost/joomla/ or simply /joomla/" => "URL der Joomla-Installation. Absoluter Pfad (http://localhost/joomla/) oder relativ (/joomla/), wenn auf dem gleichen Server.",
"Home node" => "Hauptseite",
"Main page of your Joomla! installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form!" => "Joomla Hauptseite, die ein Anmelde-Formular enthält. Wenn der Benutzer bei Pydio noch nicht eingeloggt ist wird er auf diese Seite weitergeleitet. Zudem wir diese für API-Aufrufe verwendet um Benutzer von Pyio aus anzumelden.  Stellen Sie sicher, dass diese Seite ein Anmelde-Formular enthält.",
"Drupal URL" => "Drupal-URL",
"Full path to Drupal installation, either in the form of http://localhost/drupal/ or simply /drupal/" => "URL der Drupal-Installation. Absoluter Pfad (http://localhost/drupal/) oder relativ (/drupal/), wenn auf dem gleichen Server.",
"Main page of your Drupal installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form." => "Drupal Hauptseite, die ein Anmelde-Formular enthält. Wenn der Benutzer bei Pydio noch nicht eingeloggt ist wird er auf diese Seite weitergeleitet. Zudem wir diese für API-Aufrufe verwendet um Benutzer von Pyio aus anzumelden.  Stellen Sie sicher, dass diese Seite ein Anmelde-Formular enthält.",
"Custom Auth Function" => "Benutzerdefinierte Authentifizierungs-Methode",
"User-defined function for performing real password check, necessary for REST API (including iOS client). Add this function inside the plugin cms_auth_functions.php file" => "Benutzerdefinierte Authentifizierungs-Methode zur Prüfung des Passworts für die REST-API (incl. iOS-App). Die Methode muss zur Datei cms_auth_functions.php hinzugefügt werden.",
"Custom" => "Benutzerdefiniert",
"Back to main page" => "Zurück zur Hauptseite",
"Logout" => "Abmelden",
);
