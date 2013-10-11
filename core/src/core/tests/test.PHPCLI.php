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
 * Check current PHP Version
 * @package AjaXplorer
 * @subpackage Tests
 */
class PHPCLI extends AbstractTest
{
    public function PHPCLI() { parent::AbstractTest("PHP Command Line", "Testing PHP command line (default is php)"); }
    public function doTest()
    {
        if (!is_writable(AJXP_CACHE_DIR)) {
            $this->testedParams["Command Line Available"] = "No";
            $this->failedLevel = "warning";
            $this->failedInfo = "Php command line not detected (cache directory not writeable), this is NOT BLOCKING, but enabling it could allow to send some long tasks in background. If you do not have the ability to tweak your server, you can safely ignore this warning.";
            return FALSE;
        }
        $windows = (PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows");

        $sModeExecDir = ini_get("safe_mode_exec_dir") ;
        $safeEnabled = ini_get("safe_mode") || !empty($sModeExecDir);

        $disabled_functions=explode(',',ini_get('disable_functions'));
        $fName = ($windows ? "popen" : "exec");
        $notFoundFunction = in_array($fName, $disabled_functions) || !function_exists($fName) || !is_callable($fName);

        $comEnabled = class_exists("COM");
        $useCOM = false;

        if ( ( $safeEnabled ||  $notFoundFunction )) {
            if ($comEnabled) {
                $useCOM = true;
            } else {
                $this->testedParams["Command Line Available"] = "No";
                $this->failedLevel = "warning";
                $this->failedInfo = "Php command line not detected (there seem to be some safe_mode or a-like restriction), this is NOT BLOCKING, but enabling it could allow to send some long tasks in background. If you do not have the ability to tweak your server, you can safely ignore this warning.";
                return FALSE;
            }
        }

        $defaultCli = ConfService::getCoreConf("CLI_PHP");
        if($defaultCli == null) $defaultCli = "php";

        $token = md5(time());
        $robustCacheDir = str_replace("/", DIRECTORY_SEPARATOR, AJXP_CACHE_DIR);
        $logDir = $robustCacheDir.DIRECTORY_SEPARATOR."cmd_outputs";
        if(!is_dir($logDir)) mkdir($logDir, 0755);
        $logFile = $logDir."/".$token.".out";

        $testScript = AJXP_CACHE_DIR."/cli_test.php";
        file_put_contents($testScript, "<?php file_put_contents('".$robustCacheDir.DIRECTORY_SEPARATOR."cli_result.php', 'cli'); ?>");

        $cmd = $defaultCli." ". $robustCacheDir .DIRECTORY_SEPARATOR."cli_test.php";

        if ($windows) {
            /* Next 2 lines modified by rmeske: Need to wrap the folder and file paths in double quotes.  */
            $cmd = $defaultCli." ". chr(34).$robustCacheDir .DIRECTORY_SEPARATOR."cli_test.php".chr(34);
            $cmd .= " > ".chr(34).$logFile.chr(34);

            $comCommand = $cmd;
            if ($useCOM) {
                $WshShell   = new COM("WScript.Shell");
                $res = $WshShell->Run("cmd /C $comCommand", 0, false);
            } else {
                $tmpBat = implode(DIRECTORY_SEPARATOR, array(str_replace("/", DIRECTORY_SEPARATOR, AJXP_INSTALL_PATH), "data","tmp", md5(time()).".bat"));
                $cmd .= "\n DEL ".chr(34).$tmpBat.chr(34);
                file_put_contents($tmpBat, $cmd);
                /* Following 1 line modified by rmeske: The windows Start command identifies the first parameter in quotes as a title for the window.  Therefore, when enclosing a command with double quotes you must include a window title first
                START	["title"] [/Dpath] [/I] [/MIN] [/MAX] [/SEPARATE | /SHARED] [/LOW | /NORMAL | /HIGH | /REALTIME] [/WAIT] [/B] [command / program] [parameters]
                */
                @pclose(@popen('start /b "CLI" "'.$tmpBat.'"', 'r'));
                sleep(1);
                // Failed, but we can try with COM
                if ( ! is_file(AJXP_CACHE_DIR."/cli_result.php") && $comEnabled ) {
                    $useCOM = true;
                    $WshShell   = new COM("WScript.Shell");
                    $res = $WshShell->Run("cmd /C $comCommand", 0, false);
                }
            }
        } else {
            new UnixProcess($cmd, $logFile);
        }

        sleep(1);
        $availability = true;
        if (is_file(AJXP_CACHE_DIR."/cli_result.php")) {
            $this->testedParams["Command Line Available"] = "Yes";
            unlink(AJXP_CACHE_DIR."/cli_result.php");
            if ($useCOM) {
                $this->failedLevel = "warning";
                $availability = true;
                $this->failedInfo = "Php command line detected, but using the windows COM extension. Just make sure to <b>enable COM</b> in the Pydio Core Options";
            } else {
                $this->failedInfo = "Php command line detected, this will allow to send some tasks in background. Enable it in the Pydio Core Options";
            }
        } else {
            if (is_file($logFile)) {
                $log = file_get_contents($logFile);
                unlink($logFile);
            }
            $this->testedParams["Command Line Available"] = "No : $log";
            $this->failedLevel = "warning";
            $this->failedInfo = "Php command line not detected, this is NOT BLOCKING, but enabling it could allow to send some long tasks in background. If you do not have the ability to tweak your server, you can safely ignore this warning.";
            if ($windows) {
                $this->failedInfo .= "<br> On Windows, try to activate the php COM extension, and set correct rights to the cmd exectuble to make it runnable by the web server, this should solve the problem.";
            }
            $availability = false;
        }
        unlink(AJXP_CACHE_DIR."/cli_test.php");

        return $availability;
    }
};
