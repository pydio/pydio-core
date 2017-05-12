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
namespace Pydio\Core\Utils;

use HttpClient;
use Pydio\Core\Model\Context;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Core\Utils\Vars\VarsFilter;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class FileHelper
 * @package Pydio\Core\Utils
 */
class FileHelper
{

    /**
     * Load an array stored serialized inside a file.
     * Warning : currently does not take a context, filtering will be applied only based on global configs
     * (AJXP_DATA_PATH, etc...). Make sure to filter the path if required (e.g. AJXP_USER) before passing it to the function.
     *
     * @param String $filePath Full path to the file
     * @param Boolean $skipCheck do not test for file existence before opening
     * @param string $format
     * @return array
     */
    public static function loadSerialFile($filePath, $skipCheck = false, $format = "ser")
    {
        $filePath = VarsFilter::filter($filePath, Context::emptyContext());
        $result = array();
        if ($skipCheck) {
            $fileLines = @file($filePath);
            if ($fileLines !== false) {
                if ($format == "ser") $result = unserialize(implode("", $fileLines));
                else if ($format == "json") $result = json_decode(implode("", $fileLines), true);
            }
            return $result;
        }
        if (is_file($filePath)) {
            $fileLines = file($filePath);
            if ($format == "ser") $result = unserialize(implode("", $fileLines));
            else if ($format == "json") $result = json_decode(implode("", $fileLines), true);
        }
        return $result;
    }

    /**
     * Stores an Array as a serialized string inside a file.
     * @see loadSerialFile regarding path filtering.
     *
     * @param String $filePath Full path to the file
     * @param array|object $value The value to store
     * @param Boolean $createDir Whether to create the parent folder or not, if it does not exist.
     * @param bool $silent Silently write the file, are throw an exception on problem.
     * @param string $format "ser" or "json"
     * @param bool $jsonPrettyPrint If json, use pretty printing
     * @throws \Exception
     */
    public static function saveSerialFile($filePath, $value, $createDir = true, $silent = false, $format = "ser", $jsonPrettyPrint = false)
    {
        if (!in_array($format, array("ser", "json"))) {
            throw new \Exception("Unsupported serialization format: " . $format);
        }
        $filePath = VarsFilter::filter($filePath, Context::emptyContext());
        if ($createDir && !is_dir(dirname($filePath))) {
            @mkdir(dirname($filePath), 0755, true);
            if (!is_dir(dirname($filePath))) {
                // Creation failed
                if ($silent) return;
                else throw new \Exception("[AJXP_Utils::saveSerialFile] Cannot write into " . dirname(dirname($filePath)));
            }
        }
        try {
            $fp = fopen($filePath, "w");
            if ($format == "ser") {
                $content = serialize($value);
            } else {
                $content = json_encode($value);
                if ($jsonPrettyPrint) $content = StringHelper::prettyPrintJSON($content);
            }
            fwrite($fp, $content);
            fclose($fp);
        } catch (\Exception $e) {
            if ($silent) return;
            else throw $e;
        }
    }

    /**
     * Try to remove a file without errors
     * @static
     * @param $file
     * @return void
     */
    public static function silentUnlink($file)
    {
        @unlink($file);
    }

    /**
     * @static
     * @param string $url
     * @return bool|mixed|string
     */
    public static function getRemoteContent($url)
    {
        if (ini_get("allow_url_fopen")) {
            return file_get_contents($url);
        } else if (function_exists("curl_init")) {
            $ch = curl_init();
            $timeout = 30; // set to zero for no timeout
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $return = curl_exec($ch);
            curl_close($ch);
            return $return;
        } else {
            $i = parse_url($url);
            require_once AJXP_BIN_FOLDER."/lib/HttpClient.php";
            $httpClient = new HttpClient($i["host"]);
            $httpClient->timeout = 30;
            return $httpClient->quickGet($url);
        }
    }
}