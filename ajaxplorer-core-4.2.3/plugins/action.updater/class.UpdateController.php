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

class UpdateController extends AJXP_Plugin {

	/**
	 * Parse
	 * @param DOMNode $contribNode
	 */
	protected function parseSpecificContributions(&$contribNode){
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

    function switchAction($action, $httpVars, $fileVars){
        if(!isSet($this->actions[$action])) return;
        $loggedUser = AuthService::getLoggedUser();
        if(AuthService::usersEnabled() && !$loggedUser->isAdmin()) return ;
        require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.updater/class.AjaXplorerUpgrader.php");
        if(!empty($this->pluginConf["PROXY_HOST"])){
            AjaXplorerUpgrader::configureProxy(
                $this->pluginConf["PROXY_HOST"],
                $this->pluginConf["PROXY_USER"],
                $this->pluginConf["PROXY_PASS"]
            );
        }

        switch ($action){


            case "import_from_324":

                $dryRun = !isSet($httpVars["real_run"]);
                AjaXplorerUpgrader::upgradeFrom324($httpVars["previous_location"], $dryRun);

            break;

            case "migrate_metaserial":

                $dryRun = !isSet($httpVars["real_run"]);
                AjaXplorerUpgrader::migrateMetaSerialPlugin($httpVars["repository_id"], $dryRun);

            break;

            case "get_upgrade_path":

                header("Content-type: application/json");
                print AjaXplorerUpgrader::getUpgradePath($this->pluginConf["UPDATE_SITE"], "json", $this->pluginConf["UPDATE_CHANNEL"]);

            break;

            case "perform_upgrade" :

                AJXP_Utils::safeIniSet("output_buffering", "Off");
                if(AJXP_PACKAGING != "zip"){
                    print "Your installation is managed directly via os packages, you should not upgrade manually.";
                    break;
                }
                $res = AjaXplorerUpgrader::getUpgradePath($this->pluginConf["UPDATE_SITE"], "php", $this->pluginConf["UPDATE_CHANNEL"]);
                if(!count($res["packages"])){
                    print("No update is necessary!");
                    break;
                }
                include(dirname(__FILE__)."/output_head.html");
                foreach($res["packages"] as $index => $zipPackage){
                    print("<div class='main_step'>Applying upgrade ".basename($zipPackage)."</div>");
                    $u = new AjaXplorerUpgrader(
                        $zipPackage,
                        $res["hashes"][$index],
                        $res["hash_method"],
                        explode(",",$this->pluginConf["PRESERVE_FILES"])
                    );
                    $errors = false;
                    while($u->hasNextStep()){
                        set_time_limit(180);
                        print("<div class='upgrade_step'><div class='upgrade_title'>".$u->currentStepTitle."</div>");
                        $u->execute();
                        if($u->error != null){
                            print("<div class='upgrade_result error'>- Error : ".$u->error."</div>");
                            $errors = true;
                            break;
                        }else{
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


            break;


        }

    }

}
