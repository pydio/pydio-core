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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Serializer;

use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * XML Serializer for AJXP_Node objects
 * @package Pydio\Core\Serializer
 */
class NodeXML
{

    /**
     * The basic node
     * @static
     * @param string $nodeName
     * @param string $nodeLabel
     * @param bool $isLeaf
     * @param array $metaData
     * @param bool $close
     * @return string
     * @internal param bool $print
     */
    public static function toNode($nodeName, $nodeLabel, $isLeaf, $metaData = array(), $close = true)
    {
        $string = "<tree";
        $metaData["filename"] = $nodeName;
        if (InputFilter::detectXSS($nodeName)) $metaData["filename"] = "/XSS Detected - Please contact your admin";
        if (!isSet($metaData["text"])) {
            if (InputFilter::detectXSS($nodeLabel)) $nodeLabel = "XSS Detected - Please contact your admin";
            $metaData["text"] = $nodeLabel;
        } else {
            if (InputFilter::detectXSS($metaData["text"])) $metaData["text"] = "XSS Detected - Please contact your admin";
        }
        $metaData["is_file"] = ($isLeaf ? "true" : "false");
        $metaData["ajxp_im_time"] = time();
        foreach ($metaData as $key => $value) {
            if (InputFilter::detectXSS($value)) $value = "XSS Detected!";
            $value = StringHelper::xmlEntities($value, true);
            $string .= " $key=\"$value\"";
        }
        if ($close) {
            $string .= "/>";
        } else {
            $string .= ">";
        }
        return $string;
    }

    /**
     * @static
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param bool $close
     * @return string
     */
    public static function toXML($node, $close = true)
    {
        return NodeXML::toNode($node->getPath(), $node->getLabel(), $node->isLeaf(), $node->getNodeInfoMeta(), $close);
    }
}