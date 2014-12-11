<?php
/*
* Copyright 2007-2014 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
"File System (Standard)" => "Sistema de archivos (Estándar)",
"The most standard access to a filesystem located on the server." => "El acceso más estándar al sistema de archivos en el servidor.",
"Path" => "Ruta",
"Real path to the root folder on the server" => "Ruta real a la carpeta raíz en el servidor",
"Create" => "Crear",
"Create folder if it does not exists" => "Crear carpeta si no existe",
"File Creation Mask" => "Máscara de creación de archivo",
"Optionnaly apply a chmod operation. Value must be numeric, like 0777, 0644, etc." => "Opcionalmente aplicar la operación chmod. El valor debe ser numérico, ej: 0777, 0644, etc.",
"Purge Days" => "Días para purgar",
"Option to purge documents after a given number of days. This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Opción para purgar documentos después del numero de días seleccionado. Esto requiere la configuración manual de una tarea CRON. Dejar en 0 si no deseas utilizar esta característica.",
"Real Size Probing" => "Sondaje de tamaño real (Real Size Probing)",
"Use system command line to get the filesize instead of php built-in function (fixes the 2Go limitation)" => "Utilizar la linea de comandos para obtener los tamaños de archivo en vez de la función incorporada en PHP (corrige la limitación 2Go)",
"X-SendFile Active" => "X-SendFile activo",
"Delegates all download operations to the webserver using the X-SendFile header. Warning, this is an external module to install for Apache. Module is active by default in Lighttpd. Warning, you have to manually add the folders where files will be downloaded in the module configuration (XSendFilePath directive)" => "Delega todas las operaciones de descarga al servidor utilizando el encabezado X-SendFile. Advertencia, este es un modulo externo a instalar por Apache. El módulo está activo por defecto en Lighttpd. Advertencia, debes añadir manualmente las carpetas donde los archivos serán descargados en el módulo de configuración. (directiva XSendFilePath)",
"Data template" => "Esquema de datos (Data template)",
"Path to a directory on the filesystem whose content will be copied to the repository the first time it is loaded." => "Ruta al directorio en el sistema de archivos cuyo contenido será copiado al espacio de trabajo la primera vez que sea cargado.",
"Purge Days (Hard limit)" => "Días para purgar (Hard limit)",
"Option to purge documents after a given number of days (even if shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Opción para purgar documentos después de un número determinado de días (incluso si están compartidos). Esto requiere la configuración manual de una tarea CRON. Dejar en 0 si no deseas utilizar esta característica.",
"Purge Days (Soft limit)" => "Días para purgar (Soft limit)",
"Option to purge documents after a given number of days (if not shared). This require a manual set up of a CRON task. Leave to 0 if you don't wan't to use this feature." => "Opción para purgar documentos después de un número determinado de días (si no están compartidos). Esto requiere la configuración manual de una tarea CRON. Dejar en 0 si no deseas utilizar esta característica.",
"Remote Sorting" => "Ordenamiento remoto",
"Force remote sorting when in paginated mode. Warning, this can impact the performances." => "Forzar el ordenamiento remoto cuando se encuentre en modo paginado. Advertencia, esto puede afectar en el desempeño.",
"Use POSIX" => "Utilizar POSIX",
"Use php POSIX extension to read files permissions. Only works on *nix systems." => "Utilizar la extensión php POSIX para leer archivos y permisos. Solo funciona en sistemas *nix.",
"X-Accel-Redirect Active" => "X-Accel-Redirect activado",
"Delegates all download operations to nginx using the X-Accel-Redirect header. Warning, you have to add some configuration in nginx, like X-Accel-Mapping" => "Delega todas las operaciones de descargas a nginx utilizando el encabezado X-Accel-Redirect. Advertencia, debes agregar alguna configuración en nginx como X-Accel-Mapping",
);