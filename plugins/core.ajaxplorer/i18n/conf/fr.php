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
"Repository Slug" => "Alias du dépôt",
"Alias"         => "Alias",
"Alias for replacing the generated unique id of the repository" => "Alias pour remplacer l'id unique de 32 caractères du dépôt.",
"File System (Standard)" => "Fichiers du serveur (standard)",
"Main container for core AjaXplorer settings (application title, sharing, webdav server config, etc...)"=>"Simple container pour l'édition des options principales d'AjaXplorer.",
"Main"      => "Principales Options",
"App Title" => "Titre de l'appli.",
"Your application title" => "Ce titre apparaîtra comme titre de la fenêtre, ainsi que sur l'écran de démarrage",
"Default Language" => "Langue",
"Default language when a user does not have set his/her own." => "Langue utilisée lorsque l'utilisateur n'a pas choisi sa langue",
"Sharing" => "Partage",
"Download Folder" => "Répertoire",
"Absolute path to the public folder where temporary download links will be created. Setting this empty will disable the sharing feature." => "Chemin absolu depuis la racine du serveur vers le répertoire dans lequel les liens publics seront créés. Si vous le laissez vide, cela désactive la fonction de partage.",
"Download URL" => "URL",
"If not inferred directly from the current ajaxplorer URI plus the public download folder name, replace the public access URL here." => "Si cette URL n'est pas logiquement déduite de l'URL d'AjaXplorer (si le répertoire est dans un virtual host par exemple), entrez l'URL ici.",
"Existing users" => "Autres utilisateurs",
"Allow the users to pick an existing user when sharing a folder" => "Autoriser les utilisateurs à voir une liste des utilisateurs existants pour partager des dossiers avec eux",
"Compression Features" => "Compression",
"Gzip Download" => "Compression Gzip",
"Gzip files on-the-fly before downloading. Disabled by default, as it's generally useful only on small files, and decreases performances on big files. This has nothing to see with the Zip Creation feature, it's just a on-the-fly compression applied on a unique file at download." => "Compression des fichiers en gzip à la volée lors du download. Il ne s'agit pas des fonctions de création ou extraction d'archive. Ceci est efficace seulement pour les petits fichiers.",
"Gzip Limit" => "Limite pour Gzip",
"If activated, a default limit should be set above when files are no more compressed." => "Si la compression gzip est activée, elle ne s'appliquera que pour des fichiers de taille inférieure à cette limite.",
"Zip Creation" => "Création d'archive",
"If you encounter problems with online zip creation or multiple files downloading, you can disable the feature." => "Si vous avez des problèmes avec la création d'archive ou le téléchargement de plusieurs fichiers en même temps, vous pouvez désactiver la fonction ici.",
"WebDAV Server" => "Serveur WebDAV",
"Enable WebDAV" => "Activer WebDAV",
"Enable the webDAV support. Please READ THE DOC to safely use this feature." => "Activation du serveur webDAV, veuillez vous réferrer à la documentation, il ne suffit généralement pas de switcher cette option sur Yes.",
"Shares URI" => "URI Partages",
"Common URI to access the shares. Please READ THE DOC to safely use this feature." => "URI de base pour accèder aux partages",
"Shares Host" => "Serveur",
"Host used in webDAV protocol. Should be detected by default. Please READ THE DOC to safely use this feature." => "Le serveur est détecté automatiquement, mais vous pouvez changer la valeur ici.",
"Digest Realm" => "Digest Realm",
"Default realm for authentication. Please READ THE DOC to safely use this feature." => "Default realm utilisé pour l'authentification.",
"Miscalleneous" => "Divers",
"Command-line Active" => "Ligne de commande",
"Use AjaXplorer framework via the command line, allowing CRONTAB jobs or background actions." => "Utiliser le framework AjaXplorer via la ligne de commande, ce qui permet de programmer des jobs CRONTAB ou des actions en tâche de fond",
"Command-line PHP" => "Commande PHP",
"On specific hosts, you may have to use a specific path to access the php command line" => "Sur certains hébergement, PHP en ligne de commande est accessible par une autre commande que 'php'",
"Filename length" => "Noms de fichiers",
"Maximum characters length of new files or folders" => "Nombre de caractères maximums pour les noms de fichiers",
"Temporary Folder" => "Répertoire temporaire",
"This is necessary only if you have errors concerning the tmp dir access or writeability : most probably, they are due to PHP SAFE MODE (should disappear in php6) or various OPEN_BASEDIR restrictions. In that case, create and set writeable a tmp folder somewhere at the root of your hosting (but above the web/ or www/ or http/ if possible!!) and enter here the full path to this folder" => "Ceci n'est nécessaire que si vous rencontrez des problèmes avec le répertoire temporaire de php, ou d'écriture... généralement, c'est du au SAFE_MODE ou OPEN_BASEDIR. Dans ce cas, créez un répertoire temporaire et vérifiez qu'il est accessible en écriture. Attention à ne pas le créer sous un répertoire accessible par le web.",
"Admin email" => "Email Admin",
"Administrator email, not used for the moment" => "Email de l'administrateur, pas vraiment utilisé pour le moment.",
"User Credentials" => "Données d'authentification",
"User" => "Utilisateur",
"User name - Can be overriden on a per-user basis (see users 'Personal Data' tab)" => "Peut-être déterminé pour chaque utilisateur dans le panneau des données personnelles",
"Password" => "Mot de Passe",
"User password - Can be overriden on a per-user basis." => "Peut-être déterminé pour chaque utilisateur dans le panneau des données personnelles",
"Session credentials" => "Session credentials",
"Try to use the current AjaXplorer user credentials for connecting. Warning, the AJXP_SESSION_SET_CREDENTIALS config must be set to true!" => "Utilisation des identifiants de la session, entrés via le formulaire de login. 'Set Session Credentials' doit être activé dans les préférences générales d'AjaXplorer.",
"User name" => "Nom d'utilisateur",
"User password" => "Mot de Passe",
"Template Options" => "Options du modèle",
"Allow to user" => "Autoriser les utilisateurs",
"Allow non-admin users to create a repository from this template." => "Autoriser les non-administrateur à créer des dépôts basés sur ce modèle.",
"Default Label" => "Libellé par défaut",
"Prefilled label for the new repository, you can use the AJXP_USER keyworkd in it." => "Libellé prérempli pour le nouveau dépôt, vous pouvez utiliser AJXP_USER.",
"Small Icon" => "Petite icône",
"16X16 Icon for representing the template" => "Icône 16X16 pour le modèle",
"Big Icon" => "Grande icône",
"Big Icon for representing the template" => "Grande icône pour le modèle",
"Filesystem Commons" => "Système de fichiers",
"Recycle Bin Folder" => "Corbeille",
"Leave empty if you do not want to use a recycle bin." => "Laissez vide si vous ne voulez pas activer la corbeille.",
"Default Rights" => "Droits par défaut",
"This right pattern (empty, r, or rw) will be applied at user creation for this repository." => "Ce droit d'accès sera appliqué à la création des utilisateurs",
"Character Encoding" => "Encodage",
"If your server does not set correctly its charset, it can be good to specify it here manually." => "Si le serveur n'est gère pas correctement l'encodage.",
"Pagination Threshold" => "Seuil de pagination",
"When a folder will contain more items than this number, display will switch to pagination mode, for better performances." => "Quand un répertoire contient plus d'un certain nombre de fichier, les résultats sont paginés pour de meilleurs performances.",
"#Items per page" => "Eléments par page",
"Once in pagination mode, number of items to display per page." => "Après passage en mode pagination, nombre affiché.",
"Default Metasources" => "Metasources par Défaut",
"Comma separated list of metastore and meta plugins, that will be automatically applied to all repositories created with this driver" => "Liste des plugins metastore et meta (sep par des virgules) appliqués par défaut lors de la création de dépôt utilisant ce driver.",
"Auth Driver Commons" => "Plugins d'Authentification",
"Transmit Clear Pass" => "Mot de passe clair",
"Whether the password will be transmitted clear or encoded between the client and the server" => "Selon que le mot de passe est encodé entre le serveur et le client. Meilleur pour la sécurité, mais si par exemple vous utilisez un auth driver qui doit utiliser ce mot de passe pour se logger à un autre serveur, probablement pas possible.",
"Auto Create User" => "Autocréation Utilisateur",
"When set to true, the user object is created automatically if the authentication succeed. Used by remote authentication systems." => "Lors de l'utilisation de système d'authentification externes, création automatique de l'objet utilisateur.",
"Login Redirect" => "Redirection",
"If set to a given URL, the login action will not trigger the display of login screen but redirect to this URL." => "Si non-vide, l'action de login va déclencher une redirection vers une autre page plutôt que l'affichage du formulaire de login.",
"Admin Login" => "Admin Login",
"For exotic auth drivers, an user ID that must be considered as admin by default." => "Pour les auth drivers externe, peut permettre de fixer un login qui sera considérer comme administrateur.",
);
?>