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
"File System (Standard)" => "Fichiers locaux (Standard)",
"The most standard access to a filesystem located on the server." => "Driver le plus courant : accès à un répertoire local situé sur le serveur où est installé Pydio",
"Path" => "Chemin",
"Real path to the root folder on the server" => "Chemin absolu sur le serveur du répertoire de base. Utilisez AJXP_USER pour remplacer automatiquement avec le login du user actuel.",
"Create" => "Création",
"Create folder if it does not exists" => "Création automatique du répertoire s'il n'existe pas, notamment utile avec AJXP_USER.",
"File Creation Mask" => "Masque de création",
"Optionnaly apply a chmod operation. Value must be numeric, like 0777, 0644, etc." => "Droits d'accès par défaut lors de la création de fichiers. Valeur numérique, de type 0777, 0644, etc.",
"Purge Days" => "Purge après...",
"Option to purge documents after a given number of days. This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Purger tous les documents après un certain nombre de jours. Ceci nécessite la mise en place d'un CRON, laisser à 0 pour ne pas utiliser cette feature.",
"Real Size Probing" => "Taille Réelle",
"Use system command line to get the filesize instead of php built-in function (fixes the 2Go limitation)" => "Pour contourner la limitation à 2Go, utilise un appel système pour récupérer la taille des fichiers. Peut entrer en conflit avec des limitations du php (shell_exec).",
"X-SendFile Active" => "X-Sendfile Actif",
"Delegates all download operations to the webserver using the X-SendFile header. Warning, this is an external module to install for Apache. Module is active by default in Lighttpd. Warning, you have to manually add the folders where files will be downloaded in the module configuration (XSendFilePath directive)" => "Déléguer les opérations de téléchargement au serveur web grâce au module X-SendFile. Attention, il est packagé par défaut dans Lighttpd mais doit être téléchargé et ajouté manuellement dans Apache. Il faut aussi configurer manuellement les chemins autorisés pour télécharger des fichiers, voir la directive XSendFilePath.",
"Data template" => "Données préchargées",
"Path to a directory on the filesystem whose content will be copied to the repository the first time it is loaded." => "Chemin vers un répertoire sur le filesystem dont le contenu va être copié dans le dépôt à la première connexion.",
"Purge Days (Hard limit)" => "Purge Days (Hard limit)",
"Option to purge documents after a given number of days (even if shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Option to purge documents after a given number of days (even if shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature.",
"Purge Days (Soft limit)" => "Purge Days (Soft limit)",
"Option to purge documents after a given number of days (if not shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Option to purge documents after a given number of days (if not shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature.",
"Remote Sorting" => "Remote Sorting",
"Force remote sorting when in paginated mode. Warning, this can impact the performances." => "Force remote sorting when in paginated mode. Warning, this can impact the performances.",
"Use POSIX" => "Use POSIX",
"Use php POSIX extension to read files permissions. Only works on *nix systems." => "Use php POSIX extension to read files permissions. Only works on *nix systems.",
"X-Accel-Redirect Active" => "X-Accel-Redirect Active",
"Delegates all download operations to nginx using the X-Accel-Redirect header. Warning, you have to add some configuration in nginx, like X-Accel-Mapping" => "Delegates all download operations to nginx using the X-Accel-Redirect header. Warning, you have to add some configuration in nginx, like X-Accel-Mapping",
);