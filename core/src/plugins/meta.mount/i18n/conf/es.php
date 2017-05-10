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
* The latest code can be found at <https://pydio.com>.
*/
$mess=array(
"FS Mount" => "Montaje de FS",
"Mount a remote location on the file system before accessing the repository" => "Montar una localización remota en el sistema de archivos antes de acceder al workspace",
"FS Type" => "Tipo de FS",
"Filesystem Type, will be used for the -t option of the mount command" => "Tipo de sistema de archivos, se usa para la opción -t del comando mount",
"Sudo" => "Sudo",
"Call the mount/umount commands through sudo, must be configured on the server" => "Llamar a los comandos mount/umount usando sudo, debe estar configurado en el servidor",
"Remote Path" => "Ruta Remota",
"Path to the remote share to mount, use //123.456.789.654/path you can use AJXP_USER" => "Ruta al intercambio remoto para montar, usar //123.456.789.654/path, puedes usar AJXP_USER",
"Mount Point" => "Punto de Montaje",
"Mount Path, use AJXP_USER" => "Ruta de Montaje, usar AJXP_USER",
"Mount Options" => "Opciones de Montaje",
"Used with the -o command option, use AJXP_USER, AJXP_PASS, AJXP_SERVER_UID, AJXP_SERVER_GID" => "Usado con la opción de comando -o, usar AJXP_USER, AJXP_PASS, AJXP_SERVER_UID, AJXP_SERVER_GID",
"Pass Password via environment instead of command line" => "Pasar la contraseña a traves del entorno en vez de la linea de comandos",
"Instead of setting password through the AJXP_PASS variable in mount options, pass it through the environment variables. Sudo file must be changed accordingly." => "En lugar de configurar una contraseña usando la variable AJXP_PASS en las opciones de montaje, pasarla usando las variables de entorno. El archivo sudo deber ser configurado correctamente.",
"Devil" => "Devil",
"Call the mount/umount commands through devil, must be configured on the server" => "Llamar a los comandos mount/umount usando devil, debe estar configurado en el servidor",
"Additional result codes to accept as success" => "Códigos adicionales aceptados como satisfactorios",
"On some setup result code 32 is often an already mounted code and we want to consider this as a success. Add comma-separated list of codes." => "En algunas configuraciones el código 32 significa que ya está montado y se desea considerar como satisfactorio. Añadir lista de códigos separados por comas.",
"Remove mount point on unmount" => "Eliminar punto de montaje al desmontar",
"Delete mount folder on unmount. Can be required for security reasons." => "Eliminar el directorio del montaje al desmontar. Puede ser necesario por motivos de seguridad.",
);
