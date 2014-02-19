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

require_once("class.PublicletCounter.php");

/**
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class ShareCenter extends AJXP_Plugin
{
    /**
     * @var AbstractAccessDriver
     */
    private $accessDriver;
    /**
     * @var Repository
     */
    private $repository;
    private $urlBase;
    private $baseProtocol;

    /**
     * @var MetaStoreProvider
     */
    private $metaStore;

    /**
     * @var MetaWatchRegister
     */
    private $watcher = false;

    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if (isSet($this->actions["share"])) {
            $disableSharing = false;
            $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
            if ($downloadFolder == "") {
                $disableSharing = true;
            } else if ((!is_dir($downloadFolder) || !is_writable($downloadFolder))) {
                $this->logDebug("Disabling Public links, $downloadFolder is not writeable!", array("folder" => $downloadFolder, "is_dir" => is_dir($downloadFolder),"is_writeable" => is_writable($downloadFolder)));
                $disableSharing = true;
            } else {
                if (AuthService::usersEnabled()) {
                    $loggedUser = AuthService::getLoggedUser();
                    if ($loggedUser != null && AuthService::isReservedUserId($loggedUser->getId())) {
                        $disableSharing = true;
                    }
                } else {
                    $disableSharing = true;
                }
            }
            if ($disableSharing) {
                unset($this->actions["share"]);
                $actionXpath=new DOMXPath($contribNode->ownerDocument);
                $publicUrlNodeList = $actionXpath->query('action[@name="share"]', $contribNode);
                $publicUrlNode = $publicUrlNodeList->item(0);
                $contribNode->removeChild($publicUrlNode);
            }
        }
    }

    public function init($options)
    {
        parent::init($options);
        $this->repository = ConfService::getRepository();
        if (!is_a($this->repository->driverInstance, "AjxpWrapperProvider")) {
            return;
        }
        $this->accessDriver = $this->repository->driverInstance;
        $this->urlBase = $this->repository->driverInstance->getResourceUrl("/");
        $this->baseProtocol = array_shift(explode("://", $this->urlBase));
        if (array_key_exists("meta.watch", AJXP_PluginsService::getInstance()->getActivePlugins())) {
            $this->watcher = AJXP_PluginsService::getInstance()->getPluginById("meta.watch");
        }
    }

    public function switchAction($action, $httpVars, $fileVars)
    {
        if (!isSet($this->accessDriver)) {
            throw new Exception("Cannot find access driver!");
        }


        if ($this->accessDriver->getId() == "access.demo") {
            $errorMessage = "This is a demo, all 'write' actions are disabled!";
            if ($httpVars["sub_action"] == "delegate_repo") {
                return AJXP_XMLWriter::sendMessage(null, $errorMessage, false);
            } else {
                print($errorMessage);
            }
            return;
        }


        switch ($action) {

            //------------------------------------
            // SHARING FILE OR FOLDER
            //------------------------------------
            case "share":
                $subAction = (isSet($httpVars["sub_action"])?$httpVars["sub_action"]:"");
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $ajxpNode = new AJXP_Node($this->urlBase.$file);
                if (!file_exists($ajxpNode->getUrl())) {
                    throw new Exception("Cannot share a non-existing file: ".$ajxpNode->getUrl());
                }
                $metadata = null;

                if ($subAction == "delegate_repo") {
                    header("Content-type:text/plain");
                    $result = $this->createSharedRepository($httpVars, $this->repository, $this->accessDriver);
                    if (is_a($result, "Repository")) {
                        $metadata = array("element" => $result->getUniqueId());
                        $numResult = 200;
                    } else {
                        $numResult = $result;
                    }
                    print($numResult);
                } else if ($subAction == "create_minisite") {
                    header("Content-type:text/plain");
                    $res = $this->createSharedMinisite($httpVars, $this->repository, $this->accessDriver);
                    if (!is_array($res)) {
                        $url = $res;
                    } else {
                        list($hash, $url) = $res;
                        $metadata = array("element" => $hash, "minisite" => (isSet($httpVars["create_guest_user"])?"public":"private"));
                    }
                    print($url);
                } else {
                    $maxdownload = abs(intval($this->getFilteredOption("FILE_MAX_DOWNLOAD", $this->repository->getId())));
                    $download = isset($httpVars["downloadlimit"]) ? abs(intval($httpVars["downloadlimit"])) : 0;
                    if ($maxdownload == 0) {
                        $httpVars["downloadlimit"] = $download;
                    } elseif ($maxdownload > 0 && $download == 0) {
                        $httpVars["downloadlimit"] = $maxdownload;
                    } else {
                        $httpVars["downloadlimit"] = min($download,$maxdownload);
                    }
                    $maxexpiration = abs(intval($this->getFilteredOption("FILE_MAX_EXPIRATION", $this->repository->getId())));
                    $expiration = isset($httpVars["expiration"]) ? abs(intval($httpVars["expiration"])) : 0;
                    if ($maxexpiration == 0) {
                        $httpVars["expiration"] = $expiration;
                    } elseif ($maxexpiration > 0 && $expiration == 0) {
                        $httpVars["expiration"] = $maxexpiration;
                    } else {
                        $httpVars["expiration"] = min($expiration,$maxexpiration);
                    }

                    $data = $this->accessDriver->makePublicletOptions($file, $httpVars["password"], $httpVars["expiration"], $httpVars["downloadlimit"], $this->repository);
                    $customData = array();
                    foreach ($httpVars as $key => $value) {
                        if (substr($key, 0, strlen("PLUGINS_DATA_")) == "PLUGINS_DATA_") {
                            $customData[substr($key, strlen("PLUGINS_DATA_"))] = $value;
                        }
                    }
                    if (count($customData)) {
                        $data["PLUGINS_DATA"] = $customData;
                    }
                    list($hash, $url) = $this->writePubliclet($data, $this->accessDriver, $this->repository);
                    $metaArray = array();
                    if ($ajxpNode->hasMetaStore()) {
                        $existingMeta = $ajxpNode->retrieveMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                        if (isSet($existingMeta) && is_array($existingMeta) && array_key_exists("element", $existingMeta)) {
                            if (is_string($existingMeta["element"])) {
                                $metaArray[$existingMeta["element"]] = array();
                            } else {
                                $metaArray = $existingMeta["element"];
                            }
                        }
                    }
                    $metaArray[$hash] = array();
                    $metadata = array("element" => $metaArray);
                    if (isSet($httpVars["format"]) && $httpVars["format"] == "json") {
                        header("Content-type:application/json");
                        echo json_encode(array("element_id" => $hash, "publiclet_link" => $url));
                    } else {
                        header("Content-type:text/plain");
                        echo $url;
                    }
                    flush();
                }
                if ($metadata != null && $ajxpNode->hasMetaStore()) {
                    $ajxpNode->setMetadata(
                        "ajxp_shared",
                        $metadata,
                        true,
                        AJXP_METADATA_SCOPE_REPOSITORY,
                        true
                    );
                }
                AJXP_Controller::applyHook("msg.instant", array("<reload_shared_elements/>", ConfService::getRepository()->getId()));
                // as the result can be quite small (e.g error code), make sure it's output in case of OB active.
                flush();

                break;

            case "toggle_link_watch":

                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $watchValue = $httpVars["set_watch"] == "true" ? true : false;
                $folder = false;
                if (isSet($httpVars["element_type"]) && $httpVars["element_type"] == "folder") {
                    $folder = true;
                    $node = new AJXP_Node($this->baseProtocol."://".$httpVars["repository_id"]."/");
                } else {
                    $node = new AJXP_Node($this->urlBase.$file);
                }

                $metadata = $node->retrieveMetadata(
                    "ajxp_shared",
                    true,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                $elementId = $metadata["element"];
                if (isSet($httpVars["element_id"]) && is_Array($metadata["element"]) && isSet($metadata["element"][$httpVars["element_id"]])) {
                    $elementId = $httpVars["element_id"];
                }

                if ($this->watcher !== false) {
                    if (!$folder) {
                        if ($watchValue) {
                            $this->watcher->setWatchOnFolder(
                                $node,
                                AuthService::getLoggedUser()->getId(),
                                MetaWatchRegister::$META_WATCH_USERS_READ,
                                array($elementId)
                            );
                        } else {
                            $this->watcher->removeWatchFromFolder(
                                $node,
                                AuthService::getLoggedUser()->getId(),
                                true,
                                $elementId
                            );
                        }
                    } else {
                        if ($watchValue) {
                            $this->watcher->setWatchOnFolder(
                                $node,
                                AuthService::getLoggedUser()->getId(),
                                MetaWatchRegister::$META_WATCH_BOTH
                            );
                        } else {
                            $this->watcher->removeWatchFromFolder(
                                $node,
                                AuthService::getLoggedUser()->getId());
                        }
                    }
                }
                $mess = ConfService::getMessages();
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::sendMessage($mess["share_center.47"], null);
                AJXP_XMLWriter::close();

            break;

            case "load_shared_element_data":

                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $elementType = $httpVars["element_type"];
                $messages = ConfService::getMessages();
                $node = new AJXP_Node($this->urlBase.$file);

                $metadata = $node->retrieveMetadata(
                    "ajxp_shared",
                    true,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                $elementWatch = false;
                if (count($metadata)) {
                    header("Content-type:application/json");

                    if ($elementType == "file") {
                        $elements = $metadata["element"];
                        if(is_string($elements)) $elements = array($elements => true);
                        $jsonData = array();
                        foreach ($elements as $element => $elementData) {
                            if(!is_array($elementData)) $elementData = array();

                            $pData = self::loadPublicletData($element);
                            if (!count($pData)) {
                                continue;
                            }
                            if ($pData["OWNER_ID"] != AuthService::getLoggedUser()->getId()) {
                                throw new Exception($messages["share_center.48"]);
                            }
                            if (isSet($elementData["short_form_url"])) {
                                $link = $elementData["short_form_url"];
                            } else {
                                $link = $this->buildPublicletLink($element);
                            }
                            if ($this->watcher != false) {
                                $result = array();
                                $elementWatch = $this->watcher->hasWatchOnNode(
                                    $node,
                                    AuthService::getLoggedUser()->getId(),
                                    MetaWatchRegister::$META_WATCH_USERS_NAMESPACE,
                                    $result
                                );
                                if ($elementWatch && !in_array($element, $result)) {
                                    $elementWatch = false;
                                }
                            }
                            $jsonData[] = array_merge(array(
                                "element_id"       => $element,
                                "publiclet_link"   => $link,
                                "download_counter" => PublicletCounter::getCount($element),
                                "download_limit"   => $pData["DOWNLOAD_LIMIT"],
                                "expire_time"      => ($pData["EXPIRE_TIME"]!=0?date($messages["date_format"], $pData["EXPIRE_TIME"]):0),
                                "has_password"     => (!empty($pData["PASSWORD"])),
                                "element_watch"    => $elementWatch,
                            ), $elementData);

                        }
                    } else if ($elementType == "repository") {
                        if (isSet($metadata["minisite"])) {
                            $minisiteData = self::loadPublicletData($metadata["element"]);
                            $repoId = $minisiteData["REPOSITORY"];
                            $minisiteIsPublic = isSet($minisiteData["PRELOG_USER"]);
                            $dlDisabled = isSet($minisiteData["DOWNLOAD_DISABLED"]);
                            if (isSet($metadata["short_form_url"])) {
                                $minisiteLink = $metadata["short_form_url"];
                            } else {
                                $minisiteLink = $this->buildPublicletLink($metadata["element"]);
                            }
                        } else {
                            $repoId = $metadata["element"];
                        }
                        $repo = ConfService::getRepositoryById($repoId);
                        if ($repo == null || $repo->getOwner() != AuthService::getLoggedUser()->getId()) {
                            //throw new Exception($messages["share_center.48"]);
                            $jsonData = array(
                                "repositoryId"  => $repoId,
                                "label"         => "Error - Cannot find shared data",
                                "description"   => "Cannot find repository",
                                "entries"       => array(),
                                "element_watch" => false,
                                "repository_url"=> ""
                            );
                            echo json_encode($jsonData);
                            break;

                        }
                        if ($this->watcher != false) {
                            $elementWatch = $this->watcher->hasWatchOnNode(
                                new AJXP_Node($this->baseProtocol."://".$repoId."/"),
                                AuthService::getLoggedUser()->getId(),
                                MetaWatchRegister::$META_WATCH_NAMESPACE
                            );
                        }
                        $sharedEntries = $this->computeSharedRepositoryAccessRights($repoId, true, $this->urlBase.$file);

                        $jsonData = array(
                            "repositoryId"  => $repoId,
                            "label"         => $repo->getDisplay(),
                            "description"   => $repo->getDescription(),
                            "entries"       => $sharedEntries,
                            "element_watch" => $elementWatch,
                            "repository_url"=> AJXP_Utils::detectServerURL(true)."?goto=". $repo->getSlug() ."/"
                        );
                        if (isSet($minisiteData)) {
                            $jsonData["minisite"] = array(
                                "public" => $minisiteIsPublic?"true":"false",
                                "public_link" => $minisiteLink,
                                "disable_download" => $dlDisabled
                            );

                        }
                    }
                    echo json_encode($jsonData);
                }


            break;

            case "unshare":
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $ajxpNode = new AJXP_Node($this->urlBase.$file);
                $metadata = $ajxpNode->retrieveMetadata(
                    "ajxp_shared",
                    true,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                if (count($metadata)) {
                    $eType = $httpVars["element_type"];
                    if(isSet($metadata["minisite"])) $eType = "minisite";
                    $elementId = $metadata["element"];
                    $updateMeta = false;
                    if (isSet($httpVars["element_id"])) {
                        if (is_array($metadata["element"]) && isSet($metadata["element"][$httpVars['element_id']])) {
                            $elementId = $httpVars["element_id"];
                            unset($metadata["element"][$httpVars['element_id']]);
                            if(count($metadata["element"]) > 0) $updateMeta = true;
                        }
                    }
                    self::deleteSharedElement($eType, $elementId, AuthService::getLoggedUser());
                    if ($updateMeta) {
                        $ajxpNode->setMetadata("ajxp_shared", $metadata, true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                    } else {
                        $ajxpNode->removeMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                    }
                }
                AJXP_Controller::applyHook("msg.instant", array("<reload_shared_elements/>", ConfService::getRepository()->getId()));

                break;

            case "reset_counter":

                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $ajxpNode = new AJXP_Node($this->urlBase.$file);
                $metadata = $ajxpNode->retrieveMetadata(
                    "ajxp_shared",
                    true,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                if (isSet($metadata["element"][$httpVars["element_id"]])) {
                    PublicletCounter::reset($httpVars["element_id"]);
                }

            break;

            case "update_shared_element_data":

                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                if(!in_array($httpVars["p_name"], array("counter", "tags")));
                $ajxpNode = new AJXP_Node($this->urlBase.$file);
                $metadata = $ajxpNode->retrieveMetadata(
                    "ajxp_shared",
                    true,
                    AJXP_METADATA_SCOPE_REPOSITORY
                );
                if (isSet($metadata["element"][$httpVars["element_id"]])) {
                    if (!is_array($metadata["element"][$httpVars["element_id"]])) {
                        $metadata["element"][$httpVars["element_id"]] = array();
                    }
                    $metadata["element"][$httpVars["element_id"]][$httpVars["p_name"]] = $httpVars["p_value"];
                    $ajxpNode->setMetadata(
                        "ajxp_shared",
                        $metadata,
                        true,
                        AJXP_METADATA_SCOPE_REPOSITORY);
                }

            break;

            default:
            break;
        }


    }


    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    public function nodeSharedMetadata(&$ajxpNode)
    {
        if(empty($this->accessDriver) || $this->accessDriver->getId() == "access.imap") return;
        $metadata = $ajxpNode->retrieveMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
        if (count($metadata)) {
            $eType = $ajxpNode->isLeaf()?"file":"repository";
            if(isSet($metadata["minisite"])) $eType = "minisite";
            if (is_array($metadata["element"])) {
                $updateMeta = false;
                foreach ($metadata["element"] as $elementId => $elementData) {
                    if (!self::sharedElementExists($eType, $elementId)) {
                        unset($metadata["element"][$elementId]);
                        $updateMeta = true;
                        return;
                    }
                }
                if (!count($metadata["element"])) {
                    $metadata = array();
                    $updateMeta = true;
                }
                if ($updateMeta) {
                    if (count($metadata) == 0) {
                        $ajxpNode->removeMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                    } else {
                        $ajxpNode->setMetadata("ajxp_shared", $metadata, true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                    }
                }
            } else {
                if (!self::sharedElementExists($eType, $metadata["element"])) {
                    $ajxpNode->removeMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
                    return;
                }
            }
            $merge = array(
                 "ajxp_shared"      => "true",
                 "overlay_icon"     => "shared.png",
                 "overlay_class"    => "icon-share-sign"
            );
            if($eType == "minisite") $merge["ajxp_shared_minisite"] = $metadata["minisite"];
            $ajxpNode->mergeMetadata($merge, true);
        }
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @return boolean
     */
    public static function isShared($ajxpNode)
    {
        $metadata = $ajxpNode->retrieveMetadata("ajxp_shared",true);
        if (is_array($metadata) && is_array($metadata["element"])) {
            $eType = $ajxpNode->isLeaf()?"file":"repository";
            if(isSet($metadata["minisite"])) $eType = "minisite";
            foreach ($metadata["element"] as $elementId => $elementData) {
                if (self::sharedElementExists($eType, $elementId)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     *
     * Hooked to node.change, this will update the index
     * if $oldNode = null => create node $newNode
     * if $newNode = null => delete node $oldNode
     * Else copy or move oldNode to newNode.
     *
     * @param AJXP_Node $oldNode
     */
    public function updateNodeSharedData($oldNode/*, $newNode = null, $copy = false*/)
    {
        if(empty($this->accessDriver) || $this->accessDriver->getId() == "access.imap") return;
        if($oldNode == null || !$oldNode->hasMetaStore()) return;
        $metadata = $oldNode->retrieveMetadata("ajxp_shared", true);
        if (count($metadata) && !empty($metadata["element"])) {
            // TODO
            // Make sure node info is loaded, to check if it's a dir or a file.
            // Maybe could be directly embedded in metadata, to avoid having to load here.
            $oldNode->loadNodeInfo();
            try {
                $type = $oldNode->isLeaf() ? "file":"repository";
                $elementIds = array();
                if ($type == "file") {
                    if(!is_array($metadata["element"])) $elementIds[] = $metadata["element"];
                    else $elementIds = array_keys($metadata["element"]);
                } else {
                    $elementIds[]= $metadata["element"];
                }
                foreach ($elementIds as $elementId) {
                    self::deleteSharedElement(
                        $type,
                        $elementId,
                        AuthService::getLoggedUser()
                    );
                }
                $oldNode->removeMetadata("ajxp_shared", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
            } catch (Exception $e) {
                $this->logError("Exception", $e->getMessage(), $e->getTrace() );
            }
        }
    }

    /** Cypher the publiclet object data and write to disk.
     * @param Array $data The publiclet data array to write
                     The data array must have the following keys:
                     - DRIVER      The driver used to get the file's content
                     - OPTIONS     The driver options to be successfully constructed (usually, the user and password)
                     - FILE_PATH   The path to the file's content
                     - PASSWORD    If set, the written publiclet will ask for this password before sending the content
                     - ACTION      If set, action to perform
                     - USER        If set, the AJXP user
                     - EXPIRE_TIME If set, the publiclet will deny downloading after this time, and probably self destruct.
     *               - AUTHOR_WATCH If set, will post notifications for the publiclet author each time the file is loaded
     * @param AbstractAccessDriver $accessDriver
     * @param Repository $repository
     * @return array An array containing the hash (0) and the generated url (1)
    */
    public function writePubliclet(&$data, $accessDriver, $repository)
    {
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        if (!is_dir($downloadFolder)) {
            return "ERROR : Public URL folder does not exist!";
        }
        if (!function_exists("mcrypt_create_iv")) {
            return "ERROR : MCrypt must be installed to use publiclets!";
        }
        $this->initPublicFolder($downloadFolder);
        $data["PLUGIN_ID"] = $accessDriver->getId();
        $data["BASE_DIR"] = $accessDriver->getBaseDir();
        //$data["REPOSITORY"] = $repository;
        if (AuthService::usersEnabled()) {
            $data["OWNER_ID"] = AuthService::getLoggedUser()->getId();
        }
        $storeCreds = false;
        if ($repository->getOption("META_SOURCES")) {
            $options["META_SOURCES"] = $repository->getOption("META_SOURCES");
            foreach ($options["META_SOURCES"] as $metaSource) {
                if (isSet($metaSource["USE_SESSION_CREDENTIALS"]) && $metaSource["USE_SESSION_CREDENTIALS"] === true) {
                    $storeCreds = true;
                    break;
                }
            }
        }
        if ($storeCreds || $accessDriver->hasMixin("credentials_consumer")) {
            $cred = AJXP_Safe::tryLoadingCredentialsFromSources(array(), $repository);
            if (isSet($cred["user"]) && isset($cred["password"])) {
                $data["SAFE_USER"] = $cred["user"];
                $data["SAFE_PASS"] = $cred["password"];
            }
        }

        // Force expanded path in publiclet
        $copy = clone $repository;
        $copy->addOption("PATH", $repository->getOption("PATH"));
        $data["REPOSITORY"] = $copy;
        if ($data["ACTION"] == "") $data["ACTION"] = "download";
        // Create a random key
        $data["FINAL_KEY"] = md5(mt_rand().time());
        // Cypher the data with a random key
        $outputData = serialize($data);
        // Hash the data to make sure it wasn't modified
        $hash = $this->computeHash($outputData, $downloadFolder); // md5($outputData);

        $outputData = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $hash, $outputData, MCRYPT_MODE_ECB));
        $fileData = "<"."?"."php \n".
        '   require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/publicLet.inc.php"); '."\n".
        '   $id = str_replace(".php", "", basename(__FILE__)); '."\n". // Not using "" as php would replace $ inside
        '   $cypheredData = base64_decode("'.$outputData.'"); '."\n".
        '   $inputData = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $id, $cypheredData, MCRYPT_MODE_ECB), "\0");  '."\n".
        '   if (!ShareCenter::checkHash($inputData, $id)) { header("HTTP/1.0 401 Not allowed, script was modified"); exit(); } '."\n".
        '   // Ok extract the data '."\n".
        '   $data = unserialize($inputData); ShareCenter::loadPubliclet($data); ';
        if (@file_put_contents($downloadFolder."/".$hash.".php", $fileData) === FALSE) {
            return "Can't write to PUBLIC URL";
        }
        @chmod($downloadFolder."/".$hash.".php", 0755);
        PublicletCounter::reset($hash);
        $url = $this->buildPublicletLink($hash);
        $this->logInfo("New Share", array(
            "file" => "'".$copy->display.":/".$data['FILE_PATH']."'",
            "url" => $url,
            "expiration" => $data['EXPIRE_TIME'],
            "limit" => $data['DOWNLOAD_LIMIT'],
            "repo_uuid" => $copy->uuid
        ));
        AJXP_Controller::applyHook("node.share.create", array(
            'type' => 'file',
            'repository' => &$copy,
            'accessDriver' => &$accessDriver,
            'data' => &$data,
            'url' => $url,
        ));
        return array($hash, $url);
    }

    /**
     * Computes a short form of the hash, checking if it already exists in the folder,
     * in which case it increases the hashlength until there is no collision.
     * @static
     * @param String $outputData Serialized data
     * @param String|null $checkInFolder Path to folder
     * @return string
     */
    public function computeHash($outputData, $checkInFolder = null)
    {
        $length = $this->getFilteredOption("HASH_MIN_LENGTH", $this->repository->getId());
        $full =  md5($outputData);
        $starter = substr($full, 0, $length);
        if ($checkInFolder != null) {
            while (file_exists($checkInFolder.DIRECTORY_SEPARATOR.$starter.".php")) {
                $length ++;
                $starter = substr($full, 0, $length);
            }
        }
        return $starter;
    }

    /**
     * Check if the hash seems to correspond to the serialized data.
     * @static
     * @param String $outputData serialized data
     * @param String $hash Id to check
     * @return bool
     */
    public static function checkHash($outputData, $hash)
    {
        $full = md5($outputData);
        return (!empty($hash) && strpos($full, $hash."") === 0);
    }

    public function buildPublicDlURL()
    {
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $dlURL = ConfService::getCoreConf("PUBLIC_DOWNLOAD_URL");
        $langSuffix = "?lang=".ConfService::getLanguage();
        if ($dlURL != "") {
            return rtrim($dlURL, "/");
        } else {
            $fullUrl = AJXP_Utils::detectServerURL(true);
            return str_replace("\\", "/", rtrim($fullUrl, "/").rtrim(str_replace(AJXP_INSTALL_PATH, "", $downloadFolder), "/"));
        }
    }

    public function computeMinisiteToServerURL()
    {
        $minisite = parse_url($this->buildPublicDlURL(), PHP_URL_PATH) ."/a.php";
        $server = rtrim(parse_url( AJXP_Utils::detectServerURL(true), PHP_URL_PATH), "/");
        return AJXP_Utils::getTravelPath($minisite, $server);
    }

    public function buildPublicletLink($hash)
    {
        $addLang = ConfService::getLanguage() != ConfService::getCoreConf("DEFAULT_LANGUAGE");
        if ($this->getFilteredOption("USE_REWRITE_RULE", $this->repository->getId()) == true) {
            if($addLang) return $this->buildPublicDlURL()."/".$hash."-".ConfService::getLanguage();
            else return $this->buildPublicDlURL()."/".$hash;
        } else {
            if($addLang) return $this->buildPublicDlURL()."/".$hash.".php?lang=".ConfService::getLanguage();
            else return $this->buildPublicDlURL()."/".$hash.".php";
        }
    }

    public function initPublicFolder($downloadFolder)
    {
        if (is_file($downloadFolder."/grid_t.png")) {
            return;
        }
        $language = ConfService::getLanguage();
        $pDir = dirname(__FILE__);
        $messages = array();
        if (is_file($pDir."/res/i18n/".$language.".php")) {
            include($pDir."/res/i18n/".$language.".php");
        } else {
            include($pDir."/res/i18n/en.php");
        }
        if (isSet($mess)) $messages = $mess;
        $sTitle = sprintf($messages[1], ConfService::getCoreConf("APPLICATION_TITLE"));
        $sLegend = $messages[20];

        @copy($pDir."/res/dl.png", $downloadFolder."/dl.png");
        @copy($pDir."/res/favi.png", $downloadFolder."/davi.png");
        @copy($pDir."/res/grid_t.png", $downloadFolder."/grid_t.png");
        @copy($pDir."/res/button_cancel.png", $downloadFolder."/button_cancel.png");
        @copy(AJXP_INSTALL_PATH."/server/index.html", $downloadFolder."/index.html");
        $dlUrl = $this->buildPublicDlURL();
        $htaccessContent = "ErrorDocument 404 ".$dlUrl."/404.html\n<Files \".ajxp_*\">\ndeny from all\n</Files>";
        if ($this->getFilteredOption("USE_REWRITE_RULE", $this->repository->getId()) == true) {
            $path = parse_url($dlUrl, PHP_URL_PATH);
            $htaccessContent .= '
            <IfModule mod_rewrite.c>
            RewriteEngine on
            RewriteBase '.$path.'
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^([a-z0-9]+)-([a-z]+)$ $1.php?lang=$2 [QSA]
            RewriteRule ^([a-z0-9]+)$ $1.php [QSA]
            </IfModule>
            ';
        }
        file_put_contents($downloadFolder."/.htaccess", $htaccessContent);
        $content404 = file_get_contents($pDir."/res/404.html");
        $content404 = str_replace(array("AJXP_MESSAGE_TITLE", "AJXP_MESSAGE_LEGEND"), array($sTitle, $sLegend), $content404);
        file_put_contents($downloadFolder."/404.html", $content404);

    }

    public static function loadMinisite($data)
    {
        $repository = $data["REPOSITORY"];
        AJXP_PluginsService::getInstance()->initActivePlugins();
        $shareCenter = AJXP_PluginsService::findPlugin("action", "share");
        $confs = $shareCenter->getConfigs();
        $minisiteLogo = "plugins/gui.ajax/PydioLogo250.png";
        if(isSet($confs["CUSTOM_MINISITE_LOGO"])){
            $logoPath = $confs["CUSTOM_MINISITE_LOGO"];
            if (strpos($logoPath, "plugins/") === 0 && is_file(AJXP_INSTALL_PATH."/".$logoPath)) {
                $minisiteLogo = $logoPath;
            }else{
                $minisiteLogo = "index_shared.php?get_action=get_global_binary_param&binary_id=". $logoPath;
            }
        }
        // UPDATE TEMPLATE
        $html = file_get_contents(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/res/minisite.php");
        AJXP_Controller::applyHook("tpl.filter_html", array(&$html));
        $html = AJXP_XMLWriter::replaceAjxpXmlKeywords($html);
        $html = str_replace("AJXP_MINISITE_LOGO", $minisiteLogo, $html);
        $html = str_replace("AJXP_APPLICATION_TITLE", ConfService::getCoreConf("APPLICATION_TITLE"), $html);
        $html = str_replace("PYDIO_APP_TITLE", ConfService::getCoreConf("APPLICATION_TITLE"), $html);
        $html = str_replace("AJXP_START_REPOSITORY", $repository, $html);
        $html = str_replace("AJXP_REPOSITORY_LABEL", ConfService::getRepositoryById($repository)->getDisplay(), $html);

        session_name("AjaXplorer_Shared");
        session_start();
        if (!empty($data["PRELOG_USER"])) {
            AuthService::logUser($data["PRELOG_USER"], "", true);
            $html = str_replace("AJXP_PRELOGED_USER", "ajxp_preloged_user", $html);
        } else {
            $_SESSION["PENDING_REPOSITORY_ID"] = $repository;
            $_SESSION["PENDING_FOLDER"] = "/";
            $html = str_replace("AJXP_PRELOGED_USER", "", $html);
        }
        if (isSet($_GET["lang"])) {
            $loggedUser = &AuthService::getLoggedUser();
            if ($loggedUser != null) {
                $loggedUser->setPref("lang", $_GET["lang"]);
            } else {
                setcookie("AJXP_lang", $_GET["lang"]);
            }
        }

        if (!empty($data["AJXP_APPLICATION_BASE"])) {
            $tPath = $data["AJXP_APPLICATION_BASE"];
        } else {
            $tPath = (!empty($data["TRAVEL_PATH_TO_ROOT"]) ? $data["TRAVEL_PATH_TO_ROOT"] : "../..");
        }
        $html = str_replace("AJXP_PATH_TO_ROOT", rtrim($tPath, "/")."/", $html);
        HTMLWriter::internetExplorerMainDocumentHeader();
        HTMLWriter::charsetHeader();
        echo($html);
    }

    /**
     * @static
     * @param Array $data
     * @return void
     */
    public static function loadPubliclet($data)
    {
        // create driver from $data
        $className = $data["DRIVER"]."AccessDriver";
        $hash = md5(serialize($data));
        $u = parse_url($_SERVER["REQUEST_URI"]);
        $shortHash = pathinfo(basename($u["path"]), PATHINFO_FILENAME);

        if ( ($data["EXPIRE_TIME"] && time() > $data["EXPIRE_TIME"]) ||
            ($data["DOWNLOAD_LIMIT"] && $data["DOWNLOAD_LIMIT"]> 0 && $data["DOWNLOAD_LIMIT"] <= PublicletCounter::getCount($shortHash)) )
        {
            // Remove the publiclet, it's done
            if (strstr(realpath($_SERVER["SCRIPT_FILENAME"]),realpath(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"))) !== FALSE) {
                PublicletCounter::delete($shortHash);
                unlink($_SERVER["SCRIPT_FILENAME"]);
            }

            echo "Link is expired, sorry.";
            exit();
        }
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
        if(isSet($mess)) $messages = $mess;

        $AJXP_LINK_HAS_PASSWORD = false;
        $AJXP_LINK_BASENAME = SystemTextEncoding::toUTF8(basename($data["FILE_PATH"]));
        AJXP_PluginsService::getInstance()->initActivePlugins();
        $customs = array("title", "legend", "legend_pass", "background_attributes_1", "background_attributes_2", "background_attributes_3", "text_color", "background_color", "textshadow_color");
        $images = array("button", "background_1", "background_2", "background_3");
        $shareCenter = AJXP_PluginsService::findPlugin("action", "share");
        $confs = $shareCenter->getConfigs();
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
                //AJXP_PluginsService::getInstance()->initActivePlugins();
                $AJXP_LINK_HAS_PASSWORD = true;
                $AJXP_LINK_WRONG_PASSWORD = (isSet($_POST['password']) && ($_POST['password'] != $data["PASSWORD"]));
                include (AJXP_INSTALL_PATH."/plugins/action.share/res/public_links.php");
                $res = ('<div style="position: absolute;z-index: 10000; bottom: 0; right: 0; color: #666;font-family: HelveticaNeue-Light,Helvetica Neue Light,Helvetica Neue,Helvetica,Arial,Lucida Grande,sans-serif;font-size: 13px;text-align: right;padding: 6px; line-height: 20px;text-shadow: 0px 1px 0px white;" class="no_select_bg"><br>Build your own box with Pydio : <a style="color: #000000;" target="_blank" href="http://pyd.io/">http://pyd.io/</a><br/>Community - Free non supported version © C. du Jeu 2008-2014 </div>');
                AJXP_Controller::applyHook("tpl.filter_html", array(&$res));
                echo($res);
                return;
            }
        } else {
            if (!isSet($_GET["dl"])) {
                //AJXP_PluginsService::getInstance()->initActivePlugins();
                include (AJXP_INSTALL_PATH."/plugins/action.share/res/public_links.php");
                $res = '<div style="position: absolute;z-index: 10000; bottom: 0; right: 0; color: #666;font-family: HelveticaNeue-Light,Helvetica Neue Light,Helvetica Neue,Helvetica,Arial,Lucida Grande,sans-serif;font-size: 13px;text-align: right;padding: 6px; line-height: 20px;text-shadow: 0px 1px 0px white;" class="no_select_bg"><br>Build your own box with Pydio : <a style="color: #000000;" target="_blank" href="http://pyd.io/">http://pyd.io/</a><br/>Community - Free non supported version © C. du Jeu 2008-2014 </div>';
                AJXP_Controller::applyHook("tpl.filter_html", array(&$res));
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
        PublicletCounter::increment($shortHash);

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
        AJXP_PluginsService::getInstance()->initActivePlugins();
        try {
            $params = array("file" => SystemTextEncoding::toUTF8($data["FILE_PATH"]));
            if (isSet($data["PLUGINS_DATA"])) {
                $params["PLUGINS_DATA"] = $data["PLUGINS_DATA"];
            }
            if (isset($_GET["ct"]) && $_GET["ct"] == "true") {
                $mime = pathinfo($params["file"], PATHINFO_EXTENSION);
                $editors = AJXP_PluginsService::searchAllManifests("//editor[contains(@mimes,'$mime') and @previewProvider='true']", "node", true, true, false);
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
            AJXP_Controller::findActionAndApply($data["ACTION"], $params, null);
            register_shutdown_function(array("AuthService", "clearTemporaryUser"), $shortHash);
        } catch (Exception $e) {
            AuthService::clearTemporaryUser($shortHash);
            die($e->getMessage());
        }
    }

    /**
     * @param String $repoId
     * @param $mixUsersAndGroups
     * @param $currentFileUrl
     * @return array
     */
    public function computeSharedRepositoryAccessRights($repoId, $mixUsersAndGroups, $currentFileUrl)
    {
        $loggedUser = AuthService::getLoggedUser();
        $users = AuthService::getUsersForRepository($repoId);
        $baseGroup = "/";
        $groups = AuthService::listChildrenGroups($baseGroup);
        $mess = ConfService::getMessages();
        $groups[$baseGroup] = $mess["447"];
        $sharedEntries = array();
        if (!$mixUsersAndGroups) {
            $sharedGroups = array();
        }

        foreach ($groups as $gId => $gLabel) {
            $r = AuthService::getRole("AJXP_GRP_".AuthService::filterBaseGroup($gId));
            if ($r != null) {
                $right = $r->getAcl($repoId);
                if (!empty($right)) {
                    $entry = array(
                        "ID"    => $gId,
                        "TYPE"  => "group",
                        "LABEL" => $gLabel,
                        "RIGHT" => $right);
                    if (!$mixUsersAndGroups) {
                        $sharedGroups[$gId] = $entry;
                    } else {
                        $sharedEntries[] = $entry;
                    }
                }
            }
        }

        foreach ($users as $userId => $userObject) {
            if($userObject->getId() == $loggedUser->getId()) continue;
            $ri = $userObject->personalRole->getAcl($repoId);
            $uLabel = $userObject->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
            if(empty($uLabel)) $uLabel = $userId;
            if (!empty($ri)) {
                $entry =  array(
                    "ID"    => $userId,
                    "TYPE"  => $userObject->hasParent()?"tmp_user":"user",
                    "LABEL" => $uLabel,
                    "RIGHT" => $userObject->personalRole->getAcl($repoId)
                );
                if ($this->watcher !== false) {
                    $entry["WATCH"] = $this->watcher->hasWatchOnNode(
                        new AJXP_Node($currentFileUrl),
                        $userId,
                        MetaWatchRegister::$META_WATCH_USERS_NAMESPACE
                    );
                }
                if (!$mixUsersAndGroups) {
                    $sharedEntries[$userId] = $entry;
                } else {
                    $sharedEntries[] = $entry;
                }
            }
        }

        if (!$mixUsersAndGroups) {
            return array("USERS" => $sharedEntries, "GROUPS" => $sharedGroups);
        }
        return $sharedEntries;

    }

    /**
     * @param $httpVars
     * @param $repository
     * @param $accessDriver
     * @return array An array containing the hash (0) and the generated url (1)
     */
    public function createSharedMinisite($httpVars, $repository, $accessDriver)
    {
        $uniqueUser = null;
        if (isSet($httpVars["create_guest_user"])) {
            // Create a guest user
            $userId = substr(md5(time()), 0, 12);
            $pref = $this->getFilteredOption("SHARED_USERS_TMP_PREFIX", $this->repository->getId());
            if (!empty($pref)) {
                $userId = $pref.$userId;
            }
            $userPass = substr(md5(time()), 13, 24);
            $httpVars["user_0"] = $userId;
            $httpVars["user_pass_0"] = $httpVars["shared_pass"] = $userPass;
            $httpVars["entry_type_0"] = "user";
            $httpVars["right_read_0"] = (isSet($httpVars["simple_right_read"]) ? "true" : "false");
            $httpVars["right_write_0"] = (isSet($httpVars["simple_right_write"]) ? "true" : "false");
            $httpVars["right_watch_0"] = "false";
            $httpVars["disable_download"] = (isSet($httpVars["simple_right_download"]) ? false : true);
            if ($httpVars["right_write_0"] == "false" && $httpVars["right_read_0"] == "false") {
                return "share_center.58";
            }
            if ($httpVars["right_read_0"] == "false" && !$httpVars["disable_download"]) {
                $httpVars["right_read_0"] = "true";
            }
            $uniqueUser = $userId;
        }

        $httpVars["minisite"] = true;
        $newRepo = $this->createSharedRepository($httpVars, $repository, $accessDriver, $uniqueUser);

        if(!is_a($newRepo, "Repository")) return $newRepo;

        $newId = $newRepo->getId();
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $this->initPublicFolder($downloadFolder);
        $data = array("REPOSITORY"=>$newId, "PRELOG_USER"=>$userId);
        if ($httpVars["disable_download"]) {
            $data["DOWNLOAD_DISABLED"] = true;
        }
        //$data["TRAVEL_PATH_TO_ROOT"] = $this->computeMinisiteToServerURL();
        $data["AJXP_APPLICATION_BASE"] = AJXP_Utils::detectServerURL(true);

        $outputData = serialize($data);
        $hash = self::computeHash($outputData, $downloadFolder);

        $outputData = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $hash, $outputData, MCRYPT_MODE_ECB));
        $fileData = "<"."?"."php \n".
        '   require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/publicLet.inc.php"); '."\n".
        '   $id = str_replace(".php", "", basename(__FILE__)); '."\n". // Not using "" as php would replace $ inside
        '   $cypheredData = base64_decode("'.$outputData.'"); '."\n".
        '   $inputData = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $id, $cypheredData, MCRYPT_MODE_ECB), "\0");  '."\n".
        '   if (!ShareCenter::checkHash($inputData, $id)) { header("HTTP/1.0 401 Not allowed, script was modified"); exit(); } '."\n".
        '   // Ok extract the data '."\n".
        '   $data = unserialize($inputData); ShareCenter::loadMinisite($data); ';
        if (@file_put_contents($downloadFolder."/".$hash.".php", $fileData) === FALSE) {
            return "Can't write to PUBLIC URL";
        }
        @chmod($downloadFolder."/".$hash.".php", 0755);
        $url = $this->buildPublicletLink($hash);

        AJXP_Controller::applyHook("node.share.create", array(
            'type' => 'minisite',
            'repository' => &$repository,
            'accessDriver' => &$accessDriver,
            'data' => &$data,
            'url' => $url,
            'new_repository' => &$newRepo
        ));

        return array($hash, $url);
    }

    /**
     * @param Array $httpVars
     * @param Repository $repository
     * @param AbstractAccessDriver $accessDriver
     * @param null $uniqueUser
     * @throws Exception
     * @return int|Repository
     */
    public function createSharedRepository($httpVars, $repository, $accessDriver, $uniqueUser = null)
    {
        // ERRORS
        // 100 : missing args
        // 101 : repository label already exists
        // 102 : user already exists
        // 103 : current user is not allowed to share
        // SUCCESS
        // 200

        if (!isSet($httpVars["repo_label"]) || $httpVars["repo_label"] == "") {
            return 100;
        }
        $foldersharing = $this->getFilteredOption("ENABLE_FOLDER_SHARING", $this->repository->getId());
        if (isset($foldersharing) && $foldersharing === false) {
            return 103;
        }
        $loggedUser = AuthService::getLoggedUser();
        $actRights = $loggedUser->mergedRole->listActionsStatesFor($repository);
        if (isSet($actRights["share"]) && $actRights["share"] === false) {
            return 103;
        }
        $users = array();
        $uRights = array();
        $uPasses = array();
        $groups = array();

        $index = 0;
        $prefix = $this->getFilteredOption("SHARED_USERS_TMP_PREFIX", $this->repository->getId());
        while (isSet($httpVars["user_".$index])) {
            $eType = $httpVars["entry_type_".$index];
            $rightString = ($httpVars["right_read_".$index]=="true"?"r":"").($httpVars["right_write_".$index]=="true"?"w":"");
            if($this->watcher !== false) $uWatch = $httpVars["right_watch_".$index] == "true" ? true : false;
            if (empty($rightString)) {
                $index++;
                continue;
            }
            if ($eType == "user") {
                $u = AJXP_Utils::decodeSecureMagic($httpVars["user_".$index], AJXP_SANITIZE_EMAILCHARS);
                if (!AuthService::userExists($u) && !isSet($httpVars["user_pass_".$index])) {
                    $index++;
                    continue;
                } else if (AuthService::userExists($u) && isSet($httpVars["user_pass_".$index])) {
                    throw new Exception("User $u already exists, please choose another name.");
                }
                if(!AuthService::userExists($u, "r") && !empty($prefix)
                && strpos($u, $prefix)!==0 ){
                    $u = $prefix . $u;
                }
                $users[] = $u;
            } else {
                $u = AJXP_Utils::decodeSecureMagic($httpVars["user_".$index]);
                if (strpos($u, "/AJXP_TEAM/") === 0) {
                    $confDriver = ConfService::getConfStorageImpl();
                    if (method_exists($confDriver, "teamIdToUsers")) {
                        $teamUsers = $confDriver->teamIdToUsers(str_replace("/AJXP_TEAM/", "", $u));
                        foreach ($teamUsers as $userId) {
                            $users[] = $userId;
                            $uRights[$userId] = $rightString;
                            if ($this->watcher !== false) {
                                $uWatches[$userId] = $uWatch;
                            }
                        }
                    }
                    $index++;
                    continue;
                } else {
                    $groups[] = $u;
                }
            }
            $uRights[$u] = $rightString;
            $uPasses[$u] = isSet($httpVars["user_pass_".$index])?$httpVars["user_pass_".$index]:"";
            if ($this->watcher !== false) {
                $uWatches[$u] = $uWatch;
            }
            $index ++;
        }

        $label = AJXP_Utils::decodeSecureMagic($httpVars["repo_label"]);
        $description = AJXP_Utils::decodeSecureMagic($httpVars["repo_description"]);
        if (isSet($httpVars["repository_id"])) {
            $editingRepo = ConfService::getRepositoryById($httpVars["repository_id"]);
        }

        // CHECK USER & REPO DOES NOT ALREADY EXISTS
        if ( $this->getFilteredOption("AVOID_SHARED_FOLDER_SAME_LABEL", $this->repository->getId()) == true) {
            $repos = ConfService::getRepositoriesList();
            foreach ($repos as $obj) {
                if ($obj->getDisplay() == $label && (!isSet($editingRepo) || $editingRepo != $obj)) {
                    return 101;
                }
            }
        }

        $confDriver = ConfService::getConfStorageImpl();
        foreach ($users as $userName) {
            if (AuthService::userExists($userName)) {
                // check that it's a child user
                $userObject = $confDriver->createUserObject($userName);
                if ( ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING", "conf") != true && ( !$userObject->hasParent() || $userObject->getParent() != $loggedUser->id ) ) {
                    return 102;
                }
            } else {
                if ( ($httpVars["create_guest_user"] != "true" && !ConfService::getCoreConf("USER_CREATE_USERS", "conf")) || AuthService::isReservedUserId($userName)) {
                    return 102;
                }
                if (!isSet($httpVars["shared_pass"]) || $httpVars["shared_pass"] == "") {
                    return 100;
                }
            }
        }

        // CREATE SHARED OPTIONS
        $options = $accessDriver->makeSharedRepositoryOptions($httpVars, $repository);
        $customData = array();
        foreach ($httpVars as $key => $value) {
            if (substr($key, 0, strlen("PLUGINS_DATA_")) == "PLUGINS_DATA_") {
                $customData[substr($key, strlen("PLUGINS_DATA_"))] = $value;
            }
        }
        if (count($customData)) {
            $options["PLUGINS_DATA"] = $customData;
        }
        if (isSet($editingRepo)) {
            $newRepo = $editingRepo;
            if ($editingRepo->getDisplay() != $label) {
                $newRepo->setDisplay($label);
                ConfService::replaceRepository($httpVars["repository_id"], $newRepo);
            }
            $editingRepo->setDescription($description);
        } else {
            if ($repository->getOption("META_SOURCES")) {
                $options["META_SOURCES"] = $repository->getOption("META_SOURCES");
                foreach ($options["META_SOURCES"] as $index => $data) {
                    if (isSet($data["USE_SESSION_CREDENTIALS"]) && $data["USE_SESSION_CREDENTIALS"] === true) {
                        $options["META_SOURCES"][$index]["ENCODED_CREDENTIALS"] = AJXP_Safe::getEncodedCredentialString();
                    }
                }
            }
            $newRepo = $repository->createSharedChild(
                $label,
                $options,
                $repository->id,
                $loggedUser->id,
                null
            );
            $gPath = $loggedUser->getGroupPath();
            if (!empty($gPath) && !ConfService::getCoreConf("CROSSUSERS_ALLGROUPS", "conf")) {
                $newRepo->setGroupPath($gPath);
            }
            $newRepo->setDescription($description);
            ConfService::addRepository($newRepo);
        }

        $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);

        if (isSet($editingRepo)) {

            $currentRights = $this->computeSharedRepositoryAccessRights($httpVars["repository_id"], false, $this->urlBase.$file);
            $originalUsers = array_keys($currentRights["USERS"]);
            $removeUsers = array_diff($originalUsers, $users);
            if (count($removeUsers)) {
                foreach ($removeUsers as $user) {
                    if (AuthService::userExists($user)) {
                        $userObject = $confDriver->createUserObject($user);
                        $userObject->personalRole->setAcl($newRepo->getUniqueId(), "");
                        $userObject->save("superuser");
                    }
                }
            }
            $originalGroups = array_keys($currentRights["GROUPS"]);
            $removeGroups = array_diff($originalGroups, $groups);
            if (count($removeGroups)) {
                foreach ($removeGroups as $groupId) {
                    $role = AuthService::getRole("AJXP_GRP_".AuthService::filterBaseGroup($groupId));
                    if ($role !== false) {
                        $role->setAcl($newRepo->getUniqueId(), "");
                        AuthService::updateRole($role);
                    }
                }
            }
        }

        foreach ($users as $userName) {
            if (AuthService::userExists($userName, "r")) {
                // check that it's a child user
                $userObject = $confDriver->createUserObject($userName);
            } else {
                if (ConfService::getAuthDriverImpl()->getOption("TRANSMIT_CLEAR_PASS")) {
                    $pass = $uPasses[$userName];
                } else {
                    $pass = md5($uPasses[$userName]);
                }
                $limit = $loggedUser->personalRole->filterParameterValue("core.conf", "USER_SHARED_USERS_LIMIT", AJXP_REPO_SCOPE_ALL, "");
                if (!empty($limit) && intval($limit) > 0) {
                    $count = count(ConfService::getConfStorageImpl()->getUserChildren($loggedUser->getId()));
                    if ($count >= $limit) {
                        $mess = ConfService::getMessages();
                        throw new Exception($mess['483']);
                    }
                }
                AuthService::createUser($userName, $pass);
                $userObject = $confDriver->createUserObject($userName);
                $userObject->personalRole->clearAcls();
                $userObject->setParent($loggedUser->id);
                $userObject->setGroupPath($loggedUser->getGroupPath());
                $userObject->setProfile("shared");
                if(isSet($httpVars["minisite"])){
                    $mess = ConfService::getMessages();
                    $userObject->personalRole->setParameterValue("core.conf", "USER_DISPLAY_NAME", "[".$mess["share_center.109"]."] ".$newRepo->getDisplay());
                }
                AJXP_Controller::applyHook("user.after_create", array($userObject));
            }
            // CREATE USER WITH NEW REPO RIGHTS
            $userObject->personalRole->setAcl($newRepo->getUniqueId(), $uRights[$userName]);
            if (isSet($httpVars["minisite"])) {
                $newRole = new AJXP_Role("AJXP_SHARED-".$newRepo->getUniqueId());
                $r = AuthService::getRole("MINISITE");
                if (is_a($r, "AJXP_Role")) {
                    if ($httpVars["disable_download"]) {
                        $f = AuthService::getRole("MINISITE_NODOWNLOAD");
                        if (is_a($f, "AJXP_Role")) {
                            $r = $f->override($r);
                        }
                    }
                    $allData = $r->getDataArray();
                    $newData = $newRole->getDataArray();
                    if(isSet($allData["ACTIONS"][AJXP_REPO_SCOPE_SHARED])) $newData["ACTIONS"][$newRepo->getUniqueId()] = $allData["ACTIONS"][AJXP_REPO_SCOPE_SHARED];
                    if(isSet($allData["PARAMETERS"][AJXP_REPO_SCOPE_SHARED])) $newData["PARAMETERS"][$newRepo->getUniqueId()] = $allData["PARAMETERS"][AJXP_REPO_SCOPE_SHARED];
                    $newRole->bunchUpdate($newData);
                    AuthService::updateRole($newRole);
                    $userObject->addRole($newRole);
                }
            }
            $userObject->save("superuser");
            if ($this->watcher !== false) {
                // Register a watch on the current folder for shared user
                if ($uWatches[$userName] == "true") {
                    $this->watcher->setWatchOnFolder(
                        new AJXP_Node($this->urlBase.$file),
                        $userName,
                        MetaWatchRegister::$META_WATCH_USERS_CHANGE,
                        array(AuthService::getLoggedUser()->getId())
                    );
                } else {
                    $this->watcher->removeWatchFromFolder(
                        new AJXP_Node($this->urlBase.$file),
                        $userName,
                        true
                    );
                }
            }
        }

        if ($this->watcher !== false) {
            // Register a watch on the new repository root for current user
            if ($httpVars["self_watch_folder"] == "true") {
                $this->watcher->setWatchOnFolder(
                    new AJXP_Node($this->baseProtocol."://".$newRepo->getUniqueId()."/"),
                    AuthService::getLoggedUser()->getId(),
                    MetaWatchRegister::$META_WATCH_BOTH);
            } else {
                $this->watcher->removeWatchFromFolder(
                    new AJXP_Node($this->baseProtocol."://".$newRepo->getUniqueId()."/"),
                    AuthService::getLoggedUser()->getId());
            }
        }

        foreach ($groups as $group) {
            $grRole = AuthService::getRole("AJXP_GRP_".AuthService::filterBaseGroup($group), true);
            $grRole->setAcl($newRepo->getUniqueId(), $uRights[$group]);
            AuthService::updateRole($grRole);
        }

        if (array_key_exists("minisite", $httpVars) && $httpVars["minisite"] != true) {
            AJXP_Controller::applyHook("node.share.create", array(
                'type' => 'repository',
                'repository' => &$repository,
                'accessDriver' => &$accessDriver,
                'new_repository' => &$newRepo
            ));
        }

        return $newRepo;
    }


    /**
     * @static
     * @param String $type
     * @param String $element
     * @param AbstractAjxpUser $loggedUser
     * @throws Exception
     */
    public static function deleteSharedElement($type, $element, $loggedUser)
    {
        $mess = ConfService::getMessages();
        AJXP_Logger::debug($type."-".$element);
        if ($type == "repository") {
            $repo = ConfService::getRepositoryById($element);
            if($repo == null) return;
            if (!$repo->hasOwner() || $repo->getOwner() != $loggedUser->getId()) {
                throw new Exception($mess["ajxp_shared.12"]);
            } else {
                $res = ConfService::deleteRepository($element);
                if ($res == -1) {
                    throw new Exception($mess["ajxp_conf.51"]);
                }
            }
        } else if ($type == "minisite") {
            $minisiteData = self::loadPublicletData($element);
            $repoId = $minisiteData["REPOSITORY"];
            $repo = ConfService::getRepositoryById($repoId);
            if ($repo == null) {
                return false;
            }
            if (!$repo->hasOwner() || $repo->getOwner() != $loggedUser->getId()) {
                throw new Exception($mess["ajxp_shared.12"]);
            } else {
                $res = ConfService::deleteRepository($repoId);
                if ($res == -1) {
                    throw new Exception($mess["ajxp_conf.51"]);
                }
                // Silently delete corresponding role if it exists
                AuthService::deleteRole("AJXP_SHARED-".$repoId);
                // If guest user created, remove it now.
                if (isSet($minisiteData["PRELOG_USER"])) {
                    AuthService::deleteUser($minisiteData["PRELOG_USER"]);
                }
                unlink($minisiteData["PUBLICLET_PATH"]);
            }
        } else if ($type == "user") {
            $confDriver = ConfService::getConfStorageImpl();
            $object = $confDriver->createUserObject($element);
            if (!$object->hasParent() || $object->getParent() != $loggedUser->getId()) {
                throw new Exception($mess["ajxp_shared.12"]);
            } else {
                AuthService::deleteUser($element);
            }
        } else if ($type == "file") {
            $publicletData = self::loadPublicletData($element);
            if (isSet($publicletData["OWNER_ID"]) && $publicletData["OWNER_ID"] == $loggedUser->getId()) {
                PublicletCounter::delete($element);
                unlink($publicletData["PUBLICLET_PATH"]);
            } else {
                throw new Exception($mess["ajxp_shared.12"]);
            }
        }
    }

    public static function sharedElementExists($type, $element)
    {
        if ($type == "repository") {
            return (ConfService::getRepositoryById($element) != null);
        } else if ($type == "file" || $type == "minisite") {
            $dlFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
            return is_file($dlFolder."/".$element.".php");
        }
    }


    public static function loadPublicletData($id)
    {
        $dlFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $file = $dlFolder."/".$id.".php";
        if(!is_file($file)) return array();
        $lines = file($file);
        $inputData = '';
        $code = $lines[3] . $lines[4] . $lines[5];
        eval($code);
        $dataModified = self::checkHash($inputData, $id); //(md5($inputData) != $id);
        $publicletData = unserialize($inputData);
        $publicletData["SECURITY_MODIFIED"] = $dataModified;
        if (!isSet($publicletData["REPOSITORY"])) {
            $publicletData["DOWNLOAD_COUNT"] = PublicletCounter::getCount($id);
        }
        $publicletData["PUBLICLET_PATH"] = $file;
        return $publicletData;
    }

    public static function currentContextIsLinkDownload(){
        return (isSet($_GET["dl"]) && isSet($_GET["dl"]) == "true");
    }

}
