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
namespace Pydio\Core\Exception;

use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\LocaleService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class PydioException
 * @package Pydio\Core\Exception
 */
class PydioException extends \Exception
{
    private $errorCode;

    /**
     * PydioException constructor.
     * @param string $messageString
     * @param bool $messageId
     * @param null $errorCode
     */
    public function __construct($messageString, $messageId = false, $errorCode = null)
    {
        if ($messageId !== false && class_exists("ConfService")) {
            $messages = LocaleService::getMessages();
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

    /**
     * @param $mixed
     * @throws PydioException
     */
    public function errorToXml($mixed)
    {
        if ($mixed instanceof \Exception) {
            throw $this;
        } else {
            throw new PydioException($mixed);
        }
    }

    /**
     * @return bool
     */
    public function hasErrorCode(){
        return isSet($this->errorCode);
    }

    /**
     * @return null
     */
    public function getErrorCode(){
        return $this->errorCode;
    }

    /**
     * @return string
     */
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

                $message .= "#".$i . " ". (!empty($entry['file']) ? str_replace(dirname(__FILE__), '', $entry['file']) . ':' . $entry['line'] . ' - ' : '[internal function]  - ') . $func . PHP_EOL;
            }
        }

        return $message;

    }

}
