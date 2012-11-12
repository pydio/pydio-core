<?php
/*
* Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
* This file is part of AjaXplorer.
*
* AjaXplorer is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* AjaXplorer is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
*
* The latest code can be found at <http://www.ajaxplorer.info/>.
*/
$mess=array(
"Remote authentication" => "Remote authentication",
"Authentication is done remotely (useful in CMS system)." => "Authentication is done remotely (useful in CMS system).",
"Authentication mode" => "Authentication mode",
"If set, the remote end calls us to register upon login, else, we will be calling the remote end when login is required" => "If set, the remote end calls us to register upon login, else, we will be calling the remote end when login is required",
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
);
?>