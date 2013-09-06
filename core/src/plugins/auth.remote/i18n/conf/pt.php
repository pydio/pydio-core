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
"Remote authentication" => "Autenticação Remota",
"Authentication is done remotely (useful in CMS system)." => "A autenticação é feita remotamente (útil para um sistema CMS).",
"Authentication mode" => "Modo de autenticação",
"If set, the remote end calls us to register upon login, else, we will be calling the remote end when login is required" => "Caso activado, o servidor remoto envia um pedido de registo ao iniciar sessão, caso contrario, iremos nós enviar um pedido de login ao servidor remoto quando requerido",
"Login URL" => "URL de Inicio Sessão",
"When not in slave mode, AJXP calls the given URL as URL?name=XXX&amp;pass=XXX&amp;key=XXX. Else it redirect to the given URL" => "Quando não se encontra em modo escravo, os pedidos do AJXP terão um URL do género URL?name=XXX&amp;pass=XXX&amp;key=XXX. Caso contrario, será redireccionando para um URL Especifico",
"Logout URL" => "URL de Termino de Sessão",
"Redirect to the given URL on loggin out" => "Redirecionar para o seguinte URL ao terminar sessão",
"Secret key" => "Chave Secreta",
"This key must only be known by remote end" => "Isto só deve ser conhecido pelo servidor remoto",
"Users" => "Utilizadores",
"The users list" => "Lista de Utilizadores",
"Master Auth Function" => "Função Mestre de Auth",
"User-defined function for performing real password check, necessary for REST API (including iOS client)" => "Valor de função definido pelo utilizador para realizar uma verificação de uma palavra-chave real, necessário para a API REST (incluindo o Cliente de iOS)",
"Master Host" => "Servidor Principal",
"Host used to negociated the master authentication, if not set will be detected" => "O Anfitrião irá tentar negociar a autenticação, caso não seja definido nada",
"Master Base URI" => "URI Base Mestre",
"URI to access the base of the CMS installation. Used by the master auth function, this page must contain the login form!" => "URI para aceder à base da instalação CMS. Usado pela Função Auth Mestre, esta página tem de conter um formulário de Autenticação!",
"Auth Form ID" => "Formulário de ID de Auth",
"The HTML ID of the form tag displayed for login on the page defined previously. Not necessary for WP, login-form by default for Joomla, and user-login-form for Drupal" => "O ID HTML do formulário será mostrado na página definida anteriormente. Não é necessário para WP, os formulários de login usados por pré definição no Joomla e nos formulários de autenticação usados pelo Drupal",
);
