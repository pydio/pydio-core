<?php
/*
* Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
* The latest code can be found at <https://pydio.com>.
*/
$mess=array(
"FS Mount" => "Partição FS",
"Mount a remote location on the file system before accessing the repository" => "Montar uma partição remota no sistema de ficheiros local antes de aceder à Área de Trabalho",
"FS Type" => "FS Type",
"Filesystem Type, will be used for the -t option of the mount command" => "Filesystem Type, will be used for the -t option of the mount command",
"Sudo" => "Sudo",
"Call the mount/umount commands through sudo, must be configured on the server" => "Call the mount/umount commands through sudo, must be configured on the server",
"Remote Path" => "Remote Path",
"Path to the remote share to mount, use //123.456.789.654/path you can use AJXP_USER" => "Path to the remote share to mount, use //123.456.789.654/path you can use AJXP_USER",
"Mount Point" => "Mount Point",
"Mount Path, use AJXP_USER" => "Mount Path, use AJXP_USER",
"Mount Options" => "Mount Options",
"Used with the -o command option, use AJXP_USER, AJXP_PASS, AJXP_SERVER_UID, AJXP_SERVER_GID" => "Used with the -o command option, use AJXP_USER, AJXP_PASS, AJXP_SERVER_UID, AJXP_SERVER_GID",
"Pass Password via environment instead of command line" => "Pass Password via environment instead of command line",
"Instead of setting password through the AJXP_PASS variable in mount options, pass it through the environment variables. Sudo file must be changed accordingly." => "Instead of setting password through the AJXP_PASS variable in mount options, pass it through the environment variables. Sudo file must be changed accordingly.",
"Devil" => "Devil",
"Call the mount/umount commands through devil, must be configured on the server" => "Call the mount/umount commands through devil, must be configured on the server",
"Additional result codes to accept as success" => "Additional result codes to accept as success",
"On some setup result code 32 is often an already mounted code and we want to consider this as a success. Add comma-separated list of codes." => "On some setup result code 32 is often an already mounted code and we want to consider this as a success. Add comma-separated list of codes.",
"Remove mount point on unmount" => "Remove mount point on unmount",
"Delete mount folder on unmount. Can be required for security reasons." => "Delete mount folder on unmount. Can be required for security reasons.",
);