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

namespace Pydio\Share\Legacy;

use MetaWatchRegister;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Filter\ContentFilter;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Log\Core\AJXP_Logger;
use Pydio\Share\Model\ShareLink;
use Pydio\Share\ShareCenter;
use Pydio\Share\Store\ShareRightsManager;
use Pydio\Share\Store\ShareStore;
use Pydio\Share\View\MinisiteRenderer;
use Pydio\Share\View\PublicAccessManager;

defined('AJXP_EXEC') or die('Access not allowed');


class LegacyPubliclet
{

    private static function renderError($data, $hash, $message = null){
        MinisiteRenderer::renderError($data, $hash, $message);
    }

    /**
     * @param array $data
     * @param array $options
     * @param ShareStore $shareStore
     */
    public static function render($data, $options, $shareStore){

        self::renderError($data, "false", "Link is deprecated and should be migrated to the new format.");
        
        /*
        if(isset($data["SECURITY_MODIFIED"]) && $data["SECURITY_MODIFIED"] === true){
            self::renderError($data, "false");
            return;
        }
        // create driver from $data
        $className = $data["DRIVER"]."AccessDriver";
        $u = parse_url($_SERVER["REQUEST_URI"]);
        $shortHash = pathinfo(basename($u["path"]), PATHINFO_FILENAME);

        // Load language messages
        $language = ConfService::getLanguage();
        if (isSet($_GET["lang"])) {
            $language = basename($_GET["lang"]);
        }
        $messages = array();
        if (is_file(dirname(__FILE__)."/res/i18n/".$language.".php")) {
            include(dirname(__FILE__)."/res/i18n/".$language.".php");
        } else {
            include(dirname(__FILE__)."/res/i18n/en.php");
        }
        if(isSet($mess)) {
            $messages = $mess;
        }

        $AJXP_LINK_HAS_PASSWORD = false;
        $AJXP_LINK_BASENAME = TextEncoder::toUTF8(basename($data["FILE_PATH"]));
        PluginsService::getInstance()->initActivePlugins();

        ConfService::setLanguage($language);
        $mess = ConfService::getMessages();
        if ($shareStore->isShareExpired($shortHash, $data))
        {
            self::renderError(array(), $shortHash, $mess["share_center.165"]);
            return;
        }


        $customs = array("title", "legend", "legend_pass", "background_attributes_1","text_color", "background_color", "textshadow_color");
        $images = array("button", "background_1");
        $confs = $options;
        $confs["CUSTOM_SHAREPAGE_BACKGROUND_ATTRIBUTES_1"] = "background-repeat:repeat;background-position:50% 50%;";
        $confs["CUSTOM_SHAREPAGE_BACKGROUND_1"] = "plugins/action.share/res/hi-res/02.jpg";
        $confs["CUSTOM_SHAREPAGE_TEXT_COLOR"] = "#ffffff";
        $confs["CUSTOM_SHAREPAGE_TEXTSHADOW_COLOR"] = "rgba(0,0,0,5)";
        foreach ($customs as $custom) {
            $varName = "CUSTOM_SHAREPAGE_".strtoupper($custom);
            $$varName = $confs[$varName];
        }
        $dlFolder = realpath(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"));
        foreach ($images as $custom) {
            $varName = "CUSTOM_SHAREPAGE_".strtoupper($custom);
            if (!empty($confs[$varName])) {
                if (strpos($confs[$varName], "plugins/") === 0 && is_file(AJXP_INSTALL_PATH."/".$confs[$varName])) {
                    $realFile = AJXP_INSTALL_PATH."/".$confs[$varName];
                    copy($realFile, $dlFolder."/binary-".basename($realFile));
                    $$varName = "binary-".basename($realFile);
                } else {
                    $$varName = "binary-".$confs[$varName];
                    if(is_file($dlFolder."/binary-".$confs[$varName])) continue;
                    $copiedImageName = $dlFolder."/binary-".$confs[$varName];
                    $imgFile = fopen($copiedImageName, "wb");
                    ConfService::getConfStorageImpl()->loadBinary(array(), $confs[$varName], $imgFile);
                    fclose($imgFile);
                }

            }
        }

        HTMLWriter::charsetHeader();
        // Check password
        if (strlen($data["PASSWORD"])) {
            if (!isSet($_POST['password']) || ($_POST['password'] != $data["PASSWORD"])) {
                $AJXP_LINK_HAS_PASSWORD = true;
                $AJXP_LINK_WRONG_PASSWORD = (isSet($_POST['password']) && ($_POST['password'] != $data["PASSWORD"]));
                include (AJXP_INSTALL_PATH."/plugins/action.share/res/public_links.php");
                $res = ('<div style="position: absolute;z-index: 10000; bottom: 0; right: 0; color: #666;font-family: HelveticaNeue-Light,Helvetica Neue Light,Helvetica Neue,Helvetica,Arial,Lucida Grande,sans-serif;font-size: 13px;text-align: right;padding: 6px; line-height: 20px;text-shadow: 0px 1px 0px white;" class="no_select_bg"><br>Build your own box with Pydio : <a style="color: #000000;" target="_blank" href="http://pyd.io/">http://pyd.io/</a><br/>Community - Free non supported version © C. du Jeu 2008-2014 </div>');
                Controller::applyHook("tpl.filter_html", array(&$res));
                echo($res);
                return;
            }
        } else {
            if (!isSet($_GET["dl"])) {
                include (AJXP_INSTALL_PATH."/plugins/action.share/res/public_links.php");
                $res = '<div style="position: absolute;z-index: 10000; bottom: 0; right: 0; color: #666;font-family: HelveticaNeue-Light,Helvetica Neue Light,Helvetica Neue,Helvetica,Arial,Lucida Grande,sans-serif;font-size: 13px;text-align: right;padding: 6px; line-height: 20px;text-shadow: 0px 1px 0px white;" class="no_select_bg"><br>Build your own box with Pydio : <a style="color: #000000;" target="_blank" href="http://pyd.io/">http://pyd.io/</a><br/>Community - Free non supported version © C. du Jeu 2008-2014 </div>';
                Controller::applyHook("tpl.filter_html", array(&$res));
                echo($res);
                return;
            }
        }
        $filePath = AJXP_INSTALL_PATH."/plugins/access.".$data["DRIVER"]."/class.".$className.".php";
        if (!is_file($filePath)) {
            die("Warning, cannot find driver for conf storage! ($className, $filePath)");
        }
        require_once($filePath);
        $driver = new $className($data["PLUGIN_ID"], $data["BASE_DIR"]);
        $driver->loadManifest();

        //$hash = md5(serialize($data));
        $shareStore->incrementDownloadCounter($shortHash);

        //AuthService::logUser($data["OWNER_ID"], "", true);
        AuthService::logTemporaryUser($data["OWNER_ID"], $shortHash);
        if (isSet($data["SAFE_USER"]) && isSet($data["SAFE_PASS"])) {
            // FORCE SESSION MODE
            AJXP_Safe::getInstance()->forceSessionCredentialsUsage();
            AJXP_Safe::storeCredentials($data["SAFE_USER"], $data["SAFE_PASS"]);
        }

        $repoObject = $data["REPOSITORY"];
        ConfService::switchRootDir($repoObject->getId());
        ConfService::loadRepositoryDriver();
        PluginsService::getInstance()->initActivePlugins();
        try {
            $params = array("file" => TextEncoder::toUTF8($data["FILE_PATH"]));
            if (isSet($data["PLUGINS_DATA"])) {
                $params["PLUGINS_DATA"] = $data["PLUGINS_DATA"];
            }
            if (isset($_GET["ct"]) && $_GET["ct"] == "true") {
                $mime = pathinfo($params["file"], PATHINFO_EXTENSION);
                $editors = PluginsService::searchAllManifests("//editor[contains(@mimes,'$mime') and @previewProvider='true']", "node", true, true, false);
                if (count($editors)) {
                    foreach ($editors as $editor) {
                        $xPath = new DOMXPath($editor->ownerDocument);
                        $callbacks = $xPath->query("//action[@contentTypedProvider]", $editor);
                        if ($callbacks->length) {
                            $data["ACTION"] = $callbacks->item(0)->getAttribute("name");
                            if($data["ACTION"] == "audio_proxy") $params["file"] = base64_encode($params["file"]);
                            break;
                        }
                    }
                }
            }
            Controller::findActionAndApply($data["ACTION"], $params, null);
            register_shutdown_function(function() use($shortHash){
                AuthService::clearTemporaryUser($shortHash);
            });
        } catch (Exception $e) {
            AuthService::clearTemporaryUser($shortHash);
            die($e->getMessage());
        }
        */

    }


    /** Cypher the publiclet object data and write to disk.
     * @param array $data The publiclet data array to write
    * The data array must have the following keys:
    * - DRIVER      The driver used to get the file's content
    * - OPTIONS     The driver options to be successfully constructed (usually, the user and password)
    * - FILE_PATH   The path to the file's content
    * - PASSWORD    If set, the written publiclet will ask for this password before sending the content
    * - ACTION      If set, action to perform
    * - USER        If set, the AJXP user
    * - EXPIRE_TIME If set, the publiclet will deny downloading after this time, and probably self destruct.
     *               - AUTHOR_WATCH If set, will post notifications for the publiclet author each time the file is loaded
     * @param AbstractAccessDriver $accessDriver
     * @param Repository $repository
     * @param ShareStore $shareStore
     * @param PublicAccessManager $publicAccessManager
     * @return string|array An array containing the hash (0) and the generated url (1)
     */
    public function writePubliclet(&$data, $accessDriver, $repository, $shareStore, $publicAccessManager)
    {
        $downloadFolder = $publicAccessManager->getPublicDownloadFolder();
        if (!is_dir($downloadFolder)) {
            return "ERROR : Public URL folder does not exist!";
        }
        if (!function_exists("mcrypt_create_iv")) {
            return "ERROR : MCrypt must be installed to use publiclets!";
        }
        $data["PLUGIN_ID"] = $accessDriver->getId();
        $data["BASE_DIR"] = $accessDriver->getBaseDir();
        //$data["REPOSITORY"] = $repository;
        if (AuthService::usersEnabled()) {
            $data["OWNER_ID"] = AuthService::getLoggedUser()->getId();
        }
        $shareStore->storeSafeCredentialsIfNeeded($data, $accessDriver, $repository);

        // Force expanded path in publiclet
        $copy = clone $repository;
        $copy->addOption("PATH", $repository->getOption("PATH"));
        $data["REPOSITORY"] = $copy;
        if ($data["ACTION"] == "") $data["ACTION"] = "download";

        try{
            $hash = $shareStore->storeShare($repository->getId(), $data, "publiclet");
        }catch(\Exception $e){
            return $e->getMessage();
        }

        $shareStore->resetDownloadCounter($hash, AuthService::getLoggedUser()->getId());
        $url = $publicAccessManager->buildPublicLink($hash);
        AJXP_Logger::log2(LOG_LEVEL_INFO, __CLASS__, "New Share", array(
            "file" => "'".$copy->display.":/".$data['FILE_PATH']."'",
            "files" => "'".$copy->display.":/".$data['FILE_PATH']."'",
            "url" => $url,
            "expiration" => $data['EXPIRE_TIME'],
            "limit" => $data['DOWNLOAD_LIMIT'],
            "repo_uuid" => $copy->uuid
        ));
        Controller::applyHook("node.share.create", array(
            'type' => 'file',
            'repository' => &$copy,
            'accessDriver' => &$accessDriver,
            'data' => &$data,
            'url' => $url,
        ));
        return array($hash, $url);
    }

    /**
     * @param string $shareId
     * @param ShareStore $shareStore
     * @param PublicAccessManager $publicAccessManager
     * @param MetaWatchRegister|null $watcher
     * @return array|false
     * @throws \Exception
     */
    public static function publicletToJson($shareId, $shareMeta, $shareStore, $publicAccessManager, $watcher, $node){

        $messages = ConfService::getMessages();
        $elementWatch = false;

        $pData = $shareStore->loadShare($shareId);
        if (!count($pData)) {
            return false;
        }
        foreach($shareStore->modifiableShareKeys as $key){
            if(isSet($pData[$key])) $shareMeta[$key] = $pData[$key];
        }
        if ($pData["OWNER_ID"] != AuthService::getLoggedUser()->getId() && !AuthService::getLoggedUser()->isAdmin()) {
            throw new \Exception($messages["share_center.48"]);
        }
        if (isSet($shareMeta["short_form_url"])) {
            $link = $shareMeta["short_form_url"];
        } else {
            $link = $publicAccessManager->buildPublicLink($shareId);
        }
        if ($watcher != false && $node != null) {
            $result = array();
            $elementWatch = $watcher->hasWatchOnNode(
                $node,
                AuthService::getLoggedUser()->getId(),
                MetaWatchRegister::$META_WATCH_USERS_NAMESPACE,
                $result
            );
            if ($elementWatch && !in_array($shareId, $result)) {
                $elementWatch = false;
            }
        }
        $jsonData = array_merge(array(
            "element_id"       => $shareId,
            "publiclet_link"   => $link,
            "download_counter" => 0,
            "download_limit"   => $pData["DOWNLOAD_LIMIT"],
            "expire_time"      => ($pData["EXPIRE_TIME"]!=0?date($messages["date_format"], $pData["EXPIRE_TIME"]):0),
            "has_password"     => (!empty($pData["PASSWORD"])),
            "element_watch"    => $elementWatch,
            "is_expired"       => ShareLink::isShareExpired($pData)
        ), $shareMeta);

        return $jsonData;
    }

    /**
     * @param ContextInterface $ctx
     * @param ShareCenter $shareCenter
     * @param ShareStore $shareStore
     * @param ShareRightsManager $shareRightManager
     */
    public static function migrateLegacyMeta(ContextInterface $ctx, $shareCenter, $shareStore, $shareRightManager, $dryRun = true){
        $metaStoreDir = AJXP_DATA_PATH."/plugins/metastore.serial";
        $publicFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        // TODO 1: Check all metastores of all repositories?
        // TODO 2: load $publicFolder/.ajxp_publiclet_counters.ser and reassign download counts
        $metastores = glob($metaStoreDir."/ajxp_meta_0");
        if($dryRun){
            print("RUNNING A DRY RUN FOR META MIGRATION");
        }
        foreach($metastores as $store){
            if(strpos($store, ".bak") !== false) continue;
            // Backup store
            if(!$dryRun){
                copy($store, $store.".bak");
            }

            $data = unserialize(file_get_contents($store));
            foreach($data as $filePath => &$metadata){



                foreach($metadata as $userName => &$meta){
                    if(!AuthService::userExists($userName)){
                        continue;
                    }
                    $userObject = ConfService::getConfStorageImpl()->createUserObject($userName);

                    if(isSet($meta["ajxp_shared"]) && isSet($meta["ajxp_shared"]["element"])){
                        print("\n\nItem $filePath requires upgrade :");
                        $share = $meta["ajxp_shared"];
                        $element = $meta["ajxp_shared"]["element"];
                        if(is_array($element)) $element = array_shift(array_keys($element));// Take the first one only
                        $legacyLinkFile = $publicFolder."/".$element.".php";
                        if(file_exists($legacyLinkFile)){
                            // Load file, move it to DB and move the meta
                            $publiclet = $shareStore->loadShare($element);
                            if($publiclet === false){
                                print("\n--Could not load publiclet $element, skipping");
                                continue;
                            }
                            rename($legacyLinkFile, $legacyLinkFile.".migrated");
                            if(isSet($share["minisite"])){
                                print("\n--Migrate legacy minisite to new minisite?");
                                try{
                                    $sharedRepoId = $publiclet["REPOSITORY"];
                                    $sharedRepo = ConfService::getRepositoryById($sharedRepoId);
                                    if($sharedRepo == null){
                                        print("\n--ERROR: Cannot find repository with id ".$sharedRepoId);
                                        continue;
                                    }
                                    $shareLink = new ShareLink($shareStore, $publiclet);
                                    $user = $shareLink->getUniqueUser();
                                    if(AuthService::userExists($user)){
                                        $userObject = ConfService::getConfStorageImpl()->createUserObject($user);
                                        $userObject->setHidden(true);
                                        print("\n--Should set existing user $user as hidden");
                                        if(!$dryRun){
                                            $userObject->save();
                                        }
                                    }
                                    $shareLink->parseHttpVars(["custom_handle" => $element]);
                                    $shareLink->setParentRepositoryId($sharedRepo->getParentId());
                                    print("\n--Creating the following share object");
                                    print_r($shareLink->getJsonData($shareCenter->getPublicAccessManager(), ConfService::getMessages()));
                                    if(!$dryRun){
                                        $shareLink->save();
                                    }
                                    $meta["ajxp_shared"] = ["shares" => [$element => ["type" => "minisite"], $sharedRepoId => ["type" => "repository"]]];
                                }catch(\Exception $e){
                                    print("\n-- Error ".$e->getMessage());
                                }

                            }else{
                                print("\n--Should migrate legacy link to new minisite with ContentFilter");

                                try{
                                    $link = new ShareLink($shareStore);
                                    $link->setOwnerId($userName);
                                    $parameters = array("custom_handle" => $element, "simple_right_download" => true);
                                    if(isSet($publiclet["EXPIRE_TIME"])) $parameters["expiration"] = $publiclet["EXPIRE_TIME"];
                                    if(isSet($publiclet["DOWNLOAD_LIMIT"])) $parameters["downloadlimit"] = $publiclet["DOWNLOAD_LIMIT"];
                                    $link->parseHttpVars($parameters);
                                    /**
                                     * @var Repository $parentRepositoryObject
                                     */
                                    $parentRepositoryObject = $publiclet["REPOSITORY"];

                                    /**
                                     * @var AbstractAccessDriver $driverInstance
                                     */
                                    $driverInstance = PluginsService::findPlugin("access", $parentRepositoryObject->getAccessType());
                                    if(empty($driverInstance)){
                                        print("\n-- ERROR: Cannot find driver instance!");
                                        continue;
                                    }
                                    $options = $driverInstance->makeSharedRepositoryOptions(["file" => "/"], $parentRepositoryObject);
                                    $options["SHARE_ACCESS"] = "private";
                                    $newRepo = $parentRepositoryObject->createSharedChild(
                                        basename($filePath),
                                        $options,
                                        $parentRepositoryObject->getId(),
                                        $userObject->getId(),
                                        null
                                    );
                                    $gPath = $userObject->getGroupPath();
                                    if (!empty($gPath) && !ConfService::getCoreConf("CROSSUSERS_ALLGROUPS", "conf")) {
                                        $newRepo->setGroupPath($gPath);
                                    }
                                    $newRepo->setDescription("");
                                    // Smells like dirty hack!
                                    $newRepo->options["PATH"] = TextEncoder::fromStorageEncoding($newRepo->options["PATH"]);

                                    $newRepo->setContentFilter(new ContentFilter([new AJXP_Node("pydio://".$parentRepositoryObject->getId().$filePath)]));
                                    if(!$dryRun){
                                        ConfService::addRepository($newRepo);
                                    }

                                    $hiddenUserEntry = $shareRightManager->prepareSharedUserEntry(
                                        ["simple_right_read" => true, "simple_right_download" => true],
                                        $link, false, null);

                                    $selection = new UserSelection($parentRepositoryObject, []);
                                    $selection->addFile($filePath);
                                    if(!$dryRun){
                                        $shareRightManager->assignSharedRepositoryPermissions(
                                            $parentRepositoryObject,
                                            $newRepo,
                                            false,
                                            [$hiddenUserEntry["ID"] => $hiddenUserEntry], [],
                                            $selection
                                        );
                                    }
                                    $link->setParentRepositoryId($parentRepositoryObject->getId());
                                    $link->attachToRepository($newRepo->getId());
                                    print("\n-- Should save following LINK: ");
                                    print_r($link->getJsonData($shareCenter->getPublicAccessManager(), ConfService::getMessages()));
                                    if(!$dryRun){
                                        $hash = $link->save();
                                    }

                                    // UPDATE METADATA
                                    $meta["ajxp_shared"] = ["shares" => [$element => array("type" => "minisite")]];

                                }catch(\Exception $e){
                                    print("\n-- ERROR: ".$e->getMessage());
                                }


                            }
                            if($dryRun){
                                rename($legacyLinkFile.".migrated", $legacyLinkFile);
                            }
                            continue;
                        }else{
                            //
                            // File does not exists, remove meta
                            //
                            unset($meta["ajxp_shared"]);
                        }


                        $repo = ConfService::getRepositoryById($element);
                        if($repo !== null){
                            print("\n--Shared repository: just metadata");
                            // Shared repo, migrating the meta should be enough
                            $meta["ajxp_shared"] = array("shares" => [$element => array("type" => "repository")]);
                        }
                    }
                }
            }
            print("\n\n SHOULD NOW UPDATE METADATA WITH FOLLOWING :");
            print_r($data);
            if(!$dryRun){
                file_put_contents($store, serialize($data));
            }
        }
    }

}