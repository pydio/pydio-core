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

namespace Pydio\OCS\Server\Dav;

defined('AJXP_EXEC') or die('Access not allowed');
require_once(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/action.share/vendor/autoload.php");

use Pydio\Core\Http\Dav\BrowserPlugin;
use Pydio\Core\Http\Dav\Collection;
use Pydio\Core\Http\Dav\DAVResponse;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Log\Core\Logger;
use Pydio\Share\Store\ShareStore;
use Sabre;

/**
 * Class Server
 * @package Pydio\OCS\Server\Dav
 */
class Server extends Sabre\DAV\Server
{
    var $context;
    var $uniqueBaseFile;

    /**
     * Server constructor.
     */
    public function __construct()
    {
        $this->context = $this->buildContext(Context::emptyContext());
        $rootCollection = new Collection("/", $this->context);

        parent::__construct($rootCollection);

        $this->httpResponse = new DAVResponse();
    }

    /**
     * @param ContextInterface $context
     * @return ContextInterface|null
     */
    protected function buildContext(ContextInterface $context){
        try {
            $testBackend = new BasicAuthNoPass();
            $userPass = $testBackend->getUserPass();
            if(isSet($userPass[0])) {

                $context = $context->withUserId($userPass[0]);

                $shareStore = new ShareStore($context, ConfService::getGlobalConf("PUBLIC_DOWNLOAD_FOLDER"));
                $shareData = $shareStore->loadShare($userPass[0]);

                if(isSet($shareData) && isSet($shareData["REPOSITORY"])) {
                    $context = $context->withRepositoryId($shareData["REPOSITORY"]);
                }
            }

            return $context;
        } catch (\Exception $e) {

        }
        return null;
    }

    /**
     * @param string $baseUri
     */
    public function start($baseUri = "/"){

        $this->setBaseUri($baseUri);

        $authBackend = new AuthSharingBackend($this->context);
        $authPlugin = new Sabre\DAV\Auth\Plugin($authBackend, ConfService::getGlobalConf("WEBDAV_DIGESTREALM"));
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

        if (ConfService::getGlobalConf("WEBDAV_BROWSER_LISTING")) {
            $browserPlugin = new BrowserPlugin((isSet($repository)?$repository->getDisplay():null));
            $extPlugin = new Sabre\DAV\Browser\GuessContentType();
            $this->addPlugin($browserPlugin);
            $this->addPlugin($extPlugin);
        }
        
        try {
            $this->exec();
        } catch ( \Exception $e ) {
            Logger::error(__CLASS__,"Exception",$e->getMessage());
        }
    }
}