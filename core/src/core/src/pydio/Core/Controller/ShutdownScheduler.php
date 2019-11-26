<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Core\Controller;

use Psr\Http\Message\ResponseInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Dav\DAVResponse;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Utils\Vars\BackTraceHelper;
use Pydio\Log\Core\Logger;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @param null|DAVResponse|ResponseInterface $responseObject
     */
    public static function setCloseHeaders(&$responseObject = null){
        if($responseObject instanceof DAVResponse){
            $responseObject->setHeader("Connection", "close");
        }else if($responseObject instanceof ResponseInterface){
            $responseObject = $responseObject->withHeader("Connection", "close");
        }else if(!headers_sent()){
            header("Connection: close\r\n");
        }
    }

    /**
     * ShutdownScheduler constructor.
     */
    public function __construct()
    {
        $this->callbacks = array();
        register_shutdown_function(array($this, 'callRegisteredShutdown'));
    }

    /**
     * @return bool
     * @throws PydioException
     */
    public function registerShutdownEvent()
    {
        $callback = func_get_args();

        if (empty($callback)) {
            throw new PydioException('No callback passed to '.__FUNCTION__.' method');
        }
        if (!is_callable($callback[0])) {
            throw new PydioException('Invalid callback ('.$callback[0].') passed to the '.__FUNCTION__.' method');
        }
        $flattenArray = array();
        $flattenArray[0] = $callback[0];
        if (is_array($callback[1])) {
            foreach($callback[1] as $argument) $flattenArray[] = $argument;
        }
        //$this->callbacks[] = $flattenArray;
        $this->stackCallback($this->callbacks, $callback[0], $callback[1]);
        return true;
    }

    /**
     * @param array $callbacks
     * @param callable $callable
     * @param array $arguments
     * @internal param array $cb
     */
    private function stackCallback(&$callbacks, callable $callable, array $arguments = []){
        // Try to detect if callback is already registered
        $found = false;
        foreach($callbacks as $index => $callback){
            $crtCallable = $callback[0];
            if($crtCallable !== $callable){
                continue;
            }
            $crtArgs = array_slice($callback, 1);
            if(count($crtArgs) !== count($arguments)){
                continue;
            }
            $argsDiffer = false;
            foreach($arguments as $id => $argument){
                $crtArgument = $crtArgs[$id];
                if($crtArgument === $argument
                    || ($argument instanceof AJXP_Node && $crtArgument instanceof AJXP_Node && $argument->getUrl() === $crtArgument->getUrl())
                    || ($argument instanceof ContextInterface && $crtArgument instanceof ContextInterface && $argument->getStringIdentifier() === $crtArgument->getStringIdentifier())
                ){
                    // Ok they are similar
                }else{
                    $argsDiffer = true;
                    break;
                }
            }
            if($argsDiffer) {
                continue;
            }
            $found = $index;
            break;
        }
        if($found === false){
            $callbacks[] = array_merge([$callable], $arguments);
        }else{
            $tmp = $callbacks[$found];
            unset($callbacks[$found]);
            $callbacks = array_values($callbacks);
            $callbacks[] = $tmp;
        }

    }

    /**
     * Trigger the schedulers
     * @param OutputInterface $cliOutput
     */
    public function callRegisteredShutdown($cliOutput = null)
    {
        session_write_close();
        ob_end_flush();
        flush();

        // test for backtrack
        $test = debug_backtrace();
        if(!BackTraceHelper::scan($test, BackTraceHelper::TPL_SHUTDOWN_SCHEDULER)){
            Logger::warning(__CLASS__, __FUNCTION__, "Malicious code suspected !!!");
            //return;
        }

        $index = 0;
        while (count($this->callbacks)) {
            $arguments = array_shift($this->callbacks);
            $callback = array_shift($arguments);
            try {
                if($cliOutput !== null){
                    $cliOutput->writeln("<comment>--> Applying Shutdown Hook: ". get_class($callback[0]) ."::".$callback[1]."</comment>");
                }
                call_user_func_array($callback, $arguments);
            } catch (PydioException $e) {
                Logger::error(__CLASS__, __FUNCTION__, array("context" => "Applying hook " . get_class($callback[0]) . "::" . $callback[1], "message" => $e->getMessage()));
            }
            $index++;
            if($index > 100000) {
                Logger::error(__CLASS__, __FUNCTION__, "Breaking ShutdownScheduler loop, seems too big (100000)");
                break;
            }
        }
    }
}
