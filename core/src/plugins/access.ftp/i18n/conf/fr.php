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
"FTP Server" => "Serveur FTP",
"This driver can access a remote FTP server" => "Accéder à un serveur FTP distant",
"Connexion" => "Connexion",
"Host" => "Hôte",
"Ftp Host to connect to" => "Hôte FTP auquel il faut se connecter",
"Port" => "Port",
"Ftp Host port" => "Port du serveur",
"Path" => "Chemin",
"Real path to the root folder on the server" => "Chemin du répertoire de base sur le serveur distant",
"Secure" => "Securisé",
"Whether to use ftp or ftps protocol" => "Utiliser le protocole FTP ou FTPS",
"Active" => "Actif",
"Whether to use active or passive" => "Utiliser FTP actif ou passif",
"FTP Server Tweaks" => "Spécifications FTP",
"Fix Permissions" => "Corriger les permissions",
"How to handle remote permissions to be used by PHP as local permissions. See manual." => "Considérer, par PHP, les permissions distantes comme des permissions locales. Consultez le manuel.",
"Temporary Folder" => "Répertoire temporaire",
"Temporary folder on the local server used for file uploads. For the moment, it must be placed under your ajaxplorer folder and you must create it and set it writeable by Apache." => "Répertoire local utilisé pour l'envoi des fichiers. Actuellement, il doit-être placé dans le répertoire de Pydio, vous devez le créer et autoriser Apache à y écrire.",
"Dynamic FTP" => "FTP dynamique",
"Pass Ftp data through Auth driver" => "Faire transiter les données FTP par le pilote Auth",
"In conjunction with a correctly configured auth.ftp driver, this allow to transform ajaxplorer into a simple netFtp client." => "En conjonction avec le pilote auth.ftp correctement configuré, permet de transformer Pydio en un client WebFTP.",
"Test Connexion" => "Tester la connexion",
"Test FTP connexion" => "Tester la connexion FTP",
"Create" => "Créer",
"Create folder if it does not exists" => "Créer les répertoires s'ils n'existent pas",
"User Id" => "ID de l'utilisateur",
"To fetch the user id you have to run a listing command on your ftp client (ls or dir most of the time) and take the first of the two last numbers as the user id. It can be possible that there is more than one number. If you experience errors using one id try to use another one." => "Pour obtenir l'ID de l'utilisateur, vous devez effectuer une commande de listage (généralement ls ou dir) dans votre client FTP, puis utiliser le premier des deux derniers nombres comme ID de l'utilisateur. Il est possible qu'il y ait plus d'un nombre, en cas d'erreurs avec l'un, essayez d'utiliser un autre.",
);