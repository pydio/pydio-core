<?php

/*
 * This file is part of the Guzzle description loader package.
 *
 * (c) Gordon Franke <info@nevalon.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Guzzle\Service\Loader;

use Guzzle\Service\Loader\FileLoader;

class JsonLoader extends FileLoader
{
    /**
     * {@inheritdoc}
     */
    public function loadResource($resource)
    {
        $configValues = array();
        if ($data = file_get_contents($resource)) {
            $configValues = json_decode($data, true);
            if (0 < $errorCode = json_last_error()) {
                throw new \Exception(sprintf('Error parsing JSON - %s', $this->getJSONErrorMessage($errorCode)));
            }
        }

        return $configValues;
    }

    /**
     * Translates JSON_ERROR_* constant into meaningful message.
     *
     * @param int $errorCode Error code returned by json_last_error() call
     *
     * @return string Message string
     */
    private function getJSONErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch';
            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            default:
                return 'Unknown error';
        }
    }

    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'json' === pathinfo(
            $resource,
            PATHINFO_EXTENSION
        );
    }
}