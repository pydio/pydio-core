<?php
/*
 * Copyright 2007-2016 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
namespace Pydio\Core\Controller;

use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');
/**
 *
 * Registry for callbacks that must be triggered after the script is finished.
 *
 * @package Pydio
 * @subpackage Core
 *
 */
class ShutdownScheduler
{
    private static $instance;

    private $callbacks; // array to store user callbacks

    /**
     * @static
     * @return ShutdownScheduler
     */
    public static function getInstance()
    {
        if(self::$instance == null) self::$instance = new ShutdownScheduler();
        return self::$instance;
    }

    /**
     * ShutdownScheduler constructor.
     */
    public function __construct()
    {
        $this->callbacks = array();
        register_shutdown_function(array($this, 'callRegisteredShutdown'));
        ob_start();
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function registerShutdownEventArray()
    {
        $callback = func_get_args();

        if (empty($callback)) {
            throw new \Exception('No callback passed to '.__FUNCTION__.' method');
        }
        if (!is_callable($callback[0])) {
            throw new \Exception('Invalid callback ('.$callback[0].') passed to the '.__FUNCTION__.' method');
        }
        $flattenArray = array();
        $flattenArray[0] = $callback[0];
        if (is_array($callback[1])) {
            foreach($callback[1] as $argument) $flattenArray[] = $argument;
        }
        $this->callbacks[] = $flattenArray;
        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function registerShutdownEvent()
    {
        $callback = func_get_args();

        if (empty($callback)) {
            throw new \Exception('No callback passed to '.__FUNCTION__.' method');
        }
        if (!is_callable($callback[0])) {
            throw new \Exception('Invalid callback ('.$callback[0].') passed to the '.__FUNCTION__.' method');
        }
        $this->callbacks[] = $callback;
        return true;
    }

    /**
     * Trigger the schedulers
     */
    public function callRegisteredShutdown()
    {
        session_write_close();
        if (!headers_sent()) {
            $size = ob_get_length();
            header("Connection: close\r\n");
            //header("Content-Encoding: none\r\n");
            header("Content-Length: $size");
        }
        ob_end_flush();
        flush();
        $index = 0;
        while (count($this->callbacks)) {
            $arguments = array_shift($this->callbacks);
            $callback = array_shift($arguments);
            try {
                call_user_func_array($callback, $arguments);
            } catch (\Exception $e) {
                Logger::error(__CLASS__, __FUNCTION__, array("context" => "Applying hook " . get_class($callback[0]) . "::" . $callback[1], "message" => $e->getMessage()));
            }
            $index++;
            if($index > 200) {
                Logger::error(__CLASS__, __FUNCTION__, "Breaking ShutdownScheduler loop, seems too big (200)");
                break;
            }
        }
    }
}
