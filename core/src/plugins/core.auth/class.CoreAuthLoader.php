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

/**
 * Config loader overrider
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
class CoreAuthLoader extends AJXP_Plugin
{
    /**
     * @var AbstractAuthDriver
     */
    protected static $authStorageImpl;

    public function getConfigs()
    {
        $configs = parent::getConfigs();
        $configs["ALLOW_GUEST_BROWSING"] = !isSet($_SERVER["HTTP_AJXP_FORCE_LOGIN"]) && ($configs["ALLOW_GUEST_BROWSING"] === "true" || $configs["ALLOW_GUEST_BROWSING"] === true || intval($configs["ALLOW_GUEST_BROWSING"]) == 1);
        // FORCE CASE INSENSITIVY FOR SQL BASED DRIVERS
        if (isSet($configs["MASTER_INSTANCE_CONFIG"]) && is_array($configs["MASTER_INSTANCE_CONFIG"]) &&  isSet($configs["MASTER_INSTANCE_CONFIG"]["instance_name"]) && $configs["MASTER_INSTANCE_CONFIG"]["instance_name"] == "auth.sql") {
            $configs["CASE_SENSITIVE"] = false;
        }
        if (isSet($configs["SLAVE_INSTANCE_CONFIG"]) && !empty($configs["SLAVE_INSTANCE_CONFIG"])  && isset($configs["SLAVE_INSTANCE_CONFIG"]["instance_name"]) && $configs["SLAVE_INSTANCE_CONFIG"]["instance_name"] == "auth.sql") {
            $configs["CASE_SENSITIVE"] = false;
        }
        return $configs;
    }

    public function getAuthImpl()
    {
        if (!isSet(self::$authStorageImpl)) {
            if (!isSet($this->pluginConf["MASTER_INSTANCE_CONFIG"])) {
                throw new Exception("Please set up at least one MASTER_INSTANCE_CONFIG in core.auth options");
            }
            $masterName = is_array($this->pluginConf["MASTER_INSTANCE_CONFIG"]) ? $this->pluginConf["MASTER_INSTANCE_CONFIG"]["instance_name"] : $this->pluginConf["MASTER_INSTANCE_CONFIG"];
            $masterName = str_replace("auth.", "", $masterName);
            if (!empty($this->pluginConf["SLAVE_INSTANCE_CONFIG"])) {
                $slaveName = is_array($this->pluginConf["SLAVE_INSTANCE_CONFIG"]) ? $this->pluginConf["SLAVE_INSTANCE_CONFIG"]["instance_name"] : $this->pluginConf["SLAVE_INSTANCE_CONFIG"];
                $slaveName = str_replace("auth.", "", $slaveName);
                // Manually set up a multi config

                $userBase = $this->pluginConf["MULTI_USER_BASE_DRIVER"];
                if($userBase == "master") $baseName = $masterName;
                else if($userBase == "slave") $baseName = $slaveName;
                else $baseName = "";

                $mLabel = ""; $sLabel = "";$separator = "";
                $cacheMasters = true;
                if ($this->pluginConf["MULTI_MODE"]["instance_name"] == "USER_CHOICE") {
                    $mLabel = $this->pluginConf["MULTI_MODE"]["MULTI_MASTER_LABEL"];
                    $sLabel = $this->pluginConf["MULTI_MODE"]["MULTI_SLAVE_LABEL"];
                    $separator = $this->pluginConf["MULTI_MODE"]["MULTI_USER_ID_SEPARATOR"];
                }else{
                    $cacheMasters = $this->pluginConf["MULTI_MODE"]["CACHE_MASTER_USERS_TO_SLAVE"];
                }
                $newOptions = array(
                    "instance_name" => "auth.multi",
                    "MODE" => $this->pluginConf["MULTI_MODE"]["instance_name"],
                    "MASTER_DRIVER" => $masterName,
                    "USER_BASE_DRIVER" => $baseName,
                    "USER_ID_SEPARATOR" => $separator,
                    "CACHE_MASTER_USERS_TO_SLAVE" => $cacheMasters,
                    "TRANSMIT_CLEAR_PASS" => $this->pluginConf["TRANSMIT_CLEAR_PASS"],
                    "DRIVERS" => array(
                        $masterName => array(
                            "NAME" => $masterName,
                            "LABEL" => $mLabel,
                            "OPTIONS" => $this->pluginConf["MASTER_INSTANCE_CONFIG"]
                        ),
                        $slaveName => array(
                            "NAME" => $slaveName,
                            "LABEL" => $sLabel,
                            "OPTIONS" => $this->pluginConf["SLAVE_INSTANCE_CONFIG"]
                        ),
                    )
                );
                // MERGE BASIC AUTH OPTIONS FROM MASTER
                $masterMainAuthOptions = array();
                $keys = array("TRANSMIT_CLEAR_PASS", "AUTOCREATE_AJXPUSER", "LOGIN_REDIRECT", "AJXP_ADMIN_LOGIN");
                if (is_array($this->pluginConf["MASTER_INSTANCE_CONFIG"])) {
                    foreach ($keys as $key) {
                        if (isSet($this->pluginConf["MASTER_INSTANCE_CONFIG"][$key])) {
                            $masterMainAuthOptions[$key] = $this->pluginConf["MASTER_INSTANCE_CONFIG"][$key];
                        }
                    }
                }
                $newOptions = array_merge($newOptions, $masterMainAuthOptions);
                self::$authStorageImpl = ConfService::instanciatePluginFromGlobalParams($newOptions, "AbstractAuthDriver");
                AJXP_PluginsService::getInstance()->setPluginUniqueActiveForType("auth", self::$authStorageImpl->getName(), self::$authStorageImpl);

            } else {
                self::$authStorageImpl = ConfService::instanciatePluginFromGlobalParams($this->pluginConf["MASTER_INSTANCE_CONFIG"], "AbstractAuthDriver");
                AJXP_PluginsService::getInstance()->setPluginUniqueActiveForType("auth", self::$authStorageImpl->getName());
            }
        }
        return self::$authStorageImpl;
    }


}
