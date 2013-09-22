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

defined('AJXP_EXEC') or die( 'Access not allowed');
define('AJXP_METADATA_SHAREDUSER', 'AJXP_METADATA_SHAREDUSER');

define('AJXP_METADATA_SCOPE_GLOBAL', 1);
define('AJXP_METADATA_SCOPE_REPOSITORY', 2);
/**
 * Simple metadata implementation, stored in hidden files inside the
 * folders
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
interface MetaStoreProvider
{
    public function init($options);
    public function initMeta($accessDriver);

    /**
     * @abstract
     * @return bool
     */
    public function inherentMetaMove();

    /**
     * @abstract
     * @param AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param array $metaData
     * @param bool $private
     * @param int $scope
     */

    public function setMetadata($ajxpNode, $nameSpace, $metaData, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY);
    /**
     * @abstract
     * @param AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param bool $private
     * @param int $scope
     */
    public function removeMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY);

    /**
     * @abstract
     * @param AJXP_Node $ajxpNode
     * @param String $nameSpace
     * @param bool $private
     * @param int $scope
     */
    public function retrieveMetadata($ajxpNode, $nameSpace, $private = false, $scope=AJXP_METADATA_SCOPE_REPOSITORY);

    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    public function enrichNode(&$ajxpNode);

}
