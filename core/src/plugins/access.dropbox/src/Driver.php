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

namespace Pydio\Access\DropBox;

defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(__DIR__ . '/../vendor/autoload.php');

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Stream\OAuthStream;
use Pydio\Access\Core\Stream\Stream;
use Pydio\Access\Driver\StreamProvider\FS\fsAccessDriver;
use Pydio\Access\DropBox\Listener\DropBoxSubscriber;
use Pydio\Core\Model\ContextInterface;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * AJXP_Plugin to access a DropBox enabled server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class Driver extends fsAccessDriver
{
    const PROTOCOL = "access.dropbox";
    const RESOURCES_PATH = "Resources";
    const RESOURCES_FILE = "dropbox.json";

    public $driverType = "dropbox";

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
            "subscribers" => [new DropBoxSubscriber()]
        ]);

        return true;
    }

    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response) {
        $httpVars = $request->getParsedBody();

        if (isset($httpVars["code"])) {
            $context = $request->getAttribute("ctx");

            Stream::addContextOption($context, [
                "oauth_code" => $httpVars["code"]
            ]);

            // Simulate the creation of a stream to ensure we store the oauth in the stream context
            $stream = new OAuthStream(Stream::factory('php://memory'), $context);
            $stream->close();
        }

        return parent::switchAction($request, $response);
    }

    /********************************************************
     * Static functions used in the JSON service description
     ********************************************************/
    public static function convertPath($value) {
        $node = new AJXP_Node($value);
        $path = $node->getPath();

        if (isset($path)) {
            return $path;
        }
        return "";
    }

    public static function convertToJSON($key, $value) {
        $key = '' . $key->getName();
        $value = '' . $value;
        $arr = [$key => $value];
        return json_encode($arr);
    }
}