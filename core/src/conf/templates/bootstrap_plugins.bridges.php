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
    "AUTH_DRIVER" => array(
           "NAME"		=> "remote",
           "OPTIONS"	=> array(
               "SLAVE_MODE"  => true,
               "USERS_FILEPATH" => "AJXP_DATA_PATH/plugins/auth.serial/users.ser",
               "MASTER_AUTH_FUNCTION" => "joomla_remote_auth",
               "MASTER_HOST"		=> "localhost",
               "MASTER_URI"		=> "/joomla/",
               "LOGIN_URL" => "/joomla/",  // The URL to redirect (or call) upon login (typically if one of your user type: http://yourserver/path/to/ajxp, he will get redirected to this url to login into your frontend
               "LOGOUT_URL" => "/joomla/",  // The URL to redirect upon login out (see above)
               "SECRET" => "myprivatesecret",// the same as the one you put in the WP plugin option.
               "TRANSMIT_CLEAR_PASS"   => true // Don't touch this. It's unsafe (and useless here) to transmit clear password.
           )
       ),
    /*
       // Same for Drupal 7.X
       "AUTH_DRIVER"  => array(
           "NAME" => "remote",
           "OPTIONS" => array(
               "SLAVE_MODE" => true,
               "USERS_FILEPATH" => "AJXP_INSTALL_PATH/plugins/auth.serial/users.ser",
               "LOGIN_URL" => "/drupal/",
               "LOGOUT_URL" => "/drupal/?q=user/logout",
               "MASTER_AUTH_FUNCTION" => "drupal_remote_auth",
               "MASTER_HOST" => "192.168.0.10",
               "MASTER_URI" => "/drupal/",
               "MASTER_AUTH_FORM_ID" => "user-login-form",
               "SECRET" => "my_own_private_Drupal_key",
               "TRANSMIT_CLEAR_PASS" => true
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
