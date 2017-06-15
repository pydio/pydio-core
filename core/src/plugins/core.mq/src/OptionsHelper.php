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
namespace Pydio\Mq\Core;

use Pydio\Core\Services\ApplicationState;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Helper for parsing core options
 * @package Pydio\Mq\Core
 */
class OptionsHelper
{
    const OPTION_HOST           = 'HOST';
    const OPTION_PORT           = 'PORT';
    const OPTION_SECURE         = 'SECURE';

    const FEATURE_MAIN          = 'main';
    const FEATURE_WS            = 'WS';
    const FEATURE_UPLOAD        = 'UPLOAD';
    const FEATURE_DOWNLOAD      = 'DOWNLOAD';
    const FEATURE_MESSAGING     = 'NSQ';
    const FEATURE_SERVER_URL    = 'SERVER';

    const SCOPE_EXTERNAL        = 'external';
    const SCOPE_INTERNAL        = 'internal';

    /**
     *
     * @param array $options
     * @param string $type
     * @param string $feature
     * @param string $scope
     * @return mixed
     */
    public static function getNetworkOption($options, $type = self::OPTION_HOST, $feature = self::FEATURE_WS, $scope = self::SCOPE_EXTERNAL){

        if($feature === self::FEATURE_MAIN) {

            if ($scope === self::SCOPE_INTERNAL) {
                // Check if it's the same as external
                if (isSet($options["BOOSTER_INTERNAL_CONNECTION"]) && $options["BOOSTER_INTERNAL_CONNECTION"]["group_switch_value"] === "same") {
                    return self::getNetworkOption($options, $type, $feature, self::SCOPE_EXTERNAL);
                } else {
                    return $options["BOOSTER_INTERNAL_CONNECTION"]["BOOSTER_INTERNAL_" . $type];
                }
            } else {

                return $options["BOOSTER_MAIN_" . $type];

            }

        } else if ($feature === self::FEATURE_MESSAGING) {

            if ($type === self::OPTION_HOST) return $options["NSQ_HOST"];
            else if ($type === self::OPTION_PORT) return $options["NSQ_PORT"];
            
        } else if ($feature === self::FEATURE_SERVER_URL){

            if (isSet($options["SERVER_INTERNAL_URL"]) && isSet($options["SERVER_INTERNAL_URL"]["group_switch_value"]) && $options["SERVER_INTERNAL_URL"]["group_switch_value"] === "custom") {
                $url = $options["SERVER_INTERNAL_URL"]["SERVER_INTERNAL_ADDRESS"];
            } else{
                $url = ApplicationState::detectServerURL(true, true);
            }
            return rtrim($url, "/");
            
        } else {

            $switchOptionName = "BOOSTER_". $feature. "_ADVANCED";
            if(isSet($options[$switchOptionName]) && $options[$switchOptionName]["group_switch_value"] === "same"){

                return self::getNetworkOption($options, $type, self::FEATURE_MAIN, $scope);

            }else{

                return $options[$switchOptionName][$feature."_".$type.($scope === self::SCOPE_INTERNAL ? "_INTERNAL" : "")];

            }

        }

    }

    /**
     * Check if there is a specific setup for internal IP/PORT for this feature
     * @param $options
     * @param string $feature
     * @return bool
     */
    public static function featureHasInternalSetting($options, $feature = self::FEATURE_WS){

        if( $feature === self::FEATURE_MAIN ){

            if(isSet($options["BOOSTER_INTERNAL_CONNECTION"]) && $options["BOOSTER_INTERNAL_CONNECTION"]["group_switch_value"] === "same"){
                return false;
            }
            $externalHost = $options["BOOSTER_MAIN_HOST"];
            $internalHost = $options["BOOSTER_INTERNAL_CONNECTION"]["BOOSTER_INTERNAL_HOST"];
            $externalPort = $options["BOOSTER_MAIN_PORT"];
            $internalPort = $options["BOOSTER_INTERNAL_CONNECTION"]["BOOSTER_INTERNAL_PORT"];

            if( ( !empty($externalHost) && $externalHost !== $internalHost ) || ( !empty($externalPort) && $externalPort !== $internalPort )){
                return true;
            }else{
                return false;
            }


        }else{
            $switchOptionName = "BOOSTER_". $feature. "_ADVANCED";
            if(isSet($options[$switchOptionName]) && $options[$switchOptionName]["group_switch_value"] === "same"){
                // check main options
                return self::featureHasInternalSetting($options, self::FEATURE_MAIN);
            }else{
                $externalHost = $options[$switchOptionName][$feature."_HOST"];
                $internalHost = $options[$switchOptionName][$feature."_HOST_INTERNAL"];
                $externalPort = $options[$switchOptionName][$feature."_PORT"];
                $internalPort = $options[$switchOptionName][$feature."_PORT_INTERNAL"];
                if( ( !empty($externalHost) && $externalHost !== $internalHost ) || ( !empty($externalPort) && $externalPort !== $internalPort )){
                    return true;
                }else{
                    return false;
                }
            }

        }


    }

}