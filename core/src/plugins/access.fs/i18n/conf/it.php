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
"File System (Standard)" => "File System (Standard)",
"The most standard access to a filesystem located on the server." => "L'accesso più comune ad un filesystem su un server.",
"Path" => "Destinazione",
"Real path to the root folder on the server" => "Destinazione reale della cartella principale su un server",
"Create" => "Crea",
"Create folder if it does not exists" => "Crea una cartella se non esiste",
"File Creation Mask" => "Maschera di Creazione FIle",
"Optionnaly apply a chmod operation. Value must be numeric, like 0777, 0644, etc." => "Applica operazione chmod opzionalmente. Il valore deve essere numerico, come 0777, 0644, ecc.",
"Purge Days" => "Giorni validit&agrave;",
"Option to purge documents after a given number of days. This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Opzione per eliminare documenti dopo un dato numero di giorni. Richiede l'impostazione manuale di un task CRON. Lascia 0 se non vuoi utilizzare questa funzionalità.",
"Real Size Probing" => "Verifica Dimensione Reale",
"Use system command line to get the filesize instead of php built-in function (fixes the 2Go limitation)" => "Utilizza la linea di comando del sistema per ottenere la dimensione del file invece che la funzione php (corregge la limitazione di 2Go)",
"X-SendFile Active" => "X-SendFile Attivo",
"Delegates all download operations to the webserver using the X-SendFile header. Warning, this is an external module to install for Apache. Module is active by default in Lighttpd. Warning, you have to manually add the folders where files will be downloaded in the module configuration (XSendFilePath directive)" => "Delegates all download operations to the webserver using the X-SendFile header. Warning, this is an external module to install for Apache. Module is active by default in Lighttpd. Warning, you have to manually add the folders where files will be downloaded in the module configuration (XSendFilePath directive)",
"Data template" => "Template dati",
"Path to a directory on the filesystem whose content will be copied to the repository the first time it is loaded." => "Destinazione sul filesystem, della cartella i cui contenuti saranno copiati nello spazio di lavoro la prima volta che viene caricato.",
"Purge Days (Hard limit)" => "Giorni Validit&agrave; (Limite Hard)",
"Option to purge documents after a given number of days (even if shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Opzione per eliminare automaticamente i documenti dopo il numero di giorni specificato (anche se condivisi). Richiede la configurazione manuale di un CRON job. Lascia 0 se non itendi usare questa funzione.",
"Purge Days (Soft limit)" => "Giorni validit&agrave; (Limite Soft)",
"Option to purge documents after a given number of days (if not shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Opzione per eliminare automaticamente i documenti dopo il numero di giorni specificato (se NON condivisi). Richiede la configurazione manuale di un CRON job. Lascia 0 se non itendi usare questa funzione.",
"Remote Sorting" => "Ordinamento Remoto",
"Force remote sorting when in paginated mode. Warning, this can impact the performances." => "Forza l'ordinamento remoto in modalit&agrave; paginata. Attenzione: pu&ograve; influenzare le performance.",
"Use POSIX" => "Usa POSIX",
"Use php POSIX extension to read files permissions. Only works on *nix systems." => "Usa l'estensione POSIX per PHP per leggere i permessi sui file. Funziona solo sui sistemi *nix.",
"X-Accel-Redirect Active" => "X-Accel-Redirect Attivo",
"Delegates all download operations to nginx using the X-Accel-Redirect header. Warning, you have to add some configuration in nginx, like X-Accel-Mapping" => "Delega tutte le operazioni di download a nginx, usando l'header X-Accel-Redirect. Attenzione: devi aggiungere manualmente qualche configurazione a nginx, come l'X-Accel Mapping.",
);