<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Utils\Vars;


defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Tools to clean inputs
 * @package Pydio\Core\Utils
 */
class BackTraceHelper
{
    const TPL_SHUTDOWN_SCHEDULER = [
          0 => ['f' => ['callRegisteredShutdown'], 'o' => "Pydio\\Core\\Controller\\ShutdownScheduler"],
          1 => ['f' => ['emitResponse', 'applyTask', 'sendBody']],
          #2 => [],
          #3 => ['f' => 'call_user_func_array'],
          #4 => ['f' => 'nextCallable']
        ];

    public static function scan($backtrace, $template){
        $i = 0;
        if(count($backtrace) < 2) return true;
        foreach ($template as $caller){
            if (isset($caller['f'])) {
                $testFuncName = false;
                foreach($caller['f'] as $funcName){
                    $testFuncName |=  ($backtrace[$i]['function'] == $funcName);
                }
                if (!$testFuncName) return false;

                if (isset($caller['o']) && !isset($backtrace[$i]['class'])) return false;
                if (isset($caller['o']) && ($backtrace[$i]['class'] != $caller['o'])) return false;
            }
            $i++;
        }
        return true;
    }
}
