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
namespace Pydio\Tests;

use Pydio\Core\Model\Context;
use Pydio\Core\Utils\Vars\VarsFilter;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Check the various plugins folders writeability
 * @package Pydio
 * @subpackage Tests
 */
class Writeability extends AbstractTest
{

    /**
     * @inheritdoc
     */
    public function __construct() { parent::__construct("Required writeable folder", "One of the following folder should be writeable and is not : "); }

    /**
     * @inheritdoc
     */
    public function doTest()
    {
        $checks = array();
        $checks[] = AJXP_CACHE_DIR;
        $checks[] = AJXP_DATA_PATH;
        $checked = array();
        $success = true;
        foreach ($checks as $check) {
            $w = false;
            $check = VarsFilter::filter($check, Context::emptyContext());
            if (!is_dir($check)) {// Check parent
                $check = dirname($check);
            }
            $w = is_writable($check);
            $checked[basename($check)] = "<b>".basename($check)."</b>:".($w?'true':'false');
            $success = $success & $w;
        }
        $this->testedParams["Writeable Folders"] = "[".implode(',<br> ', array_values($checked))."]";
        if (!$success) {
            $this->failedInfo .= implode(",", $checks);
            return FALSE;
        }
        $this->failedLevel = "info";
        $this->failedInfo = "[".implode(',<br>', array_values($checked))."]";
        return FALSE;
    }
}