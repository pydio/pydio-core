<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

namespace Pydio\Action\Update;

use DOMNode;
use DOMXPath;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class UpdateController extends Plugin
{
    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        if ($ctx->hasUser() && $ctx->getUser()->getGroupPath() !== "/") {
            $this->enabled = false;
        }
    }

    /**
     * Parse
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(\Pydio\Core\Model\ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if ($this->pluginConf["ENABLE_324_IMPORT"] == true) return;

        if ($contribNode->nodeName != "actions") return;
        $actionXpath = new DOMXPath($contribNode->ownerDocument);
        $compressNodeList = $actionXpath->query('action[@name="import_from_324"]', $contribNode);
        if (!$compressNodeList->length) return;
        $compressNode = $compressNodeList->item(0);
        $contribNode->removeChild($compressNode);

        $compressNodeList = $actionXpath->query('action[@name="migrate_metaserial"]', $contribNode);
        if (!$compressNodeList->length) return;
        $compressNode = $compressNodeList->item(0);
        $contribNode->removeChild($compressNode);
    }

    /**
     * @param $action
     * @param $httpVars
     * @param $fileVars
     * @param ContextInterface $contextInterface
     * @throws \Exception
     * @throws \Pydio\Core\Exception\AuthRequiredException
     */
    public function switchAction($action, $httpVars, $fileVars, \Pydio\Core\Model\ContextInterface $contextInterface)
    {
        $loggedUser = $contextInterface->getUser();
        if (UsersService::usersEnabled() && !$loggedUser->isAdmin()) {
            throw new \Pydio\Core\Exception\AuthRequiredException();
        }
        require_once(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/action.updater/UpgradeManager.php");
        if (!empty($this->pluginConf["PROXY_HOST"]) || !empty($this->pluginConf["UPDATE_SITE_USER"])) {
            UpgradeManager::configureProxy(
                $this->pluginConf["PROXY_HOST"],
                $this->pluginConf["PROXY_USER"],
                $this->pluginConf["PROXY_PASS"],
                $this->pluginConf["UPDATE_SITE_USER"],
                $this->pluginConf["UPDATE_SITE_PASS"]
            );
        }

        switch ($action) {

            case "migrate_metaserial":

                $dryRun = !isSet($httpVars["real_run"]);
                UpgradeManager::migrateMetaSerialPlugin($contextInterface, $httpVars["repository_id"], $dryRun);

                break;

            case "get_upgrade_path":

                header("Content-type: application/json");
                print UpgradeManager::getUpgradePath($this->pluginConf["UPDATE_SITE"], "json", $this->pluginConf["UPDATE_CHANNEL"]);

                break;

            case "display_upgrade_note":

                //$url = $httpVars["url"];
                $data = UpgradeManager::getUpgradePath($this->pluginConf["UPDATE_SITE"], "php", $this->pluginConf["UPDATE_CHANNEL"]);
                $url = $data["latest_note"];
                $context = UpgradeManager::getContext();
                if ($context != null) {
                    $content = file_get_contents($url, null, $context);
                } else {
                    $content = file_get_contents($url);
                }
                echo $content;

                break;

            case "test_upgrade_scripts":

                if (!AJXP_SERVER_DEBUG) {
                    break;
                }
                $upgrader = new UpgradeManager("", "", "");
                $upgrader->testUpgradeScripts();

                break;

            case "perform_upgrade" :

                ApplicationState::safeIniSet("output_buffering", "Off");
                if (AJXP_PACKAGING != "zip") {
                    $lang = LocaleService::getLanguage();
                    $file = $this->getBaseDir() . "/howto/linux_en.html";
                    if ($lang != "en" && is_file($this->getBaseDir() . "/howto/linux_$lang.html")) {
                        $file = $this->getBaseDir() . "/howto/linux_$lang.html";
                    }
                    $content = file_get_contents($file);
                    print $content;
                    break;
                }
                $res = UpgradeManager::getUpgradePath($this->pluginConf["UPDATE_SITE"], "php", $this->pluginConf["UPDATE_CHANNEL"]);
                if (!count($res["packages"])) {
                    print("No update is necessary!");
                    break;
                }
                $selectedIndex = intval($httpVars['package_index']);
                include(dirname(__FILE__) . "/output_head.html");
                $errors = false;
                foreach ($res["packages"] as $index => $zipPackage) {
                    if($index > $selectedIndex){
                        break;
                    }
                    print("<div class='main_step'>Applying upgrade " . basename($zipPackage) . "</div>");
                    $u = new UpgradeManager(
                        $zipPackage,
                        $res["hashes"][$index],
                        $res["hash_method"],
                        explode(",", $this->pluginConf["PRESERVE_FILES"])
                    );
                    while ($u->hasNextStep()) {
                        set_time_limit(180);
                        print("<div class='upgrade_step'><div class='upgrade_title'>" . $u->currentStepTitle . "</div>");
                        $u->execute();
                        if ($u->error != null) {
                            print("<div class='upgrade_result error'>- Error : " . $u->error . "</div>");
                            $errors = true;
                            break;
                        } else {
                            print("<div class='upgrade_result success'>- " . $u->result . "</div>");
                        }
                        print("</div>");
                        // FLUSH OUTPUT, SCROLL DOWN
                        print str_repeat(' ', 300);
                        print('<script type="text/javascript">doScroll();</script>');
                        flush();
                        sleep(0.5);
                    }
                    if ($errors) break;
                }
                if(!$errors){
                    print('<script type="text/javascript">replaceTop();</script>');
                    print str_repeat(' ', 300);
                    flush();
                }

                break;


        }

    }

}
