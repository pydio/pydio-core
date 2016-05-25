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

namespace Pydio\OCS\Server\Dav;

defined('AJXP_EXEC') or die('Access not allowed');
require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/class.ShareStore.php");

use Pydio\Core\Services\ConfService;
use Pydio\Log\Core\AJXP_Logger;
use Sabre;

class Server extends Sabre\DAV\Server
{
    var $rootCollection;
    var $uniqueBaseFile;

    public function __construct()
    {
        $this->rootCollection = new \AJXP_Sabre_Collection("/", null, null);
        parent::__construct($this->rootCollection);
    }

    /**
     * @return string
     */
    protected function pointToBaseFile(){
        try{
            $testBackend = new BasicAuthNoPass();
            $userPass = $testBackend->getUserPass();
            if(isSet($userPass[0])){
                $shareStore = new \ShareStore(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"));
                $shareData = $shareStore->loadShare($userPass[0]);
                if(isSet($shareData) && isSet($shareData["REPOSITORY"])){
                    $repo = ConfService::getRepositoryById($shareData["REPOSITORY"]);
                    if(!empty($repo) && $repo->hasContentFilter()){
                        return "/".$repo->getContentFilter()->getUniquePath();
                    }
                }
            }
        }catch (\Exception $e){}
        return null;
    }

    public function calculateUri($uri) {
        $uri = parent::calculateUri($uri);
        if(!empty($this->uniqueBaseFile) && '/'.$uri !== $this->uniqueBaseFile){
            $uri.= $this->uniqueBaseFile;
        }
        return $uri;
    }

    public function start($baseUri = "/"){

        $this->uniqueBaseFile = $this->pointToBaseFile();
        $this->setBaseUri($baseUri);

        $authBackend = new AuthSharingBackend($this->rootCollection);
        $authPlugin = new Sabre\DAV\Auth\Plugin($authBackend, ConfService::getCoreConf("WEBDAV_DIGESTREALM"));
        $this->addPlugin($authPlugin);

        if (!is_dir(AJXP_DATA_PATH."/plugins/server.sabredav")) {
            mkdir(AJXP_DATA_PATH."/plugins/server.sabredav", 0755);
            $fp = fopen(AJXP_DATA_PATH."/plugins/server.sabredav/locks", "w");
            fwrite($fp, "");
            fclose($fp);
        }

        $lockBackend = new Sabre\DAV\Locks\Backend\File(AJXP_DATA_PATH."/plugins/server.sabredav/locks");
        $lockPlugin = new Sabre\DAV\Locks\Plugin($lockBackend);
        $this->addPlugin($lockPlugin);

        if (ConfService::getCoreConf("WEBDAV_BROWSER_LISTING")) {
            $browerPlugin = new \AJXP_Sabre_BrowserPlugin((isSet($repository)?$repository->getDisplay():null));
            $extPlugin = new Sabre\DAV\Browser\GuessContentType();
            $this->addPlugin($browerPlugin);
            $this->addPlugin($extPlugin);
        }
        try {
            $this->exec();
        } catch ( \Exception $e ) {
            AJXP_Logger::error(__CLASS__,"Exception",$e->getMessage());
        }
    }

    /**
     * Not used for the moment
     * This will expose folder as /dav/FolderName and file as /dav/FileName.txt
     *
     * @param $baseUri
     * @return \AJXP_Sabre_Collection|SharingCollection
     * @throws \Exception
     */
    protected function initCollectionForFileOrFolderAsUniqueItem(&$baseUri){
        try{
            $testBackend = new BasicAuthNoPass();
            $userPass = $testBackend->getUserPass();
            if(isSet($userPass[0])){
                $shareStore = new \ShareStore(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"));
                $shareData = $shareStore->loadShare($userPass[0]);
                if(isSet($shareData) && isSet($shareData["REPOSITORY"])){
                    $repo = ConfService::getRepositoryById($shareData["REPOSITORY"]);
                    if(!empty($repo) && !$repo->hasContentFilter()){
                        $baseDir = basename($repo->getOption("PATH"));
                    }
                }
            }
        }catch (\Exception $e){}
        $rootCollection =  new \AJXP_Sabre_Collection("/", null, null);
        if(isSet($baseDir)){
            $currentPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
            if($currentPath == $baseUri || $currentPath == $baseUri."/"){
                $rootCollection = new SharingCollection("/", null, null);
            }else{
                $baseUri .= "/$baseDir";
            }
        }
        return $rootCollection;
    }



}