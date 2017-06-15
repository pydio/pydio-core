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
"LDAP Directory" => "Directoria LDAP",
"Authentication datas are stored on the LDAP server." => "Dados de Autenticação são guardados no servidor LDAP.",
"LDAP URL" => "URL LDAP",
"LDAP Server URL (IP or name)" => "URL do Servidor LDAP (IP ou nome)",
"LDAP Port" => "Porta LDAP",
"LDAP Server Port (leave blank for default)" => "Porta do Servidor LDAP (deixar em branco para usar a pré-definida)",
"LDAP bind username" => "Login bind LDAP",
"Username (uid + dn) of LDAP bind user" => "Login (uid + dn) do utilizador bind LDAP",
"LDAP bind password" => "Palavra-Chave do Bind LDAP",
"Password of LDAP bind user" => "Palavra-Chave do utilizador bind LDAP",
"People DN" => "Pessoas DN",
"DN where the users are stored" => "DN onde os utilizadores são guardados",
"LDAP Filter" => "Filtros LDAP",
"Filter which users to fetch." => "Filtro usado para procurar utilizadores.",
"User attribute" => "Atributo de Utilizador",
"Username attribute" => "Atributo de Login",
"LDAP/AD Directory" => "LDAP/AD Directory",
"Authentication datas are stored in an LDAP/AD directory." => "Authentication datas are stored in an LDAP/AD directory.",
"Protocol" => "Protocol",
"Connect through ldap or ldaps" => "Connect through ldap or ldaps",
"Groups DN" => "Groups DN",
"DN where the groups are stored. Must be used in cunjonction with a group parameter mapping, generally using the memberOf feature." => "DN where the groups are stored. Must be used in cunjonction with a group parameter mapping, generally using the memberOf feature.",
"LDAP Groups Filter" => "LDAP Groups Filter",
"Filter which groups to fetch." => "Filter which groups to fetch.",
"Group attribute" => "Group attribute",
"Group main attribute to be used as a label" => "Group main attribute to be used as a label",
"LDAP attribute" => "LDAP attribute",
"Name of the LDAP attribute to read" => "Name of the LDAP attribute to read",
"Mapping Type" => "Mapping Type",
"Determine the type of mapping" => "Determine the type of mapping",
"Plugin parameter" => "Plugin parameter",
"Name of the custom local parameter to set" => "Name of the custom local parameter to set",
"Test User" => "Test User",
"Use the Test Connexion button to check if this user is correctly found in your LDAP directory." => "Use the Test Connexion button to check if this user is correctly found in your LDAP directory.",
"Test Connexion" => "Test Connexion",
"Try to connect to LDAP" => "Try to connect to LDAP",
"LDAP Server page size" => "LDAP Server page size",
"Page size of LDAP Server" => "Page size of LDAP Server",
"Search Users by Attribute" => "Search Users by Attribute",
"When looking for a user through autocomplete, search on a specific parameter instead of user ID" => "When looking for a user through autocomplete, search on a specific parameter instead of user ID",
"Fake Member from..." => "Fake Member from...",
"If there is no memberOf attribute/overlay, use this option to create additional memberOf attribute. Enter the groups attribute storing the members ids, can be generally either memberUid or member, depending on the schema." => "If there is no memberOf attribute/overlay, use this option to create additional memberOf attribute. Enter the groups attribute storing the members ids, can be generally either memberUid or member, depending on the schema.",
"Role Prefix (for memberof)" => "Role Prefix (for memberof)",
"Role prefix when you mapping memberof => roleID" => "Role prefix when you mapping memberof => roleID",
"Server Connection" => "Server Connection",
"Set up main connection to server. Use the button to test that your parameters are correct." => "Set up main connection to server. Use the button to test that your parameters are correct.",
"Users Schema" => "Users Schema",
"These parameters will describe how the users will be loaded/filtered from the directory." => "These parameters will describe how the users will be loaded/filtered from the directory.",
"Groups Schema" => "Groups Schema",
"These parameters will describe how groups will optionally be loaded/filtered from the directory." => "These parameters will describe how groups will optionally be loaded/filtered from the directory.",
"Role prefix when you mapping memberof =&gt; roleID" => "Role prefix when you mapping memberof =&gt; roleID",
"Attributes Mapping" => "Attributes Mapping",
"Use this section to automatically map some LDAP attributes to Pydio plugins parameters values." => "Use this section to automatically map some LDAP attributes to Pydio plugins parameters values.",
"Advanced Parameters" => "Advanced Parameters",
"More advanced settings for LDAP/AD" => "More advanced settings for LDAP/AD",
"Fake MemberOf. value of member/memberUid attribute of group" => "Fake MemberOf. value of member/memberUid attribute of group",
"value of member/memberUid attribute of group: can be user DN or user CN. Use with Fake memberOf enabled. YES use DN, otherwise CN" => "value of member/memberUid attribute of group: can be user DN or user CN. Use with Fake memberOf enabled. YES use DN, otherwise CN",
"Cache User Count (hours)" => "Cache User Count (hours)",
"Locally cache the total number of users during X hours. Can be handy for huge directories." => "Locally cache the total number of users during X hours. Can be handy for huge directories.",
);