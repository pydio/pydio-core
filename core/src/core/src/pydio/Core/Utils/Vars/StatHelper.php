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

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\LocaleService;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class MimesHelper
 * Various utils functions for file mimetypes
 * @package Pydio\Core\Utils
 */
class StatHelper
{
    public static $sizeUnit;

    /**
     * @param AJXP_Node $ajxpNode
     * @param bool|null $isDir
     * @return array
     */
    public static function getMimeInfo($ajxpNode, $isDir = null)
    {
        if ($isDir === null) {
            $isDir = !$ajxpNode->isLeaf();
        }
        $fileName = strtolower($ajxpNode->getPath());
        $registeredExtensions = self::getRegisteredExtensions($ajxpNode->getContext());

        if ($isDir) {
            $mime = $registeredExtensions["ajxp_folder"];
        } else {
            $pos = strrpos($fileName, ".");
            if ($pos !== false) {
                $fileExt = substr($fileName, $pos + 1);
                if (!empty($fileExt) && array_key_exists($fileExt, $registeredExtensions) && $fileExt != "ajxp_folder" && $fileExt != "ajxp_empty") {
                    $mime = $registeredExtensions[$fileExt];
                }
            }
        }
        if (!isSet($mime)) {
            $mime = $registeredExtensions["ajxp_empty"];
        }
        return array($mime[3], $mime[1], $mime[2]);

    }

    /**
     * MISC CONFS
     */
    private static $extensionsCache;
    /**
     * Get all registered extensions, from both the conf/extensions.conf.php and from the plugins
     * @static
     * @param ContextInterface $ctx
     * @return array
     */
    private static function getRegisteredExtensions(ContextInterface $ctx)
    {
        if (!is_array(self::$extensionsCache) || !array_key_exists($ctx->getStringIdentifier(), self::$extensionsCache)) {
            $EXTENSIONS = array();
            $RESERVED_EXTENSIONS = array();
            include(AJXP_CONF_PATH."/extensions.conf.php");
            $EXTENSIONS = array_merge($RESERVED_EXTENSIONS, $EXTENSIONS);
            foreach ($EXTENSIONS as $key => $value) {
                unset($EXTENSIONS[$key]);
                $EXTENSIONS[$value[0]] = $value;
            }
            $nodes = PluginsService::getInstance($ctx)->searchAllManifests("//extensions/extension", "nodes", true);
            $res = array();
            /** @var \DOMElement $node */
            foreach ($nodes as $node) {
                $res[$node->getAttribute("mime")] = array(
                    $node->getAttribute("mime"),
                    $node->getAttribute("icon"),
                    $node->getAttribute("font"),
                    $node->getAttribute("messageId")
                );
            }
            if (count($res)) {
                $EXTENSIONS = array_merge($EXTENSIONS, $res);
            }
            if(!is_array(self::$extensionsCache)){
                self::$extensionsCache = [];
            }
            self::$extensionsCache[$ctx->getStringIdentifier()] = $EXTENSIONS;
        }
        return self::$extensionsCache[$ctx->getStringIdentifier()];
    }


    /**
     * Gather a list of mime that must be treated specially. Used for dynamic replacement in XML mainly.
     * @static
     * @param string $keyword "editable", "image", "audio", "zip"
     * @return string
     */
    public static function getAjxpMimes($keyword)
    {
        if ($keyword == "editable") {
            // Gather editors!
            $pServ = PluginsService::getInstance(Context::emptyContext());
            $plugs = $pServ->getPluginsByType("editor");
            //$plugin = new Plugin();
            $mimes = array();
            foreach ($plugs as $plugin) {
                $node = $plugin->getManifestRawContent("/editor/@mimes", "node");
                $openable = $plugin->getManifestRawContent("/editor/@openable", "node");
                if ($openable->item(0) && $openable->item(0)->value == "true" && $node->item(0)) {
                    $mimestring = $node->item(0)->value;
                    $mimesplit = explode(",", $mimestring);
                    foreach ($mimesplit as $value) {
                        $mimes[$value] = $value;
                    }
                }
            }
            return implode(",", array_values($mimes));
        } else if ($keyword == "image") {
            return "png,bmp,jpg,jpeg,gif";
        } else if ($keyword == "audio") {
            return "mp3";
        } else if ($keyword == "zip") {
            if (ConfService::zipBrowsingEnabled()) {
                return "zip,ajxp_browsable_archive";
            } else {
                return "none_allowed";
            }
        }
        return "";
    }

    /**
     * Whether a file is to be considered as an image or not
     * @static
     * @param $fileName
     * @return bool
     */
    public static function basenameIsImage($fileName)
    {
        return (preg_match("/\.png$|\.bmp$|\.jpg$|\.jpeg$|\.gif$/i", $fileName) ? true : false);
    }

    /**
     * Static image mime type headers
     * @static
     * @param $fileName
     * @return string
     */
    public static function getImageMimeType($fileName)
    {
        if (preg_match("/\.jpg$|\.jpeg$/i", $fileName)) {
            return "image/jpeg";
        } else if (preg_match("/\.png$/i", $fileName)) {
            return "image/png";
        } else if (preg_match("/\.bmp$/i", $fileName)) {
            return "image/bmp";
        } else if (preg_match("/\.gif$/i", $fileName)) {
            return "image/gif";
        }
        return "";
    }

    /**
     * Headers to send when streaming
     * @static
     * @param $fileName
     * @return bool|string
     */
    public static function getStreamingMimeType($fileName)
    {
        if (preg_match("/\.mp3$/i", $fileName)) {
            return "audio/mp3";
        } else if (preg_match("/\.wav$/i", $fileName)) {
            return "audio/wav";
        } else if (preg_match("/\.aac$/i", $fileName)) {
            return "audio/aac";
        } else if (preg_match("/\.m4a$/i", $fileName)) {
            return "audio/m4a";
        } else if (preg_match("/\.aiff$/i", $fileName)) {
            return "audio/aiff";
        } else if (preg_match("/\.mp4$/i", $fileName)) {
            return "video/mp4";
        } else if (preg_match("/\.mov$/i", $fileName)) {
            return "video/quicktime";
        } else if (preg_match("/\.m4v$/i", $fileName)) {
            return "video/x-m4v";
        } else if (preg_match("/\.3gp$/i", $fileName)) {
            return "video/3gpp";
        } else if (preg_match("/\.3g2$/i", $fileName)) {
            return "video/3gpp2";
        } else return false;
    }

    /**
     * Display a human readable string for a bytesize (1MB, 2,3Go, etc)
     * @static
     * @param $filesize
     * @param bool $phpConfig
     * @return string
     */
    public static function roundSize($filesize, $phpConfig = false)
    {
        if (self::$sizeUnit == null) {
            $mess = LocaleService::getMessages();
            self::$sizeUnit = $mess["byte_unit_symbol"];
        }
        if ($filesize < 0) {
            $filesize = sprintf("%u", $filesize);
        }
        if ($filesize >= 1073741824) {
            $filesize = round($filesize / 1073741824 * 100) / 100 . ($phpConfig ? "G" : " G" . self::$sizeUnit);
        } elseif ($filesize >= 1048576) {
            $filesize = round($filesize / 1048576 * 100) / 100 . ($phpConfig ? "M" : " M" . self::$sizeUnit);
        } elseif ($filesize >= 1024) {
            $filesize = round($filesize / 1024 * 100) / 100 . ($phpConfig ? "K" : " K" . self::$sizeUnit);
        } else {
            $filesize = $filesize . " " . self::$sizeUnit;
        }
        if ($filesize == 0) {
            $filesize = "-";
        }
        return $filesize;
    }

    /**
     * Hidden files start with dot
     * @static
     * @param string $fileName
     * @return bool
     */
    public static function isHidden($fileName)
    {
        return (substr($fileName, 0, 1) == ".");
    }

    /**
     * Whether a file is a browsable archive
     * @static
     * @param string $fileName
     * @return int
     */
    public static function isBrowsableArchive($fileName)
    {
        if(!ConfService::zipBrowsingEnabled()){
            return false;
        }
        return preg_match("/\.zip$/i", $fileName);
    }

    /**
     * Convert a shorthand byte value from a PHP configuration directive to an integer value
     * @param    string   $value
     * @return   int
     */
    public static function convertBytes($value)
    {
        if (is_numeric($value)) {
            return intval($value);
        } else {
            $value_length = strlen($value);
            $value = str_replace(",", ".", $value);
            $qty = floatval(substr($value, 0, $value_length - 1));
            $unit = strtolower(substr($value, $value_length - 1));
            switch ($unit) {
                case 'k':
                    $qty *= 1024;
                    break;
                case 'm':
                    $qty *= 1048576;
                    break;
                case 'g':
                    $qty *= 1073741824;
                    break;
            }
            return $qty;
        }
    }

    /**
     * Build a relative date string, using pydio messages library
     * @param $time
     * @param $messages
     * @param bool $shortestForm
     * @return bool|mixed|string
     */
    public static function relativeDate($time, $messages, $shortestForm = false)
    {
        $crtYear = date('Y');
        $today = strtotime(date('M j, Y'));
        $reldays = ($time - $today) / 86400;
        $relTime = date($messages['date_relative_time_format'], $time);

        if ($reldays >= 0 && $reldays < 1) {
            return str_replace("TIME", $relTime, $messages['date_relative_today']);
        } else if ($reldays >= 1 && $reldays < 2) {
            return str_replace("TIME", $relTime, $messages['date_relative_tomorrow']);
        } else if ($reldays >= -1 && $reldays < 0) {
            return str_replace("TIME", $relTime, $messages['date_relative_yesterday']);
        }

        if (abs($reldays) < 7) {

            if ($reldays > 0) {
                $reldays = floor($reldays);
                return str_replace("%s", $reldays, $messages['date_relative_days_ahead']);
                //return 'In ' . $reldays . ' day' . ($reldays != 1 ? 's' : '');
            } else {
                $reldays = abs(floor($reldays));
                return str_replace("%s", $reldays, $messages['date_relative_days_ago']);
                //return $reldays . ' day' . ($reldays != 1 ? 's' : '') . ' ago';
            }

        }
        $finalDate = date($messages["date_relative_date_format"], $time ? $time : time());
        if (strpos($messages["date_relative_date_format"], "F") !== false && isSet($messages["date_intl_locale"]) && extension_loaded("intl")) {
            $intl = \IntlDateFormatter::create($messages["date_intl_locale"], \IntlDateFormatter::FULL, \IntlDateFormatter::FULL, null, null, "MMMM");
            $localizedMonth = $intl->format($time ? $time : time());
            $dateFuncMonth = date("F", $time ? $time : time());
            $finalDate = str_replace($dateFuncMonth, $localizedMonth, $finalDate);
        }
        if (!$shortestForm || strpos($finalDate, $crtYear) !== false) {
            $finalDate = str_replace($crtYear, '', $finalDate);
            return str_replace("DATE", $finalDate, $messages["date_relative_date"]);
        } else {
            return $finalDate = date("M Y", $time ? $time : time());
        }

    }

    public static function relativeDateGroup($time, $messages){

        $crtYear = date('Y');
        $today = strtotime(date('M j, Y'));
        $reldays = ($time - $today) / 86400;

        if ($reldays >= 0 && $reldays < 1) {
            return 'today';
        } else if ($reldays >= 1 && $reldays < 2) {
            return 'tomorrow';
        } else if ($reldays >= -1 && $reldays < 0) {
            return 'yesterday';
        }

        if (abs($reldays) < 7) {

            if ($reldays > 0) {
                $reldays = floor($reldays);
                return str_replace("%s", $reldays, $messages['date_relative_days_ahead']);
            } else {
                $reldays = abs(floor($reldays));
                return str_replace("%s", $reldays, $messages['date_relative_days_ago']);
            }
        }

        if(abs($reldays) < 31) {

            return 'more_than_week';

        }else{

            return 'more_than_month';

        }

    }

    /**
     * Hide file or folder for Windows OS
     * @static
     * @param $file
     */
    public static function winSetHidden($file)
    {
        @shell_exec("attrib +H " . escapeshellarg($file));
    }
}