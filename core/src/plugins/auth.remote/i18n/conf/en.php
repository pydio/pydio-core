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
"Remote authentication" => "Remote authentication",
"Authentication is done remotely (useful in CMS system)." => "Authentication is done remotely (useful in CMS system).",
"Login URL" => "Login URL",
"When not in slave mode, AJXP calls the given URL as URL?name=XXX&amp;pass=XXX&amp;key=XXX. Else it redirect to the given URL" => "When not in slave mode, AJXP calls the given URL as URL?name=XXX&amp;pass=XXX&amp;key=XXX. Else it redirect to the given URL",
"Logout URL" => "Logout URL",
"Redirect to the given URL on loggin out" => "Redirect to the given URL on loggin out",
"Secret key" => "Secret key",
"This key must only be known by remote end" => "This key must only be known by remote end",
"Users" => "Users",
"The users list" => "The users list",
"Master Auth Function" => "Master Auth Function",
"User-defined function for performing real password check, necessary for REST API (including iOS client)" => "User-defined function for performing real password check, necessary for REST API (including iOS client)",
"Master Host" => "Master Host",
"Host used to negociated the master authentication, if not set will be detected" => "Host used to negociated the master authentication, if not set will be detected",
"Master Base URI" => "Master Base URI",
"URI to access the base of the CMS installation. Used by the master auth function, this page must contain the login form!" => "URI to access the base of the CMS installation. Used by the master auth function, this page must contain the login form!",
"Auth Form ID" => "Auth Form ID",
"The HTML ID of the form tag displayed for login on the page defined previously. Not necessary for WP, login-form by default for Joomla, and user-login-form for Drupal" => "The HTML ID of the form tag displayed for login on the page defined previously. Not necessary for WP, login-form by default for Joomla, and user-login-form for Drupal",
"CMS Type" => "CMS Type",
"Choose a predefined CMS or define your custom values" => "Choose a predefined CMS or define your custom values",
"Local Prefix" => "Local Prefix",
"The users created with this prefix in their identifier will be stored and handled in the local filesystem. This can be handy for managing the temporary users." => "The users created with this prefix in their identifier will be stored and handled in the local filesystem. This can be handy for managing the temporary users.",
"Roles Map" => "Roles Map",
"Define a map of key/values for automatically mapping roles from the CMS to Pydio." => "Define a map of key/values for automatically mapping roles from the CMS to Pydio.",
"Wordpress URL" => "Wordpress URL",
"URL of your WP installation, either http://host/path or simply /path if it's on the same host" => "URL of your WP installation, either http://host/path or simply /path if it's on the same host",
"Login URI" => "Login URI",
"Exit Action" => "Exit Action",
"Choose the action performed when the user wants to quit Pydio : either trigger a Joomla! logout, or simply go back to the main page." => "Choose the action performed when the user wants to quit Pydio : either trigger a Joomla! logout, or simply go back to the main page.",
"Joomla! URL" => "Joomla! URL",
"Full path to Joomla! installation, either in the form of http://localhost/joomla/ or simply /joomla/" => "Full path to Joomla! installation, either in the form of http://localhost/joomla/ or simply /joomla/",
"Home node" => "Home node",
"Main page of your Joomla! installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form!" => "Main page of your Joomla! installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form!",
"Drupal URL" => "Drupal URL",
"Full path to Drupal installation, either in the form of http://localhost/drupal/ or simply /drupal/" => "Full path to Drupal installation, either in the form of http://localhost/drupal/ or simply /drupal/",
"Main page of your Drupal installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form." => "Main page of your Drupal installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form.",
"Custom Auth Function" => "Custom Auth Function",
"User-defined function for performing real password check, necessary for REST API (including iOS client). Add this function inside the plugin cms_auth_functions.php file" => "User-defined function for performing real password check, necessary for REST API (including iOS client). Add this function inside the plugin cms_auth_functions.php file",
"Custom" => "Custom",
"Back to main page" => "Back to main page",
"Logout" => "Logout",
);