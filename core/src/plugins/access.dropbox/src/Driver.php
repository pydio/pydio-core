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

namespace Pydio\Access\DropBox;

defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(__DIR__ . '/../vendor/autoload.php');

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Stream\OAuthStream;
use Pydio\Access\Core\Stream\Stream;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
use Pydio\Access\DropBox\Listener\DropBoxSubscriber;
use Pydio\Core\Model\ContextInterface;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * AJXP_Plugin to access a DropBox enabled server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class Driver extends FsAccessDriver
{
    const PROTOCOL = "access.dropbox";
    const RESOURCES_PATH = "Resources";
    const RESOURCES_FILE = "dropbox.json";

    public $driverType = "dropbox";

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
                new DropBoxSubscriber()
            ]
        ]);

        return true;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws \Exception
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response) {
        $httpVars = $request->getParsedBody();

        if (isset($httpVars["code"])) {
            $context = $request->getAttribute("ctx");

            Stream::addContextOption($context, [
                "oauth_code" => $httpVars["code"]
            ]);

            // Simulate the creation of a stream to ensure we store the oauth in the stream context
            $stream = new OAuthStream(Stream::factory('php://memory'), $context);

        }

        return parent::switchAction($request, $response);
    }

    /********************************************************
     * Static functions used in the JSON service description
     *******************************************************
     * @param AJXP_Node $node
     * @return string
     */

    public static function convertPath(AJXP_Node $node) {
        $path = $node->getPath();

        if (isset($path) && $path !== "/") {
            return $path;
        }
        return "";
    }

    /**
     * @param $key
     * @param $value
     * @return string
     */
    public static function convertToJSON($key, $value) {
        $key = '' . $key->getName();

        if ($value instanceof AJXP_Node) {
            $value = self::convertPath($value);
        } else {
            $value = '' . $value;
        }

        $arr = [$key => $value];
        return json_encode($arr);
    }

    /**
     * @param $key
     * @param $value
     * @return string
     */
    public static function convertToJSONOverwrite($key, $value) {
        $key = '' . $key->getName();

        if ($value instanceof AJXP_Node) {
            $value = self::convertPath($value);
        } else {
            $value = '' . $value;
        }

        $arr = [$key => $value,
            "mode" => [
                ".tag" => "overwrite"
            ]
        ];
        return json_encode($arr);
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
