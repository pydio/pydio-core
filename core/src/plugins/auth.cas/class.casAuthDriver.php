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
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once 'CAS.php';

/**
 * @package info.ajaxplorer.plugins
 * AJXP_Plugin to authenticate users against CAS Single sign-on mechanism
 */
class casAuthDriver extends serialAuthDriver
{
  private $cas_server;
  private $cas_port;
  private $cas_uri;

  function init($options)
  {
    parent::init($options);
    $this->cas_server = $this->getOption("CAS_SERVER");
    $this->cas_port = $this->getOption("CAS_PORT");
    $this->cas_uri = $this->getOption("CAS_URI");
    phpCAS::client(CAS_VERSION_1_0, $this->cas_server, $this->cas_port, $this->cas_uri, false);
    phpCAS::setNoCasServerValidation();
  }

  function usersEditable()
  {
    return false;
  }

  function passwordsEditable()
  {
    return false;
  }

  function preLogUser($sessionId)
  {
    if ($_GET['get_action'] == "logout")
    {
      phpCAS::logout();
      return;
    }
    phpCAS::forceAuthentication();
    $cas_user = phpCAS::getUser();
    
    if($this->userExists($cas_user)){
      AuthService::logUser($cas_user, "", true);
    }
  }

  function getLogoutRedirect()
  {
    $_SESSION = array();
    session_destroy();
    return phpCAS::getServerLogoutURL();
  }
}
