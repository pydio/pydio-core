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
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
interface AJXP_MessageExchanger
{
    /**
     * @abstract
     * @param $channelName
     * @param $clientId
     * @return mixed
     */
    public function suscribeToChannel($channelName, $clientId);

    /**
     * @abstract
     * @param $channelName
     * @param $clientId
     * @return mixed
     */
    public function unsuscribeFromChannel($channelName, $clientId);

    /**
     * @abstract
     * @param $channelName
     * @param $clientId
     * @param $userId
     * @param $userGroup
     * @return mixed
     */
    public function consumeInstantChannel($channelName, $clientId, $userId, $userGroup);

    /**
     * @abstract
     * @param $channelName
     * @param $filter
     * @return mixed
     */
    public function consumeWorkerChannel($channelName, $filter = null);


    /**
     * @abstract
     * @param string $channel Name of the persistant queue to create
     * @param object $message Message to send
     * @return mixed
     */
    public function publishWorkerMessage($channel, $message);

    /**
     * @abstract
     * @param $channel
     * @param $message
     * @return mixed
     */
    public function publishInstantMessage($channel, $message);


}
