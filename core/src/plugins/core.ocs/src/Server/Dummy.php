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
namespace Pydio\OCS\Server;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Dummy Server for not active Federated Sharing
 * @package Pydio\OCS\Server
 */
class Dummy
{
    /**
     * Dummy constructor.
     */
    public function __construct()
    {
        set_exception_handler(array($this, "handleException"));
    }

    /**
     * @param \Exception $exception
     */
    public function handleException($exception){
        $response = $this->buildResponse("fail", $exception->getCode() ? $exception->getCode() : 500, $exception->getMessage());
        $this->sendResponse($response, "json");
    }

    public static function notImplemented($uriParts, $parameters){

        $d = new Dummy();
        $response = $d->buildResponse("fail", 503, "Federated Sharing is not active on this server");
        $d->sendResponse($response, $d->getFormat($parameters));

    }

    public function getFormat($parameters){
        return isSet($parameters["format"]) && in_array($parameters["format"], array("json", "xml")) ? $parameters["format"] : "json";
    }

    public function buildResponse($status = "ok", $code = 200, $message = null, $data = null){

        $ocs = array(
            "ocs" => array(
                "meta" => array(
                    "status" => $status,
                    "statuscode" => $code,
                    "message" => $message
                )
            )
        );
        if(!empty($data)){
            $ocs["ocs"]["data"] = $data;
        }
        return $ocs;

    }

    public function sendResponse($response, $format = "json"){
        if($format == "json"){
            header("Content-Type: text/json");
            print json_encode($response);
        }else if($format == "xml"){
            header("Content-Type: text/xml");
            print $this->array2xml($response["ocs"], "ocs");
        }
    }

    protected function array2xml($array, $node_name="root")
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElement($node_name);
        $dom->appendChild($root);

        $array2xml = function ($node, $array) use ($dom, &$array2xml) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $n = $dom->createElement($key);
                    $node->appendChild($n);
                    $array2xml($n, $value);
                } else {
                    if(is_numeric($key)){
                        $n = $dom->createElement('element', $value);
                    }else{
                        $n = $dom->createElement($key, $value);
                    }
                    $node->appendChild($n);
                }
            }
        };

        $array2xml($root, $array);

        return $dom->saveXML();
    }

}