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
namespace Pydio\Core\Exception;

use Pydio\Core\Services\ConfService;

defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * Custom exception (legacy from php4 when there were no exceptions)
 * @package Pydio
 * @subpackage Core
 */
class PydioException extends \Exception
{
    private $errorCode;

    public function __construct($messageString, $messageId = false, $errorCode = null)
    {
        if ($messageId !== false && class_exists("ConfService")) {
            $messages = ConfService::getMessages();
            if (array_key_exists($messageId, $messages)) {
                $messageString = $messages[$messageId];
            } else {
                $messageString = $messageId;
            }
        }
        if(isSet($errorCode)){
            $this->errorCode = $errorCode;
        }
        parent::__construct($messageString);
    }

    public function errorToXml($mixed)
    {
        if ($mixed instanceof \Exception) {
            throw $this;
        } else {
            throw new PydioException($mixed);
        }
    }

    public function hasErrorCode(){
        return isSet($this->errorCode);
    }

    public function getErrorCode(){
        return $this->errorCode;
    }

    public static function buildDebugBackTrace(){

        $message = "";

        if (ConfService::getConf("SERVER_DEBUG")) {
            $stack = debug_backtrace();
            $stackLen = count($stack);
            for ($i = 1; $i < $stackLen; $i++) {
                $entry = $stack[$i];

                $func = $entry['function'] . '(';
                $argsLen = count($entry['args']);
                for ($j = 0; $j < $argsLen; $j++) {
                    $s = $entry['args'][$j];
                    if(is_string($s)){
                        $func .= $s;
                    }else if (is_object($s)){
                        $func .= get_class($s);
                    }
                    if ($j < $argsLen - 1) $func .= ', ';
                }
                $func .= ')';

                $message .= "\n". str_replace(dirname(__FILE__), '', $entry['file']) . ':' . $entry['line'] . ' - ' . $func . PHP_EOL;
            }
        }

        return $message;

    }

}
