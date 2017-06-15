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

use Pydio\Core\Services\ConfService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Test upload tmp dir
 * @package Pydio
 * @subpackage Tests
 */
class Upload extends AbstractTest
{
    /**
     * Upload constructor.
     */
    public function __construct() { parent::__construct("Upload particularities", "<b>Testing configs</b>"); }

    /**
     * @return bool
     */
    public function doTest()
    {
        $tmpDir = ini_get("upload_tmp_dir");
        if (!$tmpDir) $tmpDir = realpath(sys_get_temp_dir());
        if (ConfService::getGlobalConf("AJXP_TMP_DIR") != "") {
            $tmpDir = ConfService::getGlobalConf("AJXP_TMP_DIR");
        }
        if (defined("AJXP_TMP_DIR") && AJXP_TMP_DIR !="") {
            $tmpDir = AJXP_TMP_DIR;
        }
        $this->testedParams["Upload Tmp Dir Writeable"] = @is_writable($tmpDir);
        $this->testedParams["PHP Upload Max Size"] = $this->returnBytes(ini_get("upload_max_filesize"));
        $this->testedParams["PHP Post Max Size"] = $this->returnBytes(ini_get("post_max_size"));
        foreach ($this->testedParams as $paramName => $paramValue) {
            $this->failedInfo .= "\n$paramName=$paramValue";
        }
        if (!$this->testedParams["Upload Tmp Dir Writeable"]) {
            $this->failedLevel = "error";
            $this->failedInfo = "The temporary folder used by PHP to upload files is either incorrect or not writeable! Upload will not work. Please check : ".ini_get("upload_tmp_dir");
            $this->failedInfo .= "<p class='suggestion'><b>Suggestion</b> : Set the AJXP_TMP_DIR parameter in the <i>conf/bootstrap_conf.php</i> file</p>";
            return FALSE;
        }

        $this->failedLevel = "info";
        return FALSE;
    }
}