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

namespace Pydio\Share\Legacy;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Filter\ContentFilter;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Access\Meta\Watch\WatchRegister;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\Services\ConfService;

use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\UsersService;

use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Share\Model\ShareLink;
use Pydio\Share\ShareCenter;
use Pydio\Share\Store\ShareRightsManager;
use Pydio\Share\Store\ShareStore;
use Pydio\Share\View\MinisiteRenderer;
use Pydio\Share\View\PublicAccessManager;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class LegacyPubliclet
 * Model for links generated in old versions of Pydio when they
 * were stored in small php files on disk instead of inside Database
 *
 * @package Pydio\Share\Legacy
 */
class LegacyPubliclet
{

    /**
     * @param $data
     * @param $hash
     * @param null $message
     */
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
        
    }


    /**
     * @param ContextInterface $ctx
     * @param string $shareId
     * @param $shareMeta
     * @param ShareStore $shareStore
     * @param PublicAccessManager $publicAccessManager
     * @param WatchRegister|null $watcher
     * @param $node
     * @return array|false
     * @throws \Exception
     */
    public static function publicletToJson(ContextInterface $ctx, $shareId, $shareMeta, $shareStore, $publicAccessManager, $watcher, $node){

        $messages = LocaleService::getMessages();
        $elementWatch = false;

        $pData = $shareStore->loadShare($shareId);
        if (!count($pData)) {
            return false;
        }
        foreach($shareStore->modifiableShareKeys as $key){
            if(isSet($pData[$key])) $shareMeta[$key] = $pData[$key];
        }
        if ($pData["OWNER_ID"] != $ctx->getUser()->getId() && !$ctx->getUser()->isAdmin()) {
            throw new \Exception($messages["share_center.48"]);
        }
        if (isSet($shareMeta["short_form_url"])) {
            $link = $shareMeta["short_form_url"];
        } else {
            $link = $publicAccessManager->buildPublicLink($shareId);
        }
        if ($watcher != false && $node != null) {
            $result = [];
            $elementWatch = $watcher->hasWatchOnNode(
                $node,
                $ctx->getUser()->getId(),
                WatchRegister::$META_WATCH_USERS_NAMESPACE,
                $result
            );
            if ($elementWatch && !in_array($shareId, $result)) {
                $elementWatch = false;
            }
        }
        $jsonData = array_merge([
            "element_id"       => $shareId,
            "publiclet_link"   => $link,
            "download_counter" => 0,
            "download_limit"   => $pData["DOWNLOAD_LIMIT"],
            "expire_time"      => ($pData["EXPIRE_TIME"]!=0?date($messages["date_format"], $pData["EXPIRE_TIME"]):0),
            "has_password"     => (!empty($pData["PASSWORD"])),
            "element_watch"    => $elementWatch,
            "is_expired"       => ShareLink::isShareExpired($pData)
        ], $shareMeta);

        return $jsonData;
    }

    /**
     * @param ContextInterface $ctx
     * @param ShareCenter $shareCenter
     * @param ShareStore $shareStore
     * @param ShareRightsManager $shareRightManager
     * @param bool $dryRun
     * @throws \Pydio\Core\Exception\UserNotFoundException
     */
    public static function migrateLegacyMeta(ContextInterface $ctx, $shareCenter, $shareStore, $shareRightManager, $dryRun = true){
        $metaStoreDir = AJXP_DATA_PATH."/plugins/metastore.serial";
        $publicFolder = ConfService::getGlobalConf("PUBLIC_DOWNLOAD_FOLDER");
        $metastores = glob($metaStoreDir."/ajxp_meta_*");
        if($dryRun){
            print("RUNNING A DRY RUN FOR META MIGRATION");
        }
        foreach($metastores as $store){
            if(strpos($store, ".bak") !== false) continue;
            // Backup store
            if(!$dryRun){
                copy($store, $store.".bak");
            }

            $data = StringHelper::safeUnserialize(file_get_contents($store));
            foreach($data as $filePath => &$metadata){

                foreach($metadata as $userName => &$meta){
                    if(!UsersService::userExists($userName)){
                        continue;
                    }
                    $userObject = UsersService::getUserById($userName, false);

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
                                    $sharedRepo = RepositoryService::getRepositoryById($sharedRepoId);
                                    if($sharedRepo == null){
                                        print("\n--ERROR: Cannot find repository with id ".$sharedRepoId);
                                        continue;
                                    }
                                    $shareLink = new ShareLink($shareStore, $publiclet);
                                    $user = $shareLink->getUniqueUser();
                                    $oldPassword = $publiclet["PASSWORD"];
                                    if(UsersService::userExists($user)){
                                        $userObject = UsersService::getUserById($user, false);
                                        $userObject->setHidden(true);
                                        if(!empty($oldPassword)){
                                            UsersService::updatePassword($user, $oldPassword);
                                            $shareLink->setUniqueUser($user, true);
                                        }
                                        print("\n--Should set existing user $user as hidden");
                                        if(!$dryRun){
                                            $userObject->save();
                                        }
                                    }
                                    $shareLink->parseHttpVars(["custom_handle" => $element]);
                                    $shareLink->setParentRepositoryId($sharedRepo->getParentId());
                                    print("\n--Creating the following share object");
                                    print_r($shareLink->getJsonData($shareCenter->getPublicAccessManager(), LocaleService::getMessages()));
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
                                    $parameters = ["custom_handle" => $element, "simple_right_download" => true];
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
                                    $currentContext = new Context($userName);
                                    $currentContext->setRepositoryObject($parentRepositoryObject);
                                    $driverInstance = PluginsService::getInstance($currentContext)->getPluginByTypeName("access", $parentRepositoryObject->getAccessType());
                                    if(empty($driverInstance)){
                                        print("\n-- ERROR: Cannot find driver instance!");
                                        continue;
                                    }
                                    $options = $driverInstance->makeSharedRepositoryOptions($currentContext, ["file" => "/"]);
                                    $options["SHARE_ACCESS"] = "private";
                                    $newRepo = $parentRepositoryObject->createSharedChild(
                                        basename($filePath),
                                        $options,
                                        $parentRepositoryObject->getId(),
                                        $userObject->getId(),
                                        null
                                    );
                                    $gPath = $userObject->getGroupPath();
                                    if (!empty($gPath) && !ConfService::getContextConf($ctx, "CROSSUSERS_ALLGROUPS", "conf")) {
                                        $newRepo->setGroupPath($gPath);
                                    }
                                    $newRepo->setDescription("");
                                    $newRepo->setContentFilter(new ContentFilter([new AJXP_Node("pydio://".$ctx->getUser()->getId()."@".$parentRepositoryObject->getId().$filePath)]));
                                    if(!$dryRun){
                                        RepositoryService::addRepository($newRepo);
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
                                    print_r($link->getJsonData($shareCenter->getPublicAccessManager(), LocaleService::getMessages()));
                                    if(!$dryRun){
                                        $link->save();
                                    }

                                    // UPDATE METADATA
                                    $meta["ajxp_shared"] = ["shares" => [$element => ["type" => "minisite"]]];

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


                        $repo = RepositoryService::getRepositoryById($element);
                        if($repo !== null){
                            print("\n--Shared repository: just metadata");
                            // Shared repo, migrating the meta should be enough
                            $meta["ajxp_shared"] = ["shares" => [$element => ["type" => "repository"]]];
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