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
 *
 */

namespace Pydio\Access\WebDAV;

defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(__DIR__ . '/../vendor/autoload.php');

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Stream\Listener\PathSubscriber;
use Pydio\Access\Core\Stream\Stream;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
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
    public function init(ContextInterface $ctx, $options = array())
    {
        parent::init($ctx, $options);
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

        $repository = $context->getRepository();
        $resourcesFile = $repository->getContextOption($context, "API_RESOURCES_FILE", __DIR__ . "/" . self::RESOURCES_PATH . "/" . self::RESOURCES_FILE);

        Stream::addContextOption($context, [
            "resources"   => $resourcesFile,
            "subscribers" => [
                new PathSubscriber(),
                new WebDAVSubscriber()
            ]
        ]);

        return true;
    }

    /********************************************************
     * Static functions used in the JSON service description
     ******************************************************
     * @param AJXP_Node $node
     * @return string
     */
    public static function convertPath($node, $withHost = false) {

        $ctx = $node->getContext();
        $repository = $node->getRepository();

        $basePath = ltrim($repository->getContextOption($ctx, "PATH"), "/");
        $path = $node->getPath();

        $contentFilters = $node->getRepository()->getContentFilter();

        if (isset($contentFilters)) {
            $contentFilters = $contentFilters->filters;

            foreach ($contentFilters as $key => $value) {
                if ($value == $path || empty($path)) {
                    $path = $key;
                    break;
                }
            }
        }

        $host = ""; // By default, we don't return the host
        if ($withHost) {
            $host = rtrim($repository->getContextOption($ctx, "HOST"), "/");
        }

        if (isset($path)) {
            return rtrim($host . "/" . $basePath . $path, "/");
        }

        return ltrim($host . "/" . $basePath, "/");
    }

    /**
     * @param $date
     * @return int
     */
    public static function convertTime($date) {
        $date = date_create($date);

        return date_timestamp_get($date);
    }

}
