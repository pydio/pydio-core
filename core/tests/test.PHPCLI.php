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
defined('AJXP_EXEC') or die( 'Access not allowed');
require_once('../classes/class.AbstractTest.php');

/**
 * @package info.ajaxplorer.test
 * Check current PHP Version
 */
class PHPCLI extends AbstractTest
{
    function PHPCLI() { parent::AbstractTest("PHP Command Line", "Testing PHP command line (default is php)"); }
    function doTest() 
    {
        $defaultCli = "php";
        $token = md5(time());
        $windows = (PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows");
        if(!is_writable(AJXP_CACHE_DIR) || ($windows && !function_exists("popen")) || (!$windows && !function_exists("exec"))){
            $this->testedParams["Command Line Available"] = "No";
            $this->failedLevel = "warning";
            $this->failedInfo = "Php command line not detected (cache directory not writeable), this is NOT BLOCKING, but enabling it could allow to send some long tasks in background. If you do not have the ability to tweak your server, you can safely ignore this warning.";
            return FALSE;
        }
        $logDir = AJXP_CACHE_DIR."/cmd_outputs";
        if(!is_dir($logDir)) mkdir($logDir, 755);
        $logFile = $logDir."/".$token.".out";

        $testScript = AJXP_CACHE_DIR."/cli_test.php";
        file_put_contents($testScript, "<?php file_put_contents('".AJXP_CACHE_DIR.DIRECTORY_SEPARATOR."cli_result.php', 'cli'); ?>");

        $cmd = $defaultCli." ".AJXP_CACHE_DIR.DIRECTORY_SEPARATOR."cli_test.php";

        if ($windows){
            $tmpBat = implode(DIRECTORY_SEPARATOR, array(AJXP_INSTALL_PATH, "data","tmp", md5(time()).".bat"));
            $cmd .= " > ".$logFile;
            $cmd .= "\n DEL $tmpBat";
            file_put_contents($tmpBat, $cmd);
            pclose(popen("start /b ".$tmpBat, 'r'));
        }else{
            new UnixProcess($cmd, $logFile);
        }

        sleep(1);
        $availability = true;
        if(is_file(AJXP_CACHE_DIR."/cli_result.php")){
            $this->testedParams["Command Line Available"] = "Yes";
            unlink(AJXP_CACHE_DIR."/cli_result.php");
            $this->failedInfo = "Php command line detected, this will allow to send some tasks in background!";
        }else{
            if(is_file($logFile)){
                $log = file_get_contents($logFile);
            }
            $this->testedParams["Command Line Available"] = "No : $log";
            $this->failedLevel = "warning";
            $this->failedInfo = "Php command line not detected, this is NOT BLOCKING, but enabling it could allow to send some long tasks in background. If you do not have the ability to tweak your server, you can safely ignore this warning.";
            $availability = false;
        }
        unlink(AJXP_CACHE_DIR."/cli_test.php");

        return $availability;
    }
};

?>