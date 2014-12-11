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
    "Order" => "Orden",
    "Order this plugin with other auth frontends" => "Ordenar este plugin con otros frontends de autenticación",
    "Create User" => "Crear usuario",
    "Automatically create user if it does not already exists" => "Automáticamente crea un usuario si no existe",
    "General" => "General",
    "Protocol Type" => "Tipo de protocolo",
    "Enable/disable automatically based on the protocol used" => "Habilitar/Deshabilitar automáticamente basado en el protocolo usado",
    "CAS server address" => "Dirección de Servidor CAS",
    "CAS Server" => "Servidor CAS",
    "CAS Port" => "Puerto CAS" ,
    "Port where CAS server is running on. Default: 443" => "Puerto en el que el Servidor CAS está activo. Predeterminado: 443",
    "CAS URI" => "URI de Servidor CAS" ,
    "URI for CAS service (without / at the end). Default:" => "URI para el servicio CAS (sin el '/' al final). Predeterminado:",
    "Redirect to the given URL on loggin out" => "Redireccionar a la URL definida al desconectar",
    "Modify login page" => "Modificar página de ingreso (login)" ,
    "Login page will be modified to give user a link to authenticate via CAS manually. Otherwise Pydio will redirect automatically to CAS login page." => "La página de ingreso (login) será modificada para entregar al usuario un enlace para autenticar vía CAS manualmente. Sino Pydio redireccionará automáticamente a la página de ingreso de CAS.",
    "Certificate path" => "Ruta del certificado" ,
    "Path to the ca chain that issued the cas server certificate" => "Ruta a la cadena CA que emitió el certificado del servidor CAS",
    "Debug mode" => "Modo Debug" ,
    "Debug file" => "Archivo Debug" ,
    "Set phpCAS in debug mode" => "Definir phpCAS en modo Debug" ,
    "Log to file. If null, use yyyy-mm-dd.txt" => "Registrar (log) a archivo. Si es nulo, utilizar aaaa-mm-dd.txt",
    "phpCAS mode" => "Modo phpCAS" ,
    "In mode proxy, phpCAS works as a CAS Proxy who provides Proxy ticket for others services such as SMB, IMAP." => "En modo proxy, phpCAS funciona como un proxy CAS que provee tickets proxy para otros servicios como SMB, IMAP.",
    "Client" => "Cliente" ,
    "Proxy" => "Proxy" ,
    "Proxied Service" => "Servicio con Proxy" ,
    "Proxied service who uses Proxy Ticket provided by this CAS Proxy.Ex smb://pydio.com" => "Servicio con proxy que utiliza Tickets proxy proporcionados por este Proxy CAS. Ej: smb://pydio.com",
    "PTG store mode" => "Modo de almacenamiento PTG (PTG store mode)" ,
    "Config for Proxy Granting Ticket Storage. If is file option, location for storate is session_save_path()" => "Configuración para almacenamiento de otorgamiento de tickets de proxy. Si es un archivo de opciones, la localizacion para almacenamiento es session_save_path()",
    "Install SQL Table (support only mysql)" => "Instalar tabla SQL (soporta solo MySQL)"
);
