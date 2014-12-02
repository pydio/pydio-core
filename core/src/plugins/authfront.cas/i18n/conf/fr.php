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
    "Order" => "Commande",
    "Order this plugin with other auth frontends" => "Commander ce plugin avec d'autres interfaces d'authentification",
    "Create User" => "Créer un utilisateur",
    "Automatically create user if it does not already exists" => "Créer automatiquement l'utilisateur s'il n'existe pas déjà",
    "General" =>"Général" ,
    "Protocol Type" =>"Type de protocole",
    "Enable/disable automatically based on the protocol used" => "Activer / désactiver automatiquement en fonction du protocole utilisé",
    "CAS server address" => "Adresse du serveur CAS",
    "CAS Server" => "Serveur CAS",
    "CAS Port" => "Port CAS" ,
    "Port where CAS server is running on. Default: 443" => "Port utilisé par le serveur CAS. Par défaut : 443",
    "CAS URI" => "URI CAS" ,
    "URI for CAS service (without / at the end). Default:" => "URI du service CAS (sans / à la fin). Par défaut : ",
    "Redirect to the given URL on loggin out" => "Rediriger vers l'URL à la déconnexion",
    "Modify login page" => "Modifier la page de connexion",
    "Login page will be modified to give user a link to authenticate via CAS manually. Otherwise Pydio will redirect automatically to CAS login page." => "La page de connexion sera modifiée afin de fournir aux utilisateur un lien pour se connecter manuellement via CAS. Sinon, Pydio redirigera automatiquement vers  la page de connexion CAS.",
    "Certificate path" => "Chemin du certificat" ,
    "Path to the ca chain that issued the cas server certificate" => "Chemin de la chaîne ca qui a émis le certificat du serveur CAS",
    "Debug mode" => "Mode débug",
    "Debug file" => "Fichier de débug",
    "Set phpCAS in debug mode" => "Définir phpCAS en mode débug",
    "Log to file. If null, use yyyy-mm-dd.txt" => "Journaliser dans le fichier. Si nul, utilisera yyyy-mm-dd.txt",
    "phpCAS mode" => "Mode phpCAS",
    "In mode proxy, phpCAS works as a CAS Proxy who provides Proxy ticket for others services such as SMB, IMAP." => "En mode proxy, phpCAS fonctionnera comme un proxy CAS fournissant un ticket proxy pour les autres services tels SMB, IMAP.",
    "Client" => "Client" ,
    "Proxy" => "Proxy" ,
    "Proxied Service" => "Service Proxifié" ,
    "Proxied service who uses Proxy Ticket provided by this CAS Proxy.Ex smb://pydio.com" => "Service proxifié utilisant un ticket proxy fourni par ce proxy CAS. Ex : smb://pydio.com",
    "PTG store mode" => "Stockage du mode PTG" ,
    "Config for Proxy Granting Ticket Storage. If is file option, location for storate is session_save_path()" => "Configuration pour Proxy Granting Ticket Storage. Si un fichier est utilisé, l'emplacement du stockage sera session_save_path()",
    "Install SQL Table (support only mysql)" => "Installer la table SQL (supporte uniquement mysql)"
);