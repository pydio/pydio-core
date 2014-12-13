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
"FS Mount" => "Dateisystem einbinden",
"Mount a remote location on the file system before accessing the repository" => "Dateisystem einbinden, bevor auf die Arbeitsumgebung zugegriffen wird",
"FS Type" => "Art des Dateisystems",
"Filesystem Type, will be used for the -t option of the mount command" => "Art des Dateisystems, die beim Mount-Befehl mit dem Parameter -t angegeben wird.",
"Sudo" => "Sudo",
"Call the mount/umount commands through sudo, must be configured on the server" => "Ruft den mount/umount Befehl mit sudo auf. (Muss auf dem Server konfiguriert sein)",
"Remote Path" => "Netzwerkpfad zur Freigabe",
"Path to the remote share to mount, use //123.456.789.654/path you can use AJXP_USER" => "Pfad zur Netzwerkfreigabe, die eingebunden werden soll. (z.B. //123.456.789.654/path ) Die Variable AJXP_USER steht hier zur Verfügung.",
"Mount Point" => "Mountpunkt",
"Mount Path, use AJXP_USER" => "Ordner, auf den die Freigabe gemountet wird (benutzbare Variablen: AJXP_USER)",
"Mount Options" => "Einhängoptionen",
"Used with the -o command option, use AJXP_USER, AJXP_PASS, AJXP_SERVER_UID, AJXP_SERVER_GID" => "Wird mit dem Parameter -o verwendet (benutzbare Variablen: AJXP_USER, AJXP_PASS, AJXP_SERVER_UID, AJXP_SERVER_GID)",
"Pass Password via environment instead of command line" => "Passwort über Umgebungsvariable statt Kommandozeile",
"Instead of setting password through the AJXP_PASS variable in mount options, pass it through the environment variables. Sudo file must be changed accordingly." => "Statt das Passwort durch die AJXP_PASS-Variable an die Mount-Optionen anzuhängen wird es über eine Umgebungsvariable gesetzt. Sudo-Datei muss entsprechend angepasst werden.",
);
