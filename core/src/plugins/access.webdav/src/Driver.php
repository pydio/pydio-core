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
 *
 */

namespace Pydio\Access\WebDAV;

defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(__DIR__ . '/../vendor/autoload.php');

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Stream\Listener\PathListener;
use Pydio\Access\Core\Stream\Stream;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
use Pydio\Access\DropBox\Listener\DropBoxSubscriber;
use Pydio\Access\WebDAV\Listener\WebDAVSubscriber;
use Pydio\Core\Model\ContextInterface;

/**
 * AJXP_Plugin to access a DropBox enabled server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class Driver extends FsAccessDriver
{
    const PROTOCOL = "access.webdav";
    const RESOURCES_PATH = "Resources";
    const RESOURCES_FILE = "dav.json";

    public $driverType = "webdav";

    /**
     * Driver Initialization
     * @param $repository
     * @param array $options
     */
    public function init($repository, $options = array())
    {
        parent::init($repository, $options);
    }

    /**
     * Repository Initialization
     * @param ContextInterface $context
     * @return bool|void
     * @internal param ContextInterface $contextInterface
     */
    protected function initRepository(ContextInterface $context)
    {
        $this->detectStreamWrapper(true);

        Stream::addContextOption($context, [
            "subscribers" => [
                new PathListener(),
                new WebDAVSubscriber()
            ]
        ]);

        return true;
    }

    /********************************************************
     * Static functions used in the JSON service description
     *******************************************************
     * @param AJXP_Node $node
     */


    public static function convertPath($node) {

        $ctx = $node->getContext();
        $repository = $node->getRepository();

        $basePath = $repository->getContextOption($ctx, "PATH");
        $path = $node->getPath();

        if (isset($path)) {
            return "/" . $basePath . $path;
        }

        return $basePath;
    }

    public static function convertToJSON($key, $value) {
        $key = '' . $key->getName();
        $value = '' . $value;
        $arr = [$key => $value];
        return json_encode($arr);
    }
}
