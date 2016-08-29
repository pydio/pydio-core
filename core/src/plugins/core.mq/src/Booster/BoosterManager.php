<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Mq\Core\Booster;

use PhpOption\Option;
use Pydio\Core\Controller\CliRunner;
use Pydio\Core\Controller\UnixProcess;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Services\ApiKeysService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\ApplicationState;
use Pydio\Mq\Core\OptionsHelper;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class BoosterManager
 * Utilitary tools for managing external binary tool
 * @package Pydio\Mq\Core\Booster
 */
class BoosterManager
{
    private $pluginOptions;
    private $pluginWorkDir;
    private $pluginCacheDir;

    private $configFileName = "pydiocaddy";
    private $logFileName    = "pydio.out";
    private $pidFileName    = "caddy-pid";

    /**
     * BoosterManager constructor.
     * @param $pluginOptions
     * @param $pluginWorkDir
     * @param $pluginCacheDir
     */
    public function __construct($pluginOptions, $pluginWorkDir, $pluginCacheDir) {

        $this->pluginOptions = $pluginOptions;
        $this->pluginWorkDir = $pluginWorkDir;
        $this->pluginCacheDir = $pluginCacheDir;

    }

    // Handler testing the generation of the caddy file to spot any error
    /**
     * @param $params
     * @param string $adminKeyString
     * @return string
     */
    public function testPydioBoosterFile($params, $adminKeyString) {
        $error = "OK";

        set_error_handler(function ($e) use (&$error) {
            $error = $e;
        }, E_WARNING);

        $data = $this->generatePydioBoosterFile($params, $adminKeyString);

        // Generate the caddyfile
        file_put_contents(ApplicationState::getAjxpTmpDir() . DIRECTORY_SEPARATOR . "testcaddy", $data);

        restore_error_handler();

        return $error;
    }

    /**
     * @param int $offset
     * @return array
     */
    public function tailLogs($offset = 0){

        $fileName = $this->pluginWorkDir. DIRECTORY_SEPARATOR . $this->logFileName;

        if(!file_exists($fileName)){
            return [
                "output" => "File was not created yet",
                "offset" => 0
            ];
        }

        if($offset > 0){
            $f = fopen($fileName, "rb");
            $output = stream_get_contents($f, -1, $offset);
            fclose($f);
        }else{
            $output = $this->tail($fileName, 20, true);
        }
        return [
            "output" => explode("\n", $output),
            "offset" => filesize($fileName)
        ];

    }

    /**
     * @param $params
     * @param string
     * @return string
     */
    public function generatePydioBoosterFile($params, $adminKeyString) {

        $data = "";
        $hosts = [];

        // Getting URLs of the Pydio system
        $serverURL = ApplicationState::detectServerURL(true);
        $tokenURL = $serverURL . "?get_action=keystore_generate_auth_token";

        // Websocket Server Config
        $active = $params["WS_ACTIVE"];

        if ($active) {

            $authURL = $serverURL . "/api/pydio/ws_authenticate?key=" . $adminKeyString;

            $host       = OptionsHelper::getNetworkOption($params, OptionsHelper::OPTION_HOST,   OptionsHelper::FEATURE_WS, OptionsHelper::SCOPE_EXTERNAL);
            $port       = OptionsHelper::getNetworkOption($params, OptionsHelper::OPTION_PORT,   OptionsHelper::FEATURE_WS, OptionsHelper::SCOPE_EXTERNAL);
            $secure     = OptionsHelper::getNetworkOption($params, OptionsHelper::OPTION_SECURE, OptionsHelper::FEATURE_WS, OptionsHelper::SCOPE_EXTERNAL);
            $path       = "/" . trim($params["WS_PATH"], "/");

            $key = "http" . ($secure ? "s" : "") . "://" . $host . ":" . $port;

            if(OptionsHelper::featureHasInternalSetting($params, OptionsHelper::FEATURE_WS)){
                $intHost       = OptionsHelper::getNetworkOption($params, OptionsHelper::OPTION_HOST,   OptionsHelper::FEATURE_WS, OptionsHelper::SCOPE_INTERNAL);
                $intPort       = OptionsHelper::getNetworkOption($params, OptionsHelper::OPTION_PORT,   OptionsHelper::FEATURE_WS, OptionsHelper::SCOPE_INTERNAL);
                // We assume that internal connection is always http
                $key .= ", http://$intHost:$intPort";
            }

            $hosts[$key] = array_merge(
                (array)$hosts[$key],
                [
                    "pydioauth " . $path => [$tokenURL . "&device=websocket"],
                    "pydiopre " . $path => [$authURL],
                    "pydiows " . $path => []
                ]
            );
        }

        // Upload Server Config
        $active = $params["UPLOAD_ACTIVE"];

        if ($active) {

            $authURL = $serverURL . "/api/{repo}/upload/put/{nodedir}?xhr_uploader=true";

            $host       = OptionsHelper::getNetworkOption($params, OptionsHelper::OPTION_HOST,   OptionsHelper::FEATURE_UPLOAD, OptionsHelper::SCOPE_EXTERNAL);
            $port       = OptionsHelper::getNetworkOption($params, OptionsHelper::OPTION_PORT,   OptionsHelper::FEATURE_UPLOAD, OptionsHelper::SCOPE_EXTERNAL);
            $secure     = OptionsHelper::getNetworkOption($params, OptionsHelper::OPTION_SECURE, OptionsHelper::FEATURE_UPLOAD, OptionsHelper::SCOPE_EXTERNAL);
            $path = "/" . trim($params["UPLOAD_PATH"], "/");

            /*
            // WE SHOULD HAVE A CONTEXT AT THIS POINT, INSTEAD OF CALLING ::getLoggedUser()
            $adminKey = ApiKeysService::findPairForAdminTask("go-upload", AuthService::getLoggedUser()->getId());
            if($adminKey === null){
                $adminKey = ApiKeysService::generatePairForAdminTask("go-upload", AuthService::getLoggedUser()->getId(), $host);
            }
            $adminKeyString = $adminKey["t"].":".$adminKey["p"];
            */

            $key = "http" . ($secure ? "s" : "") . "://" . $host . ":" . $port;

            if(OptionsHelper::featureHasInternalSetting($params, OptionsHelper::FEATURE_UPLOAD)){
                $intHost       = OptionsHelper::getNetworkOption($params, OptionsHelper::OPTION_HOST,   OptionsHelper::FEATURE_UPLOAD, OptionsHelper::SCOPE_INTERNAL);
                $intPort       = OptionsHelper::getNetworkOption($params, OptionsHelper::OPTION_PORT,   OptionsHelper::FEATURE_UPLOAD, OptionsHelper::SCOPE_INTERNAL);
                // We assume that internal connection is always http
                $key .= ", http://$intHost:$intPort";
            }

            $hosts[$key] = array_merge(
                (array)$hosts[$key],
                [
                    "header " . $path => ["{\n" .
                        "\t\tAccess-Control-Allow-Origin " . $serverURL . "\n" .
                        "\t\tAccess-Control-Request-Headers *\n" .
                        "\t\tAccess-Control-Allow-Methods POST\n" .
                        "\t\tAccess-Control-Allow-Headers Range\n" .
                        "\t\tAccess-Control-Allow-Credentials true\n" .
                        "\t}"
                    ],
                    "pydioauth " . $path => [$tokenURL . "&device=upload"],
                    "pydiopre " . $path => [$authURL, "{\n" .
                        "\t\theader X-File-Direct-Upload request-options\n" .
                        "\t\theader X-Pydio-Admin-Auth $adminKeyString\n" .
                        "\t}"
                    ],
                    "pydioupload " . $path => [],
                    "pydiopost " . $path => [$authURL, "{\n" .
                        "\t\theader X-File-Direct-Upload upload-finished\n" .
                        "\t\theader X-File-Name {nodename}\n" .
                        "\t}"
                    ],
                ]
            );
        }

        foreach ($hosts as $host => $config) {
            $data .= $host . " {\n";

            foreach ($config as $key => $value) {
                $data .= "\t" . $key . " " . join($value, " ") . "\n";
            }

            $data .= "}\n";
        }

        return $data;
    }

    /**
     * @param array $params
     * @param string $adminKeyString
     * @return string
     * @throws \Exception
     */
    public function savePydioBoosterFile($params, $adminKeyString) {
        $data = $this->generatePydioBoosterFile($params, $adminKeyString);

        $wDir = $this->pluginWorkDir;
        $caddyFile = $wDir.DIRECTORY_SEPARATOR.$this->configFileName;

        // Generate the caddyfile
        file_put_contents($caddyFile, $data);

        return $caddyFile;
    }

    /**
     * @param array $params
     * @param string $adminKeyString
     * @return string
     * @throws PydioException
     */
    public function switchPydioBoosterOn($params, $adminKeyString) {

        $caddyFile = $this->savePydioBoosterFile($params, $adminKeyString);

        $wDir = $this->pluginWorkDir;
        $pidFile = $wDir.DIRECTORY_SEPARATOR.$this->pidFileName;
        if (file_exists($pidFile)) {
            $pId = file_get_contents($pidFile);
            $unixProcess = new UnixProcess();
            $unixProcess->setPid($pId);
            $status = $unixProcess->status();
            if ($status) {
                throw new PydioException("PydioBooster server seems to already be running!");
            }
        }

        $tmpDir = ApplicationState::getAjxpTmpDir();
        chdir($wDir);

        $cmd = "env TMPDIR=".$tmpDir." ". $params["CLI_PYDIO"]." -conf ".$caddyFile;
        $process = CliRunner::runCommandInBackground($cmd, $this->pluginWorkDir.DIRECTORY_SEPARATOR.$this->logFileName, true);
        if ($process != null) {
            $pId = $process->getPid();
            file_put_contents($pidFile, $pId);
            return "Started Server with process ID $pId";
        }
        return "Started Server";

    }

    /**
     * @param $params
     * @return string
     * @throws \Exception
     */
    public function switchPydioBoosterOff($params){
        return $this->switchOff($params, "caddy");
    }

    /**
     * @param $params
     * @return string
     */
    public function getPydioBoosterStatus($params){
        return $this->getStatus($params, "caddy");
    }


    /*******************/
    /* WORKERS METHODS */
    /*******************/

    /**
     * @param $params
     * @return string
     * @throws \Exception
     */
    public function switchWorkerOn($params, $adminKeyString)
    {
        $wDir = $this->pluginWorkDir;
        $pidFile = $wDir.DIRECTORY_SEPARATOR."worker-pid";
        if (file_exists($pidFile)) {
            $pId = file_get_contents($pidFile);
            $unixProcess = new UnixProcess();
            $unixProcess->setPid($pId);
            $status = $unixProcess->status();
            if ($status) {
                throw new \Exception("Worker seems to already be running!");
            }
        }
        $cmd = ConfService::getGlobalConf("CLI_PHP")." worker.php";
        chdir(AJXP_INSTALL_PATH);
        $process = CliRunner::runCommandInBackground($cmd, AJXP_CACHE_DIR . "/cmd_outputs/worker.log", true);
        if ($process != null) {
            $pId = $process->getPid();
            file_put_contents($pidFile, $pId);
            return "Started worker with process ID $pId";
        }
        return "Started worker Server";
    }

    /**
     * @param $params
     * @return string
     * @throws \Exception
     */
    public function switchWorkerOff($params){
        return $this->switchOff($params, "worker");
    }

    /**
     * @param $params
     * @return string
     */
    public function getWorkerStatus($params){
        return $this->getStatus($params, "worker");
    }



    /*******************/
    /* PRIVATE METHODS */
    /*******************/

    /**
     * @param $params
     * @param string $type
     * @return string
     * @throws \Exception
     */
    private function switchOff($params, $type = "caddy")
    {
        $wDir = $this->pluginWorkDir;
        $pidFile = $wDir.DIRECTORY_SEPARATOR."$type-pid";
        if (!file_exists($pidFile)) {
            throw new \Exception("No information found about $type server");
        } else {
            $pId = file_get_contents($pidFile);
            $unixProcess = new UnixProcess();
            $unixProcess->setPid($pId);
            $unixProcess->stop();
            unlink($pidFile);
        }
        return "SUCCESS: Killed $type Server";
    }

    /**
     * @param $params
     * @param string $type
     * @return string
     * @throws \Exception
     */
    private function getStatus($params, $type = "caddy")
    {
        $wDir = $this->pluginWorkDir;
        $pidFile = $wDir.DIRECTORY_SEPARATOR."$type-pid";
        if (!file_exists($pidFile)) {
            return "OFF";
        } else {
            $pId = file_get_contents($pidFile);
            $unixProcess = new UnixProcess();
            $unixProcess->setPid($pId);
            $status = $unixProcess->status();
            if($status) return "ON";
            else return "OFF";
        }
    }


    /**
     * @param $filepath
     * @param int $lines
     * @param bool $adaptive
     * @return bool|string
     */
    private function tail($filepath, $lines = 1, $adaptive = true) {
        $f = @fopen($filepath, "rb");
        if ($f === false) return false;
        if (!$adaptive) $buffer = 4096;
        else $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));
        fseek($f, -1, SEEK_END);
        if (fread($f, 1) != "\n") $lines -= 1;

        $output = '';
        $chunk = '';
        while (ftell($f) > 0 && $lines >= 0) {
            $seek = min(ftell($f), $buffer);
            fseek($f, -$seek, SEEK_CUR);
            $output = ($chunk = fread($f, $seek)) . $output;
            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
            $lines -= substr_count($chunk, "\n");
        }
        while ($lines++ < 0) {
            $output = substr($output, strpos($output, "\n") + 1);
        }
        fclose($f);
        return trim($output);
    }



}