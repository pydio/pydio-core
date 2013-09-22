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
 *
 * @package Pydio
 * @subpackage Core
 *
 */
class AJXP_ShutdownScheduler
{
    private static $instance;

    private $callbacks; // array to store user callbacks

    /**
     * @static
     * @return AJXP_ShutdownScheduler
     */
    public static function getInstance()
    {
        if(self::$instance == null) self::$instance = new AJXP_ShutdownScheduler();
        return self::$instance;
    }

     public function __construct()
     {
         $this->callbacks = array();
         register_shutdown_function(array($this, 'callRegisteredShutdown'));
         ob_start();
     }
    public function registerShutdownEventArray()
    {
        $callback = func_get_args();

        if (empty($callback)) {
            throw new Exception('No callback passed to '.__FUNCTION__.' method');
        }
        if (!is_callable($callback[0])) {
            throw new Exception('Invalid callback ('.$callback[0].') passed to the '.__FUNCTION__.' method');
        }
        $flattenArray = array();
        $flattenArray[0] = $callback[0];
        if (is_array($callback[1])) {
            foreach($callback[1] as $argument) $flattenArray[] = $argument;
        }
        $this->callbacks[] = $flattenArray;
        return true;
    }
     public function registerShutdownEvent()
     {
         $callback = func_get_args();

         if (empty($callback)) {
             throw new Exception('No callback passed to '.__FUNCTION__.' method');
         }
         if (!is_callable($callback[0])) {
             throw new Exception('Invalid callback ('.$callback[0].') passed to the '.__FUNCTION__.' method');
         }
         $this->callbacks[] = $callback;
         return true;
     }
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
         foreach ($this->callbacks as $arguments) {
             $callback = array_shift($arguments);
             try {
                 call_user_func_array($callback, $arguments);
             } catch (Exception $e) {
                 AJXP_Logger::error(__CLASS__, __FUNCTION__, array("context"=>"Applying hook ".get_class($callback[0])."::".$callback[1],  "message" => $e->getMessage()));
             }
         }
     }
}
