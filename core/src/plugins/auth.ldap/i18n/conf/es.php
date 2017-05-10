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
"LDAP Directory" => "Directorio LDAP",
"Authentication datas are stored on the LDAP server." => "Los datos de autenticación se almacenan en el servidor LDAP.",
"LDAP URL" => "URL de LDAP",
"LDAP Server URL (IP or name)" => "URL del servidor LDAP (IP o nombre)",
"LDAP Port" => "Puerto de LDAP",
"LDAP Server Port (leave blank for default)" => "Puerto del servidor LDAP (dejar en blanco para usar predeterminado)",
"LDAP bind username" => "Usuario para LDAP bind",
"Username (uid + dn) of LDAP bind user" => "Nombre de usuario (uid + dn) del usuario para LDAP bind",
"LDAP bind password" => "Contraseña para LDAP bind",
"Password of LDAP bind user" => "Contraseña del usuario para LDAP bind",
"People DN" => "People DN",
"DN where the users are stored" => "DN donde se almacenan los usuarios",
"LDAP Filter" => "Filtro LDAP",
"Filter which users to fetch." => "Filtrar los usuarios a recuperar.",
"User attribute" => "Atributo de Usuario",
"Username attribute" => "Atributo de nombre de usuario",
"LDAP/AD Directory" => "Directorio LDAP/AD",
"Authentication datas are stored in an LDAP/AD directory." => "Los datos de autenticación se almacenan en un directorio LDAP/AD.",
"Protocol" => "Protocolo",
"Connect through ldap or ldaps" => "Conectar por ldap o ldaps",
"Groups DN" => "Groups DN",
"DN where the groups are stored. Must be used in cunjonction with a group parameter mapping, generally using the memberOf feature." => "DN donde se almacenan los grupos. Debe ser usado junto con un parámetro de mapeado de grupo, normalmente usando la función mermberOf.",
"LDAP Groups Filter" => "Filtro de Grupos LDAP",
"Filter which groups to fetch." => "Filtrar los grupos a recuperar.",
"Group attribute" => "Atributo de grupo",
"Group main attribute to be used as a label" => "El atributo principal del grupo para usarlo como etiqueta",
"LDAP attribute" => "Atributo LDAP",
"Name of the LDAP attribute to read" => "Nombre del atributo LDAP a leer",
"Mapping Type" => "Tipo de Mapeado",
"Determine the type of mapping" => "Determina el tipo de mapeado",
"Plugin parameter" => "Parámetro del Plugin",
"Name of the custom local parameter to set" => "Nombre del parámetro local a personalizar",
"Test User" => "Probar Usuario",
"Use the Test Connexion button to check if this user is correctly found in your LDAP directory." => "Usar el botón Test Connection para comprobar si el usuario se encuentra correctamente en el directorio LDAP.",
"Test Connexion" => "Probar Conexión",
"Try to connect to LDAP" => "Intenta conectar a LDAP",
"LDAP Server page size" => "Tamaño de página del servidor LDAP",
"Page size of LDAP Server" => "Tamaño de página del servidor LDAP",
"Search Users by Attribute" => "Buscar usuarios por Atributo",
"When looking for a user through autocomplete, search on a specific parameter instead of user ID" => "Cuando se busca a un usuario usando autocompletado, buscar un parámetro específico en lugar de la ID de usuario",
"Fake Member from..." => "Miembro Falso desde...",
"If there is no memberOf attribute/overlay, use this option to create additional memberOf attribute. Enter the groups attribute storing the members ids, can be generally either memberUid or member, depending on the schema." => "Si no hay atributo memberOf, usar esta opción para crear un atributo memberOf. Introducir los atributos de grupo que almacenan los ids de los miembros, puede ser generalmente  tanto memberUid como member, dependiendo del esquema.",
"Role Prefix (for memberof)" => "Prefijo del Rol (para memberOf)",
"Role prefix when you mapping memberof => roleID" => "Prefijo del rol cuando se mapea memberOf => roleID",
"Server Connection" => "Conexión al Servidor",
"Set up main connection to server. Use the button to test that your parameters are correct." => "Configurar la conexión principal al servidor. Usar el botón para comprobar que los parámetros sean correctos.",
"Users Schema" => "Esquema de Usuarios",
"These parameters will describe how the users will be loaded/filtered from the directory." => "Estos parámetros describirán como los usuarios se cargarán/filtrarán desde el directorio.",
"Groups Schema" => "Esquema de Grupos",
"These parameters will describe how groups will optionally be loaded/filtered from the directory." => "Estos parámetros describirán como los grupos se cargarán/filtrarán desde el directorio.",
"Role prefix when you mapping memberof =&gt; roleID" => "Prefijo del rol cuando se mapea memberOf =&gt; roleID",
"Attributes Mapping" => "Mapeado de Atributos",
"Use this section to automatically map some LDAP attributes to Pydio plugins parameters values." => "Usar esta sección automáticamente para mapear algunos atributos LDAP a valores de parámetros de plugins en Pydio.",
"Advanced Parameters" => "Parámetros Avanzados",
"More advanced settings for LDAP/AD" => "Más configuraciones avanzadas para LDAP/AD",
"Fake MemberOf. value of member/memberUid attribute of group" => "Falsear el valor MemberOf del atributo member/memberUid del grupo",
"value of member/memberUid attribute of group: can be user DN or user CN. Use with Fake memberOf enabled. YES use DN, otherwise CN" => "valor del atributio member/memberUid del grupo: puede ser DN de usuario o CN de usuario. Usar con Falsear memberOf activado. YES usa DN, otro valor usa CN",
"Cache User Count (hours)" => "Almacenar Contador Usuario (horas)",
"Locally cache the total number of users during X hours. Can be handy for huge directories." => "Almacenar localmente el número total de usuarios durante X horas. Puede ser útil para directorios muy extensos.",
);
