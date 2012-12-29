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

defined('AJXP_EXEC') or die('Access not allowed');
 
interface AJXP_MessageExchanger{

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
    function unsuscribeFromChannel($channelName, $clientId);

    /**
     * @abstract
     * @param $channelName
     * @param $clientId
     * @return mixed
     */
    public function consumeInstantChannel($channelName, $clientId);

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