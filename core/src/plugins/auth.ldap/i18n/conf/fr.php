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
"LDAP Directory" => "Annuaire LDAP",
"Authentication datas are stored on the LDAP server." => "Les données d'authentification sont stockées dans un serveur LDAP",
"LDAP URL" => "URL LDAP",
"LDAP Server URL (IP or name)" => "URL du serveur LDAP (IP ou nom)",
"LDAP Port" => "Port LDAP",
"LDAP Server Port (leave blank for default)" => "Port du serveur LDAP (laisser vide par défaut)",
"LDAP bind username" => "Nom d'utilisateur pour l'authentification LDAP",
"Username (uid + dn) of LDAP bind user" => "Nom d'utilisateur (uid + dn) pour l'utilisateur d'authentification LDAP",
"LDAP bind password" => "Mot de passe pour l'authentification LDAP",
"Password of LDAP bind user" => "Mot de passe de l'utilisateur d'authentification LDAP",
"People DN" => "DN des utilisateurs",
"DN where the users are stored" => "DN où les utilisateurs sont stockés",
"LDAP Filter" => "Filtre LDAP",
"Filter which users to fetch." => "Filtre les utilisateurs à chercher.",
"User attribute" => "Attribut utilisateur",
"Username attribute" => "Attribut nom d'utilisateur",
"LDAP/AD Directory" => "Annuaire LDAP / AD",
"Authentication datas are stored in an LDAP/AD directory." => "Les données d'authentification sont stockées dans un annuaire LDAP / AD.",
"Protocol" => "Protocole",
"Connect through ldap or ldaps" => "Connexion via ldap ou ldaps",
"Groups DN" => "DN des groupes",
"DN where the groups are stored. Must be used in cunjonction with a group parameter mapping, generally using the memberOf feature." => "DN où les groupes sont stockés. A utiliser en conjontion avec un mappage des paramètres de groupe, le plus souvent en utilisant la fonctionnalité memberOf.",
"LDAP Groups Filter" => "Filtre de groupes LDAP",
"Filter which groups to fetch." => "Filtre les groupes à rechercher.",
"Group attribute" => "Attribut de groupe",
"Group main attribute to be used as a label" => "Attribut de groupe à utiliser en tant que libellé",
"LDAP attribute" => "Attribut LDAP",
"Name of the LDAP attribute to read" => "Nom de l'attribut LDAP à lire",
"Mapping Type" => "Type de mappage",
"Determine the type of mapping" => "Déterminer le type de mappage",
"Plugin parameter" => "Paramètres du plugin",
"Name of the custom local parameter to set" => "Nom du paramètre personnalisé à définir",
"Test User" => "Tester l'utilisateur",
"Use the Test Connexion button to check if this user is correctly found in your LDAP directory." => "Utiliser le bouton 'Tester la connexion' pour vérifier la présence de cet utilisateur dans l'annuaire LDAP.",
"Test Connexion" => "Tester la connexion",
"Try to connect to LDAP" => "Essayer de se connecter à LDAP",
"LDAP Server page size" => "Pagination des résultats",
"Page size of LDAP Server" => "Nombre de résultats pour les requêtes au serveur LDAP",
"Search Users by Attribute" => "Chercher les utilisateurs par attribut",
"When looking for a user through autocomplete, search on a specific parameter instead of user ID" => "Lors de la recherche d'utilisateur par auto-complétion, chercher sur un attribut spécifique plutôt que sur l'identifiant",
"Fake Member from..." => "Emulation de 'Member of'...",
"If there is no memberOf attribute/overlay, use this option to create additional memberOf attribute. Enter the groups attribute storing the members ids, can be generally either memberUid or member, depending on the schema." => "Si l'annuaire n'a pas d'overlay 'memberOf', utiliser cette option pour l'émuler. Entrer l'attribut de group contenant les id des membres, généralement 'memberUid' ou 'member', dépend du schéma",
"Role Prefix (for memberof)" => "Préfix de rôle(pour memberof)",
"Role prefix when you mapping memberof => roleID" => "Ajouter un prefixe lorsque vous mappez memberof => roleID",
"Server Connection" => "Connexion au serveur",
"Set up main connection to server. Use the button to test that your parameters are correct." => "Connexion au serveur. Utilisez le bouton de test pour vérifier que vos paramêtres sont corrects.",
"Users Schema" => "Schema des utilisateurs",
"These parameters will describe how the users will be loaded/filtered from the directory." => "Paramètres déterminant comment les utilisateurs sont chargés/filtrés depuis l'annuaire.",
"Groups Schema" => "Schema des groupes",
"These parameters will describe how groups will optionally be loaded/filtered from the directory." => "Paramètres déterminant comment les groupes sont chargés/filtrés depuis l'annuaire.",
"Role prefix when you mapping memberof =&gt; roleID" => "Ajouter un prefixe lorsque vous mappez memberof =&gt; roleID",
"Attributes Mapping" => "Mapping d'attributs",
"Use this section to automatically map some LDAP attributes to Pydio plugins parameters values." => "Utiliser cette section pour importer automatiquement des attributs ldap vers des paramètres Pydio.",
"Advanced Parameters" => "Paramètres avancés",
"More advanced settings for LDAP/AD" => "Paramètres avancés pour la connexion à l'annuaire LDAP/AD.",
"Fake MemberOf. value of member/memberUid attribute of group" => "Fake MemberOf. value of member/memberUid attribute of group",
"value of member/memberUid attribute of group: can be user DN or user CN. Use with Fake memberOf enabled. YES use DN, otherwise CN" => "Value of member/memberUid attribute of group: can be user DN or user CN. Use with Fake memberOf enabled. YES use DN, otherwise CN",
"Cache User Count (hours)" => "Cacher le compte des utilisateurs (hours)",
"Locally cache the total number of users during X hours. Can be handy for huge directories." => "Cache le nombre total d'utilisateur localement. Important pour les larges annuaires.",
);