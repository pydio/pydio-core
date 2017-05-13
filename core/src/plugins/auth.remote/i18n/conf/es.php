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
"Remote authentication" => "Autenticación remota",
"Authentication is done remotely (useful in CMS system)." => "La autenticación se realiza de forma remota (útil para CMS)",
"Login URL" => "URL de Inicio",
"When not in slave mode, AJXP calls the given URL as URL?name=XXX&amp;pass=XXX&amp;key=XXX. Else it redirect to the given URL" => "Cuando no está en modo esclavo, AJXP llama a la URL dada como URL?name=XXX&amp;pass=XXX&amp;key=XXX. En otro caso redirecciona a la URL data.",
"Logout URL" => "URL de Desconexión",
"Redirect to the given URL on loggin out" => "Redireccionar a la URL cuando se desconecta",
"Secret key" => "Clave secreta",
"This key must only be known by remote end" => "Esta clave solo debe ser conocida por el extremo remoto",
"Users" => "Usuarios",
"The users list" => "Lista de usuarios",
"Master Auth Function" => "Función de Autenticación Maestra",
"User-defined function for performing real password check, necessary for REST API (including iOS client)" => "Función definida por el usuario para realizar la comprobación de contraseña, necesrio para REST API (incluyendo cliente iOS)",
"Master Host" => "Host Maestro",
"Host used to negociated the master authentication, if not set will be detected" => "Host usado para negociar la autenticación maestra, si no se configura será detectado",
"Master Base URI" => "URI Base Maestra",
"URI to access the base of the CMS installation. Used by the master auth function, this page must contain the login form!" => "URI para acceder a la base de la instalación CMS. Usado por la función de autenticación maestra, ¡esta pagina tiene que contener un formulario de inicio de sesión!",
"Auth Form ID" => "ID Formulario de Autenticación",
"The HTML ID of the form tag displayed for login on the page defined previously. Not necessary for WP, login-form by default for Joomla, and user-login-form for Drupal" => "La ID HTML del formulario mostrado para inicio de sesión en la página definida. No se necesita para WP, login-form por defecto para Joomla y user-login-form para Drupal",
"CMS Type" => "Tipo de CMS",
"Choose a predefined CMS or define your custom values" => "Elegir CMS predefinido o definir valores personalizados",
"Local Prefix" => "Prefijo Local",
"The users created with this prefix in their identifier will be stored and handled in the local filesystem. This can be handy for managing the temporary users." => "Los usuarios creados con este prefijo en su identificador se almacenaran en el sistema de archivos local. Esto es útil para administrar usuarios temporales.",
"Roles Map" => "Mapa de Roles",
"Define a map of key/values for automatically mapping roles from the CMS to Pydio." => "Definir un mapa de clave/valor para mapear automaticamente los roles de CMS a Pydio.",
"Wordpress URL" => "URL de Wordpress",
"URL of your WP installation, either http://host/path or simply /path if it's on the same host" => "URL de la instalación WP, es válido tanto http://host/path como /path si está en el mismo host",
"Login URI" => "URI de Inicio",
"Exit Action" => "Acción de Desconexión",
"Choose the action performed when the user wants to quit Pydio : either trigger a Joomla! logout, or simply go back to the main page." => "Elegir la acción realizada cuando el usuario quiere salir de Pydio: se puede activar una desconexión de Joomla! o simplemente ir a la página principal.",
"Joomla! URL" => "URL de Joomla!",
"Full path to Joomla! installation, either in the form of http://localhost/joomla/ or simply /joomla/" => "Ruta completa a Joomla!, es valido tanto http://localhost/joomla como /joomla/",
"Home node" => "Nodo de inicio",
"Main page of your Joomla! installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form!" => "Página principal de Joomla! que contiene el formulario de inicio de sesión. Si no se ha iniciado sesión, Pydio redirecciona a esta página. También se usa para las llamadas a la API desde Pydio. ¡Asegurate de que contiene un formulario de inicio de sesión!",
"Drupal URL" => "URL de Drupal",
"Full path to Drupal installation, either in the form of http://localhost/drupal/ or simply /drupal/" => "Ruta completa a Drupal, es valido tanto http://localhost/drupal como /drupal/",
"Main page of your Drupal installation that contains a login form. When not logged, accessing Pydio will redirect to this page. It's also used for the API calls for logging in a user from within Pydio. Make sure it does contain a login form." => "Página principal de Drupal que contiene el formulario de inicio de sesión. Si no se ha iniciado sesión, Pydio redirecciona a esta página. También se usa para las llamadas a la API desde Pydio. ¡Asegurate de que contiene un formulario de inicio de sesión!",
"Custom Auth Function" => "Función de Autenticación Personalizada",
"User-defined function for performing real password check, necessary for REST API (including iOS client). Add this function inside the plugin cms_auth_functions.php file" => "Función definida por el usuario para realizar la comprobación de contraseña, necesrio para REST API (incluyendo cliente iOS). Añadir esta función al archivo cms_auth_functions.php del plugin",
"Custom" => "Personalizado",
"Back to main page" => "Página Principal",
"Logout" => "Desconexión",
);
