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
"Remote authentication" => "Authentication externe",
"Authentication is done remotely (useful in CMS system)." => "L'authentification est effectuée à distance (utile pour les CMS)",
"Authentication mode" => "Mode d'authentification",
"If set, the remote end calls us to register upon login, else, we will be calling the remote end when login is required" => "Si défini, l'extrémité distante contacte Pydio pour enregistrement lors de la connexion, sinon Pydio contactera l'extremité distante lorsqu'une connexion sera requise",
"Login URL" => "URL de connexion",
"When not in slave mode, AJXP calls the given URL as URL?name=XXX&amp;pass=XXX&amp;key=XXX. Else it redirect to the given URL" => "Lorsqu'il n'est pas en mode Esclave, Pydio contacte l'URL définie (ex : URL?name=XXX&amp;pass=XXX&amp;key=XXX). Sinon, il redirige vers cette URL.",
"Logout URL" => "URL de déconnexion",
"Redirect to the given URL on loggin out" => "Redirection vers cette URL lors de la déconnexion.",
"Secret key" => "Clef secrète",
"This key must only be known by remote end" => "Cette clef ne doit-être connue que par l'extrémité distante",
"Users" => "Utilisateurs",
"The users list" => "Liste des utilisateurs",
"Master Auth Function" => "Fonction Maître d'authentification",
"User-defined function for performing real password check, necessary for REST API (including iOS client)" => "Fonction définie par l'utilisateur pour effectuer la vérification du mot de passe. Requise pour l'API REST (incluant les clients iOS)",
"Master Host" => "Hôte Maître",
"Host used to negociated the master authentication, if not set will be detected" => "Hôte utilisé pour négocier l'authentification maître. Si non défini, il sera détécté.",
"Master Base URI" => "URI de base Maître",
"URI to access the base of the CMS installation. Used by the master auth function, this page must contain the login form!" => "URI d'accès à la base de l'installation du CMS. Utilisée par la fonction d'authentification maître, cette page doit contenir un formulaire de connexion !",
"Auth Form ID" => "ID du formulaire d'authentification",
"The HTML ID of the form tag displayed for login on the page defined previously. Not necessary for WP, login-form by default for Joomla, and user-login-form for Drupal" => "ID HTML du formulaire affiché pour la connexion sur la page définie précédement. Non requis pour WP, 'login-form' pour Joomla, 'user-login-form' pour Drupal",
"CMS Type" => "Type de CMS",
"Choose a predefined CMS or define your custom values" => "Choisissez un CMS prédéfini, ou définissez vos valeurs personnalisées",
"Local Prefix" => "Préfixe local",
"The users created with this prefix in their identifier will be stored and handled in the local filesystem. This can be handy for managing the temporary users." => "Les utilisateurs créés avec ce préfixe dans leur identifiant seront stockés traités dans le système de fichier local. Peut-être pratique pour gérer les utilisateurs temporaires.",
"Roles Map" => "Map des rôles",
"Define a map of key/values for automatically mapping roles from the CMS to Pydio." => "Defini une map de clefs/valeurs pour un mappage automatique des rôles du CMS dans Pydio.",
"Wordpress URL" => "URL de Wordpress",
"URL of your WP installation, either http://host/path or simply /path if it's on the same host" => "URL de votre installation de WP, 'http://host/path' ou simplement '/path' si sur le même hôte",
"Login URI" => "URI de connexion",
"Exit Action" => "Action de sortie",
"Choose the action performed when the user wants to quit Pydio : either trigger a Joomla! logout, or simply go back to the main page." => "Choisissez l'action à effectuer lorsque l'utilisateur souhaite quitter Pydio : se déconnecter de Joomla! ou simplement revenir à la page d'accueil.",
"Joomla! URL" => "URL de Joomla!",
"Full path to Joomla! installation, either in the form of http://localhost/joomla/ or simply /joomla/" => "URL de votre installation de WP, 'http://host/joomla' ou simplement '/joomla' si sur le même hôte",
"Home node" => "Node d'accueil",
"Main page of your Joomla! installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form!" => "Page principale de votre installation de Joomla! contenant un formulaire de connexion. Tant que la connexion n'a pas été effectué, accèder à Pydio redirigera vers cette page. Sera également utilisé par l'API pour connecter un utilisateur à Pydio. Vérifiez que la page contienne bien un formulaire de connexion !",
"Drupal URL" => "URL de Drupal",
"Full path to Drupal installation, either in the form of http://localhost/drupal/ or simply /drupal/" => "URL de votre installation de WP, 'http://host/drupal' ou simplement '/drupal' si sur le même hôte",
"Main page of your Drupal installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form." => "Page principale de votre installation de Drupal contenant un formulaire de connexion. Tant que la connexion n'a pas été effectué, accèder à Pydio redirigera vers cette page. Sera également utilisé par l'API pour connecter un utilisateur à Pydio. Vérifiez que la page contienne bien un formulaire de connexion !",
"Custom Auth Function" => "Fonction d'authentification personnalisée",
"User-defined function for performing real password check, necessary for REST API (including iOS client). Add this function inside the plugin cms_auth_functions.php file" => "Fonction définie par l'utilisateur pour effectuer la vérification du mot de passe. Requise pour l'API REST (incluant les clients iOS). Ajoutez cette fonction dans le fichier du plugin 'cms_auth_functions.php'",
);