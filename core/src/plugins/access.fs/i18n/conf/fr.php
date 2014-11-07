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
"Create folder if it does not exists" => "Créer le répertoire s'il n'existe pas, notamment utile avec AJXP_USER.",
"File Creation Mask" => "Masque de création",
"Optionnaly apply a chmod operation. Value must be numeric, like 0777, 0644, etc." => "Optionnelemen définir les droits d'accès. Valeur numérique, de type 0777, 0644, ...",
"Purge Days" => "Purge après... (jours)",
"Option to purge documents after a given number of days. This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Purger tous les documents après un certain nombre de jours. Nécessite la mise en place manuelle d'une tâche CRON. Laisser à 0 pour ne pas utiliser.",
"Real Size Probing" => "Taille réelle",
"Use system command line to get the filesize instead of php built-in function (fixes the 2Go limitation)" => "Pour contourner la limitation à 2Go, utilise un appel système pour récupérer la taille des fichiers.",
"X-SendFile Active" => "X-Sendfile Actif",
"Delegates all download operations to the webserver using the X-SendFile header. Warning, this is an external module to install for Apache. Module is active by default in Lighttpd. Warning, you have to manually add the folders where files will be downloaded in the module configuration (XSendFilePath directive)" => "Déléguer l'ensemble des opérations de téléchargement au serveur web en utilisant l'entête X-SendFile. Attention, il s'agit d'un module externe pour Apache. Le module est actif par défaut pour Lighthttpd. Attention, il faut définir, dans la configuration du module, les dossiers où les fichiers seront téléchargés (Directive XSendFilePath).",
"Data template" => "Données modèles",
"Path to a directory on the filesystem whose content will be copied to the repository the first time it is loaded." => "Chemin vers un répertoire sur le système de fichier dont le contenu va être copié dans le dépôt à la première utilisation.",
"Purge Days (Hard limit)" => "Purge après (limite stricte)",
"Option to purge documents after a given number of days (even if shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Option pour purger les documents après un nombre de jours défini (même en cas de partage). Nécessite la mise en place manuelle d'une tâche 'CRON'. Laisser à 0 pour ne pas utiliser.",
"Purge Days (Soft limit)" => "Purge après (limite douce)",
"Option to purge documents after a given number of days (if not shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Option pour purger les documents après un nombre de jours défini (s'ils ne sont pas partagés). Nécessite la mise en place manuelle d'une tâche 'CRON'. Laisser à 0 pour ne pas utiliser.",
"Remote Sorting" => "Tri distant",
"Force remote sorting when in paginated mode. Warning, this can impact the performances." => "Force le tri distant en mode paginé. Impacte les performances !",
"Use POSIX" => "Utiliser POSIX",
"Use php POSIX extension to read files permissions. Only works on *nix systems." => "Utiliser l'extension PHP POSIX pour lire les permissions de fichiers. Uniquement pour les systèmes *nix.",
"X-Accel-Redirect Active" => "Activer X-Accel-Redirect",
"Delegates all download operations to nginx using the X-Accel-Redirect header. Warning, you have to add some configuration in nginx, like X-Accel-Mapping" => "Déléguer l'ensemble des opérations de téléchargement à nginx en utilisant l'entête X-SendFile. Attention, il faut ajouter une configuration à nginx, par exemple X-Accel-Mapping",
);