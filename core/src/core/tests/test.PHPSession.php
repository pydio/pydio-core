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
defined('AJXP_EXEC') or die( 'Access not allowed');
require_once('../classes/class.AbstractTest.php');

/**
 * Check php session writeability
 * @package Pydio
 * @subpackage Tests
 */
class PHPSession extends AbstractTest
{
    public function PHPSession() { parent::AbstractTest("PHP Session", "<b>Testing configs</b>"); }
    public function doTest()
    {
        $handler = ini_get("session.save_handler");
        if ($handler != "files") {
            $this->testedParams["Session Save Path"] = "Handler is not file based";
            return TRUE;
        }
        $tmpDir = session_save_path();
        $this->testedParams["Session Save Path"] = $tmpDir;
        if ($tmpDir != "") {
            $this->testedParams["Session Save Path Writeable"] = @is_writable($tmpDir);
            if (!$this->testedParams["Session Save Path Writeable"]) {
                $this->failedLevel = "error";
                $this->failedInfo = "The temporary folder used by PHP to save the session data is either incorrect or not writeable! Please check : ".session_save_path();
                $this->failedInfo .= "<p class='suggestion'><b>Suggestion</b> : create your own temporary folder for sessions and set the session.save_path parameter in the conf/bootstrap_conf.php</p>";
                return FALSE;
            }
        } else {
            $this->failedLevel = "warning";
            $this->failedInfo = "Warning, it seems that your temporary folder used to save session data is not set. If you are encountering troubles with logging and sessions, please check session.save_path in your php.ini. Otherwise you can ignore this.";
            return FALSE;
        }
        $this->failedLevel = "info";
        return FALSE;
    }
};
