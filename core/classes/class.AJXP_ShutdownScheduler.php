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

class AJXP_ShutdownScheduler
{
    private static $instance;

    private $callbacks; // array to store user callbacks

    /**
     * @static
     * @return AJXP_ShutdownScheduler
     */
    public static function getInstance(){
        if(self::$instance == null) self::$instance = new AJXP_ShutdownScheduler();
        return self::$instance;
    }

     public function __construct() {
         $this->callbacks = array();
         register_shutdown_function(array($this, 'callRegisteredShutdown'));
         ob_start();
     }
    public function registerShutdownEventArray() {
        $callback = func_get_args();

        if (empty($callback)) {
            throw new Exception('No callback passed to '.__FUNCTION__.' method');
        }
        if (!is_callable($callback[0])) {
            throw new Exception('Invalid callback ('.$callback[0].') passed to the '.__FUNCTION__.' method');
        }
        $flattenArray = array();
        $flattenArray[0] = $callback[0];
        if(is_array($callback[1])) {
            foreach($callback[1] as $argument) $flattenArray[] = $argument;
        }
        $this->callbacks[] = $flattenArray;
        return true;
    }
     public function registerShutdownEvent() {
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
     public function callRegisteredShutdown() {
        session_write_close();
        header("Connection: close");
        $size = ob_get_length();
        header("Content-Length: $size");
        ob_end_flush();
        flush();
        foreach ($this->callbacks as $arguments) {
            $callback = array_shift($arguments);
            call_user_func_array($callback, $arguments);
        }
     }
}
