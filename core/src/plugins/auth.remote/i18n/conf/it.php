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
"Remote authentication" => "Autenticazione Remota",
"Authentication is done remotely (useful in CMS system)." => "L'autenticazione è eseguita in modo remoto (utilizzando il sistema integrato nel CMS).",
"Authentication mode" => "Modalità Autenticazione",
"If set, the remote end calls us to register upon login, else, we will be calling the remote end when login is required" => "Se impostato, il contatto remoto ci chiede la registrazione al momento del login; altrimenti, il sistema chiamerà il contatto remoto quando il login è richiesto",
"Login URL" => "Login URL",
"When not in slave mode, AJXP calls the given URL as URL?name=XXX&amp;pass=XXX&amp;key=XXX. Else it redirect to the given URL" => "Quando il sistema non è Secondario, AJXP chiama l'indirizzo specificato come URL?name=XXX&amp;pass=XXX&amp;key=XXX. Altrimenti reindirizza all'indirizzo specificato.",
"Logout URL" => "Logout URL",
"Redirect to the given URL on loggin out" => "Reindirizza all'URL specificato dopo il logout.",
"Secret key" => "Chiave Segreta",
"This key must only be known by remote end" => "Questa chiave deve essere nota SOLO al sistema remoto",
"Users" => "Utenti",
"The users list" => "Lista utenti",
"Master Auth Function" => "Funzione Principale Autenticazione",
"User-defined function for performing real password check, necessary for REST API (including iOS client)" => "Funzione definita lato utente, per consentire il controllo della password, necessario per l'uso delle REST API (iOS incluso)",
"Master Host" => "Host Principale",
"Host used to negociated the master authentication, if not set will be detected" => "Host utilizzato per negoziare l'autenticazione principale; se non impostato, verrà dedotto automaticamente",
"Master Base URI" => "URI Base Principale",
"URI to access the base of the CMS installation. Used by the master auth function, this page must contain the login form!" => "URI per l'accesso all'installazione base del CMS. Utilizzato dalla Funzione Principale di Autenticazione, questa pagina deve contenere la form di login!",
"Auth Form ID" => "ID Form Autenticazione",
"The HTML ID of the form tag displayed for login on the page defined previously. Not necessary for WP, login-form by default for Joomla, and user-login-form for Drupal" => "ID HTML del tag form visualizzato per il login. Non è necessario per WP, la 'login-base' di Joomla e la 'user-login-form' di Drupal",
"CMS Type" => "Tipo CMS",
"Choose a predefined CMS or define your custom values" => "Scegli un CMS predefinito o inserisci i valori manualmente",
"Local Prefix" => "Prefisso Locale",
"The users created with this prefix in their identifier will be stored and handled in the local filesystem. This can be handy for managing the temporary users." => "Gli utenti creati con questo prefisso nell'identificativo, verranno memorizzati e gestiti dal filesystem locale. Può essere utile per gestire utenti temporanei.",
"Roles Map" => "Mappatura Ruoli",
"Define a map of key/values for automatically mapping roles from the CMS to Pydio." => "Definire una mappa di chiave/valore per la mappatura automatica dei ruoli tra il CMS e il Pydio.",
"Wordpress URL" => "Wordpress URL",
"URL of your WP installation, either http://host/path or simply /path if it's on the same host" => "URL dell'installazione WP; sia 'http://host/path' che semplicemente '/path', se sullo stesso host",
"Login URI" => "Login URI",
"Exit Action" => "Azione Uscita",
"Choose the action performed when the user wants to quit Pydio : either trigger a Joomla! logout, or simply go back to the main page." => "Scegliere l'azione da eseguire quando un utente vuole uscire da Pydio: esecuzione del logout anche su Joomla! oppure ritorno alla pagina principale.",
"Joomla! URL" => "Joomla! URL",
"Full path to Joomla! installation, either in the form of http://localhost/joomla/ or simply /joomla/" => "Percorso assoluto dell'installazione Joomla!; sia nel formato 'http://localhost/joomla/' che '/joomla/'",
"Home node" => "Nodo Home",
"Main page of your Joomla! installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form!" => "Pagina principale dell'installazione Joomla! che contiene la form di login. Quando non loggato, accedendo a Pydio si verrà reindirizzati a questa pagina. E' anche utilizzato per le chiamate delle API durante il login in Pydio. Assicurati che contenga la form di login!",
"Drupal URL" => "Drupal URL",
"Full path to Drupal installation, either in the form of http://localhost/drupal/ or simply /drupal/" => "Percorso assoluto dell'installazione Drupal; sia nel formato 'http://localhost/drupal/' che '/drupal/'",
"Main page of your Drupal installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form." => "Pagina principale dell'installazione Drupal che contiene la form di login. Quando non loggato, accedendo a Pydio si verrà reindirizzati a questa pagina. E' anche utilizzato per le chiamate delle API durante il login in Pydio. Assicurati che contenga la form di login!.",
"Custom Auth Function" => "Funzione Autenticazione Personalizzata",
"User-defined function for performing real password check, necessary for REST API (including iOS client). Add this function inside the plugin cms_auth_functions.php file" => "Funzione definita lato utente, per consentire il controllo della password, necessario per l'uso delle REST API (iOS incluso). Aggiungi questa funzione nel file 'cms_auth_functions.php'",
);
