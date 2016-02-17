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
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

require_once __DIR__ . '/../core.access/vendor/autoload.php';

use CoreAccess\Stream\Client\DAVClient;
use CoreAccess\Stream\StreamWrapper;

/**
 * AJXP_Plugin to access a webdav enabled server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class DAVAccessDriver extends fsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $client;
    protected $wrapperClassName;
    protected $urlBase;

    public function performChecks()
    {
        if(!file_exists($this->getBaseDir()."/../../core/classes/sabredav/lib/Sabre/autoload.php")){
            throw new Exception("You must download the sabredav and install it with Composer for this plugin");
        }
    }

    /*
     * @param bool $register
     * @return array|bool|void
     * Override parent to register underlying wrapper
     */
    public function detectStreamWrapper($register = false){
        //AJXP_MetaStreamWrapper::appendMetaWrapper("ajxp.dav", "CoreAccess\Stream\StreamWrapper");

        return parent::detectStreamWrapper(true);
    }

    public function initRepository()
    {
        include(__DIR__ . '/../../core/classes/guzzle/vendor/autoload.php');
        include(__DIR__ . '/../../core/classes/sabredav/vendor/autoload.php');

        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        // Params
        $secure = $this->repository->getOption("SECURE");
        $protocol = $secure ? 'https' : 'http';
        $host = $this->repository->getOption("HOST");
        $path = $this->repository->getOption("PATH");
        $username = $this->repository->getOption("USERNAME");
        $password = $this->repository->getOption("PASSWORD");

        // Connexion
        $settings = array(
            'baseUri' => $protocol . '://' . $host . $path,
            'username' => $username,
            'password' => $password
        );

        $this->client = DAVClient::factory($settings);
        $this->client->registerStreamWrapper();

        // Params
        $recycle = $this->repository->getOption("RECYCLE_BIN");

        // Config
        ConfService::setConf("PROBE_REAL_SIZE", false);
        $this->urlBase = "pydio://".$this->repository->getId();
        if ($recycle != "") {
            RecycleBinManager::init($this->urlBase, "/".$recycle);
        }
    }
}
