<?php

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : configuration file
 */
/********************************************
 * CUSTOM VARIABLES HOOK
 ********************************************/
/**
 * This is a sample "hard" hook, directly included. See directly the HookDemo class
 * for more explanation.
 */
//require_once AJXP_INSTALL_PATH."/plugins/hook.demo/class.HookDemo.php";
//AJXP_Controller::registerIncludeHook("vars.filter", array("HookDemo", "filterVars"));

/*********************************************************/
/* PLUGINS DEFINITIONS
/* Drivers will define how the application will work. For 
/* each type of operation, there are multiple implementation
/* possible. Check the content of the plugins folder.
/* CONF = users and repositories definition, 
/* AUTH = users authentification mechanism,
/* LOG = logs of the application.
/*
/* By default, the three are all based on files.
/*
/* ACTIVE_PLUGINS adds other type of plugins to the application.
/* If you are developping your own plugin, do not forget to declare
/* it here.
/*********************************************************/
$PLUGINS = array(
	"CONF_DRIVER" => array(
		"NAME"		=> "serial",
		"OPTIONS"	=> array(
			"REPOSITORIES_FILEPATH"	=> "AJXP_DATA_PATH/plugins/conf.serial/repo.ser",
			"ROLES_FILEPATH"		=> "AJXP_DATA_PATH/plugins/auth.serial/roles.ser",
			"USERS_DIRPATH"			=> "AJXP_DATA_PATH/plugins/auth.serial",
			/*"CUSTOM_DATA"			=> array(
					"email"	=> "Email", 
					"country" => "Country"
				)*/
			)
	),
	"AUTH_DRIVER" => array(
		"NAME"		=> "serial",
		"OPTIONS"	=> array(
			"LOGIN_REDIRECT"		=> false,
			"USERS_FILEPATH"		=> "AJXP_DATA_PATH/plugins/auth.serial/users.ser",
			"AUTOCREATE_AJXPUSER" 	=> false, 
			"TRANSMIT_CLEAR_PASS"	=> false )
	),
    "LOG_DRIVER" => array(
         "NAME" => "text",
         "OPTIONS" => array(
             "LOG_PATH" => "AJXP_INSTALL_PATH/data/logs/",
             "LOG_FILE_NAME" => 'log_' . date('m-d-y') . '.txt',
             "LOG_CHMOD" => 0770
         )
    ),
	/*
	// ALTERNATE AUTH_DRIVER CONFIG SAMPLE
	"AUTH_DRIVER" => array(
		"NAME"		=> "remote",
		"OPTIONS"	=> array(
			"SLAVE_MODE"  => true,
			"USERS_FILEPATH" => "AJXP_INSTALL_PATH/data/plugins/auth.serial/users.ser",
			"MASTER_AUTH_FUNCTION" => "joomla_remote_auth",
			"MASTER_HOST"		=> "localhost",
			"MASTER_URI"		=> "/joomla/",
			"LOGIN_URL" => "/joomla/",  // The URL to redirect (or call) upon login (typically if one of your user type: http://yourserver/path/to/ajxp, he will get redirected to this url to login into your frontend
			"LOGOUT_URL" => "/joomla/",  // The URL to redirect upon login out (see above)
			"SECRET" => "myprivatesecret",// the same as the one you put in the WP plugin option.
			"TRANSMIT_CLEAR_PASS"   => true // Don't touch this. It's unsafe (and useless here) to transmit clear password.
		)
	),
	*/
);