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
"Client Plugin" => "Interface Client",
"Browser-based rich interface. Contains configurations for theming, custom welcome message, etc." => "Client riche pour les navigateurs standards. ",
"Main Options" => "Options Principales",
"Theme" => "Theme",
"Theme used for display" => "Theme d'affichage",
"Start Up Screen" => "Ecran de démarrage",
"Title Font Size" => "Taille du titre",
"Font sized used for the title in the start up screen" => "Taille de la police du titre",
"Custom Icon" => "Icône",
"URI to a custom image to be used as start up logo" => "Icône personnalisée pour l'écran",
"Icon Width" => "Largeur icône",
"Width of the custom image (by default 35px)" => "Largeur de l'icône pour assurer un bon placement du titre.",
"Welcome Message" => "Message d'accueil",
"An additionnal message displayed in the start up screen" => "Message supplémentaire ajouté sur l'écran d'acceuil, puis dans la boîte de login.",
"Client Session Config" => "Configuration de la session client",
"Client Timeout" => "Expiration",
"The length of the client session in SECONDS. By default, it's copying the server session length. In most PHP installation, it will be 1440, ie 24minutes. You can set this value to 0, this will make the client session 'infinite' by pinging the server at regular occasions (thus keeping the PHP session alive). This is not a recommanded setting for evident security reasons." => "Longueur de la session. Par défaut elle sera calquée sur la session PHP (généralement environ 20mn). Vous pouvez forcer le client à rendre la session infinie en utilisant la valeur -1. Ce n'est pas recommandé pour des raisons de sécurité.",
"Warning Before" => "Alerte avant",
"Number of MINUTES before the session expiration for issuing an alert to the user" => "Nombre de minutes avant d'alerter l'utilisateur que la session va se fermer.",
"Google Analytics" => "Google Analytics",
"Analytics ID" => "Identifiant GA",
"Id of your GA account, something like UE-XXXX-YY" => "Compte GA, du type UE-XXXX-YY",
"Analytics Domain" => "Domaine GA",
"Set the domain for yuor analytics reports (not mandatory!)" => "Domaine pour le rapport Google Analytics",
"Analytics Events" => "Evenements GA",
"Use Events Logging, experimental only implemented on download action in AjaXplorer" => "Utiliser les Events google analytics, implémenté seulement pour l'action download actuellement",
);
?>