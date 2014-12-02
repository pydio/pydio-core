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
 */

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Monitor filesystem using Python
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class FSMonitoringManager extends AJXP_AbstractMetaSource
{
    private $repoBase;

    /**
     * @param AbstractAccessDriver $accessDriver
     */
    public function initMeta($accessDriver)
    {
        parent::initMeta($accessDriver);
        $repo = $accessDriver->repository;
        $this->repoBase = $repo->getOption("PATH");
    }

    public function beforePathChange(AJXP_Node $node){
        $this->informWatcher("path_change", $node->getPath());
    }

    public function beforeChange(AJXP_Node $node){
        $this->informWatcher("content_change", $node->getPath());
    }

    public function beforeCreate(AJXP_Node $node){
        $this->informWatcher("create", $node->getPath());
    }

    protected function informWatcher($action, $path)
    {
        $cmd = "python ".$this->getBaseDir()."/framework_watch.py --action=$action --path=". escapeshellarg($path);
        AJXP_Controller::runCommandInBackground($cmd, $this->getBaseDir()."/cmd.out");
    }

}