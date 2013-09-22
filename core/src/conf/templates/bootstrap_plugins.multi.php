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
 *
 * This is the main configuration file for configuring the basic plugins the application
 * needs to run properly : an Authentication plugin, a Configuration plugin, and a Logger plugin.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');
/********************************************
 * CUSTOM VARIABLES HOOK
 ********************************************/
/**
 * This is a sample "hard" hook, directly included. See directly the PluginSkeleton class
 * for more explanation.
 */
//require_once AJXP_INSTALL_PATH."/plugins/action.skeleton/class.PluginSkeleton.php";
//AJXP_Controller::registerIncludeHook("vars.filter", array("PluginSkeleton", "filterVars"));

/*********************************************************
 * PLUGINS DEFINITIONS
 * Drivers will define how the application will work. For
 * each type of operation, there are multiple implementation
 * possible. Check the content of the plugins folder.
 * CONF = users and repositories definition,
 * AUTH = users authentification mechanism,
 * LOG = logs of the application.
 *
 * This template shows how to configure the auth.remote plugin
 * for joomla or Drupal
 *
*********************************************************/
$PLUGINS = array(
    "CONF_DRIVER" => array(
           "NAME"		=> "serial",
           "OPTIONS"	=> array(
               "REPOSITORIES_FILEPATH"	=> "AJXP_DATA_PATH/plugins/conf.serial/repo.ser",
               "ROLES_FILEPATH"		=> "AJXP_DATA_PATH/plugins/auth.serial/roles.ser",
               "USERS_DIRPATH"			=> "AJXP_DATA_PATH/plugins/auth.serial",
               "FAST_CHECKS"		    => false,
               "CUSTOM_DATA"			=> array(
                       "email"	    => "Email",
                       "country"   => "Country",
                       "USER_QUOTA"=> "Quota"
                   )
               )
       ),

    /**
     * THIS CONFIGURATION WILL USE AN LDAP SERVER AS MASTER, AND A LOCAL BASE FOR CREATING TMP USERS
     */
    "AUTH_DRIVER" => array(
        "NAME"  => "multi",
        "OPTIONS" => array(
            "MODE"  => "MASTER_SLAVE",
            "MASTER_DRIVER" => "ldap",
            "USER_BASE_DRIVER" => "serial",
            "TRANSMIT_CLEAR_PASS" => true,
            "DRIVERS"  => array(
                "ldap" => array(
                    "NAME"   => "ldap",
                    "OPTIONS"   => array(
                       "LDAP_URL" => "SERVER_HOST",
                       "LDAP_PORT" => 389,
                       "LDAP_USER" => "cn=admin,dc=domain,dc=ext",
                       "LDAP_PASSWORD" => "SERVER_PASSWORD",
                       "LDAP_DN" => "ou=People,dc=domain,dc=ext",
                       "LDAP_FILTER" => "(objectClass=account)",
                       "LDAP_USERATTR" => "uid"
                    )
                ),
                "serial" => array(
                       "NAME"		=> "serial",
                       "OPTIONS"	=> array(
                           "LOGIN_REDIRECT"		=> false,
                           "USERS_FILEPATH"		=> "AJXP_DATA_PATH/plugins/auth.serial/users.ser",
                           "AUTOCREATE_AJXPUSER" 	=> false,
                        "FAST_CHECKS"		    => false
                    )
                   )
            )
        )
    ),

     /*
     * HERE, WOULD ALLOW TO LOG FROM THE LOCAL SERIAL FILES, OR AUTHENTICATING AGAINST A PREDEFINED FTP SERVER.
     * THE REPOSITORY "dynamic_ftp" SHOULD BE DEFINED INSIDE bootstrap_repositories.php
     * WITH THE CORRECT FTP CONNEXION DATA, AND THE CORE APPLICATION CONFIG "Set Credentials in Session"
     * SHOULD BE SET TO TRUE.
    "AUTH_DRIVER" => array(
        "NAME"      => "multi",
        "OPTIONS"   => array(
            "MASTER_DRIVER"         => "serial",
            "TRANSMIT_CLEAR_PASS"	=> true,
            "USER_ID_SEPARATOR"     => "_-_",
            "DRIVERS" => array(
                "serial" => array(
                        "LABEL"     => "Local",
                        "NAME"		=> "serial",
                        "OPTIONS"	=> array(
                            "LOGIN_REDIRECT"		=> false,
                            "USERS_FILEPATH"		=> "AJXP_DATA_PATH/plugins/auth.serial/users.ser",
                            "AUTOCREATE_AJXPUSER" 	=> false,
                            "TRANSMIT_CLEAR_PASS"	=> false )
                    ),
                "ftp"   => array(
                    "LABEL"     => "Remote FTP",
                    "NAME"		=> "ftp",
                    "OPTIONS"	=> array(
                        "LOGIN_REDIRECT"		=> false,
                        "REPOSITORY_ID"		    => "dynamic_ftp",
                        "ADMIN_USER"		    => "admin",
                        "FTP_LOGIN_SCREEN"      => false,
                        "AUTOCREATE_AJXPUSER" 	=> true,
                        "TRANSMIT_CLEAR_PASS"	=> true,
                    )
                )
            )
        )
    ),
    */

    "LOG_DRIVER" => array(
         "NAME" => "text",
         "OPTIONS" => array(
             "LOG_PATH" => (defined("AJXP_FORCE_LOGPATH")?AJXP_FORCE_LOGPATH:"AJXP_INSTALL_PATH/data/logs/"),
             "LOG_FILE_NAME" => 'log_' . date('m-d-y') . '.txt',
             "LOG_CHMOD" => 0770
         )
    ),

);
