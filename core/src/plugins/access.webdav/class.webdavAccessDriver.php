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

use CoreAccess\Stream\Client\DAVClient;
use CoreAccess\Stream\StreamWrapper;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * AJXP_Plugin to access a webdav enabled server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class webdavAccessDriver extends fsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $client;
    protected $wrapperClassName;
    protected $urlBase;

    /*
     * @param bool $register
     * @return array|bool|void
     * Override parent to register underlying wrapper
     */
    public function detectStreamWrapper($register = false){
        return parent::detectStreamWrapper(true);
    }

    /**
     * Repository Initialization
     *
     */
    public function initRepository()
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }

        // Params
        $host = $this->repository->getOption("HOST");
        $path = $this->repository->getOption("PATH");

        $hostParts = parse_url($host);

        // Connexion
        $settings = array(
            'baseUri' => $this->getSanitizedUrl($hostParts) . "/" . trim($path, '/'),
        );

        $this->client = new DAVClient($settings);
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

    /**
     * Sanitize a URL removin all unwanted / trailing slashes
     *
     * @param array url_parts
     * return string url
     */
    public function getSanitizedUrl($arr) {
        $credentials = join(':', array_filter([$arr['user'], $arr['pass']]));
        $hostname = join(':', array_filter([$arr['host'], $arr['port']]));
        $domain = join('@', array_filter([$credentials, $hostname]));
        return $arr['scheme'] . '://' . join('/', array_filter([$domain, $arr['path']]));
    }


}
