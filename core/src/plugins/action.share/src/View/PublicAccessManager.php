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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Share\View;

use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\ApplicationState;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class PublicAccessManager
 * @package Pydio\Share\View
 */
class PublicAccessManager
{
    /**
     * @var array
     */
    private $options;

    /**
     * PublicFolderManager constructor.
     * @param array $options Key => value options
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
        $addLang = LocaleService::getLanguage() != ConfService::getGlobalConf("DEFAULT_LANGUAGE");
        if($addLang) return $this->buildPublicDlURL()."/".$hash."--". LocaleService::getLanguage();
        else return $this->buildPublicDlURL()."/".$hash;
    }

    /**
     * Compute base URL for external links
     * @return mixed|string
     */
    public function buildPublicDlURL()
    {
        $dlURL = ConfService::getGlobalConf("PUBLIC_DOWNLOAD_URL");
        if (!empty($dlURL)) {
            $parts = parse_url($dlURL);
            if($parts['scheme']) {
                return rtrim($dlURL, "/");
            } else {
                $host = ApplicationState::detectServerURL();
                return rtrim($host, "/")."/".trim($dlURL, "/");
            }
        } else {
            $fullUrl = ApplicationState::detectServerURL(true);
            return str_replace("\\", "/", rtrim($fullUrl, "/")."/".trim(ConfService::getGlobalConf("PUBLIC_BASEURI"), "/"));
        }
    }

    /**
     * @return string
     */
    public function computeMinisiteToServerURL()
    {
        $minisite = parse_url($this->buildPublicDlURL(), PHP_URL_PATH) ."/a.php";
        $server = rtrim(parse_url( ApplicationState::detectServerURL(true), PHP_URL_PATH), "/");
        return ApplicationState::getTravelPath($minisite, $server);
    }

}