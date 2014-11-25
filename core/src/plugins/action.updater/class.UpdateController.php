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
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class UpdateController extends AJXP_Plugin
{
    public function init($options)
    {
        parent::init($options);
        $u = AuthService::getLoggedUser();
        if($u == null) return;
        if ($u->getGroupPath() != "/") {
            $this->enabled = false;
        }
    }

    /**
     * Parse
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if($this->pluginConf["ENABLE_324_IMPORT"] == true) return;

        if($contribNode->nodeName != "actions") return ;
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $compressNodeList = $actionXpath->query('action[@name="import_from_324"]', $contribNode);
        if(!$compressNodeList->length) return ;
        unset($this->actions["import_from_324"]);
        $compressNode = $compressNodeList->item(0);
        $contribNode->removeChild($compressNode);

        $compressNodeList = $actionXpath->query('action[@name="migrate_metaserial"]', $contribNode);
        if(!$compressNodeList->length) return ;
        unset($this->actions["import_from_324"]);
        $compressNode = $compressNodeList->item(0);
        $contribNode->removeChild($compressNode);
    }

    public function switchAction($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return;
        $loggedUser = AuthService::getLoggedUser();
        if(AuthService::usersEnabled() && !$loggedUser->isAdmin()) return ;
        require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.updater/class.AjaXplorerUpgrader.php");
        if (!empty($this->pluginConf["PROXY_HOST"])) {
            AjaXplorerUpgrader::configureProxy(
                $this->pluginConf["PROXY_HOST"],
                $this->pluginConf["PROXY_USER"],
                $this->pluginConf["PROXY_PASS"]
            );
        }

        switch ($action) {

            case "migrate_metaserial":

                $dryRun = !isSet($httpVars["real_run"]);
                AjaXplorerUpgrader::migrateMetaSerialPlugin($httpVars["repository_id"], $dryRun);

            break;

            case "get_upgrade_path":

                header("Content-type: application/json");
                print AjaXplorerUpgrader::getUpgradePath($this->pluginConf["UPDATE_SITE"], "json", $this->pluginConf["UPDATE_CHANNEL"]);

            break;

            case "test_upgrade_scripts":

                if(!AJXP_SERVER_DEBUG
                    || AuthService::getLoggedUser() == null
                    || !AuthService::getLoggedUser()->isAdmin()){
                    break;
                }
                $upgrader = new AjaXplorerUpgrader("", "", "");
                $upgrader->testUpgradeScripts();

            break;

            case "perform_upgrade" :

                AJXP_Utils::safeIniSet("output_buffering", "Off");
                if (AJXP_PACKAGING != "zip") {
                    print "Your installation is managed directly via os packages, you should not upgrade manually.";
                    break;
                }
                $res = AjaXplorerUpgrader::getUpgradePath($this->pluginConf["UPDATE_SITE"], "php", $this->pluginConf["UPDATE_CHANNEL"]);
                if (!count($res["packages"])) {
                    print("No update is necessary!");
                    break;
                }
                include(dirname(__FILE__)."/output_head.html");
                foreach ($res["packages"] as $index => $zipPackage) {
                    print("<div class='main_step'>Applying upgrade ".basename($zipPackage)."</div>");
                    $u = new AjaXplorerUpgrader(
                        $zipPackage,
                        $res["hashes"][$index],
                        $res["hash_method"],
                        explode(",",$this->pluginConf["PRESERVE_FILES"])
                    );
                    $errors = false;
                    while ($u->hasNextStep()) {
                        set_time_limit(180);
                        print("<div class='upgrade_step'><div class='upgrade_title'>".$u->currentStepTitle."</div>");
                        $u->execute();
                        if ($u->error != null) {
                            print("<div class='upgrade_result error'>- Error : ".$u->error."</div>");
                            $errors = true;
                            break;
                        } else {
                            print("<div class='upgrade_result success'>- ".$u->result."</div>");
                        }
                        print("</div>");
                        // FLUSH OUTPUT, SCROLL DOWN
                        print str_repeat(' ',300);
                        print('<script type="text/javascript">doScroll();</script>');
                        flush();
                        sleep(0.5);
                    }
                    if($errors) break;
                }
                print('<script type="text/javascript">replaceTop();</script>');
                print str_repeat(' ',300);
                flush();


            break;


        }

    }

}
