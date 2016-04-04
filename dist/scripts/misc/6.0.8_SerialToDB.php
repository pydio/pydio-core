<?php
/*
 * Copyright 2016 Michael Hafen
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
 * Description : Script to migrate from auth.serial and conf.serial to database
 *               For MySQL!
 *               just set database paramaters in $sql_param
 */
if (php_sapi_name() !== "cli") {
    die("This program is ment to be run on the command line, you are not allowed to access this page");
}

include_once("base.conf.php");

$sql_param = array( "SQL_DRIVER"=>array(
   'driver' => 'mysql',
   'host' => 'localhost',
   'username' => 'TheUser',
   'password' => 'ThePassword',
   'database' => 'TheDatabase'
) );

print "Starting migration\n";

$pServ = AJXP_PluginsService::getInstance();

ConfService::init();
$confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);
$pServ->loadPluginsRegistry(AJXP_INSTALL_PATH."/plugins", $confPlugin);
ConfService::start();
$confStorageDriver = ConfService::getConfStorageImpl();
require_once($confStorageDriver->getUserClassFileName());
$tmpConf = '';

$authDriver = ConfService::getAuthDriverImpl();
$tmpAuth = '';

AJXP_PluginsService::getInstance()->initActivePlugins();

if ( $confStorageDriver->getName() == 'serial' ) {
  print "Migrating core.conf from serial to sql\n";
  $repos = $confStorageDriver->listRepositories();
  $roles = $confStorageDriver->listRoles();
  $groups = $confStorageDriver->getChildrenGroups();

  $tmpConf = $pServ->softLoad( 'conf.sql' , $sql_param );

  print "Installing sql tables\n";
  $tmpConf->installSQLTables( $sql_param );

  print "Migrating Repositories";
  foreach ( $repos as $repoId => $repoObj ) {
    $tmpConf->saveRepository( $repoObj );
    print ".";
  }
  print "\n";

  print "Migrating Roles\n";
  // need to munge roles because core.auth/class.CoreAuthLoader forces auth.sql
  //  CASE_SENSITIVE to false - roles will need to be lowercase
  $newRoles = array();
  foreach ( $roles as $roleId => $roleObj ) {
    if ( strpos($roleId,"AJXP_USR_") === 0 ) {
      $new_id = 'AJXP_USR' . strtolower(substr($roleId,8));
      $new_role = new AJXP_Role($new_id);
      $new_role = $roleObj->override( $new_role );
      $newRoles[ $new_id ] = $new_role;
    }
  }
  $tmpConf->saveRoles( $newRoles );

  print "Migrating Groups";
  foreach ( $groups as $path => $label ) {
    $tmpConf->createGroup( $path, $label );
    print ".";
  }
  print "\n";

  print "Migrating options of core plugins\n";
  $plugins = array( 'core.ajaxplorer', 'core.notifications', 'gui.ajax', 'core.mailer', 'mailer.phpmailer-lite', 'core.log', 'core.mq' );
  foreach ( $plugins as $plugId ) {
    $options = array();
    $confStorageDriver->_loadPluginConfig( $plugId, $options );
    if ( !empty($options) ) {
      if ( $plugId == 'core.notifications' ) {
        $options['UNIQUE_FEED_INSTANCE'] = 'feed.sql';
        $options['instance_name'] = 'feed.sql';
        $options['group_switch_value'] = 'feed.sql';
        $options['SQL_DRIVER'] = array(
          "core_driver" => "core",
          "group_switch_value" => "core"
        );
      }
      if ( $plugId == 'core.log' ) {
        $options['UNIQUE_PLUGIN_INSTANCE'] = 'log.sql';
        $options['instance_name'] = 'log.sql';
        $options['group_switch_value'] = 'log.sql';
        $options['SQL_DRIVER'] = array(
          "core_driver" => "core",
          "group_switch_value" => "core"
        );
      }
      if ( $plugId == 'core.mq' ) {
        $options['UNIQUE_MS_INSTANCE'] = 'mq.sql';
        $options['instance_name'] = 'mq.sql';
        $options['group_switch_value'] = 'mq.sql';
        $options['SQL_DRIVER'] = array(
          "core_driver" => "core",
          "group_switch_value" => "core"
        );
      }

      $tmpConf->_savePluginConfig( $plugId, $options );
    }
  }
}

if (AuthService::usersEnabled() && $authDriver->getName() == 'serial' ) {
  print "Migrating core.auth from serial to sql\n";
  $tmpAuth = $pServ->softLoad( 'auth.sql' , $sql_param );

  print "Installing sql tables\n";
  $tmpAuth->installSQLTables( $sql_param );
  // Need this for createUser with passwords
  $tmpAuth->options['TRANSMIT_CLEAR_PASS'] = "false";

  if ( !empty($tmpConf) ) require_once($tmpConf->getUserClassFileName());

  print "Migrating users with rights, prefs, bookmarks, and binaries\n";
  $users = $authDriver->listUsers();
  foreach ( $users as $login => $pass ) {
    if ( $login == 'ajxp.admin.users' && is_array($pass) ) { continue; }
    $tmpAuth->createUser( $login, $pass );
    if ( !empty($tmpConf) ) {
      $theUser = new AJXP_SerialUser( $login );
      $theUser->load();
      $newUser = new AJXP_SqlUser( $login, $tmpConf );

      // load $newUser rights, roles, prefs, bookmarks, etc. from $theUser
      foreach ( $theUser->rights as $key => $rightObj ) {  // includes roles
        if ( !empty($rightObj) ) {
          // SqlUser doesn't implement children_pointer
          if ( $key == 'ajxp.children_pointer' ) continue;

          // need to munge roles because core.auth/class.CoreAuthLoader forces
          //  auth.sql CASE_SENSITIVE to false - roles will need to be lowercase
          if ( $key == 'ajxp.roles' && !empty($rightObj["AJXP_USR_/".$login]) ) {
            $new_id = 'AJXP_USR_/' . strtolower($login);
            $rightObj[$new_id] = $rightObj['AJXP_USR_/'.$login];
            unset( $rightObj['AJXP_USR_/'.$login] );

            $newUser->rights[$key] = $rightObj;
          }
          else {
            $newUser->rights[$key] = $rightObj;
          }
        }
      }
      $newUser->setProfile( $theUser->getProfile() );
      $newUser->setGroupPath( $theUser->getGroupPath() );
      if ( $theUser->isAdmin() === true ) $newUser->setAdmin(true);
      if ( $theUser->hasParent() ) $newUser->setParent( $theUser->getParent() );

      $newUser->save();

      if ( !empty($theUser->roles['AJXP_USR_/'.$login]) ) {
        $roleObj = $theUser->roles['AJXP_USR_/'.$login];
        $new_id = 'AJXP_USR_/' . strtolower($login);
        $new_role = new AJXP_Role($new_id);
        $new_role = $roleObj->override( $new_role );

        $tmpConf->updateRole( $new_role );
      }

      foreach ( $theUser->prefs as $name => $value ) {
	// history/last_repository is usually fetched with getArrayPref
	//  Which SqlUser doesn't overload, and is broken.
	if ( $name == 'history' && is_array($value) ) { continue; }
        $newUser->setPref( $name, $value );
      }

      foreach ( $theUser->bookmarks as $repoId => $paths ) {
        foreach ( $paths as $mark ) {
          $newUser->addBookmark( $mark['PATH'], $mark['TITLE'], $repoId );
        }
      }

      // conf.serial/binaries/users/[username]/ *
      $plug_dir = AJXP_DATA_PATH.DIRECTORY_SEPARATOR."plugins".DIRECTORY_SEPARATOR.$confStorageDriver->getId();
      $simple_store_base_path = $plug_dir ."/binaries";
      $contexts = array();
      $dh = @opendir( $simple_store_base_path );
      if ( $dh !== false ) {
        while ( false !== ($filename = readdir($dh)) ) {
          if ( strpos($filename,'.') === 0 ) continue;
          switch ($filename) {
            case 'users': $contexts['USER'] = $filename; break;
            case 'repos': $contexts['REPO'] = $filename; break;
            case 'roles': $contexts['ROLE'] = $filename; break;
            case 'plugins': $contexts['PLUGIN'] = $filename; break;
          }
        }
        closedir( $dh );
      }

      foreach ( $contexts as $con => $dir ) {
        $path = $simple_store_base_path . "/" . $dir;

        $dh = opendir( $path );
        while ( false !== ($context_name = readdir($dh)) ) {
          if ( strpos($context_name,'.') === 0 ) continue;
          $theContext = array( $con => $context_name );
          $ch = opendir( $path . "/" . $context_name );
          while ( false !== ($filename = readdir($ch)) ) {
            if ( strpos($filename,'.') === 0 ) continue;
            $tmpConf->saveBinary( $theContext, $path ."/". $context_name ."/". $filename, $filename );
          }
          closedir( $ch );
        }
        closedir( $dh );
      }
    }
    print ".";
  }
  print "\n";
}

print "Writing core settings\n";
$boot = $pServ->softLoad( 'boot.conf' , $sql_param );

$conf_core = array();
$boot->_loadPluginConfig( 'core.conf', $conf_core );
$conf_core['UNIQUE_INSTANCE_CONFIG'] = array(
    'instance_name' => 'conf.sql',
    'group_switch_value' => 'conf.sql',
    'SQL_DRIVER' => array(
      'core_driver' => 'core',
      'group_switch_value' => 'core'
    )
  );
$conf_core['DIBI_PRECONFIGURATION'] = array(
    'mysql_username' => $sql_param['SQL_DRIVER']['username'],
    'mysql_use_mysqli' => true,
    'mysql_password' => $sql_param['SQL_DRIVER']['password'],
    'mysql_host' => $sql_param['SQL_DRIVER']['host'],
    'mysql_driver' => 'mysql',
    'mysql_database' => $sql_param['SQL_DRIVER']['database'],
    'group_switch_value' => 'mysql'
  );

$auth_core = array();
$boot->_loadPluginConfig( 'core.auth', $auth_core );
$auth_core['MASTER_INSTANCE_CONFIG'] = array(
    'instance_name' => 'auth.sql',
    'group_switch_value' => 'auth.sql',
    'SQL_DRIVER' => array(
      'core_driver' => 'core',
      'group_switch_value' => 'core'
    )
  );


$boot->_savePluginConfig('core.conf', $conf_core );
$boot->_savePluginConfig('core.auth', $auth_core );

// reset conf with new settings for (meta.syncable) calls below
$pServ = AJXP_PluginsService::getInstance();
$confPlugin = ConfService::getInstance()->confPluginSoftLoad($pServ);

// conf.sql and auth.sql done already and mq.sql doesn't work
print "Installing sql tables for a few more plugins\n";
$sqlPlugs = array("feed.sql", "log.sql", "meta.syncable" ); //, "mq.sql");
foreach ($sqlPlugs as $plugId) {
  $plug = $pServ->softLoad( $plugId , $sql_param ); // some need sql_param for init
  $plug->installSQLTables($sql_param);
}

// finally, clear the plugin cache
print "Clearing plugins cache files\n";
AJXP_PluginsService::clearPluginsCache();

print "Done!\n";
