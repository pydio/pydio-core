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
"Remote authentication" => "Authentication externe",
"Authentication is done remotely (useful in CMS system)." => "Interface avec un CMS extener",
"Authentication mode" => "Mode",
"If set, the remote end calls us to register upon login, else, we will be calling the remote end when login is required" => "Qui est le maitre de l'authentification ? AjaXplorer ou le CMS externe?",
"Login URL" => "URL d'identification",
"When not in slave mode, AJXP calls the given URL as URL?name=XXX&amp;pass=XXX&amp;key=XXX. Else it redirect to the given URL" => "En mode master, AjaXplorer appelle l'URL URL?name=XXX&amp;pass=XXX&amp;key=XXX. sinon redirige vers cette URL.",
"Logout URL" => "URL déconnexion",
"Redirect to the given URL on loggin out" => "Redirection vers cette URL au logout.",
"Secret key" => "Secret key",
"This key must only be known by remote end" => "This key must only be known by remote end",
"Users" => "Utilisateurs",
"The users list" => "Liste des utilisateurs",
"Master Auth Function" => "Fonction Master",
"User-defined function for performing real password check, necessary for REST API (including iOS client)" => "Fonction définie pour chaque CMS que le plugin peut appeler pour effectuer une authentification. Nécessaire pour le bon fonctionnement du client iOS.",
"Master Host" => "Hôte Master",
"Host used to negociated the master authentication, if not set will be detected" => "Hôte appelé pour la négociation master, peut être détecté automatiquement",
"Master Base URI" => "URI Master",
"URI to access the base of the CMS installation. Used by the master auth function, this page must contain the login form!" => "Paramêtre utilisé par la fonction de auth master, pour trouver le formulaire de login du CMS et le soumettre automatiquement.",
"Auth Form ID" => "Form ID Master",
"The HTML ID of the form tag displayed for login on the page defined previously. Not necessary for WP, login-form by default for Joomla, and user-login-form for Drupal" => "ID HTML du formulaire de login affiché sur la page du CMS. Not necessary for WP, login-form by default for Joomla, and user-login-form for Drupal",
);
?>