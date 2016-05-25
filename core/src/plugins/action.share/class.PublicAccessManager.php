<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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

use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Utils;

defined('AJXP_EXEC') or die('Access not allowed');


class PublicAccessManager
{
    /**
     * @var array
     */
    private $options;

    /**
     * PublicFolderManager constructor.
     * @param array $options Key => value options, currently supports only USE_REWRITE_RULE = true / false
     */
    public function __construct($options){
        $this->options = $options;
    }
    
    /**
     * Compute external link from the given hash
     * @param string $hash
     * @return string
     */
    public function buildPublicLink($hash)
    {
        $addLang = ConfService::getLanguage() != ConfService::getCoreConf("DEFAULT_LANGUAGE");
        if ($this->options["USE_REWRITE_RULE"]) {
            if($addLang) return $this->buildPublicDlURL()."/".$hash."--".ConfService::getLanguage();
            else return $this->buildPublicDlURL()."/".$hash;
        } else {
            if($addLang) return $this->buildPublicDlURL()."/".$hash.".php?lang=".ConfService::getLanguage();
            else return $this->buildPublicDlURL()."/".$hash.".php";
        }
    }

    /**
     * Compute base URL for external links
     * @return mixed|string
     */
    public function buildPublicDlURL()
    {
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $dlURL = ConfService::getCoreConf("PUBLIC_DOWNLOAD_URL");
        if (!empty($dlURL)) {
            $parts = parse_url($dlURL);
            if($parts['scheme']) {
                return rtrim($dlURL, "/");
            } else {
                $host = Utils::detectServerURL();
                return rtrim($host, "/")."/".trim($dlURL, "/");
            }
        } else {
            $fullUrl = Utils::detectServerURL(true);
            return str_replace("\\", "/", rtrim($fullUrl, "/").rtrim(str_replace(AJXP_INSTALL_PATH, "", $downloadFolder), "/"));
        }
    }

    public function computeMinisiteToServerURL()
    {
        $minisite = parse_url($this->buildPublicDlURL(), PHP_URL_PATH) ."/a.php";
        $server = rtrim(parse_url( Utils::detectServerURL(true), PHP_URL_PATH), "/");
        return Utils::getTravelPath($minisite, $server);
    }

    /**
     * Get download folder path from configuration
     * @return string
     */
    public function getPublicDownloadFolder(){
        return ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
    }

    /**
     * Build download folder URL from configuration and current URL
     * @return string|null
     */
    public function getPublicDownloadUrl(){
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $dlURL = ConfService::getCoreConf("PUBLIC_DOWNLOAD_URL");
        if (!empty($dlURL)) {
            $parts = parse_url($dlURL);
            if($parts['scheme']) {
                return rtrim($dlURL, "/");
            } else {
                $host = Utils::detectServerURL();
                return rtrim($host, "/")."/".trim($dlURL, "/");
            }
        } else {
            $fullUrl = Utils::detectServerURL(true);
            return str_replace("\\", "/", rtrim($fullUrl, "/").rtrim(str_replace(AJXP_INSTALL_PATH, "", $downloadFolder), "/"));
        }
    }

}